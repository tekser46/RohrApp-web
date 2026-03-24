<?php
/**
 * RohrApp+ — Installation Script (MySQL)
 * Creates database, tables, and default admin user.
 * Run once, then delete or restrict access.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$success = false;
$error = '';
$alreadyInstalled = false;

try {
    // Connect to MySQL server (without DB first to create it)
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    // Check if already installed
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('users', $tables)) {
        $reinstall = isset($_GET['force']);
        if (!$reinstall) {
            $alreadyInstalled = true;
        } else {
            // Drop all tables for fresh install
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            foreach ($tables as $t) {
                $pdo->exec("DROP TABLE IF EXISTS `$t`");
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }

    if (!$alreadyInstalled) {
        // ── Users ──
        $pdo->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','enterprise','professional','starter') DEFAULT 'starter',
            name VARCHAR(200),
            company VARCHAR(200),
            email VARCHAR(200),
            avatar VARCHAR(500),
            sipgate_number VARCHAR(50),
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sipgate (sipgate_number)
        ) ENGINE=InnoDB");

        // ── Customers ──
        $pdo->exec("CREATE TABLE customers (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(200) NOT NULL,
            company     VARCHAR(200),
            phone       VARCHAR(50),
            email       VARCHAR(200),
            address     VARCHAR(300),
            city        VARCHAR(100),
            zip         VARCHAR(20),
            notes       TEXT,
            work_type   VARCHAR(200),
            appointment DATETIME,
            source      ENUM('call','email','chat','manual','website') DEFAULT 'manual',
            status      ENUM('active','inactive','blocked') DEFAULT 'active',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB");

        // ── Calls ──
        $pdo->exec("CREATE TABLE calls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NULL,
            phone_number VARCHAR(50),
            caller_name VARCHAR(200),
            direction ENUM('inbound','outbound') DEFAULT 'inbound',
            duration INT DEFAULT 0,
            status ENUM('answered','missed','voicemail','busy') DEFAULT 'missed',
            notes TEXT,
            agent VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // ── Emails ──
        $pdo->exec("CREATE TABLE emails (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NULL,
            from_address VARCHAR(300),
            to_address VARCHAR(300),
            subject VARCHAR(500),
            body LONGTEXT,
            body_html LONGTEXT,
            direction ENUM('inbound','outbound') DEFAULT 'inbound',
            status ENUM('unread','read','replied','archived','draft') DEFAULT 'unread',
            is_starred TINYINT(1) DEFAULT 0,
            replied_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            INDEX idx_starred (is_starred),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // ── Messages ──
        $pdo->exec("CREATE TABLE messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NULL,
            channel ENUM('sms','whatsapp','contact_form','telegram') DEFAULT 'contact_form',
            phone_number VARCHAR(50),
            sender_name VARCHAR(200),
            content TEXT,
            direction ENUM('inbound','outbound') DEFAULT 'inbound',
            status ENUM('unread','read','replied') DEFAULT 'unread',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // ── Chat Conversations ──
        $pdo->exec("CREATE TABLE chat_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visitor_name VARCHAR(200),
            visitor_email VARCHAR(200),
            visitor_ip VARCHAR(45),
            status ENUM('active','closed','bot') DEFAULT 'active',
            customer_id INT NULL,
            assigned_to INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME,
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // ── Chat Messages ──
        $pdo->exec("CREATE TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender ENUM('visitor','agent','bot') DEFAULT 'visitor',
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (conversation_id),
            FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── Settings ──
        $pdo->exec("CREATE TABLE settings (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` TEXT
        ) ENGINE=InnoDB");

        // ── Activity Log ──
        $pdo->exec("CREATE TABLE activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(100),
            target_type VARCHAR(50),
            target_id INT,
            details TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // ── Login Attempts ──
        $pdo->exec("CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            attempt_count INT DEFAULT 1,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address)
        ) ENGINE=InnoDB");

        // ── App Tokens (iOS Bearer Auth) ──
        $pdo->exec("CREATE TABLE app_tokens (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            token       VARCHAR(128) UNIQUE NOT NULL,
            device_name VARCHAR(200),
            device_os   VARCHAR(50),
            last_used   DATETIME,
            expires_at  DATETIME,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── Licenses ──
        $pdo->exec("CREATE TABLE licenses (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL UNIQUE,
            license_key  VARCHAR(64) UNIQUE,
            plan         ENUM('starter','professional','enterprise') DEFAULT 'starter',
            status       ENUM('active','expired','suspended','trial') DEFAULT 'trial',
            trial_ends   DATETIME,
            expires_at   DATETIME,
            max_users    INT DEFAULT 1,
            features     JSON,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── Invoices ──
        $pdo->exec("CREATE TABLE invoices (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number  VARCHAR(50) UNIQUE NOT NULL,
            customer_id     INT NULL,
            user_id         INT NOT NULL,
            status          ENUM('draft','sent','paid','storniert') DEFAULT 'draft',
            customer_name   VARCHAR(200),
            customer_address TEXT,
            customer_email  VARCHAR(200),
            subtotal        DECIMAL(10,2) DEFAULT 0,
            tax_rate        DECIMAL(5,2) DEFAULT 19.00,
            tax_amount      DECIMAL(10,2) DEFAULT 0,
            total           DECIMAL(10,2) DEFAULT 0,
            notes           TEXT,
            due_date        DATE,
            paid_at         DATETIME,
            sent_at         DATETIME,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_customer (customer_id),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── Invoice Items ──
        $pdo->exec("CREATE TABLE invoice_items (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id  INT NOT NULL,
            description TEXT NOT NULL,
            quantity    DECIMAL(10,2) DEFAULT 1,
            unit_price  DECIMAL(10,2) DEFAULT 0,
            total       DECIMAL(10,2) DEFAULT 0,
            sort_order  INT DEFAULT 0,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── APNs Devices ──
        $pdo->exec("CREATE TABLE apns_devices (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            device_token VARCHAR(300) NOT NULL,
            bundle_id    VARCHAR(200) DEFAULT 'de.sahinkurt.rohrexpert',
            environment  ENUM('sandbox','production') DEFAULT 'production',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_device (user_id, device_token),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── Game Scores ──
        $pdo->exec("CREATE TABLE game_scores (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            game       VARCHAR(50) NOT NULL,
            score      INT NOT NULL DEFAULT 0,
            level      INT DEFAULT 1,
            duration   INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_game_score (game, score DESC),
            INDEX idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // ── Sipgate Calls (extended) ──
        $pdo->exec("CREATE TABLE sipgate_calls (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            call_id     VARCHAR(100) UNIQUE,
            user_id     INT NULL,
            direction   ENUM('in','out') DEFAULT 'in',
            from_number VARCHAR(50),
            to_number   VARCHAR(50),
            caller_name VARCHAR(200),
            duration    INT DEFAULT 0,
            status      ENUM('new','answered','missed','rejected','irrelevant') DEFAULT 'new',
            customer_id INT NULL,
            note        TEXT,
            answered_at DATETIME,
            ended_at    DATETIME,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_call_id (call_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_customer (customer_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // ── Default Users (one per role) ──
        $pdo->prepare("INSERT INTO users (username, password_hash, role, name, email) VALUES (?, ?, 'admin', ?, ?)")
           ->execute(['admin', password_hash('admin123', PASSWORD_BCRYPT), 'Administrator', 'admin@rohrapp.de']);
        $pdo->prepare("INSERT INTO users (username, password_hash, role, name, email) VALUES (?, ?, 'enterprise', ?, ?)")
           ->execute(['enterprise', password_hash('demo123', PASSWORD_BCRYPT), 'Enterprise User', 'enterprise@demo.de']);
        $pdo->prepare("INSERT INTO users (username, password_hash, role, name, email) VALUES (?, ?, 'professional', ?, ?)")
           ->execute(['professional', password_hash('demo123', PASSWORD_BCRYPT), 'Professional User', 'professional@demo.de']);
        $pdo->prepare("INSERT INTO users (username, password_hash, role, name, email) VALUES (?, ?, 'starter', ?, ?)")
           ->execute(['starter', password_hash('demo123', PASSWORD_BCRYPT), 'Starter User', 'starter@demo.de']);

        // ── Default Licenses ──
        $licenseFeatures = [
            'starter'      => ['calls'=>true,'customers'=>true,'invoices'=>false,'ai_chat'=>false,'sipgate'=>false,'push'=>false,'games'=>true,'max_invoices'=>0],
            'professional' => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>50],
            'enterprise'   => ['calls'=>true,'customers'=>true,'invoices'=>true,'ai_chat'=>true,'sipgate'=>true,'push'=>true,'games'=>true,'max_invoices'=>-1],
        ];
        $licenseRoleMap = ['admin'=>'enterprise','enterprise'=>'enterprise','professional'=>'professional','starter'=>'starter'];
        $allUsers = $pdo->query("SELECT id, role FROM users")->fetchAll();
        foreach ($allUsers as $u) {
            $plan = $licenseRoleMap[$u['role']] ?? 'starter';
            $features = json_encode($licenseFeatures[$plan]);
            $trialEnds = date('Y-m-d H:i:s', strtotime('+30 days'));
            $pdo->prepare("INSERT INTO licenses (user_id, plan, status, trial_ends, features) VALUES (?, ?, 'trial', ?, ?)")
               ->execute([$u['id'], $plan, $trialEnds, $features]);
        }

        // ── Default Settings ──
        $defaults = [
            'company_name' => 'Die Rohrreiniger GmbH',
            'company_email' => 'info@rohrreiniger.de',
            'company_phone' => '+49 641 12345',
            'company_address' => 'Friedrich-List-Str. 29, 35398 Gießen',
            'chat_bot_enabled' => '1',
            'chat_bot_greeting' => 'Hallo! Wie kann ich Ihnen helfen? Ich bin der virtuelle Assistent von Die Rohrreiniger GmbH.',
            'chat_bot_prompt' => 'Du bist ein freundlicher Kundenservice-Bot für Die Rohrreiniger GmbH, ein Rohrreinigungsunternehmen in Gießen. Beantworte Fragen zu Dienstleistungen, Preisen und Terminen.',
            'idle_timeout' => '300',
            'notification_sound' => '1',
            'theme' => 'light',
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // ── Demo Data ──
        $pdo->exec("INSERT INTO customers (name, company, phone, email, address, city, zip, source) VALUES
            ('Max Mustermann', 'Mustermann GmbH', '+49 171 1234567', 'max@mustermann.de', 'Hauptstr. 1', 'Frankfurt', '60311', 'call'),
            ('Anna Schmidt', '', '+49 152 9876543', 'anna.schmidt@email.de', 'Berliner Str. 42', 'Gießen', '35390', 'email'),
            ('Peter Wagner', 'Wagner Immobilien', '+49 163 5551234', 'p.wagner@wagner-immo.de', 'Marktplatz 7', 'Wetzlar', '35578', 'chat'),
            ('Maria Hoffmann', 'Hausverwaltung Hoffmann', '+49 176 3334444', 'maria@hv-hoffmann.de', 'Schillerstr. 15', 'Gießen', '35394', 'website'),
            ('Thomas Becker', '', '+49 151 7778888', 'thomas.becker@gmx.de', 'Bahnhofstr. 8', 'Marburg', '35037', 'call')
        ");

        $pdo->exec("INSERT INTO calls (customer_id, phone_number, caller_name, direction, duration, status, notes, agent, created_at) VALUES
            (1, '+49 171 1234567', 'Max Mustermann', 'inbound', 180, 'answered', 'Rohrverstopfung im Keller, Termin für morgen vereinbart', 'Karaaslan', NOW() - INTERVAL 2 HOUR),
            (2, '+49 152 9876543', 'Anna Schmidt', 'inbound', 0, 'missed', '', '', NOW() - INTERVAL 1 HOUR),
            (NULL, '+49 175 5559999', 'Unbekannt', 'inbound', 45, 'answered', 'Preisanfrage Rohrreinigung', 'Karaaslan', NOW() - INTERVAL 30 MINUTE),
            (4, '+49 176 3334444', 'Maria Hoffmann', 'inbound', 320, 'answered', 'Wartungsvertrag für 3 Mehrfamilienhäuser besprochen', 'Karaaslan', NOW() - INTERVAL 4 HOUR),
            (5, '+49 151 7778888', 'Thomas Becker', 'outbound', 90, 'answered', 'Rückruf wegen Angebot', 'Karaaslan', NOW() - INTERVAL 5 HOUR),
            (NULL, '+49 160 1112222', 'Unbekannt', 'inbound', 0, 'missed', '', '', NOW() - INTERVAL 3 HOUR)
        ");

        $pdo->exec("INSERT INTO emails (customer_id, from_address, to_address, subject, body, direction, status, created_at) VALUES
            (2, 'anna.schmidt@email.de', 'info@rohrreiniger.de', 'Terminanfrage Rohrreinigung', 'Sehr geehrte Damen und Herren,\n\nich hätte gerne einen Termin für eine Rohrreinigung in meiner Wohnung. Die Küche ist betroffen.\n\nWann hätten Sie Zeit?\n\nMit freundlichen Grüßen\nAnna Schmidt', 'inbound', 'unread', NOW() - INTERVAL 3 HOUR),
            (3, 'p.wagner@wagner-immo.de', 'info@rohrreiniger.de', 'Angebot für Mehrfamilienhaus', 'Hallo,\n\nwir benötigen eine Rohrreinigung für ein Mehrfamilienhaus mit 8 Einheiten in der Bahnhofstraße 12.\nKönnen Sie uns ein Angebot erstellen?\n\nMit freundlichen Grüßen\nPeter Wagner\nWagner Immobilien', 'inbound', 'unread', NOW() - INTERVAL 1 HOUR),
            (4, 'maria@hv-hoffmann.de', 'info@rohrreiniger.de', 'Wartungsvertrag Verlängerung', 'Guten Tag,\n\nunser Wartungsvertrag läuft Ende des Monats aus. Wir möchten diesen gerne verlängern und um ein weiteres Objekt erweitern.\n\nBitte senden Sie uns ein aktualisiertes Angebot.\n\nFreundliche Grüße\nMaria Hoffmann', 'inbound', 'read', NOW() - INTERVAL 6 HOUR),
            (1, 'info@rohrreiniger.de', 'max@mustermann.de', 'Re: Terminbestätigung', 'Sehr geehrter Herr Mustermann,\n\nhiermit bestätigen wir Ihren Termin für morgen, 14:00 Uhr.\n\nMit freundlichen Grüßen\nDie Rohrreiniger GmbH', 'outbound', 'read', NOW() - INTERVAL 1 HOUR)
        ");

        $pdo->exec("INSERT INTO messages (customer_id, channel, phone_number, sender_name, content, direction, status, created_at) VALUES
            (NULL, 'contact_form', '', 'Klaus Weber', 'Hallo, ich habe eine verstopfte Toilette. Können Sie heute noch kommen? Dringend! PLZ 35398', 'inbound', 'unread', NOW() - INTERVAL 45 MINUTE),
            (5, 'whatsapp', '+49 151 7778888', 'Thomas Becker', 'Danke für das Angebot, wir nehmen es an. Wann können Sie anfangen?', 'inbound', 'unread', NOW() - INTERVAL 2 HOUR)
        ");

        // Demo chat conversation
        $pdo->exec("INSERT INTO chat_conversations (visitor_name, visitor_email, visitor_ip, status) VALUES
            ('Sandra Klein', 'sandra@example.de', '192.168.1.100', 'active')
        ");
        $pdo->exec("INSERT INTO chat_messages (conversation_id, sender, content, created_at) VALUES
            (1, 'bot', 'Hallo! Wie kann ich Ihnen helfen? Ich bin der virtuelle Assistent von Die Rohrreiniger GmbH.', NOW() - INTERVAL 10 MINUTE),
            (1, 'visitor', 'Hallo, was kostet eine Rohrreinigung?', NOW() - INTERVAL 9 MINUTE),
            (1, 'bot', 'Die Kosten für eine Rohrreinigung hängen vom Umfang ab. Eine einfache Rohrreinigung beginnt ab ca. 89€. Für ein genaues Angebot können wir gerne einen Vor-Ort-Termin vereinbaren. Möchten Sie einen Termin?', NOW() - INTERVAL 8 MINUTE),
            (1, 'visitor', 'Ja, gerne. Geht morgen Nachmittag?', NOW() - INTERVAL 7 MINUTE)
        ");

        $success = true;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RohrApp+ Installation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; max-width: 640px; margin: 60px auto; padding: 20px; color: #333; line-height: 1.6; }
        h1 { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        h1 img { width: 48px; height: 48px; border-radius: 12px; }
        .box { border-radius: 12px; padding: 24px; margin: 20px 0; }
        .success { background: #ecfdf5; border: 1px solid #10b981; }
        .info { background: #eff6ff; border: 1px solid #3b82f6; }
        .error { background: #fef2f2; border: 1px solid #ef4444; color: #b91c1c; }
        .warning { background: #fffbeb; border: 1px solid #f59e0b; }
        a { color: #0066a1; text-decoration: none; font-weight: 600; }
        code { background: #f1f5f9; padding: 2px 8px; border-radius: 6px; font-size: 13px; font-family: 'JetBrains Mono', monospace; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 22px; background: #0066a1; color: #fff; border-radius: 10px; font-weight: 600; font-size: 14px; margin-top: 12px; }
        .btn:hover { background: #004d7a; }
        .checklist { list-style: none; margin: 12px 0; }
        .checklist li { padding: 6px 0; font-size: 14px; }
        .checklist li::before { content: '\2713'; color: #10b981; font-weight: 700; margin-right: 8px; }
    </style>
</head>
<body>

<h1>
    <img src="assets/img/appicon.png" alt="RohrApp+">
    RohrApp+ Installation
</h1>

<?php if ($error): ?>
    <div class="box error">
        <strong>Fehler:</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>
    <div class="box warning">
        <strong>Checkliste:</strong>
        <ul class="checklist" style="list-style:none">
            <li>XAMPP MySQL/MariaDB läuft?</li>
            <li>config.php Zugangsdaten korrekt?</li>
            <li>PHP PDO MySQL Extension aktiviert?</li>
        </ul>
    </div>

<?php elseif ($alreadyInstalled): ?>
    <div class="box success">
        <strong>&#10004; Bereits installiert!</strong>
        <p style="margin-top:8px">Die Datenbank <code><?= DB_NAME ?></code> existiert bereits.</p>
    </div>
    <a href="index.php" class="btn">&#8594; Zum Login</a>
    <p style="margin-top:20px;font-size:13px;color:#94a3b8">
        <a href="?force" style="color:#ef4444">Neuinstallation erzwingen</a> (alle Daten werden gelöscht!)
    </p>

<?php elseif ($success): ?>
    <div class="box success">
        <strong>&#10004; Installation erfolgreich!</strong>
        <p style="margin-top:8px">Datenbank <code><?= DB_NAME ?></code> wurde erstellt.</p>
    </div>

    <div class="box info">
        <strong>Was wurde installiert:</strong>
        <ul class="checklist">
            <li>16 Datenbanktabellen (MySQL/InnoDB)</li>
            <li>Admin-Benutzer erstellt</li>
            <li>5 Demo-Kunden</li>
            <li>6 Demo-Anrufe</li>
            <li>4 Demo-E-Mails</li>
            <li>2 Demo-Nachrichten</li>
            <li>1 Demo-Chat mit KI-Bot</li>
            <li>Standard-Einstellungen</li>
        </ul>
    </div>

    <div class="box warning">
        <strong>Login-Daten:</strong><br>
        Benutzer: <code>admin</code><br>
        Passwort: <code>admin123</code><br>
        <small style="color:#d97706">Bitte nach dem ersten Login ändern!</small>
    </div>

    <a href="index.php" class="btn">&#8594; Zum Login</a>

    <p style="margin-top:24px;font-size:13px;color:#94a3b8">
        &#9888; Diese Datei nach der Installation löschen oder den Zugriff einschränken.
    </p>
<?php endif; ?>

</body>
</html>
