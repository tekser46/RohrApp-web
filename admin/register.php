<?php
/**
 * RohrApp+ — Öffentliche Registrierungsseite
 */
$appUrl = 'https://rohrapp.de/admin';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RohrApp+ — Registrierung</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #0066a1;
            --primary-dark: #004d7a;
            --success: #059669;
            --danger: #dc2626;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 10px;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card {
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.10);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0084cc 100%);
            padding: 28px 32px;
            text-align: center;
        }
        .logo { color: #fff; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .logo span { color: #7dd3fc; }
        .subtitle { color: rgba(255,255,255,0.75); font-size: 13px; margin-top: 4px; }
        .card-body { padding: 32px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 6px; }
        input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
        }
        input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(0,102,161,0.1); }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .btn {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn.loading::after {
            content: '';
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert {
            padding: 12px 14px;
            border-radius: var(--radius);
            font-size: 13px;
            margin-bottom: 18px;
            display: none;
        }
        .alert.error { background: rgba(220,38,38,0.08); border: 1px solid rgba(220,38,38,0.25); color: var(--danger); }
        .alert.success { background: rgba(5,150,105,0.08); border: 1px solid rgba(5,150,105,0.25); color: var(--success); }
        .alert.show { display: block; }
        .divider { text-align: center; color: var(--text-muted); font-size: 13px; margin: 20px 0 16px; position: relative; }
        .divider::before { content: ''; position: absolute; left: 0; top: 50%; width: 40%; height: 1px; background: var(--border); }
        .divider::after  { content: ''; position: absolute; right: 0; top: 50%; width: 40%; height: 1px; background: var(--border); }
        .login-link { text-align: center; font-size: 13px; color: var(--text-muted); }
        .login-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .plan-info {
            background: rgba(0,102,161,0.05);
            border: 1px solid rgba(0,102,161,0.15);
            border-radius: var(--radius);
            padding: 12px 14px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        .plan-info strong { color: var(--primary); }
        .success-card { text-align: center; padding: 8px 0; display: none; }
        .success-card.show { display: block; }
        .success-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(5,150,105,0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .license-key-box { background: #f8fafc; border: 1.5px solid var(--border); border-radius: 10px; padding: 16px; margin: 16px 0; }
        .license-key-box .key-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 6px; }
        .license-key-box .key-value { font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700; color: var(--primary); letter-spacing: 2px; }
        @media (max-width: 480px) { .card-body { padding: 24px 20px; } .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="logo">RohrApp<span>+</span></div>
        <div class="subtitle">Kostenloses Konto erstellen</div>
    </div>
    <div class="card-body">
        <div class="plan-info">
            Kostenlos starten mit dem <strong>Starter-Paket</strong> — 30 Tage Trial, kein Kreditkarte erforderlich.
        </div>

        <div class="alert error" id="alertError"></div>

        <div id="registerForm">
            <div class="grid-2">
                <div class="form-group">
                    <label>Vorname *</label>
                    <input type="text" id="firstName" placeholder="Max" required>
                </div>
                <div class="form-group">
                    <label>Nachname *</label>
                    <input type="text" id="lastName" placeholder="Mustermann" required>
                </div>
            </div>
            <div class="form-group">
                <label>Firmenname</label>
                <input type="text" id="company" placeholder="Mustermann GmbH (optional)">
            </div>
            <div class="form-group">
                <label>E-Mail *</label>
                <input type="email" id="email" placeholder="max@mustermann.de" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Passwort *</label>
                    <input type="password" id="password" placeholder="Min. 8 Zeichen" required>
                </div>
                <div class="form-group">
                    <label>Passwort wiederholen *</label>
                    <input type="password" id="passwordConfirm" placeholder="••••••••" required>
                </div>
            </div>
            <button class="btn" id="registerBtn" onclick="handleRegister()">Konto erstellen</button>
            <div class="divider">oder</div>
            <div class="login-link">Bereits registriert? <a href="index.php">Jetzt anmelden</a></div>
        </div>

        <div class="success-card" id="successCard">
            <div class="success-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:8px">Registrierung erfolgreich!</h2>
            <p style="color:#64748b;font-size:14px;margin-bottom:16px">Ihre Zugangsdaten wurden per E-Mail gesendet.</p>
            <div class="license-key-box">
                <div class="key-label">Ihr Lizenzschlüssel</div>
                <div class="key-value" id="licenseKeyDisplay"></div>
            </div>
            <p style="color:#94a3b8;font-size:12px;margin-bottom:20px">Bitte bewahren Sie diesen Schlüssel sicher auf.</p>
            <a href="index.php" style="display:inline-block;background:var(--primary);color:#fff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:600;font-size:14px">Jetzt anmelden →</a>
        </div>
    </div>
</div>

<script>
function showError(msg) {
    var el = document.getElementById('alertError');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(function() { el.classList.remove('show'); }, 5000);
}

async function handleRegister() {
    var firstName  = document.getElementById('firstName').value.trim();
    var lastName   = document.getElementById('lastName').value.trim();
    var company    = document.getElementById('company').value.trim();
    var email      = document.getElementById('email').value.trim();
    var password   = document.getElementById('password').value;
    var passwordC  = document.getElementById('passwordConfirm').value;

    if (!firstName || !lastName || !email || !password) { showError('Bitte alle Pflichtfelder ausfüllen.'); return; }
    if (password.length < 8) { showError('Passwort muss mindestens 8 Zeichen haben.'); return; }
    if (password !== passwordC) { showError('Passwörter stimmen nicht überein.'); return; }

    var btn = document.getElementById('registerBtn');
    btn.classList.add('loading');
    btn.disabled = true;

    try {
        var res = await fetch('api.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: firstName + ' ' + lastName,
                company: company,
                email: email,
                password: password,
                password_confirm: passwordC
            })
        });
        var data = await res.json();
        if (!res.ok) { showError(data.error || 'Fehler bei der Registrierung.'); return; }

        // Show success
        document.getElementById('registerForm').style.display = 'none';
        document.getElementById('licenseKeyDisplay').textContent = data.license_key || '';
        document.getElementById('successCard').classList.add('show');
    } catch(e) {
        showError('Verbindungsfehler. Bitte versuchen Sie es erneut.');
    } finally {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

// Enter key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') handleRegister();
});
</script>
</body>
</html>
