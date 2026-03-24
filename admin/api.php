<?php
/**
 * RohrApp+ — API Endpoint (MySQL)
 * All data operations go through this file.
 */
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=UTF-8');

// ── Security Headers ──
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── Helpers ──
$_dbInstance = null;
function getDB() {
    global $_dbInstance;
    if ($_dbInstance) return $_dbInstance;
    try {
        $_dbInstance = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $_dbInstance;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen. Bitte install.php ausführen.']);
        exit;
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth() {
    if (empty($_SESSION['rohrapp_user'])) {
        jsonResponse(['error' => 'Nicht autorisiert'], 401);
    }
}

function requireRole($allowedRoles) {
    requireAuth();
    $role = $_SESSION['rohrapp_user']['role'] ?? '';
    if (!in_array($role, (array)$allowedRoles)) {
        jsonResponse(['error' => 'Keine Berechtigung für diese Aktion', 'required' => $allowedRoles], 403);
    }
}

function getUserRole() {
    return $_SESSION['rohrapp_user']['role'] ?? 'starter';
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ── Brute Force Protection (MySQL) ──
function checkBruteForce() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Clean old attempts (older than lockout period)
    $db->prepare("DELETE FROM login_attempts WHERE last_attempt < NOW() - INTERVAL ? SECOND")
       ->execute([BRUTE_FORCE_LOCKOUT]);

    $stmt = $db->prepare("SELECT attempt_count, last_attempt FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if ($row && $row['attempt_count'] >= BRUTE_FORCE_MAX) {
        $lockUntil = strtotime($row['last_attempt']) + BRUTE_FORCE_LOCKOUT;
        $remaining = $lockUntil - time();
        if ($remaining > 0) {
            jsonResponse(['error' => "Zu viele Versuche. Bitte warten Sie $remaining Sekunden."], 429);
        }
    }
}

function recordFailedLogin() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->prepare("INSERT INTO login_attempts (ip_address, attempt_count, last_attempt) VALUES (?, 1, NOW())
                  ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt = NOW()")
       ->execute([$ip]);
}

function clearLoginAttempts() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

// ── Auto-migration: company column on users ──
(function() {
    try {
        $db = getDB();
        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'company'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN company VARCHAR(200) NULL AFTER name");
        }
    } catch (Throwable $e) { /* ignore */ }
})();

// Auto-migration: license_key on licenses
(function() {
    try {
        $db = getDB();
        $cols = $db->query("SHOW COLUMNS FROM licenses LIKE 'license_key'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE licenses ADD COLUMN license_key VARCHAR(64) UNIQUE NULL AFTER user_id");
            // Generate keys for existing licenses that don't have one
            $rows = $db->query("SELECT id FROM licenses WHERE license_key IS NULL")->fetchAll();
            foreach ($rows as $r) {
                $key = 'ROHR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
                $db->prepare("UPDATE licenses SET license_key = ? WHERE id = ?")->execute([$key, $r['id']]);
            }
        }
    } catch (Throwable $e) { /* ignore */ }
})();

// ── Router ──
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

    // ════════════════════════════════════════
    // AUTH
    // ════════════════════════════════════════
    case 'login':
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body = getBody();
        $login    = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$login || !$password) {
            jsonResponse(['error' => 'E-Mail/Benutzername und Passwort erforderlich'], 400);
        }

        checkBruteForce();
        $db = getDB();
        // Allow login with e-mail OR username
        $user = $db->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
        $user->execute([$login, $login]);
        $user = $user->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            clearLoginAttempts();
            $_SESSION['rohrapp_user'] = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'role'     => $user['role'],
                'name'     => $user['name'],
                'email'    => $user['email'],
            ];
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            jsonResponse(['success' => true, 'user' => $_SESSION['rohrapp_user']]);
        } else {
            recordFailedLogin();
            jsonResponse(['error' => 'Ungültige E-Mail/Benutzername oder Passwort'], 401);
        }
        break;

    case 'logout':
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    case 'me':
        requireAuth();
        if ($method === 'POST') {
            $body  = getBody();
            $db    = getDB();
            $uid   = $_SESSION['rohrapp_user']['id'];
            $fields = [];
            $params = [];
            if (!empty($body['name']))  { $fields[] = 'name=?';  $params[] = trim($body['name']); }
            if (!empty($body['email'])) { $fields[] = 'email=?'; $params[] = trim($body['email']); }
            if ($fields) {
                $params[] = $uid;
                $db->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
                // Update session
                if (!empty($body['name']))  $_SESSION['rohrapp_user']['name']  = trim($body['name']);
                if (!empty($body['email'])) $_SESSION['rohrapp_user']['email'] = trim($body['email']);
            }
            jsonResponse(['success' => true, 'user' => $_SESSION['rohrapp_user']]);
        }
        jsonResponse(['user' => $_SESSION['rohrapp_user']]);
        break;

    // ════════════════════════════════════════
    // DASHBOARD STATS
    // ════════════════════════════════════════
    case 'dashboard':
        requireAuth();
        $db = getDB();
        $today = date('Y-m-d');

        $stats = [
            'missed_calls' => $db->query("SELECT COUNT(*) FROM calls WHERE status='missed' AND DATE(created_at)='$today'")->fetchColumn(),
            'total_calls' => $db->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at)='$today'")->fetchColumn(),
            'unread_emails' => $db->query("SELECT COUNT(*) FROM emails WHERE status='unread'")->fetchColumn(),
            'unread_messages' => $db->query("SELECT COUNT(*) FROM messages WHERE status='unread'")->fetchColumn(),
            'active_chats' => $db->query("SELECT COUNT(*) FROM chat_conversations WHERE status='active'")->fetchColumn(),
            'total_customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        ];

        // Last 7 days call stats
        $weekStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime($d));
            $answered = $db->query("SELECT COUNT(*) FROM calls WHERE status='answered' AND DATE(created_at)='$d'")->fetchColumn();
            $missed = $db->query("SELECT COUNT(*) FROM calls WHERE status='missed' AND DATE(created_at)='$d'")->fetchColumn();
            $weekStats[] = ['date' => $d, 'label' => $label, 'answered' => (int)$answered, 'missed' => (int)$missed];
        }
        $stats['week'] = $weekStats;

        // Recent activity
        $recent = $db->query("
            SELECT 'call' as type, caller_name as title, status as detail, created_at FROM calls
            UNION ALL
            SELECT 'email', subject, status, created_at FROM emails
            UNION ALL
            SELECT 'message', sender_name, channel, created_at FROM messages
            ORDER BY created_at DESC LIMIT 10
        ")->fetchAll();
        $stats['recent'] = $recent;

        jsonResponse($stats);
        break;

    // ════════════════════════════════════════
    // CUSTOMERS
    // ════════════════════════════════════════
    case 'customers':
        requireAuth();
        $db = getDB();

        if ($method === 'GET') {
            $search = $_GET['q'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 25;
            $offset = ($page - 1) * $limit;

            if ($search) {
                $stmt = $db->prepare("SELECT * FROM customers WHERE name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $like = "%$search%";
                $stmt->execute([$like, $like, $like, $like, $limit, $offset]);
                $total = $db->prepare("SELECT COUNT(*) FROM customers WHERE name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ?");
                $total->execute([$like, $like, $like, $like]);
            } else {
                $stmt = $db->prepare("SELECT * FROM customers ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
                $total = $db->query("SELECT COUNT(*) FROM customers");
            }

            jsonResponse([
                'customers' => $stmt->fetchAll(),
                'total' => (int)$total->fetchColumn(),
                'page' => $page,
                'pages' => ceil($total->fetchColumn() / $limit) ?: 1
            ]);
        }

        if ($method === 'POST') {
            $body = getBody();
            $stmt = $db->prepare("INSERT INTO customers (name, company, phone, email, address, city, zip, notes, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $body['name'] ?? '', $body['company'] ?? '', $body['phone'] ?? '',
                $body['email'] ?? '', $body['address'] ?? '', $body['city'] ?? '',
                $body['zip'] ?? '', $body['notes'] ?? '', $body['source'] ?? 'manual'
            ]);
            jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        }
        break;

    case 'customer':
        requireAuth();
        $db = getDB();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

        if ($method === 'GET') {
            $customer = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $customer->execute([$id]);
            $customer = $customer->fetch();
            if (!$customer) jsonResponse(['error' => 'Kunde nicht gefunden'], 404);

            // Get related data
            $calls = $db->prepare("SELECT * FROM calls WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
            $calls->execute([$id]);
            $emails = $db->prepare("SELECT * FROM emails WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
            $emails->execute([$id]);
            $messages = $db->prepare("SELECT * FROM messages WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
            $messages->execute([$id]);

            jsonResponse([
                'customer' => $customer,
                'calls' => $calls->fetchAll(),
                'emails' => $emails->fetchAll(),
                'messages' => $messages->fetchAll()
            ]);
        }

        if ($method === 'PUT' || $method === 'POST') {
            $body = getBody();
            $stmt = $db->prepare("UPDATE customers SET name=?, company=?, phone=?, email=?, address=?, city=?, zip=?, notes=?, status=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $body['name'] ?? '', $body['company'] ?? '', $body['phone'] ?? '',
                $body['email'] ?? '', $body['address'] ?? '', $body['city'] ?? '',
                $body['zip'] ?? '', $body['notes'] ?? '', $body['status'] ?? 'active', $id
            ]);
            jsonResponse(['success' => true]);
        }

        if ($method === 'DELETE') {
            $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
            jsonResponse(['success' => true]);
        }
        break;

    // ════════════════════════════════════════
    // CALLS
    // ════════════════════════════════════════
    case 'calls':
        requireAuth();
        $db = getDB();

        if ($method === 'GET') {
            $filter = $_GET['filter'] ?? 'all'; // all, missed, answered
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 30;
            $offset = ($page - 1) * $limit;

            $where = '';
            if ($filter === 'missed') $where = "WHERE status='missed'";
            elseif ($filter === 'answered') $where = "WHERE status='answered'";

            $calls = $db->query("SELECT c.*, cu.name as customer_name FROM calls c LEFT JOIN customers cu ON c.customer_id = cu.id $where ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
            $total = $db->query("SELECT COUNT(*) FROM calls $where")->fetchColumn();

            jsonResponse(['calls' => $calls, 'total' => (int)$total]);
        }

        if ($method === 'POST') {
            $body = getBody();
            $stmt = $db->prepare("INSERT INTO calls (customer_id, phone_number, caller_name, direction, duration, status, notes, agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $body['customer_id'] ?: null, $body['phone_number'] ?? '',
                $body['caller_name'] ?? '', $body['direction'] ?? 'inbound',
                $body['duration'] ?? 0, $body['status'] ?? 'answered',
                $body['notes'] ?? '', $body['agent'] ?? ''
            ]);
            jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        }
        break;

    // ════════════════════════════════════════
    // EMAILS
    // ════════════════════════════════════════
    case 'emails':
        requireAuth();
        $db = getDB();

        if ($method === 'GET') {
            $filter = $_GET['filter'] ?? 'all'; // all, unread, starred, archived
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 30;
            $offset = ($page - 1) * $limit;

            $where = '';
            if ($filter === 'unread') $where = "WHERE e.status='unread'";
            elseif ($filter === 'starred') $where = "WHERE e.is_starred=1";
            elseif ($filter === 'archived') $where = "WHERE e.status='archived'";

            $emails = $db->query("SELECT e.*, cu.name as customer_name FROM emails e LEFT JOIN customers cu ON e.customer_id = cu.id $where ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
            $total = $db->query("SELECT COUNT(*) FROM emails " . str_replace('e.', '', $where))->fetchColumn();

            jsonResponse(['emails' => $emails, 'total' => (int)$total]);
        }

        if ($method === 'POST') {
            $body = getBody();
            $stmt = $db->prepare("INSERT INTO emails (customer_id, from_address, to_address, subject, body, body_html, direction, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $body['customer_id'] ?: null, $body['from_address'] ?? '',
                $body['to_address'] ?? '', $body['subject'] ?? '',
                $body['body'] ?? '', $body['body_html'] ?? '',
                $body['direction'] ?? 'outbound', $body['status'] ?? 'read'
            ]);
            jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        }
        break;

    case 'email':
        requireAuth();
        $db = getDB();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

        if ($method === 'GET') {
            $email = $db->prepare("SELECT e.*, cu.name as customer_name FROM emails e LEFT JOIN customers cu ON e.customer_id = cu.id WHERE e.id = ?");
            $email->execute([$id]);
            $email = $email->fetch();
            if (!$email) jsonResponse(['error' => 'E-Mail nicht gefunden'], 404);
            // Mark as read
            if ($email['status'] === 'unread') {
                $db->prepare("UPDATE emails SET status='read' WHERE id=?")->execute([$id]);
                $email['status'] = 'read';
            }
            jsonResponse($email);
        }

        if ($method === 'PUT' || $method === 'POST') {
            $body = getBody();
            if (isset($body['status'])) {
                $db->prepare("UPDATE emails SET status=? WHERE id=?")->execute([$body['status'], $id]);
            }
            if (isset($body['is_starred'])) {
                $db->prepare("UPDATE emails SET is_starred=? WHERE id=?")->execute([$body['is_starred'] ? 1 : 0, $id]);
            }
            jsonResponse(['success' => true]);
        }

        if ($method === 'DELETE') {
            $db->prepare("DELETE FROM emails WHERE id=?")->execute([$id]);
            jsonResponse(['success' => true]);
        }
        break;

    // ════════════════════════════════════════
    // MESSAGES
    // ════════════════════════════════════════
    case 'messages':
        requireRole(['admin', 'enterprise', 'professional']);
        $db = getDB();

        if ($method === 'GET') {
            $filter = $_GET['filter'] ?? 'all';
            $messages = $db->query("SELECT m.*, cu.name as customer_name FROM messages m LEFT JOIN customers cu ON m.customer_id = cu.id ORDER BY m.created_at DESC LIMIT 50")->fetchAll();
            jsonResponse(['messages' => $messages]);
        }

        if ($method === 'POST') {
            $body = getBody();
            $stmt = $db->prepare("INSERT INTO messages (customer_id, channel, phone_number, sender_name, content, direction, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $body['customer_id'] ?: null, $body['channel'] ?? 'contact_form',
                $body['phone_number'] ?? '', $body['sender_name'] ?? '',
                $body['content'] ?? '', $body['direction'] ?? 'inbound',
                $body['status'] ?? 'unread'
            ]);
            jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        }
        break;

    // ════════════════════════════════════════
    // CHAT
    // ════════════════════════════════════════
    case 'chats':
        requireRole(['admin', 'enterprise', 'professional']);
        $db = getDB();
        $conversations = $db->query("SELECT cc.*, (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=cc.id) as msg_count FROM chat_conversations cc ORDER BY cc.created_at DESC LIMIT 50")->fetchAll();
        jsonResponse(['conversations' => $conversations]);
        break;

    case 'chat':
        $db = getDB();
        $id = intval($_GET['id'] ?? 0);

        if ($method === 'GET' && $id) {
            $msgs = $db->prepare("SELECT * FROM chat_messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $msgs->execute([$id]);
            jsonResponse(['messages' => $msgs->fetchAll()]);
        }

        if ($method === 'POST') {
            $body = getBody();
            // New conversation or new message
            if (!$id) {
                $stmt = $db->prepare("INSERT INTO chat_conversations (visitor_name, visitor_email, visitor_ip) VALUES (?, ?, ?)");
                $stmt->execute([$body['name'] ?? 'Besucher', $body['email'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '']);
                $id = $db->lastInsertId();
            }
            if (!empty($body['message'])) {
                $sender = $body['sender'] ?? 'visitor';
                $db->prepare("INSERT INTO chat_messages (conversation_id, sender, content) VALUES (?, ?, ?)")
                   ->execute([$id, $sender, $body['message']]);
            }
            jsonResponse(['success' => true, 'conversation_id' => $id]);
        }
        break;

    // ════════════════════════════════════════
    // SETTINGS
    // ════════════════════════════════════════
    case 'settings':
        requireRole(['admin', 'enterprise']);
        $db = getDB();

        if ($method === 'GET') {
            $settings = [];
            foreach ($db->query("SELECT * FROM settings") as $row) {
                $settings[$row['key']] = $row['value'];
            }
            jsonResponse($settings);
        }

        if ($method === 'POST') {
            $body = getBody();
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
            foreach ($body as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            jsonResponse(['success' => true]);
        }
        break;

    // ════════════════════════════════════════
    // CHANGE PASSWORD
    // ════════════════════════════════════════
    case 'change-password':
        requireAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body = getBody();
        $current = $body['current'] ?? '';
        $newPass = $body['new'] ?? '';

        if (strlen($newPass) < 8) {
            jsonResponse(['error' => 'Mindestens 8 Zeichen erforderlich'], 400);
        }

        $db = getDB();
        $user = $db->prepare("SELECT * FROM users WHERE id = ?");
        $user->execute([$_SESSION['rohrapp_user']['id']]);
        $user = $user->fetch();

        if (!password_verify($current, $user['password_hash'])) {
            jsonResponse(['error' => 'Aktuelles Passwort ist falsch'], 403);
        }

        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($newPass, PASSWORD_BCRYPT), $user['id']]);
        jsonResponse(['success' => true]);
        break;

    // ════════════════════════════════════════
    // USERS (Admin only)
    // ════════════════════════════════════════
    // ── License helper ──
    // (defined inline, not as function to avoid redeclaration on repeated includes)

    case 'users':
        requireRole('admin');
        $db = getDB();

        if ($method === 'GET') {
            $users = $db->query("
                SELECT u.id, u.username, u.role, u.name, u.company, u.email, u.avatar, u.sipgate_number,
                       u.last_login, u.created_at,
                       l.plan        AS license_plan,
                       l.status      AS license_status,
                       l.trial_ends  AS license_trial_ends,
                       l.expires_at  AS license_expires_at,
                       l.license_key AS license_key
                FROM users u
                LEFT JOIN licenses l ON l.user_id = u.id
                ORDER BY u.created_at ASC
            ")->fetchAll();
            jsonResponse(['users' => $users]);
        }

        if ($method === 'POST') {
            $body    = getBody();
            $email   = trim($body['email']   ?? '');
            $name    = trim($body['name']    ?? '');
            $company = trim($body['company'] ?? '');
            $role    = $body['role'] ?? 'starter';

            if (!$email) {
                jsonResponse(['error' => 'E-Mail ist erforderlich'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Ungültige E-Mail-Adresse'], 400);
            }
            if (!in_array($role, ['admin', 'enterprise', 'professional', 'starter'])) {
                jsonResponse(['error' => 'Ungültige Rolle'], 400);
            }

            // Check duplicate email
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                jsonResponse(['error' => 'E-Mail-Adresse bereits vergeben'], 409);
            }

            // Auto-generate username from email
            $username = strtolower(preg_replace('/[^a-z0-9_]/i', '', explode('@', $email)[0]));
            if (!$username) $username = 'user' . time();
            $baseUser = $username;
            $suffix = 1;
            while (true) {
                $cu = $db->prepare("SELECT id FROM users WHERE username = ?");
                $cu->execute([$username]);
                if (!$cu->fetch()) break;
                $username = $baseUser . $suffix++;
            }

            // Generate random password
            $plainPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#'), 0, 10);

            $sipgateNumber = trim($body['sipgate_number'] ?? '');
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, name, company, email, sipgate_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, password_hash($plainPassword, PASSWORD_BCRYPT), $role, $name, $company ?: null, $email, $sipgateNumber ?: null]);
            $newUserId = (int)$db->lastInsertId();

            // ── Auto-create license ──
            $licenseFeatures = [
                'starter'      => ['calls'=>true,'customers'=>true,'invoices'=>false,'ai_chat'=>false,'sipgate'=>false,'push'=>false,'games'=>true,'max_invoices'=>0],
                'professional' => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>50],
                'enterprise'   => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>-1],
            ];
            $planMap  = ['admin'=>'enterprise','enterprise'=>'enterprise','professional'=>'professional','starter'=>'starter'];
            $plan     = $planMap[$role] ?? 'starter';
            $features = json_encode($licenseFeatures[$plan]);
            $trialEnd = date('Y-m-d H:i:s', strtotime('+30 days'));
            $licenseKey = 'ROHR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
            $db->prepare("INSERT INTO licenses (user_id, plan, status, trial_ends, features, license_key) VALUES (?, ?, 'trial', ?, ?, ?)")
               ->execute([$newUserId, $plan, $trialEnd, $features, $licenseKey]);

            // ── Send welcome email ──
            $settings = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('company_name','company_email')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $companyName  = $settings['company_name']  ?? 'RohrApp+';
            $companyEmail = $settings['company_email'] ?? 'noreply@rohrapp.de';
            $appUrl = defined('APP_URL') ? APP_URL : 'https://rohrapp.de';
            $displayName = $name ?: $username;
            $planLabel = ucfirst($plan);
            $emailBody = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Inter,sans-serif;background:#f1f5f9;margin:0;padding:30px'>
<div style='max-width:520px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08)'>
  <div style='background:#0066a1;padding:28px 32px'>
    <div style='color:#fff;font-size:22px;font-weight:700'>RohrApp<span style=\"color:#7dd3fc\">+</span></div>
    <div style='color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px'>Ihre Zugangsdaten</div>
  </div>
  <div style='padding:32px'>
    <p style='margin:0 0 20px;color:#1e293b;font-size:15px'>Hallo <strong>" . htmlspecialchars($displayName) . "</strong>,</p>
    <p style='margin:0 0 24px;color:#475569;font-size:14px'>Ihr Konto wurde erfolgreich erstellt. Hier sind Ihre Anmeldedaten:</p>
    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:24px'>
      <div style='margin-bottom:12px'><span style='font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase'>E-Mail</span><br><span style='font-size:15px;color:#0066a1;font-weight:600'>" . htmlspecialchars($email) . "</span></div>
      <div style='margin-bottom:12px'><span style='font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase'>Passwort</span><br><span style='font-size:18px;color:#1e293b;font-weight:700;font-family:monospace;letter-spacing:2px'>" . htmlspecialchars($plainPassword) . "</span></div>
      <div><span style='font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase'>Lizenz</span><br><span style='font-size:14px;color:#059669;font-weight:600'>{$planLabel} – 30 Tage Trial</span></div>
    </div>
    <a href='{$appUrl}/admin' style='display:inline-block;background:#0066a1;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:14px'>Jetzt anmelden →</a>
    <p style='margin:24px 0 0;color:#94a3b8;font-size:12px'>Bitte ändern Sie Ihr Passwort nach der ersten Anmeldung.<br>Bei Fragen wenden Sie sich an: " . htmlspecialchars($companyEmail) . "</p>
  </div>
</div></body></html>";
            $mailHeaders = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$companyName} <{$companyEmail}>\r\nX-Mailer: RohrApp+";
            @mail($email, '=?UTF-8?B?' . base64_encode("RohrApp+ – Ihre Zugangsdaten") . '?=', $emailBody, $mailHeaders);

            jsonResponse(['success' => true, 'id' => $newUserId, 'username' => $username, 'password' => $plainPassword, 'license_key' => $licenseKey]);
        }
        break;

    case 'user':
        requireRole('admin');
        $db = getDB();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

        if ($method === 'GET') {
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.role, u.name, u.company, u.email, u.avatar, u.sipgate_number,
                       u.last_login, u.created_at,
                       l.id          AS license_id,
                       l.plan        AS license_plan,
                       l.status      AS license_status,
                       l.trial_ends  AS license_trial_ends,
                       l.expires_at  AS license_expires_at,
                       l.features    AS license_features,
                       l.license_key   AS license_key
                FROM users u
                LEFT JOIN licenses l ON l.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
            jsonResponse($user);
        }

        if ($method === 'PUT' || $method === 'POST') {
            $body   = getBody();
            $fields = [];
            $params = [];

            if (isset($body['name']))    { $fields[] = 'name = ?';    $params[] = $body['name']; }
            if (isset($body['company'])) { $fields[] = 'company = ?'; $params[] = trim($body['company']) ?: null; }
            if (isset($body['email'])) {
                if ($body['email'] && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
                    jsonResponse(['error' => 'Ungültige E-Mail-Adresse'], 400);
                }
                // Check email uniqueness (excluding self)
                if ($body['email']) {
                    $ec = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $ec->execute([$body['email'], $id]);
                    if ($ec->fetch()) jsonResponse(['error' => 'E-Mail bereits vergeben'], 409);
                }
                $fields[] = 'email = ?'; $params[] = $body['email'];
            }
            if (array_key_exists('sipgate_number', $body)) {
                $fields[] = 'sipgate_number = ?';
                $params[] = trim($body['sipgate_number']) ?: null;
            }
            if (isset($body['role'])) {
                if (!in_array($body['role'], ['admin', 'enterprise', 'professional', 'starter'])) {
                    jsonResponse(['error' => 'Ungültige Rolle'], 400);
                }
                $fields[] = 'role = ?';
                $params[] = $body['role'];
            }
            if (!empty($body['password'])) {
                if (strlen($body['password']) < 8) {
                    jsonResponse(['error' => 'Mindestens 8 Zeichen für Passwort'], 400);
                }
                $fields[] = 'password_hash = ?';
                $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
            }
            if ($fields) {
                $params[] = $id;
                $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            }

            // ── License update ──
            $licPlans   = ['starter', 'professional', 'enterprise'];
            $licStatuses = ['trial', 'active', 'expired', 'suspended'];
            if (isset($body['license_plan']) || isset($body['license_status']) || isset($body['license_expires_at'])) {
                $lic = $db->prepare("SELECT id FROM licenses WHERE user_id = ?");
                $lic->execute([$id]);
                $licRow = $lic->fetch();

                if ($licRow) {
                    $lf = []; $lp = [];
                    if (isset($body['license_plan']) && in_array($body['license_plan'], $licPlans)) {
                        $lf[] = 'plan = ?'; $lp[] = $body['license_plan'];
                        // Update features for new plan
                        $licenseFeatures = [
                            'starter'      => ['calls'=>true,'customers'=>true,'invoices'=>false,'ai_chat'=>false,'sipgate'=>false,'push'=>false,'games'=>true,'max_invoices'=>0],
                            'professional' => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>50],
                            'enterprise'   => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>-1],
                        ];
                        $lf[] = 'features = ?'; $lp[] = json_encode($licenseFeatures[$body['license_plan']] ?? $licenseFeatures['starter']);
                    }
                    if (isset($body['license_status']) && in_array($body['license_status'], $licStatuses)) {
                        $lf[] = 'status = ?'; $lp[] = $body['license_status'];
                    }
                    if (isset($body['license_expires_at'])) {
                        $lf[] = 'expires_at = ?'; $lp[] = $body['license_expires_at'] ?: null;
                    }
                    if ($lf) { $lp[] = $id; $db->prepare("UPDATE licenses SET " . implode(', ', $lf) . " WHERE user_id = ?")->execute($lp); }
                } else {
                    // Create missing license
                    $plan = $body['license_plan'] ?? 'starter';
                    $licenseFeatures = [
                        'starter'      => ['calls'=>true,'customers'=>true,'invoices'=>false,'ai_chat'=>false,'sipgate'=>false,'push'=>false,'games'=>true,'max_invoices'=>0],
                        'professional' => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>50],
                        'enterprise'   => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>-1],
                    ];
                    $db->prepare("INSERT INTO licenses (user_id, plan, status, trial_ends, features) VALUES (?, ?, 'trial', ?, ?)")
                       ->execute([$id, $plan, date('Y-m-d H:i:s', strtotime('+30 days')), json_encode($licenseFeatures[$plan] ?? $licenseFeatures['starter'])]);
                }
            }
            jsonResponse(['success' => true]);
        }

        if ($method === 'DELETE') {
            if ($id === ($_SESSION['rohrapp_user']['id'] ?? 0)) {
                jsonResponse(['error' => 'Sie können sich nicht selbst löschen'], 400);
            }
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            jsonResponse(['success' => true]);
        }
        break;

    // ════════════════════════════════════════
    // PERMISSIONS INFO
    // ════════════════════════════════════════
    case 'permissions':
        requireAuth();
        $role = getUserRole();
        $perms = [
            'dashboard' => true,
            'calls' => true,
            'calls_write' => in_array($role, ['admin', 'enterprise', 'professional']),
            'emails' => true,
            'emails_write' => in_array($role, ['admin', 'enterprise', 'professional']),
            'messages' => in_array($role, ['admin', 'enterprise', 'professional']),
            'chat' => in_array($role, ['admin', 'enterprise', 'professional']),
            'customers' => true,
            'customers_write' => true,
            'games' => true,
            'settings' => in_array($role, ['admin', 'enterprise']),
            'users' => $role === 'admin',
        ];
        jsonResponse(['role' => $role, 'permissions' => $perms]);
        break;

    // ════════════════════════════════════════
    // VERSION & AUTO-UPDATE
    // ════════════════════════════════════════
    case 'version':
        requireAuth();
        $localVersion = '?.?.?';
        $versionFile = dirname(__DIR__) . '/version.json';
        if (file_exists($versionFile)) {
            $v = json_decode(file_get_contents($versionFile), true);
            $localVersion = $v['version'] ?? '?.?.?';
        }
        jsonResponse(['local' => $localVersion]);
        break;

    case 'check-update':
        requireRole('admin');
        $localVersion = '0.0.0';
        $versionFile = dirname(__DIR__) . '/version.json';
        if (file_exists($versionFile)) {
            $v = json_decode(file_get_contents($versionFile), true);
            $localVersion = $v['version'] ?? '0.0.0';
        }

        // Fetch remote version.json from GitHub
        $remoteUrl = 'https://raw.githubusercontent.com/tekser46/RohrApp-web/main/version.json';
        $response = false;

        // Try curl first
        if (function_exists('curl_init')) {
            $ch = curl_init($remoteUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'RohrApp-Updater/1.0',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200) $response = false;
        }

        // Fallback: file_get_contents
        if (!$response && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout'    => 10,
                'user_agent' => 'RohrApp-Updater/1.0',
            ]]);
            $response = @file_get_contents($remoteUrl, false, $ctx);
        }

        if (!$response) {
            jsonResponse(['error' => 'Konnte Remote-Version nicht abrufen. Bitte manuell prüfen.', 'local' => $localVersion, 'remote_url' => $remoteUrl], 502);
        }

        $remote = json_decode($response, true);
        $remoteVersion = $remote['version'] ?? '0.0.0';
        $updateAvailable = version_compare($remoteVersion, $localVersion, '>');

        jsonResponse([
            'local' => $localVersion,
            'remote' => $remoteVersion,
            'update_available' => $updateAvailable,
            'build' => $remote['build'] ?? '',
            'channel' => $remote['channel'] ?? 'stable',
        ]);
        break;

    case 'do-update':
        requireRole('admin');
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);

        $rootDir = dirname(__DIR__);
        $tmpFile = $rootDir . '/data/update.zip';
        $tmpDir  = $rootDir . '/data/update_tmp';

        // 1. Download zip from GitHub
        $zipUrl = 'https://github.com/tekser46/RohrApp-web/archive/refs/heads/main.zip';
        $zipData = false;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($zipUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'RohrApp-Updater/1.0',
            ]);
            $zipData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200 || !$zipData) $zipData = false;
        }

        // Fallback: file_get_contents
        if (!$zipData && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout'    => 60,
                'user_agent' => 'RohrApp-Updater/1.0',
                'follow_location' => 1,
            ]]);
            $zipData = @file_get_contents($zipUrl, false, $ctx);
        }

        if (!$zipData) {
            jsonResponse(['error' => 'Download fehlgeschlagen (HTTP ' . $httpCode . '). Bitte manuell aktualisieren.'], 502);
        }

        // Ensure data directory exists
        if (!is_dir($rootDir . '/data')) mkdir($rootDir . '/data', 0755, true);

        file_put_contents($tmpFile, $zipData);

        // 2. Extract zip
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            jsonResponse(['error' => 'ZIP-Datei konnte nicht geöffnet werden'], 500);
        }

        // Clean old tmp
        if (is_dir($tmpDir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath()); }
            rmdir($tmpDir);
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        // 3. Find extracted folder (GitHub adds "RohrApp-web-main/" prefix)
        $extractedDir = null;
        foreach (scandir($tmpDir) as $d) {
            if ($d !== '.' && $d !== '..' && is_dir($tmpDir . '/' . $d)) {
                $extractedDir = $tmpDir . '/' . $d;
                break;
            }
        }

        if (!$extractedDir) {
            unlink($tmpFile);
            jsonResponse(['error' => 'Extrahierter Ordner nicht gefunden'], 500);
        }

        // 4. Copy files (skip: data/, admin/config.php, .git/)
        $skipPaths = ['data', '.git', '.gitignore'];
        $skipFiles = ['admin/config.php'];
        $updated = [];

        function copyUpdateFiles($src, $dst, $baseSrc, &$updated, $skipPaths, $skipFiles) {
            if (!is_dir($dst)) mkdir($dst, 0755, true);
            $dir = opendir($src);
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') continue;
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;
                $relPath = ltrim(str_replace($baseSrc, '', $srcPath), '/\\');

                // Skip protected paths
                $skip = false;
                foreach ($skipPaths as $sp) {
                    if (strpos($relPath, $sp) === 0) { $skip = true; break; }
                }
                if ($skip || in_array($relPath, $skipFiles)) continue;

                if (is_dir($srcPath)) {
                    copyUpdateFiles($srcPath, $dstPath, $baseSrc, $updated, $skipPaths, $skipFiles);
                } else {
                    copy($srcPath, $dstPath);
                    $updated[] = $relPath;
                }
            }
            closedir($dir);
        }

        copyUpdateFiles($extractedDir, $rootDir, $extractedDir, $updated, $skipPaths, $skipFiles);

        // 5. Cleanup
        unlink($tmpFile);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath()); }
        rmdir($tmpDir);

        // 6. Read new version
        $newVersion = '?.?.?';
        if (file_exists($rootDir . '/version.json')) {
            $v = json_decode(file_get_contents($rootDir . '/version.json'), true);
            $newVersion = $v['version'] ?? '?.?.?';
        }

        jsonResponse([
            'success' => true,
            'version' => $newVersion,
            'files_updated' => count($updated),
            'updated_files' => array_slice($updated, 0, 20),
        ]);
        break;

    case 'register':
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body    = getBody();
        $email   = trim($body['email']   ?? '');
        $name    = trim($body['name']    ?? '');
        $company = trim($body['company'] ?? '');
        $password = $body['password'] ?? '';
        $passwordConfirm = $body['password_confirm'] ?? '';

        if (!$email || !$name || !$password) {
            jsonResponse(['error' => 'Name, E-Mail und Passwort sind erforderlich'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Ungültige E-Mail-Adresse'], 400);
        }
        if (strlen($password) < 8) {
            jsonResponse(['error' => 'Passwort muss mindestens 8 Zeichen haben'], 400);
        }
        if ($passwordConfirm && $password !== $passwordConfirm) {
            jsonResponse(['error' => 'Passwörter stimmen nicht überein'], 400);
        }

        $db = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            jsonResponse(['error' => 'Diese E-Mail ist bereits registriert'], 409);
        }

        // Auto-generate username
        $username = strtolower(preg_replace('/[^a-z0-9_]/i', '', explode('@', $email)[0]));
        if (!$username) $username = 'user' . time();
        $baseUser = $username; $suffix = 1;
        while (true) {
            $cu = $db->prepare("SELECT id FROM users WHERE username = ?");
            $cu->execute([$username]);
            if (!$cu->fetch()) break;
            $username = $baseUser . $suffix++;
        }

        $db->prepare("INSERT INTO users (username, password_hash, role, name, company, email) VALUES (?, ?, 'starter', ?, ?, ?)")
           ->execute([$username, password_hash($password, PASSWORD_BCRYPT), $name, $company ?: null, $email]);
        $newUserId = (int)$db->lastInsertId();

        $licenseFeatures = ['calls'=>true,'customers'=>true,'invoices'=>false,'ai_chat'=>false,'sipgate'=>false,'push'=>false,'games'=>true,'max_invoices'=>0];
        $trialEnd = date('Y-m-d H:i:s', strtotime('+30 days'));
        $licenseKey = 'ROHR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        $db->prepare("INSERT INTO licenses (user_id, plan, status, trial_ends, features, license_key) VALUES (?, 'starter', 'trial', ?, ?, ?)")
           ->execute([$newUserId, $trialEnd, json_encode($licenseFeatures), $licenseKey]);

        // Welcome email
        $appUrl = defined('APP_URL') ? APP_URL : 'https://rohrapp.de';
        $settings = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('company_name','company_email')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $companyName  = $settings['company_name']  ?? 'RohrApp+';
        $companyEmail = $settings['company_email'] ?? 'noreply@rohrapp.de';
        $emailBody = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Inter,sans-serif;background:#f1f5f9;margin:0;padding:30px'>
<div style='max-width:520px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08)'>
  <div style='background:#0066a1;padding:28px 32px'>
    <div style='color:#fff;font-size:22px;font-weight:700'>RohrApp<span style=\"color:#7dd3fc\">+</span></div>
    <div style='color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px'>Registrierung erfolgreich</div>
  </div>
  <div style='padding:32px'>
    <p style='margin:0 0 20px;color:#1e293b;font-size:15px'>Hallo <strong>" . htmlspecialchars($name) . "</strong>,</p>
    <p style='margin:0 0 24px;color:#475569;font-size:14px'>Ihr Konto wurde erfolgreich erstellt. Hier sind Ihre Zugangsdaten:</p>
    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:24px'>
      <div style='margin-bottom:12px'><span style='font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase'>E-Mail</span><br><span style='font-size:15px;color:#0066a1;font-weight:600'>" . htmlspecialchars($email) . "</span></div>
      <div style='margin-bottom:12px'><span style='font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase'>Lizenzschlüssel</span><br><span style='font-size:14px;font-family:monospace;font-weight:700;color:#1e293b;letter-spacing:1px'>{$licenseKey}</span></div>
      <div><span style='font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase'>Paket</span><br><span style='font-size:14px;color:#059669;font-weight:600'>Starter – 30 Tage Trial</span></div>
    </div>
    <a href='{$appUrl}/admin' style='display:inline-block;background:#0066a1;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:14px'>Jetzt anmelden →</a>
  </div>
</div></body></html>";
        $mailHeaders = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$companyName} <{$companyEmail}>";
        @mail($email, '=?UTF-8?B?' . base64_encode("RohrApp+ – Registrierung erfolgreich") . '?=', $emailBody, $mailHeaders);

        jsonResponse(['success' => true, 'message' => 'Registrierung erfolgreich! Bitte prüfen Sie Ihre E-Mail.', 'license_key' => $licenseKey]);
        break;

    default:
        jsonResponse(['error' => 'Unbekannte Aktion: ' . $action], 404);
}
