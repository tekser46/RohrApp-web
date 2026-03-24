<?php
/**
 * RohrApp+ — Login Page
 */
session_start();
if (isset($_SESSION['rohrapp_user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RohrApp+ — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a1628 0%, #0d2847 50%, #0a1628 100%);
            overflow: hidden;
            position: relative;
        }

        /* Animated background particles */
        .bg-particles {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: 0;
        }
        .bg-particles span {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(0, 102, 161, 0.3);
            border-radius: 50%;
            animation: float linear infinite;
        }
        @keyframes float {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        .logo-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #0066a1, #00a1e0);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            box-shadow: 0 8px 24px rgba(0, 102, 161, 0.4);
        }

        .app-name {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .app-name span {
            color: #00a1e0;
        }

        .app-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 36px;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            padding-left: 44px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            color: #fff;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .form-group input:focus {
            border-color: #0066a1;
            background: rgba(0, 102, 161, 0.1);
            box-shadow: 0 0 0 3px rgba(0, 102, 161, 0.15);
        }

        .form-icon {
            position: absolute;
            left: 14px;
            bottom: 14px;
            color: rgba(255, 255, 255, 0.35);
            width: 20px;
            height: 20px;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0066a1, #00a1e0);
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            color: #fff;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 102, 161, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .login-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            right: 16px;
            top: 50%;
            margin-top: -10px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .error-msg {
            background: rgba(229, 57, 53, 0.15);
            border: 1px solid rgba(229, 57, 53, 0.3);
            color: #ff6b6b;
            font-size: 13px;
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: none;
        }

        .error-msg.show {
            display: block;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }

        .footer-text {
            margin-top: 24px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.3);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 36px 24px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>

<div class="bg-particles" id="particles"></div>

<div class="login-container">
    <div class="login-card">
        <div class="logo-icon" style="background:none;box-shadow:none">
            <img src="assets/img/appicon.png" alt="RohrApp+" style="width:72px;height:72px;border-radius:18px">
        </div>
        <div class="app-name">RohrApp<span>+</span></div>
        <div class="app-subtitle">Kommunikations- & Kundenmanagement</div>

        <div class="error-msg" id="errorMsg"></div>

        <form id="loginForm" onsubmit="return handleLogin(event)">
            <div class="form-group">
                <label>E-Mail</label>
                <svg class="form-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <input type="text" id="username" name="username" placeholder="admin@rohrapp.de" required autocomplete="email" autofocus>
            </div>

            <div class="form-group">
                <label>Passwort</label>
                <svg class="form-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Anmelden</button>
            <div style="text-align:center;margin-top:16px;font-size:13px;color:rgba(255,255,255,0.6)">
                Noch kein Konto? <a href="register.php" style="color:#7dd3fc;font-weight:600;text-decoration:none">Kostenlos registrieren →</a>
            </div>
        </form>

        <div class="footer-text">RohrApp+ v1.0 &mdash; Powered by Sahin</div>
    </div>
</div>

<script>
// Animated particles
(function() {
    var c = document.getElementById('particles');
    for (var i = 0; i < 30; i++) {
        var s = document.createElement('span');
        s.style.left = Math.random() * 100 + '%';
        s.style.width = s.style.height = (Math.random() * 4 + 2) + 'px';
        s.style.animationDuration = (Math.random() * 15 + 10) + 's';
        s.style.animationDelay = (Math.random() * 10) + 's';
        c.appendChild(s);
    }
})();

function handleLogin(e) {
    e.preventDefault();
    var btn = document.getElementById('loginBtn');
    var err = document.getElementById('errorMsg');
    btn.classList.add('loading');
    btn.textContent = 'Wird angemeldet...';
    err.classList.remove('show');

    fetch('api.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            username: document.getElementById('username').value,
            password: document.getElementById('password').value
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            err.textContent = data.error || 'Anmeldung fehlgeschlagen';
            err.classList.add('show');
            btn.classList.remove('loading');
            btn.textContent = 'Anmelden';
        }
    })
    .catch(function() {
        err.textContent = 'Verbindungsfehler. Bitte erneut versuchen.';
        err.classList.add('show');
        btn.classList.remove('loading');
        btn.textContent = 'Anmelden';
    });
    return false;
}
</script>
</body>
</html>
