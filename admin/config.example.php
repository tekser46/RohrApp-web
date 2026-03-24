<?php
/**
 * RohrApp+ — Konfiguration (Beispiel)
 * Kopieren Sie diese Datei als config.php und passen Sie die Werte an.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'rohrapp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'RohrApp+');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/rohrapp');

define('SESSION_NAME', 'rohrapp_session');
define('SESSION_LIFETIME', 86400);

define('BRUTE_FORCE_MAX', 5);
define('BRUTE_FORCE_LOCKOUT', 900);

define('CLAUDE_API_KEY', '');
define('CLAUDE_MODEL', 'claude-sonnet-4-20250514');
