<?php
/**
 * RohrApp+ — iOS App API (v1)
 * Bearer Token Authentication + License System
 *
 * Base URL: https://rohrapp.de/rohrapp/admin/app-api.php?action=...
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════
$_dbInstance = null;
function getDB() {
    global $_dbInstance;
    if ($_dbInstance) return $_dbInstance;
    try {
        $_dbInstance = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $_dbInstance;
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Datenbankfehler. Bitte install.php ausführen.'], 500);
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getBody() {
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?? [];
        // Also accept form data
        if (empty($body) && !empty($_POST)) $body = $_POST;
    }
    return $body;
}

function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

// ── Bearer Token Auth ──
$_authUser = null;
function requireAppAuth(): array {
    global $_authUser;
    if ($_authUser) return $_authUser;

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        jsonResponse(['error' => 'Authorization header fehlt oder ungültig'], 401);
    }
    $token = trim($m[1]);
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, t.id as token_id, t.token, t.expires_at
        FROM app_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ?
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) jsonResponse(['error' => 'Ungültiger oder abgelaufener Token'], 401);
    if ($user['expires_at'] && strtotime($user['expires_at']) < time()) {
        jsonResponse(['error' => 'Token abgelaufen. Bitte erneut anmelden.'], 401);
    }

    // Update last_used
    $db->prepare("UPDATE app_tokens SET last_used = NOW() WHERE id = ?")->execute([$user['token_id']]);

    $_authUser = $user;
    return $user;
}

// ── License Check ──
function getLicense(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM licenses WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lic = $stmt->fetch();
    if (!$lic) {
        return [
            'plan'     => 'starter',
            'status'   => 'active',
            'features' => getDefaultFeatures('starter'),
        ];
    }
    $lic['features'] = json_decode($lic['features'] ?? '{}', true);
    return $lic;
}

function getDefaultFeatures(string $plan): array {
    $plans = [
        'starter'      => ['calls'=>true, 'customers'=>true, 'invoices'=>false, 'ai_chat'=>false, 'sipgate'=>false, 'push'=>false, 'games'=>true, 'max_invoices'=>0],
        'professional' => ['calls'=>true, 'customers'=>true, 'invoices'=>true,  'ai_chat'=>true,  'sipgate'=>true,  'push'=>true,  'games'=>true, 'max_invoices'=>50],
        'enterprise'   => ['calls'=>true, 'customers'=>true, 'invoices'=>true,  'ai_chat'=>true,  'sipgate'=>true,  'push'=>true,  'games'=>true, 'max_invoices'=>-1],
    ];
    return $plans[$plan] ?? $plans['starter'];
}

function requireFeature(string $feature): void {
    $user = requireAppAuth();
    $lic  = getLicense($user['id']);
    if (empty($lic['features'][$feature])) {
        $plan = $lic['plan'] ?? 'starter';
        jsonResponse([
            'error'   => "Diese Funktion erfordert einen höheren Plan. Aktuell: $plan",
            'feature' => $feature,
            'upgrade' => true,
        ], 403);
    }
}

// ── Brute Force (by IP, shared table) ──
function checkBrute() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->prepare("DELETE FROM login_attempts WHERE last_attempt < NOW() - INTERVAL ? SECOND")->execute([BRUTE_FORCE_LOCKOUT]);
    $row = $db->prepare("SELECT attempt_count FROM login_attempts WHERE ip_address = ?")->execute([$ip]) ? $db->prepare("SELECT attempt_count FROM login_attempts WHERE ip_address = ?")->execute([$ip]) : null;
    // Simplified check
    $stmt = $db->prepare("SELECT attempt_count FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    if ($row && $row['attempt_count'] >= BRUTE_FORCE_MAX) {
        jsonResponse(['error' => 'Zu viele Versuche. Bitte warten.'], 429);
    }
}

function recordFail() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->prepare("INSERT INTO login_attempts (ip_address, attempt_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt = NOW()")->execute([$ip]);
}

function clearFail() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

// ════════════════════════════════════════
// ROUTER
// ════════════════════════════════════════
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

    // ════════════════════════════════════════
    // AUTH — app-login / app-logout / me
    // ════════════════════════════════════════

    case 'app-login':
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body     = getBody();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $deviceName = trim($body['device_name'] ?? 'iPhone');
        $deviceOS   = trim($body['device_os']   ?? 'iOS');

        if (!$username || !$password) {
            jsonResponse(['error' => 'Benutzername und Passwort erforderlich'], 400);
        }

        checkBrute();
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            recordFail();
            jsonResponse(['error' => 'Ungültige Zugangsdaten'], 401);
        }

        clearFail();
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        // Generate token (90 days)
        $token     = generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
        $db->prepare("INSERT INTO app_tokens (user_id, token, device_name, device_os, expires_at) VALUES (?, ?, ?, ?, ?)")
           ->execute([$user['id'], $token, $deviceName, $deviceOS, $expiresAt]);

        $license = getLicense($user['id']);

        jsonResponse([
            'success' => true,
            'token'   => $token,
            'expires_at' => $expiresAt,
            'user'    => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'avatar'   => $user['avatar'],
            ],
            'license' => $license,
        ]);
        break;

    case 'app-logout':
        $user = requireAppAuth();
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m);
        $token = trim($m[1] ?? '');
        getDB()->prepare("DELETE FROM app_tokens WHERE token = ?")->execute([$token]);
        jsonResponse(['success' => true]);
        break;

    case 'me':
        $user = requireAppAuth();
        $license = getLicense($user['id']);
        jsonResponse([
            'user' => [
                'id'         => $user['id'],
                'username'   => $user['username'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'avatar'     => $user['avatar'],
                'last_login' => $user['last_login'],
            ],
            'license' => $license,
        ]);
        break;

    // ════════════════════════════════════════
    // LICENSE
    // ════════════════════════════════════════

    case 'license':
        $user = requireAppAuth();
        $db = getDB();
        $lic = getLicense($user['id']);

        // Check expiry
        if (!empty($lic['expires_at']) && strtotime($lic['expires_at']) < time() && $lic['status'] !== 'trial') {
            $db->prepare("UPDATE licenses SET status='expired' WHERE user_id=?")->execute([$user['id']]);
            $lic['status'] = 'expired';
        }
        if (!empty($lic['trial_ends']) && strtotime($lic['trial_ends']) < time() && $lic['status'] === 'trial') {
            $lic['status'] = 'expired_trial';
        }

        jsonResponse(['license' => $lic]);
        break;

    case 'license-update':
        // Admin: update a user's license
        $user = requireAppAuth();
        if ($user['role'] !== 'admin') jsonResponse(['error' => 'Keine Berechtigung'], 403);
        $body   = getBody();
        $uid    = intval($body['user_id'] ?? 0);
        $plan   = $body['plan'] ?? 'starter';
        $status = $body['status'] ?? 'active';
        $expiresAt = $body['expires_at'] ?? null;

        if (!in_array($plan, ['starter','professional','enterprise'])) {
            jsonResponse(['error' => 'Ungültiger Plan'], 400);
        }

        $features = json_encode(getDefaultFeatures($plan));
        $db = getDB();
        $db->prepare("INSERT INTO licenses (user_id, plan, status, expires_at, features)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE plan=VALUES(plan), status=VALUES(status), expires_at=VALUES(expires_at), features=VALUES(features), updated_at=NOW()")
           ->execute([$uid, $plan, $status, $expiresAt, $features]);

        jsonResponse(['success' => true]);
        break;

    // ════════════════════════════════════════
    // APP DASHBOARD (iOS-optimized)
    // ════════════════════════════════════════

    case 'app-dashboard':
        $user = requireAppAuth();
        $db   = getDB();
        $today = date('Y-m-d');

        $stats = [
            'calls_today'   => (int)$db->query("SELECT COUNT(*) FROM sipgate_calls WHERE DATE(created_at)='$today'")->fetchColumn(),
            'missed_today'  => (int)$db->query("SELECT COUNT(*) FROM sipgate_calls WHERE status='missed' AND DATE(created_at)='$today'")->fetchColumn(),
            'calls_total'   => (int)$db->query("SELECT COUNT(*) FROM sipgate_calls")->fetchColumn(),
            'customers'     => (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
            'unread_emails' => (int)$db->query("SELECT COUNT(*) FROM emails WHERE status='unread'")->fetchColumn(),
        ];

        // Recent sipgate calls
        $recentCalls = $db->query("
            SELECT sc.*, c.name as customer_name
            FROM sipgate_calls sc
            LEFT JOIN customers c ON sc.customer_id = c.id
            ORDER BY sc.created_at DESC LIMIT 5
        ")->fetchAll();

        $stats['recent_calls'] = $recentCalls;
        $stats['license'] = getLicense($user['id']);

        jsonResponse($stats);
        break;

    // ════════════════════════════════════════
    // SIPGATE CALLS
    // ════════════════════════════════════════

    case 'sipgate-webhook':
        // No auth — Sipgate posts directly
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $db = getDB();

        // Sipgate sends application/x-www-form-urlencoded
        $event     = $_POST['event']     ?? '';
        $callId    = $_POST['callId']    ?? '';
        $from      = $_POST['from']      ?? '';
        $to        = $_POST['to']        ?? '';
        $direction = ($_POST['direction'] ?? '') === 'out' ? 'out' : 'in';
        $cause     = $_POST['cause']     ?? '';

        // Caller name from Sipgate user array
        $callerName = '';
        if (!empty($_POST['user'])) {
            $users = is_array($_POST['user']) ? $_POST['user'] : [$_POST['user']];
            $callerName = implode(', ', $users);
        }

        switch ($event) {
            case 'newCall':
                // Find matching customer
                $cleanFrom = preg_replace('/[^+0-9]/', '', $from);
                $custStmt  = $db->prepare("SELECT id, name FROM customers WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') = ? LIMIT 1");
                $custStmt->execute([$cleanFrom]);
                $customer = $custStmt->fetch();

                $db->prepare("INSERT INTO sipgate_calls (call_id, direction, from_number, to_number, caller_name, status, customer_id, created_at)
                    VALUES (?, ?, ?, ?, ?, 'new', ?, NOW())
                    ON DUPLICATE KEY UPDATE status='new'")
                   ->execute([$callId, $direction, $from, $to, $callerName ?: ($customer['name'] ?? ''), $customer['id'] ?? null]);

                // Return Sipgate XML response (accept call)
                header('Content-Type: application/xml');
                echo '<?xml version="1.0" encoding="UTF-8"?><Response onHangup="' . htmlspecialchars(APP_URL . '/admin/app-api.php?action=sipgate-webhook') . '" onAnswer="' . htmlspecialchars(APP_URL . '/admin/app-api.php?action=sipgate-webhook') . '"></Response>';
                exit;

            case 'onAnswer':
                $db->prepare("UPDATE sipgate_calls SET status='answered', answered_at=NOW() WHERE call_id=?")
                   ->execute([$callId]);
                break;

            case 'onHangup':
                $status = 'missed';
                if ($cause === 'normalClearing') $status = 'answered';
                elseif (in_array($cause, ['cancel','noanswer'])) $status = 'missed';
                elseif ($cause === 'busy') $status = 'rejected';

                // Compute duration
                $call = $db->prepare("SELECT answered_at FROM sipgate_calls WHERE call_id=?");
                $call->execute([$callId]);
                $callRow  = $call->fetch();
                $duration = 0;
                if ($callRow && $callRow['answered_at']) {
                    $duration = max(0, time() - strtotime($callRow['answered_at']));
                }

                $db->prepare("UPDATE sipgate_calls SET status=?, duration=?, ended_at=NOW() WHERE call_id=?")
                   ->execute([$status, $duration, $callId]);
                break;
        }

        jsonResponse(['success' => true]);
        break;

    case 'sipgate-calls':
        $user = requireAppAuth();
        requireFeature('calls');
        $db     = getDB();
        $filter = $_GET['filter'] ?? 'all'; // all, incoming, outgoing, missed, irrelevant
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $where = '';
        if ($filter === 'incoming')   $where = "WHERE sc.direction='in'";
        elseif ($filter === 'outgoing') $where = "WHERE sc.direction='out'";
        elseif ($filter === 'missed')   $where = "WHERE sc.status='missed'";
        elseif ($filter === 'irrelevant') $where = "WHERE sc.status='irrelevant'";

        $calls = $db->query("
            SELECT sc.*, c.name as customer_name, c.company as customer_company
            FROM sipgate_calls sc
            LEFT JOIN customers c ON sc.customer_id = c.id
            $where
            ORDER BY sc.created_at DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll();

        $totalStmt = $db->query("SELECT COUNT(*) FROM sipgate_calls sc $where");
        $total = (int)$totalStmt->fetchColumn();

        jsonResponse(['calls' => $calls, 'total' => $total, 'page' => $page]);
        break;

    case 'sipgate-call-update':
        $user = requireAppAuth();
        requireFeature('calls');
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body = getBody();
        $id   = intval($body['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);
        $db = getDB();

        $fields = [];
        $params = [];
        if (isset($body['status'])) {
            if (!in_array($body['status'], ['new','answered','missed','rejected','irrelevant'])) {
                jsonResponse(['error' => 'Ungültiger Status'], 400);
            }
            $fields[] = 'status=?'; $params[] = $body['status'];
        }
        if (isset($body['note']))        { $fields[] = 'note=?';        $params[] = $body['note']; }
        if (isset($body['customer_id'])) { $fields[] = 'customer_id=?'; $params[] = $body['customer_id'] ?: null; }

        if (!$fields) jsonResponse(['error' => 'Keine Felder zum Aktualisieren'], 400);
        $params[] = $id;
        $db->prepare("UPDATE sipgate_calls SET " . implode(',', $fields) . " WHERE id=?")->execute($params);

        jsonResponse(['success' => true]);
        break;

    // ════════════════════════════════════════
    // CUSTOMERS (CRM)
    // ════════════════════════════════════════

    case 'app-customers':
        $user = requireAppAuth();
        requireFeature('customers');
        $db = getDB();

        if ($method === 'GET') {
            $search = trim($_GET['q'] ?? '');
            $page   = max(1, intval($_GET['page'] ?? 1));
            $limit  = 25;
            $offset = ($page - 1) * $limit;

            if ($search) {
                $like = "%$search%";
                $stmt = $db->prepare("SELECT * FROM customers WHERE status != 'blocked' AND (name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ? OR work_type LIKE ?) ORDER BY updated_at DESC LIMIT ? OFFSET ?");
                $stmt->execute([$like, $like, $like, $like, $like, $limit, $offset]);
                $countStmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE status != 'blocked' AND (name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ? OR work_type LIKE ?)");
                $countStmt->execute([$like, $like, $like, $like, $like]);
            } else {
                $stmt = $db->prepare("SELECT * FROM customers WHERE status != 'blocked' ORDER BY updated_at DESC LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
                $countStmt = $db->query("SELECT COUNT(*) FROM customers WHERE status != 'blocked'");
            }

            jsonResponse([
                'customers' => $stmt->fetchAll(),
                'total'     => (int)$countStmt->fetchColumn(),
                'page'      => $page,
            ]);
        }

        if ($method === 'POST') {
            $body = getBody();
            if (empty($body['name'])) jsonResponse(['error' => 'Name erforderlich'], 400);
            $stmt = $db->prepare("INSERT INTO customers (name, company, phone, email, address, city, zip, notes, work_type, appointment, source) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $body['name']        ?? '',
                $body['company']     ?? '',
                $body['phone']       ?? '',
                $body['email']       ?? '',
                $body['address']     ?? '',
                $body['city']        ?? '',
                $body['zip']         ?? '',
                $body['notes']       ?? '',
                $body['work_type']   ?? '',
                $body['appointment'] ?? null,
                $body['source']      ?? 'manual',
            ]);
            $newId = $db->lastInsertId();

            // Link to sipgate call if provided
            if (!empty($body['call_id'])) {
                $db->prepare("UPDATE sipgate_calls SET customer_id=? WHERE id=?")->execute([$newId, intval($body['call_id'])]);
            }

            jsonResponse(['success' => true, 'id' => $newId]);
        }
        break;

    case 'app-customer':
        $user = requireAppAuth();
        requireFeature('customers');
        $db = getDB();
        $id = intval($_GET['id'] ?? getBody()['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM customers WHERE id=?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            if (!$customer) jsonResponse(['error' => 'Kunde nicht gefunden'], 404);

            $calls = $db->prepare("SELECT * FROM sipgate_calls WHERE customer_id=? ORDER BY created_at DESC LIMIT 10");
            $calls->execute([$id]);

            jsonResponse(['customer' => $customer, 'calls' => $calls->fetchAll()]);
        }

        if ($method === 'PUT' || ($method === 'POST' && isset(getBody()['_method']) && getBody()['_method'] === 'PUT')) {
            $body = getBody();
            $stmt = $db->prepare("UPDATE customers SET name=?,company=?,phone=?,email=?,address=?,city=?,zip=?,notes=?,work_type=?,appointment=?,status=?,updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $body['name']        ?? '',
                $body['company']     ?? '',
                $body['phone']       ?? '',
                $body['email']       ?? '',
                $body['address']     ?? '',
                $body['city']        ?? '',
                $body['zip']         ?? '',
                $body['notes']       ?? '',
                $body['work_type']   ?? '',
                $body['appointment'] ?? null,
                $body['status']      ?? 'active',
                $id,
            ]);
            jsonResponse(['success' => true]);
        }

        if ($method === 'DELETE') {
            $db->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
            jsonResponse(['success' => true]);
        }
        break;

    // ════════════════════════════════════════
    // INVOICES
    // ════════════════════════════════════════

    case 'app-invoices':
        $user = requireAppAuth();
        requireFeature('invoices');
        $db   = getDB();

        if ($method === 'GET') {
            $status = $_GET['status'] ?? '';
            $search = trim($_GET['q'] ?? '');
            $page   = max(1, intval($_GET['page'] ?? 1));
            $limit  = 25;
            $offset = ($page - 1) * $limit;

            $where  = 'WHERE i.user_id = ?';
            $params = [$user['id']];

            if ($status && in_array($status, ['draft','sent','paid','storniert'])) {
                $where .= ' AND i.status = ?'; $params[] = $status;
            }
            if ($search) {
                $where .= ' AND (i.invoice_number LIKE ? OR i.customer_name LIKE ?)';
                $like = "%$search%"; $params[] = $like; $params[] = $like;
            }

            $stmt = $db->prepare("SELECT i.*, c.name as linked_customer_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id $where ORDER BY i.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM invoices i $where");
            $countStmt->execute($params);

            $invoices = $stmt->fetchAll();
            // Attach items
            foreach ($invoices as &$inv) {
                $items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order");
                $items->execute([$inv['id']]);
                $inv['items'] = $items->fetchAll();
            }

            jsonResponse(['invoices' => $invoices, 'total' => (int)$countStmt->fetchColumn(), 'page' => $page]);
        }

        if ($method === 'POST') {
            $body = getBody();

            // Check invoice limit
            $lic = getLicense($user['id']);
            $maxInv = $lic['features']['max_invoices'] ?? 0;
            if ($maxInv !== -1) {
                $count = (int)$db->prepare("SELECT COUNT(*) FROM invoices WHERE user_id=?")->execute([$user['id']]) ? $db->query("SELECT COUNT(*) FROM invoices WHERE user_id={$user['id']}")->fetchColumn() : 0;
                if ($count >= $maxInv) {
                    jsonResponse(['error' => "Rechnungslimit erreicht ($maxInv). Bitte upgraden.", 'upgrade' => true], 403);
                }
            }

            // Generate invoice number: RE-YYYY-XXXXX
            $year = date('Y');
            $last = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number,'-',-1) AS UNSIGNED)) FROM invoices WHERE invoice_number LIKE 'RE-$year-%'")->fetchColumn();
            $num  = str_pad(($last ?? 0) + 1, 5, '0', STR_PAD_LEFT);
            $invoiceNumber = "RE-$year-$num";

            $items    = $body['items'] ?? [];
            $taxRate  = floatval($body['tax_rate'] ?? 19);
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += floatval($item['quantity'] ?? 1) * floatval($item['unit_price'] ?? 0);
            }
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total     = round($subtotal + $taxAmount, 2);

            $stmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_id, user_id, status, customer_name, customer_address, customer_email, subtotal, tax_rate, tax_amount, total, notes, due_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $invoiceNumber,
                $body['customer_id'] ?: null,
                $user['id'],
                $body['status'] ?? 'draft',
                $body['customer_name']    ?? '',
                $body['customer_address'] ?? '',
                $body['customer_email']   ?? '',
                $subtotal, $taxRate, $taxAmount, $total,
                $body['notes']    ?? '',
                $body['due_date'] ?? null,
            ]);
            $invoiceId = $db->lastInsertId();

            $itemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total, sort_order) VALUES (?,?,?,?,?,?)");
            foreach ($items as $i => $item) {
                $qty   = floatval($item['quantity']   ?? 1);
                $price = floatval($item['unit_price'] ?? 0);
                $itemStmt->execute([$invoiceId, $item['description'] ?? '', $qty, $price, round($qty * $price, 2), $i]);
            }

            jsonResponse(['success' => true, 'id' => $invoiceId, 'invoice_number' => $invoiceNumber]);
        }
        break;

    case 'app-invoice':
        $user = requireAppAuth();
        requireFeature('invoices');
        $db = getDB();
        $id = intval($_GET['id'] ?? getBody()['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);

        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM invoices WHERE id=? AND user_id=?");
            $stmt->execute([$id, $user['id']]);
            $inv = $stmt->fetch();
            if (!$inv) jsonResponse(['error' => 'Rechnung nicht gefunden'], 404);
            $items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order");
            $items->execute([$id]);
            $inv['items'] = $items->fetchAll();
            jsonResponse(['invoice' => $inv]);
        }

        if ($method === 'POST') {
            $body = getBody();
            // Update invoice
            $items    = $body['items'] ?? null;
            $taxRate  = floatval($body['tax_rate'] ?? 19);
            $subtotal = 0;
            if ($items !== null) {
                foreach ($items as $item) {
                    $subtotal += floatval($item['quantity'] ?? 1) * floatval($item['unit_price'] ?? 0);
                }
            } else {
                $subtotal = floatval($body['subtotal'] ?? 0);
            }
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total     = round($subtotal + $taxAmount, 2);

            $db->prepare("UPDATE invoices SET status=?,customer_name=?,customer_address=?,customer_email=?,subtotal=?,tax_rate=?,tax_amount=?,total=?,notes=?,due_date=?,updated_at=NOW() WHERE id=? AND user_id=?")
               ->execute([
                   $body['status'] ?? 'draft',
                   $body['customer_name']    ?? '',
                   $body['customer_address'] ?? '',
                   $body['customer_email']   ?? '',
                   $subtotal, $taxRate, $taxAmount, $total,
                   $body['notes']    ?? '',
                   $body['due_date'] ?? null,
                   $id, $user['id'],
               ]);

            if ($items !== null) {
                $db->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$id]);
                $itemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total, sort_order) VALUES (?,?,?,?,?,?)");
                foreach ($items as $i => $item) {
                    $qty   = floatval($item['quantity']   ?? 1);
                    $price = floatval($item['unit_price'] ?? 0);
                    $itemStmt->execute([$id, $item['description'] ?? '', $qty, $price, round($qty * $price, 2), $i]);
                }
            }

            jsonResponse(['success' => true]);
        }

        if ($method === 'DELETE') {
            $db->prepare("DELETE FROM invoices WHERE id=? AND user_id=?")->execute([$id, $user['id']]);
            jsonResponse(['success' => true]);
        }
        break;

    case 'app-invoice-send':
        $user = requireAppAuth();
        requireFeature('invoices');
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body  = getBody();
        $id    = intval($body['id'] ?? 0);
        $email = trim($body['email'] ?? '');
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Ungültige E-Mail'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT i.*, u.name as user_name, u.email as user_email FROM invoices i JOIN users u ON i.user_id = u.id WHERE i.id=? AND i.user_id=?");
        $stmt->execute([$id, $user['id']]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(['error' => 'Rechnung nicht gefunden'], 404);

        $items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order");
        $items->execute([$id]);
        $itemRows = $items->fetchAll();

        // Build HTML email
        $itemsHtml = '';
        foreach ($itemRows as $item) {
            $itemsHtml .= "<tr>
                <td style='padding:8px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($item['description']) . "</td>
                <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center'>" . number_format($item['quantity'], 2, ',', '.') . "</td>
                <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right'>" . number_format($item['unit_price'], 2, ',', '.') . " €</td>
                <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right'><strong>" . number_format($item['total'], 2, ',', '.') . " €</strong></td>
            </tr>";
        }

        $invoiceDate = date('d.m.Y', strtotime($inv['created_at']));
        $dueDate     = $inv['due_date'] ? date('d.m.Y', strtotime($inv['due_date'])) : date('d.m.Y', strtotime('+14 days'));

        $html = "<!DOCTYPE html><html lang='de'><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#1f2937'>
            <h2 style='color:#1e3a5f'>Rechnung " . htmlspecialchars($inv['invoice_number']) . "</h2>
            <p>Sehr geehrte Damen und Herren,</p>
            <p>anbei erhalten Sie Ihre Rechnung.</p>
            <hr style='border:1px solid #e5e7eb;margin:20px 0'>
            <p><strong>An:</strong> " . htmlspecialchars($inv['customer_name']) . "<br>
            <strong>Rechnungsnummer:</strong> " . htmlspecialchars($inv['invoice_number']) . "<br>
            <strong>Rechnungsdatum:</strong> $invoiceDate<br>
            <strong>Fällig bis:</strong> $dueDate</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <thead><tr style='background:#f3f4f6'>
                    <th style='padding:10px;text-align:left'>Beschreibung</th>
                    <th style='padding:10px;text-align:center'>Menge</th>
                    <th style='padding:10px;text-align:right'>Einzelpreis</th>
                    <th style='padding:10px;text-align:right'>Gesamt</th>
                </tr></thead>
                <tbody>$itemsHtml</tbody>
                <tfoot>
                    <tr><td colspan='3' style='padding:8px;text-align:right'>Nettobetrag:</td><td style='padding:8px;text-align:right'>" . number_format($inv['subtotal'], 2, ',', '.') . " €</td></tr>
                    <tr><td colspan='3' style='padding:8px;text-align:right'>MwSt. " . number_format($inv['tax_rate'], 0) . "%:</td><td style='padding:8px;text-align:right'>" . number_format($inv['tax_amount'], 2, ',', '.') . " €</td></tr>
                    <tr style='background:#1e3a5f;color:#fff'><td colspan='3' style='padding:10px;text-align:right'><strong>Gesamtbetrag:</strong></td><td style='padding:10px;text-align:right'><strong>" . number_format($inv['total'], 2, ',', '.') . " €</strong></td></tr>
                </tfoot>
            </table>
            <p style='margin-top:20px;font-size:13px;color:#6b7280'>Mit freundlichen Grüßen<br>" . htmlspecialchars($inv['user_name']) . "</p>
        </body></html>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@rohrapp.de\r\n";
        $headers .= "Reply-To: " . ($inv['user_email'] ?? 'noreply@rohrapp.de') . "\r\n";

        $subject = "Rechnung " . $inv['invoice_number'];
        $sent    = @mail($email, $subject, $html, $headers);

        if ($sent) {
            $db->prepare("UPDATE invoices SET status='sent', sent_at=NOW() WHERE id=?")->execute([$id]);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'E-Mail konnte nicht gesendet werden'], 500);
        }
        break;

    case 'app-invoice-cancel':
        $user = requireAppAuth();
        requireFeature('invoices');
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body   = getBody();
        $id     = intval($body['id'] ?? 0);
        $status = $body['status'] ?? 'storniert';
        if (!$id) jsonResponse(['error' => 'ID erforderlich'], 400);
        if (!in_array($status, ['storniert','paid','open','draft','sent'])) {
            jsonResponse(['error' => 'Ungültiger Status'], 400);
        }
        $db = getDB();
        $extra = $status === 'paid' ? ", paid_at=NOW()" : '';
        $db->prepare("UPDATE invoices SET status=?$extra WHERE id=? AND user_id=?")->execute([$status, $id, $user['id']]);
        jsonResponse(['success' => true]);
        break;

    // ════════════════════════════════════════
    // APNs PUSH NOTIFICATIONS
    // ════════════════════════════════════════

    case 'apns-register':
        $user = requireAppAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body        = getBody();
        $deviceToken = trim($body['device_token'] ?? '');
        $bundleId    = trim($body['bundle_id'] ?? 'de.sahinkurt.rohrexpert');
        $env         = $body['environment'] ?? 'production';
        if (!$deviceToken) jsonResponse(['error' => 'device_token erforderlich'], 400);

        $db = getDB();
        $db->prepare("INSERT INTO apns_devices (user_id, device_token, bundle_id, environment) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE bundle_id=VALUES(bundle_id), environment=VALUES(environment), updated_at=NOW()")
           ->execute([$user['id'], $deviceToken, $bundleId, $env]);

        jsonResponse(['success' => true]);
        break;

    case 'apns-unregister':
        $user = requireAppAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body        = getBody();
        $deviceToken = trim($body['device_token'] ?? '');
        if (!$deviceToken) jsonResponse(['error' => 'device_token erforderlich'], 400);
        getDB()->prepare("DELETE FROM apns_devices WHERE user_id=? AND device_token=?")->execute([$user['id'], $deviceToken]);
        jsonResponse(['success' => true]);
        break;

    // ════════════════════════════════════════
    // GAME SCORES
    // ════════════════════════════════════════

    case 'game-scores':
        $user = requireAppAuth();
        $db   = getDB();
        $game = $_GET['game'] ?? 'rohr_puzzle';

        if ($method === 'GET') {
            $limit = min(50, intval($_GET['limit'] ?? 20));
            $scores = $db->prepare("
                SELECT gs.*, u.name as player_name, u.username
                FROM game_scores gs
                JOIN users u ON gs.user_id = u.id
                WHERE gs.game = ?
                ORDER BY gs.score DESC
                LIMIT $limit
            ");
            $scores->execute([$game]);
            $all = $scores->fetchAll();

            // My best
            $myBest = $db->prepare("SELECT MAX(score) FROM game_scores WHERE user_id=? AND game=?");
            $myBest->execute([$user['id'], $game]);

            jsonResponse([
                'scores'    => $all,
                'my_best'   => (int)$myBest->fetchColumn(),
                'game'      => $game,
            ]);
        }

        if ($method === 'POST') {
            $body  = getBody();
            $score = intval($body['score'] ?? 0);
            $level = intval($body['level'] ?? 1);
            $dur   = intval($body['duration'] ?? 0);
            $gameName = $body['game'] ?? $game;

            if (!in_array($gameName, ['rohr_puzzle','rohr_tetris'])) {
                jsonResponse(['error' => 'Ungültiges Spiel'], 400);
            }
            if ($score < 0 || $score > 9999999) jsonResponse(['error' => 'Ungültiger Score'], 400);

            $db->prepare("INSERT INTO game_scores (user_id, game, score, level, duration) VALUES (?,?,?,?,?)")
               ->execute([$user['id'], $gameName, $score, $level, $dur]);

            // Return rank
            $rank = (int)$db->prepare("SELECT COUNT(*) + 1 FROM game_scores WHERE game=? AND score > ?")->execute([$gameName, $score])
                ? $db->query("SELECT COUNT(*) + 1 FROM game_scores WHERE game='$gameName' AND score > $score")->fetchColumn()
                : 1;

            jsonResponse(['success' => true, 'rank' => $rank]);
        }
        break;

    // ════════════════════════════════════════
    // PROFILE & SETTINGS
    // ════════════════════════════════════════

    case 'app-profile':
        $user = requireAppAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body = getBody();
        $db   = getDB();

        $fields = [];
        $params = [];
        if (isset($body['name']))  { $fields[] = 'name=?';  $params[] = trim($body['name']); }
        if (isset($body['email'])) { $fields[] = 'email=?'; $params[] = trim($body['email']); }

        if (!empty($body['new_password'])) {
            if (strlen($body['new_password']) < 8) jsonResponse(['error' => 'Mindestens 8 Zeichen'], 400);
            if (empty($body['current_password'])) jsonResponse(['error' => 'Aktuelles Passwort erforderlich'], 400);
            $row = $db->prepare("SELECT password_hash FROM users WHERE id=?");
            $row->execute([$user['id']]);
            $row = $row->fetch();
            if (!password_verify($body['current_password'], $row['password_hash'])) {
                jsonResponse(['error' => 'Aktuelles Passwort falsch'], 403);
            }
            $fields[] = 'password_hash=?';
            $params[] = password_hash($body['new_password'], PASSWORD_BCRYPT);
        }

        if (!$fields) jsonResponse(['error' => 'Keine Felder angegeben'], 400);
        $params[] = $user['id'];
        $db->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
        jsonResponse(['success' => true]);
        break;

    case 'app-tokens-list':
        $user = requireAppAuth();
        $db   = getDB();
        $tokens = $db->prepare("SELECT id, device_name, device_os, last_used, expires_at, created_at FROM app_tokens WHERE user_id=? ORDER BY last_used DESC");
        $tokens->execute([$user['id']]);
        jsonResponse(['tokens' => $tokens->fetchAll()]);
        break;

    case 'app-token-revoke':
        $user = requireAppAuth();
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body     = getBody();
        $tokenId  = intval($body['token_id'] ?? 0);
        if (!$tokenId) jsonResponse(['error' => 'token_id erforderlich'], 400);
        getDB()->prepare("DELETE FROM app_tokens WHERE id=? AND user_id=?")->execute([$tokenId, $user['id']]);
        jsonResponse(['success' => true]);
        break;

    // ════════════════════════════════════════
    // MAIL AI (Claude)
    // ════════════════════════════════════════

    case 'mail-ai-compose':
        $user = requireAppAuth();
        requireFeature('ai_chat');
        if ($method !== 'POST') jsonResponse(['error' => 'POST erforderlich'], 405);
        $body    = getBody();
        $context = trim($body['context'] ?? '');
        $tone    = $body['tone'] ?? 'professional'; // professional, friendly, formal
        $lang    = $body['language'] ?? 'de';
        if (!$context) jsonResponse(['error' => 'Kontext erforderlich'], 400);

        if (!defined('CLAUDE_API_KEY') || !CLAUDE_API_KEY) {
            jsonResponse(['error' => 'Claude API Key nicht konfiguriert'], 503);
        }

        $systemPrompt = "Du bist ein professioneller E-Mail-Assistent für ein Rohrreinigungsunternehmen. Schreibe E-Mails auf " . ($lang === 'de' ? 'Deutsch' : 'Englisch') . " in einem " . ($tone === 'formal' ? 'formellen' : ($tone === 'friendly' ? 'freundlichen' : 'professionellen')) . " Ton. Antworte NUR mit dem E-Mail-Text, ohne Erklärungen.";

        $payload = [
            'model'      => CLAUDE_MODEL,
            'max_tokens' => 800,
            'system'     => $systemPrompt,
            'messages'   => [['role' => 'user', 'content' => $context]],
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) jsonResponse(['error' => 'AI-Fehler: ' . $code], 502);
        $data    = json_decode($resp, true);
        $content = $data['content'][0]['text'] ?? '';
        jsonResponse(['email' => $content]);
        break;

    // ════════════════════════════════════════
    // EMAILS (read-only for iOS app)
    // ════════════════════════════════════════

    case 'app-emails':
        $user = requireAppAuth();
        $db   = getDB();
        $filter = $_GET['filter'] ?? 'unread';
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where = '';
        if ($filter === 'unread')   $where = "WHERE e.status='unread'";
        elseif ($filter === 'starred') $where = "WHERE e.is_starred=1";

        $emails = $db->query("SELECT e.id, e.from_address, e.subject, e.status, e.is_starred, e.created_at, cu.name as customer_name FROM emails e LEFT JOIN customers cu ON e.customer_id = cu.id $where ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $total  = $db->query("SELECT COUNT(*) FROM emails " . str_replace('e.', '', $where))->fetchColumn();

        jsonResponse(['emails' => $emails, 'total' => (int)$total]);
        break;

    // ════════════════════════════════════════
    // DEFAULT
    // ════════════════════════════════════════

    default:
        jsonResponse([
            'error'   => 'Unbekannte Aktion',
            'action'  => $action,
            'version' => '1.0.0',
            'endpoints' => [
                'POST app-login', 'POST app-logout', 'GET me',
                'GET license', 'POST license-update',
                'GET app-dashboard',
                'GET|POST sipgate-calls', 'POST sipgate-call-update', 'POST sipgate-webhook',
                'GET|POST app-customers', 'GET|POST|DELETE app-customer',
                'GET|POST app-invoices', 'GET|POST|DELETE app-invoice',
                'POST app-invoice-send', 'POST app-invoice-cancel',
                'POST apns-register', 'POST apns-unregister',
                'GET|POST game-scores',
                'POST app-profile', 'GET app-tokens-list', 'POST app-token-revoke',
                'POST mail-ai-compose',
                'GET app-emails',
            ],
        ], 404);
}
