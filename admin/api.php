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
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$password) {
            jsonResponse(['error' => 'Benutzername und Passwort erforderlich'], 400);
        }

        checkBruteForce();
        $db = getDB();
        $user = $db->prepare("SELECT * FROM users WHERE username = ?");
        $user->execute([$username]);
        $user = $user->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            clearLoginAttempts();
            $_SESSION['rohrapp_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'name' => $user['name']
            ];
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            jsonResponse(['success' => true, 'user' => $_SESSION['rohrapp_user']]);
        } else {
            recordFailedLogin();
            jsonResponse(['error' => 'Ungültiger Benutzername oder Passwort'], 401);
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
    case 'users':
        requireRole('admin');
        $db = getDB();

        if ($method === 'GET') {
            $users = $db->query("SELECT id, username, role, name, email, avatar, sipgate_number, last_login, created_at FROM users ORDER BY created_at ASC")->fetchAll();
            jsonResponse(['users' => $users]);
        }

        if ($method === 'POST') {
            $body = getBody();
            $username = trim($body['username'] ?? '');
            $password = $body['password'] ?? '';
            $name = trim($body['name'] ?? '');
            $email = trim($body['email'] ?? '');
            $role = $body['role'] ?? 'starter';

            if (!$username || !$password) {
                jsonResponse(['error' => 'Benutzername und Passwort erforderlich'], 400);
            }
            if (strlen($password) < 8) {
                jsonResponse(['error' => 'Mindestens 8 Zeichen für Passwort'], 400);
            }
            if (!in_array($role, ['admin', 'enterprise', 'professional', 'starter'])) {
                jsonResponse(['error' => 'Ungültige Rolle'], 400);
            }

            // Check duplicate username
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                jsonResponse(['error' => 'Benutzername bereits vergeben'], 409);
            }

            $sipgateNumber = trim($body['sipgate_number'] ?? '');
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, name, email, sipgate_number) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), $role, $name, $email, $sipgateNumber ?: null]);
            jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        }
        break;

    case 'user':
        requireRole('admin');
        $db = getDB();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

        if ($method === 'GET') {
            $user = $db->prepare("SELECT id, username, role, name, email, avatar, sipgate_number, last_login, created_at FROM users WHERE id = ?");
            $user->execute([$id]);
            $user = $user->fetch();
            if (!$user) jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
            jsonResponse($user);
        }

        if ($method === 'PUT' || $method === 'POST') {
            $body = getBody();

            // Update basic info
            $fields = [];
            $params = [];

            if (isset($body['name']))           { $fields[] = 'name = ?';           $params[] = $body['name']; }
            if (isset($body['email']))          { $fields[] = 'email = ?';          $params[] = $body['email']; }
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
            jsonResponse(['success' => true]);
        }

        if ($method === 'DELETE') {
            // Don't allow deleting yourself
            if ($id === $_SESSION['rohrapp_user']['id']) {
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

    default:
        jsonResponse(['error' => 'Unbekannte Aktion: ' . $action], 404);
}
