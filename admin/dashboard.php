<?php
/**
 * RohrApp+ — Dashboard (SPA Shell)
 */
session_start();
if (empty($_SESSION['rohrapp_user'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['rohrapp_user'];
$role = $user['role'] ?? 'starter';

// Role-based permissions
$perms = [
    'dashboard'    => true,
    'calls'        => true,
    'emails'       => true,
    'messages'     => in_array($role, ['admin', 'enterprise', 'professional']),
    'chat'         => in_array($role, ['admin', 'enterprise', 'professional']),
    'customers'    => true,
    'games'        => true,
    'settings'     => in_array($role, ['admin', 'enterprise']),
    'users'        => $role === 'admin',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RohrApp+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-icon-sm" style="background:none">
                    <img src="assets/img/appicon.png" alt="RohrApp+" style="width:38px;height:38px;border-radius:10px">
                </div>
                <span class="logo-text">RohrApp<span class="accent">+</span></span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" title="Menü einklappen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="#dashboard" class="nav-item active" data-page="dashboard">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="#calls" class="nav-item" data-page="calls">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <span class="nav-label">Anrufe</span>
                <span class="nav-badge" id="badge-calls"></span>
            </a>
            <a href="#emails" class="nav-item" data-page="emails">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span class="nav-label">E-Mails</span>
                <span class="nav-badge" id="badge-emails"></span>
            </a>
            <?php if ($perms['messages']): ?>
            <a href="#messages" class="nav-item" data-page="messages">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span class="nav-label">Nachrichten</span>
                <span class="nav-badge" id="badge-messages"></span>
            </a>
            <?php endif; ?>
            <?php if ($perms['chat']): ?>
            <a href="#chat" class="nav-item" data-page="chat">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <span class="nav-label">Live Chat</span>
                <span class="nav-badge" id="badge-chats"></span>
            </a>
            <?php endif; ?>
            <a href="#customers" class="nav-item" data-page="customers">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="nav-label">Kunden</span>
            </a>

            <div class="nav-divider"></div>

            <a href="#games" class="nav-item" data-page="games">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4"/><circle cx="16" cy="10" r="1"/><circle cx="18" cy="12" r="1"/></svg>
                <span class="nav-label">Spiele</span>
            </a>
            <?php if ($perms['settings']): ?>
            <a href="#settings" class="nav-item" data-page="settings">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="nav-label">Einstellungen</span>
            </a>
            <?php endif; ?>
            <?php if ($perms['users']): ?>
            <a href="#users" class="nav-item" data-page="users">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="nav-label">Benutzer</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info" onclick="App.openProfile()" title="Profil bearbeiten" style="cursor:pointer;flex:1">
                <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? $user['username'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($user['name'] ?? $user['username']) ?></div>
                    <div class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></div>
                </div>
            </div>
            <button class="logout-btn" onclick="App.logout()" title="Abmelden">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </aside>

    <!-- Mobile topbar -->
    <header class="topbar" id="topbar">
        <button class="topbar-menu" id="mobileMenuBtn">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <span class="topbar-title" id="topbarTitle">Dashboard</span>
        <button class="topbar-action" onclick="App.logout()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
    </header>

    <!-- Mobile overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Profile Modal -->
    <div class="modal-overlay" id="profileModal" style="display:none" onclick="if(event.target===this)App.closeProfile()">
        <div class="modal" style="max-width:440px">
            <div class="modal-header">
                <h3 class="modal-title">Mein Profil</h3>
                <button class="modal-close" onclick="App.closeProfile()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding:16px;background:var(--bg-secondary);border-radius:12px">
                    <div class="user-avatar" style="width:52px;height:52px;font-size:22px;flex-shrink:0"><?= strtoupper(substr($user['name'] ?? $user['username'], 0, 1)) ?></div>
                    <div>
                        <div style="font-weight:600;font-size:16px"><?= htmlspecialchars($user['name'] ?? $user['username']) ?></div>
                        <div style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars(ucfirst($user['role'])) ?></div>
                    </div>
                </div>

                <div style="border-bottom:1px solid var(--border);margin-bottom:20px;padding-bottom:4px">
                    <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">Profil bearbeiten</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input class="form-input" type="text" id="profile_name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Vollständiger Name">
                </div>
                <div class="form-group">
                    <label class="form-label">E-Mail</label>
                    <input class="form-input" type="email" id="profile_email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="E-Mail Adresse">
                </div>
                <button class="btn btn-primary" style="width:100%;margin-bottom:24px" onclick="App.saveProfileInfo()">Profil speichern</button>

                <div style="border-bottom:1px solid var(--border);margin-bottom:20px;padding-bottom:4px">
                    <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">Passwort ändern</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Aktuelles Passwort</label>
                    <input class="form-input" type="password" id="profile_pw_current" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label class="form-label">Neues Passwort</label>
                    <input class="form-input" type="password" id="profile_pw_new" placeholder="Min. 8 Zeichen">
                </div>
                <div class="form-group">
                    <label class="form-label">Neues Passwort bestätigen</label>
                    <input class="form-input" type="password" id="profile_pw_confirm" placeholder="Wiederholen">
                </div>
                <button class="btn btn-danger" style="width:100%" onclick="App.saveProfilePassword()">Passwort ändern</button>
            </div>
        </div>
    </div>

    <!-- Main content area -->
    <main class="main-content" id="mainContent">
        <div class="page-content" id="pageContent">
            <!-- Dynamic content loaded here by JS -->
        </div>
    </main>

    <!-- User data for JS -->
    <script>
        window.ROHRAPP_USER = <?= json_encode($user) ?>;
        window.ROHRAPP_PERMS = <?= json_encode($perms) ?>;
        window.ROHRAPP_API = 'api.php';
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
