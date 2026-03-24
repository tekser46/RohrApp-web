/**
 * RohrApp+ — Main Application
 * SPA Router, State Management, Module Loading
 */
const App = (function() {
    'use strict';

    let currentPage = '';
    let dashboardData = null;

    // ── Permission Helper ──
    function hasAccess(feature) {
        return window.ROHRAPP_PERMS && window.ROHRAPP_PERMS[feature] === true;
    }

    function getRoleBadge(role) {
        var badges = {
            admin: '<span class="badge badge-danger">Admin</span>',
            enterprise: '<span class="badge badge-info">Enterprise</span>',
            professional: '<span class="badge badge-success">Professional</span>',
            starter: '<span class="badge badge-muted">Starter</span>'
        };
        return badges[role] || '<span class="badge badge-muted">' + esc(role) + '</span>';
    }

    // ── API Helper ──
    async function api(action, options = {}) {
        const method = options.method || 'GET';
        const params = options.params ? '&' + new URLSearchParams(options.params) : '';
        const url = 'api.php?action=' + action + params;
        const fetchOpts = { method, headers: {} };

        if (options.body) {
            fetchOpts.headers['Content-Type'] = 'application/json';
            fetchOpts.body = JSON.stringify(options.body);
        }

        const res = await fetch(url, fetchOpts);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'API Fehler');
        return data;
    }

    // ── Toast Notifications ──
    function toast(message, type = 'info') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.innerHTML = '<span>' + esc(message) + '</span>';
        container.appendChild(el);
        setTimeout(function() {
            el.style.opacity = '0';
            el.style.transform = 'translateX(40px)';
            setTimeout(function() { el.remove(); }, 300);
        }, 3500);
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ── Router ──
    function navigate(page) {
        if (page === currentPage) return;
        currentPage = page;

        // Update sidebar active state
        document.querySelectorAll('.nav-item').forEach(function(el) {
            el.classList.toggle('active', el.dataset.page === page);
        });

        // Update topbar title
        var titles = {
            dashboard: 'Dashboard', calls: 'Anrufe', emails: 'E-Mails',
            messages: 'Nachrichten', chat: 'Live Chat', customers: 'Kunden',
            games: 'Spiele', licenses: 'Lizenzen', settings: 'Einstellungen',
            users: 'Benutzerverwaltung', requests: 'Anfragen'
        };
        var tb = document.getElementById('topbarTitle');
        if (tb) tb.textContent = titles[page] || page;

        // Close mobile sidebar
        closeMobileSidebar();

        // Load page
        var content = document.getElementById('pageContent');
        content.innerHTML = '<div class="loading-page"><div class="spinner"></div></div>';

        // Permission check — redirect to dashboard if no access
        var pagePerms = { messages: 'messages', chat: 'chat', settings: 'settings', users: 'users', requests: 'requests' };
        if (pagePerms[page] && !hasAccess(pagePerms[page])) {
            content.innerHTML = '<div class="empty-state" style="padding:80px 20px"><svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><p style="font-size:16px;font-weight:600;margin:16px 0 8px">Keine Berechtigung</p><p>Ihr aktueller Tarif (<strong>' + esc(window.ROHRAPP_USER.role) + '</strong>) hat keinen Zugriff auf diese Funktion.</p><a href="' + (window.location.origin) + '/preise.html" class="btn btn-primary" style="margin-top:16px">Tarif upgraden</a></div>';
            return;
        }

        switch (page) {
            case 'dashboard': renderDashboard(); break;
            case 'calls': renderCalls(); break;
            case 'emails': renderEmails(); break;
            case 'messages': renderMessages(); break;
            case 'chat': renderChat(); break;
            case 'customers': renderCustomers(); break;
            case 'games': renderGames(); break;
            case 'licenses': renderLicenses(); break;
            case 'settings': renderMySettings(); break;
            case 'users': renderUsers(); break;
            case 'requests': renderRequests(); break;
            default: renderDashboard();
        }
    }

    // ── Mobile Sidebar ──
    function closeMobileSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }

    // ══════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════
    async function renderDashboard() {
        try {
            var data = await api('dashboard');
            dashboardData = data;
            var c = document.getElementById('pageContent');
            c.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Dashboard</h1>
                        <p class="page-subtitle">Willkommen zurück, ${esc(window.ROHRAPP_USER.name || window.ROHRAPP_USER.username)}</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card" onclick="App.go('calls')">
                        <div class="stat-icon calls">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.11 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${data.missed_calls}</div>
                            <div class="stat-label">Verpasste Anrufe heute</div>
                        </div>
                    </div>
                    <div class="stat-card" onclick="App.go('emails')">
                        <div class="stat-icon emails">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${data.unread_emails}</div>
                            <div class="stat-label">Ungelesene E-Mails</div>
                        </div>
                    </div>
                    <div class="stat-card" onclick="App.go('messages')">
                        <div class="stat-icon messages">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${data.unread_messages}</div>
                            <div class="stat-label">Ungelesene Nachrichten</div>
                        </div>
                    </div>
                    <div class="stat-card" onclick="App.go('chat')">
                        <div class="stat-icon chats">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${data.active_chats}</div>
                            <div class="stat-label">Aktive Chats</div>
                        </div>
                    </div>
                    <div class="stat-card" onclick="App.go('customers')">
                        <div class="stat-icon customers">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${data.total_customers}</div>
                            <div class="stat-label">Kunden gesamt</div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Anrufe diese Woche</span>
                            <div style="display:flex;gap:12px;font-size:11px">
                                <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;background:var(--success);border-radius:2px;display:inline-block"></span> Angenommen</span>
                                <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;background:var(--danger);border-radius:2px;display:inline-block"></span> Verpasst</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">${renderChart(data.week)}</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Letzte Aktivität</span>
                        </div>
                        <div class="card-body" style="padding:8px 20px">
                            <ul class="activity-list">${renderActivity(data.recent)}</ul>
                        </div>
                    </div>
                </div>
            `;

            // Update badges
            setBadge('calls', data.missed_calls);
            setBadge('emails', data.unread_emails);
            setBadge('messages', data.unread_messages);
            setBadge('chats', data.active_chats);

        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function renderChart(week) {
        var maxVal = 1;
        week.forEach(function(d) { maxVal = Math.max(maxVal, d.answered + d.missed); });
        var html = '';
        week.forEach(function(d) {
            var aH = Math.max(2, (d.answered / maxVal) * 140);
            var mH = Math.max(2, (d.missed / maxVal) * 140);
            if (!d.answered && !d.missed) { aH = 2; mH = 2; }
            html += '<div class="chart-bar-group">' +
                '<div class="chart-bars">' +
                '<div class="chart-bar answered" style="height:' + aH + 'px" title="' + d.answered + ' angenommen"></div>' +
                '<div class="chart-bar missed" style="height:' + mH + 'px" title="' + d.missed + ' verpasst"></div>' +
                '</div>' +
                '<span class="chart-label">' + d.label + '</span>' +
                '</div>';
        });
        return html;
    }

    function renderActivity(recent) {
        if (!recent || !recent.length) return '<li class="activity-item"><span style="color:var(--text-muted)">Keine Aktivität</span></li>';
        var html = '';
        recent.slice(0, 6).forEach(function(r) {
            var icon = r.type === 'call' ? 'call' : r.type === 'email' ? 'email' : 'message';
            var svg = r.type === 'call'
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/></svg>'
                : r.type === 'email'
                    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>'
                    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
            var time = timeAgo(r.created_at);
            html += '<li class="activity-item">' +
                '<div class="activity-icon ' + icon + '">' + svg + '</div>' +
                '<div class="activity-text"><strong>' + esc(r.title || 'Unbekannt') + '</strong> — ' + esc(r.detail || '') + '</div>' +
                '<span class="activity-time">' + time + '</span></li>';
        });
        return html;
    }

    function setBadge(id, count) {
        var el = document.getElementById('badge-' + id);
        if (!el) return;
        if (count > 0) {
            el.textContent = count;
            el.classList.add('show');
        } else {
            el.classList.remove('show');
        }
    }

    // ══════════════════════════════════════
    // CALLS
    // ══════════════════════════════════════
    async function renderCalls() {
        try {
            var data = await api('calls');
            var c = document.getElementById('pageContent');
            c.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Anrufe</h1>
                        <p class="page-subtitle">${data.total} Anrufe gesamt</p>
                    </div>
                    <button class="btn btn-primary" onclick="App.showAddCall()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Neuer Anruf
                    </button>
                </div>
                <div class="card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Anrufer</th>
                                <th>Telefon</th>
                                <th>Richtung</th>
                                <th>Dauer</th>
                                <th>Notizen</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>${renderCallRows(data.calls)}</tbody>
                    </table>
                </div>
            `;
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderCallRows(calls) {
        if (!calls.length) return '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Keine Anrufe vorhanden</td></tr>';
        return calls.map(function(c) {
            var statusBadge = c.status === 'answered'
                ? '<span class="badge badge-success">Angenommen</span>'
                : c.status === 'missed'
                    ? '<span class="badge badge-danger">Verpasst</span>'
                    : '<span class="badge badge-muted">' + esc(c.status) + '</span>';
            var dir = c.direction === 'inbound' ? '&#8592; Eingehend' : '&#8594; Ausgehend';
            var dur = c.duration > 0 ? Math.floor(c.duration / 60) + ':' + String(c.duration % 60).padStart(2, '0') : '-';
            return '<tr>' +
                '<td>' + statusBadge + '</td>' +
                '<td style="font-weight:500">' + esc(c.caller_name || c.customer_name || 'Unbekannt') + '</td>' +
                '<td style="font-family:var(--font-mono);font-size:12px">' + esc(c.phone_number) + '</td>' +
                '<td>' + dir + '</td>' +
                '<td style="font-family:var(--font-mono)">' + dur + '</td>' +
                '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(c.notes || '-') + '</td>' +
                '<td style="white-space:nowrap">' + formatDate(c.created_at) + '</td>' +
                '</tr>';
        }).join('');
    }

    // ══════════════════════════════════════
    // EMAILS
    // ══════════════════════════════════════
    async function renderEmails() {
        try {
            var data = await api('emails');
            var c = document.getElementById('pageContent');
            c.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">E-Mails</h1>
                        <p class="page-subtitle">${data.total} E-Mails</p>
                    </div>
                </div>
                <div class="card">
                    <div class="tabs">
                        <button class="tab active" data-filter="all">Alle</button>
                        <button class="tab" data-filter="unread">Ungelesen</button>
                        <button class="tab" data-filter="starred">Markiert</button>
                    </div>
                    <div id="emailList">${renderEmailList(data.emails)}</div>
                </div>
            `;
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderEmailList(emails) {
        if (!emails.length) return '<div class="empty-state"><p>Keine E-Mails</p></div>';
        return emails.map(function(e) {
            var cls = e.status === 'unread' ? ' unread' : '';
            var starCls = e.is_starred ? ' starred' : '';
            return '<div class="email-list-item' + cls + '" onclick="App.viewEmail(' + e.id + ')">' +
                '<button class="email-star' + starCls + '" onclick="event.stopPropagation();App.toggleStar(' + e.id + ',' + (e.is_starred ? 0 : 1) + ')">&#9733;</button>' +
                '<span class="email-from">' + esc(e.customer_name || e.from_address) + '</span>' +
                '<div class="email-content"><span class="email-subject">' + esc(e.subject) + '</span>' +
                '<span class="email-preview"> — ' + esc((e.body || '').substring(0, 80)) + '</span></div>' +
                '<span class="email-date">' + formatDate(e.created_at) + '</span></div>';
        }).join('');
    }

    async function viewEmail(id) {
        try {
            var data = await api('email', { params: { id: id } });
            showModal('E-Mail', `
                <div style="margin-bottom:16px">
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Von: ${esc(data.from_address)}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px">An: ${esc(data.to_address)}</div>
                    <div style="font-size:16px;font-weight:600;margin-bottom:16px">${esc(data.subject)}</div>
                    <div style="white-space:pre-wrap;font-size:13px;line-height:1.7;color:var(--text-secondary)">${esc(data.body)}</div>
                </div>
            `);
        } catch (e) { toast(e.message, 'error'); }
    }

    async function toggleStar(id, val) {
        try {
            await api('email', { method: 'POST', params: { id: id }, body: { is_starred: val } });
            renderEmails();
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // MESSAGES
    // ══════════════════════════════════════
    async function renderMessages() {
        try {
            var data = await api('messages');
            var c = document.getElementById('pageContent');
            c.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Nachrichten</h1>
                        <p class="page-subtitle">${data.messages.length} Nachrichten</p>
                    </div>
                </div>
                <div class="card">
                    <table class="data-table">
                        <thead><tr><th>Status</th><th>Absender</th><th>Kanal</th><th>Nachricht</th><th>Datum</th></tr></thead>
                        <tbody>${renderMessageRows(data.messages)}</tbody>
                    </table>
                </div>
            `;
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderMessageRows(msgs) {
        if (!msgs.length) return '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">Keine Nachrichten</td></tr>';
        return msgs.map(function(m) {
            var statusBadge = m.status === 'unread'
                ? '<span class="badge badge-info">Neu</span>'
                : '<span class="badge badge-muted">Gelesen</span>';
            var channelBadge = {
                sms: '<span class="badge badge-warning">SMS</span>',
                whatsapp: '<span class="badge badge-success">WhatsApp</span>',
                contact_form: '<span class="badge badge-info">Formular</span>'
            }[m.channel] || '<span class="badge badge-muted">' + esc(m.channel) + '</span>';
            return '<tr>' +
                '<td>' + statusBadge + '</td>' +
                '<td style="font-weight:500">' + esc(m.sender_name || m.customer_name || 'Unbekannt') + '</td>' +
                '<td>' + channelBadge + '</td>' +
                '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(m.content) + '</td>' +
                '<td style="white-space:nowrap">' + formatDate(m.created_at) + '</td></tr>';
        }).join('');
    }

    // ══════════════════════════════════════
    // CHAT
    // ══════════════════════════════════════
    async function renderChat() {
        try {
            var data = await api('chats');
            var c = document.getElementById('pageContent');
            c.innerHTML = `
                <div class="page-header">
                    <h1 class="page-title">Live Chat</h1>
                </div>
                <div class="chat-layout">
                    <div class="chat-sidebar">
                        <div class="chat-sidebar-header">
                            <div class="search-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" class="form-input" placeholder="Chat suchen...">
                            </div>
                        </div>
                        <div class="chat-list">${renderChatList(data.conversations)}</div>
                    </div>
                    <div class="chat-main" id="chatMain">
                        <div class="empty-state" style="margin:auto">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                            <p>Chat auswählen um zu beginnen</p>
                        </div>
                    </div>
                </div>
            `;
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderChatList(convs) {
        if (!convs.length) return '<div class="empty-state" style="padding:30px"><p>Keine Chats</p></div>';
        return convs.map(function(c) {
            var initial = (c.visitor_name || '?')[0].toUpperCase();
            var statusDot = c.status === 'active' ? ' style="box-shadow:0 0 0 2px #fff,0 0 0 4px var(--success)"' : '';
            return '<div class="chat-list-item" onclick="App.openChat(' + c.id + ')">' +
                '<div class="chat-avatar"' + statusDot + '>' + initial + '</div>' +
                '<div class="chat-item-info">' +
                '<div class="chat-item-name">' + esc(c.visitor_name || 'Besucher') + '</div>' +
                '<div class="chat-item-preview">' + c.msg_count + ' Nachrichten</div></div></div>';
        }).join('');
    }

    async function openChat(id) {
        try {
            var data = await api('chat', { params: { id: id } });
            var main = document.getElementById('chatMain');
            main.innerHTML = `
                <div class="chat-header">
                    <div class="chat-avatar" style="width:36px;height:36px;font-size:13px">B</div>
                    <div><div style="font-weight:600;font-size:14px">Besucher</div><div style="font-size:11px;color:var(--text-muted)">Aktiv</div></div>
                </div>
                <div class="chat-messages" id="chatMessages">${renderChatBubbles(data.messages)}</div>
                <div class="chat-input-area">
                    <input type="text" class="form-input" id="chatInput" placeholder="Nachricht eingeben..." onkeydown="if(event.key==='Enter')App.sendChat(${id})">
                    <button class="btn btn-primary" onclick="App.sendChat(${id})">Senden</button>
                </div>
            `;
            var msgs = document.getElementById('chatMessages');
            msgs.scrollTop = msgs.scrollHeight;
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderChatBubbles(msgs) {
        return msgs.map(function(m) {
            var time = formatTime(m.created_at);
            return '<div class="chat-bubble ' + m.sender + '">' +
                esc(m.content) +
                '<div class="chat-bubble-time">' + time + '</div></div>';
        }).join('');
    }

    async function sendChat(convId) {
        var input = document.getElementById('chatInput');
        var msg = input.value.trim();
        if (!msg) return;
        input.value = '';
        try {
            await api('chat', { method: 'POST', params: { id: convId }, body: { message: msg, sender: 'agent' } });
            openChat(convId);
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // CUSTOMERS
    // ══════════════════════════════════════
    async function renderCustomers() {
        try {
            var data = await api('customers');
            var c = document.getElementById('pageContent');
            c.innerHTML = `
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Kunden</h1>
                        <p class="page-subtitle">${data.total} Kunden</p>
                    </div>
                    <div style="display:flex;gap:10px">
                        <div class="search-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" class="form-input" placeholder="Suchen..." id="customerSearch" oninput="App.searchCustomers(this.value)">
                        </div>
                        <button class="btn btn-primary" onclick="App.showAddCustomer()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Neuer Kunde
                        </button>
                    </div>
                </div>
                <div class="card">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Firma</th><th>Telefon</th><th>E-Mail</th><th>Stadt</th><th>Quelle</th><th>Erstellt</th></tr></thead>
                        <tbody id="customerTableBody">${renderCustomerRows(data.customers)}</tbody>
                    </table>
                </div>
            `;
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderCustomerRows(customers) {
        if (!customers.length) return '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Keine Kunden</td></tr>';
        return customers.map(function(c) {
            var src = { call: 'Anruf', email: 'E-Mail', chat: 'Chat', manual: 'Manuell' }[c.source] || c.source;
            return '<tr style="cursor:pointer" onclick="App.viewCustomer(' + c.id + ')">' +
                '<td style="font-weight:600">' + esc(c.name) + '</td>' +
                '<td>' + esc(c.company || '-') + '</td>' +
                '<td style="font-family:var(--font-mono);font-size:12px">' + esc(c.phone || '-') + '</td>' +
                '<td>' + esc(c.email || '-') + '</td>' +
                '<td>' + esc(c.city || '-') + '</td>' +
                '<td><span class="badge badge-muted">' + src + '</span></td>' +
                '<td style="white-space:nowrap">' + formatDate(c.created_at) + '</td></tr>';
        }).join('');
    }

    var searchTimer;
    async function searchCustomers(q) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async function() {
            var data = await api('customers', { params: { q: q } });
            document.getElementById('customerTableBody').innerHTML = renderCustomerRows(data.customers);
        }, 300);
    }

    function showAddCustomer() {
        showModal('Neuer Kunde', `
            <form id="addCustomerForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div class="form-group"><label class="form-label">Name *</label><input class="form-input" name="name" required></div>
                    <div class="form-group"><label class="form-label">Firma</label><input class="form-input" name="company"></div>
                    <div class="form-group"><label class="form-label">Telefon</label><input class="form-input" name="phone"></div>
                    <div class="form-group"><label class="form-label">E-Mail</label><input class="form-input" name="email" type="email"></div>
                    <div class="form-group"><label class="form-label">Adresse</label><input class="form-input" name="address"></div>
                    <div class="form-group"><label class="form-label">Stadt</label><input class="form-input" name="city"></div>
                    <div class="form-group"><label class="form-label">PLZ</label><input class="form-input" name="zip"></div>
                </div>
                <div class="form-group"><label class="form-label">Notizen</label><textarea class="form-textarea" name="notes" rows="3"></textarea></div>
            </form>
        `, [
            { label: 'Abbrechen', cls: 'btn-secondary', action: 'closeModal()' },
            { label: 'Speichern', cls: 'btn-primary', action: 'App.saveCustomer()' }
        ]);
    }

    async function saveCustomer() {
        var form = document.getElementById('addCustomerForm');
        var fd = new FormData(form);
        var body = {};
        fd.forEach(function(v, k) { body[k] = v; });
        if (!body.name) { toast('Name ist erforderlich', 'error'); return; }
        try {
            await api('customers', { method: 'POST', body: body });
            closeModal();
            toast('Kunde erstellt', 'success');
            renderCustomers();
        } catch (e) { toast(e.message, 'error'); }
    }

    async function viewCustomer(id) {
        try {
            var data = await api('customer', { params: { id: id } });
            var cu = data.customer;
            showModal(esc(cu.name), `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                    <div><span style="font-size:11px;color:var(--text-muted)">FIRMA</span><div style="font-weight:500">${esc(cu.company || '-')}</div></div>
                    <div><span style="font-size:11px;color:var(--text-muted)">TELEFON</span><div style="font-family:var(--font-mono)">${esc(cu.phone || '-')}</div></div>
                    <div><span style="font-size:11px;color:var(--text-muted)">E-MAIL</span><div>${esc(cu.email || '-')}</div></div>
                    <div><span style="font-size:11px;color:var(--text-muted)">ADRESSE</span><div>${esc([cu.address, cu.zip, cu.city].filter(Boolean).join(', ') || '-')}</div></div>
                </div>
                ${cu.notes ? '<div style="background:var(--bg-body);padding:12px;border-radius:8px;font-size:13px;margin-bottom:16px"><strong>Notizen:</strong> ' + esc(cu.notes) + '</div>' : ''}
                <div style="font-size:12px;color:var(--text-muted)">
                    <strong>${data.calls.length}</strong> Anrufe &middot;
                    <strong>${data.emails.length}</strong> E-Mails &middot;
                    <strong>${data.messages.length}</strong> Nachrichten
                </div>
            `);
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // GAMES
    // ══════════════════════════════════════
    function renderGames() {
        var c = document.getElementById('pageContent');
        c.innerHTML = `
            <div class="page-header">
                <h1 class="page-title">Spiele</h1>
            </div>
            <div class="games-grid">
                <div class="game-card" onclick="App.startGame('snake')">
                    <div class="game-card-icon">&#128013;</div>
                    <div class="game-card-title">Snake</div>
                    <div class="game-card-desc">Klassisches Snake-Spiel</div>
                </div>
                <div class="game-card" onclick="App.startGame('2048')">
                    <div class="game-card-icon">&#127922;</div>
                    <div class="game-card-title">2048</div>
                    <div class="game-card-desc">Zahlen-Puzzle</div>
                </div>
                <div class="game-card" onclick="App.startGame('tetris')">
                    <div class="game-card-icon">&#129513;</div>
                    <div class="game-card-title">Tetris</div>
                    <div class="game-card-desc">Block-Puzzle</div>
                </div>
                <div class="game-card" onclick="App.startGame('memory')">
                    <div class="game-card-icon">&#129504;</div>
                    <div class="game-card-title">Memory</div>
                    <div class="game-card-desc">Karten-Paare finden</div>
                </div>
            </div>
            <div id="gameArea" style="margin-top:24px;display:none">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <span id="gameTitle" style="font-weight:600;font-size:16px"></span>
                    <div style="display:flex;gap:8px;align-items:center">
                        <span id="gameScore" style="font-family:var(--font-mono);font-size:14px;color:var(--primary)"></span>
                        <button class="btn btn-secondary btn-sm" onclick="App.stopGame()">Beenden</button>
                    </div>
                </div>
                <div class="game-canvas" id="gameCanvas"></div>
            </div>
        `;
    }

    // ── Minimal game stubs (to be expanded) ──
    var activeGame = null, gameInterval = null, gameKeyHandler = null;

    function startGame(name) {
        stopGame();
        document.getElementById('gameArea').style.display = 'block';
        document.getElementById('gameTitle').textContent = name.toUpperCase();
        document.getElementById('gameScore').textContent = 'Score: 0';
        var canvas = document.getElementById('gameCanvas');

        if (name === 'snake') startSnake(canvas);
        else if (name === '2048') start2048(canvas);
        else if (name === 'tetris') startTetris(canvas);
        else if (name === 'memory') startMemory(canvas);
    }

    function stopGame() {
        if (gameInterval) clearInterval(gameInterval);
        if (gameKeyHandler) document.removeEventListener('keydown', gameKeyHandler);
        activeGame = null;
        gameInterval = null;
        gameKeyHandler = null;
        var area = document.getElementById('gameArea');
        if (area) area.style.display = 'none';
    }

    // ── Snake ──
    function startSnake(canvas) {
        activeGame = 'snake';
        var W = 20, H = 15, snake = [{x:10,y:7}], dir = {x:1,y:0}, food = null, score = 0;
        function placeFood() {
            do { food = {x:Math.floor(Math.random()*W),y:Math.floor(Math.random()*H)}; }
            while (snake.some(function(s){return s.x===food.x&&s.y===food.y}));
        }
        placeFood();
        function draw() {
            var grid = [];
            for (var y=0;y<H;y++){var row='';for(var x=0;x<W;x++){
                if(snake.some(function(s){return s.x===x&&s.y===y}))row+='&#9608;&#9608;';
                else if(food.x===x&&food.y===y)row+='&#9679; ';
                else row+='&#183; ';
            }grid.push(row);}
            canvas.innerHTML=grid.join('\n');
        }
        function tick() {
            var head = {x:snake[0].x+dir.x,y:snake[0].y+dir.y};
            if(head.x<0||head.x>=W||head.y<0||head.y>=H||snake.some(function(s){return s.x===head.x&&s.y===head.y})){
                clearInterval(gameInterval);canvas.innerHTML+='\n\n   GAME OVER!\n   Score: '+score;return;
            }
            snake.unshift(head);
            if(head.x===food.x&&head.y===food.y){score+=10;placeFood();document.getElementById('gameScore').textContent='Score: '+score;}
            else snake.pop();
            draw();
        }
        gameKeyHandler = function(e){
            if(e.key==='ArrowUp'&&dir.y!==1)dir={x:0,y:-1};
            else if(e.key==='ArrowDown'&&dir.y!==-1)dir={x:0,y:1};
            else if(e.key==='ArrowLeft'&&dir.x!==1)dir={x:-1,y:0};
            else if(e.key==='ArrowRight'&&dir.x!==-1)dir={x:1,y:0};
            if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key))e.preventDefault();
        };
        document.addEventListener('keydown',gameKeyHandler);
        draw();
        gameInterval=setInterval(tick,150);
    }

    // ── 2048 ──
    function start2048(canvas) {
        activeGame = '2048';
        var grid = [[0,0,0,0],[0,0,0,0],[0,0,0,0],[0,0,0,0]], score = 0;
        function addTile(){var empty=[];for(var y=0;y<4;y++)for(var x=0;x<4;x++)if(!grid[y][x])empty.push({x:x,y:y});if(!empty.length)return;var t=empty[Math.floor(Math.random()*empty.length)];grid[t.y][t.x]=Math.random()<0.9?2:4;}
        function draw(){var s='';for(var y=0;y<4;y++){for(var x=0;x<4;x++){var v=grid[y][x];s+=v?String(v).padStart(6,' '):'     .';} s+='\n\n';}canvas.innerHTML=s;document.getElementById('gameScore').textContent='Score: '+score;}
        function move(dx,dy){var moved=false;var rng=function(n){var a=[];for(var i=0;i<n;i++)a.push(i);return a;};
            var rows=rng(4),cols=rng(4);if(dy>0)rows.reverse();if(dx>0)cols.reverse();
            rows.forEach(function(y){cols.forEach(function(x){if(!grid[y][x])return;var ny=y+dy,nx=x+dx;
                while(ny>=0&&ny<4&&nx>=0&&nx<4&&!grid[ny][nx]){grid[ny][nx]=grid[y][x];grid[y-((ny-y)-dy)][x-((nx-x)-dx)]=0;ny+=dy;nx+=dx;moved=true;}
                ny-=dy;nx-=dx;if(ny>=0&&ny<4&&nx>=0&&nx<4&&ny!==y&&nx!==x&&grid[ny][nx]===grid[ny-dy][nx-dx]){/* merge logic simplified */}
            });});if(moved)addTile();draw();}
        addTile();addTile();draw();
        gameKeyHandler=function(e){if(e.key==='ArrowUp')move(0,-1);else if(e.key==='ArrowDown')move(0,1);else if(e.key==='ArrowLeft')move(-1,0);else if(e.key==='ArrowRight')move(1,0);if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key))e.preventDefault();};
        document.addEventListener('keydown',gameKeyHandler);
    }

    // ── Tetris (stub) ──
    function startTetris(canvas) {
        activeGame = 'tetris';
        canvas.innerHTML = '\n\n     TETRIS\n\n     Pfeiltasten zum Spielen\n\n     Kommt bald...';
    }

    // ── Memory ──
    function startMemory(canvas) {
        activeGame = 'memory';
        var emojis = ['&#9829;','&#9830;','&#9827;','&#9824;','&#9733;','&#9788;','&#9728;','&#9731;'];
        var cards = emojis.concat(emojis);
        // Shuffle
        for(var i=cards.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=cards[i];cards[i]=cards[j];cards[j]=t;}
        canvas.innerHTML = '\n\n     MEMORY\n\n     Finde die Paare!\n     (Klicke auf die Karten)\n\n     Kommt bald...';
    }

    // ══════════════════════════════════════
    // SETTINGS
    // ══════════════════════════════════════
    async function renderMySettings() {
        try {
            var data = await api('settings');
            var profile = await api('my-profile');
            var numbers = [];
            try { var nd = await api('my-sipgate-numbers'); numbers = nd.numbers || []; } catch(e) {}
            var c = document.getElementById('pageContent');
            var initial = (profile.name || '?').charAt(0).toUpperCase();
            var avatarUrl = profile.avatar;
            var logoUrl = profile.company_logo;
            var hasSipgate = parseInt(profile.has_sipgate) === 1;
            var webhookUrl = window.location.origin + '/admin/api.php?action=sipgate-webhook&key=' + (window.ROHRAPP_USER.id || '');

            // Number rows
            var numRows = '';
            numbers.forEach(function(n) {
                numRows += '<tr><td style="font-weight:600;font-family:monospace">' + esc(n.number) + '</td><td>' + esc(n.label || '-') + '</td><td>' + esc(n.block_name || '-') + '</td><td><span class="badge ' + (n.is_active ? 'badge-success' : 'badge-muted') + '">' + (n.is_active ? 'Aktiv' : 'Inaktiv') + '</span></td></tr>';
            });

            c.innerHTML = `
                <div class="page-header"><h1 class="page-title">Einstellungen</h1></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

                    <!-- ══ 1. Profil & Firma ══ -->
                    <div class="card" style="grid-column:1/-1">
                        <div class="card-header"><span class="card-title">Profil & Firma</span></div>
                        <div class="card-body">
                            <div style="display:flex;gap:32px;margin-bottom:24px;flex-wrap:wrap">
                                <div style="text-align:center">
                                    <div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;margin-bottom:8px">Profilbild</div>
                                    <div style="position:relative;width:88px;height:88px;cursor:pointer" onclick="App.uploadAvatar()">
                                        ${avatarUrl ? '<img id="profileAvatar" src="' + esc(avatarUrl) + '" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">' : '<div id="profileAvatar" style="width:88px;height:88px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;border:3px solid var(--border)">' + esc(initial) + '</div>'}
                                        <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;border:2px solid var(--bg-primary)">📷</div>
                                    </div>
                                </div>
                                <div style="text-align:center">
                                    <div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;margin-bottom:8px">Firmenlogo</div>
                                    <div style="position:relative;width:88px;height:88px;cursor:pointer" onclick="App.uploadLogo()">
                                        ${logoUrl ? '<img id="companyLogo" src="' + esc(logoUrl) + '" style="width:88px;height:88px;border-radius:12px;object-fit:contain;border:2px dashed var(--border);padding:4px">' : '<div id="companyLogo" style="width:88px;height:88px;border-radius:12px;border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:11px;text-align:center;padding:8px">Logo<br>hochladen</div>'}
                                        <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;border:2px solid var(--bg-primary)">📷</div>
                                    </div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                                <div class="form-group"><label class="form-label">Name & Nachname</label><input class="form-input" id="s_user_name" value="${esc(data.user_name || '')}"></div>
                                <div class="form-group"><label class="form-label">Firmenname</label><input class="form-input" id="s_user_company" value="${esc(data.user_company || data.company_name || '')}"></div>
                                <div class="form-group"><label class="form-label">E-Mail</label><input class="form-input" id="s_user_email" value="${esc(data.user_email || '')}"></div>
                                <div class="form-group"><label class="form-label">Telefon</label><input class="form-input" id="s_company_phone" value="${esc(data.company_phone || '')}" placeholder="+49 ..."></div>
                                <div class="form-group"><label class="form-label">Straße & Hausnummer</label><input class="form-input" id="s_company_address" value="${esc(data.company_address || '')}" placeholder="Musterstraße 1"></div>
                                <div class="form-group"><label class="form-label">PLZ & Stadt</label><div style="display:flex;gap:8px"><input class="form-input" id="s_company_zip" value="${esc(data.company_zip || '')}" placeholder="12345" style="max-width:90px"><input class="form-input" id="s_company_city" value="${esc(data.company_city || '')}" placeholder="München"></div></div>
                            </div>
                            <button class="btn btn-primary" onclick="App.saveSettings()" style="margin-top:8px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><polyline points="20 6 9 17 4 12"/></svg>Speichern</button>
                        </div>
                    </div>

                    <!-- ══ 2. Sipgate ══ -->
                    <div class="card" style="grid-column:1/-1">
                        <div class="card-header"><span class="card-title">Sipgate Konfiguration</span></div>
                        <div class="card-body">
                            <div style="display:flex;gap:12px;margin-bottom:20px">
                                <button class="btn ${hasSipgate ? 'btn-primary' : 'btn-secondary'}" onclick="App.toggleSipgate(true)" style="flex:1;justify-content:center">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07"/></svg>
                                    Sipgate Konto vorhanden
                                </button>
                                <button class="btn ${!hasSipgate ? 'btn-primary' : 'btn-secondary'}" onclick="App.toggleSipgate(false)" style="flex:1;justify-content:center">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                    Kein Sipgate Konto
                                </button>
                            </div>

                            <!-- Has Sipgate Panel -->
                            <div id="sipgateHasPanel" style="display:${hasSipgate ? 'block' : 'none'}">
                                <div style="padding:20px;background:var(--bg-secondary);border-radius:12px;margin-bottom:16px">
                                    <div style="font-size:13px;font-weight:700;margin-bottom:12px">🔗 Webhook-URL für Sipgate</div>
                                    <div style="display:flex;gap:8px;align-items:center">
                                        <input class="form-input" value="${esc(webhookUrl)}" readonly style="font-family:monospace;font-size:12px;flex:1" id="webhookUrlInput">
                                        <button class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('webhookUrlInput').value);App.toast('Webhook-URL kopiert!','success')">Kopieren</button>
                                    </div>
                                    <div style="margin-top:14px;padding:14px;background:var(--bg-primary);border-radius:8px;font-size:12px;color:var(--text-light);line-height:1.7">
                                        <strong style="color:var(--text-primary)">So richten Sie den Webhook ein:</strong><br>
                                        1. Melden Sie sich bei <a href="https://app.sipgate.com" target="_blank" style="color:var(--primary)">app.sipgate.com</a> an<br>
                                        2. Gehen Sie zu <strong>Einstellungen → Webhooks</strong><br>
                                        3. Klicken Sie auf <strong>"Neuen Webhook erstellen"</strong><br>
                                        4. Fügen Sie die obige URL als <strong>Webhook-URL</strong> ein<br>
                                        5. Wählen Sie die Events: <strong>newCall, hangUp</strong><br>
                                        6. Speichern Sie die Einstellung
                                    </div>
                                </div>
                            </div>

                            <!-- No Sipgate Panel -->
                            <div id="sipgateNoPanel" style="display:${!hasSipgate ? 'block' : 'none'}">
                                <div style="padding:24px;background:var(--bg-secondary);border-radius:12px;text-align:center">
                                    <div style="font-size:40px;margin-bottom:12px">📞</div>
                                    <div style="font-size:15px;font-weight:700;margin-bottom:8px">Kein Sipgate Konto?</div>
                                    <p style="font-size:13px;color:var(--text-light);max-width:400px;margin:0 auto 16px">Sipgate ermöglicht VoIP-Telefonie mit automatischer Anrufverfolgung. Die Integration trackt eingehende und ausgehende Anrufe automatisch.</p>
                                    <a href="https://www.sipgate.de" target="_blank" class="btn btn-primary">Sipgate Konto erstellen →</a>
                                </div>
                            </div>

                            <!-- Assigned Numbers -->
                            <div style="margin-top:20px">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                                    <div style="font-size:14px;font-weight:700">Zugewiesene Nummern</div>
                                    <button class="btn btn-secondary btn-sm" onclick="document.getElementById('numberRequestPanel').style.display=document.getElementById('numberRequestPanel').style.display==='none'?'block':'none'">+ Neue Nummer anfragen</button>
                                </div>
                                ${numbers.length ? '<div class="table-wrap"><table class="data-table"><thead><tr><th>Nummer</th><th>Label</th><th>Block</th><th>Status</th></tr></thead><tbody>' + numRows + '</tbody></table></div>'
                                : '<div style="padding:20px;text-align:center;color:var(--text-light);font-size:13px;background:var(--bg-secondary);border-radius:8px">Keine Nummern zugewiesen. Fordern Sie neue Nummern über den Button oben an.</div>'}
                                <div id="numberRequestPanel" style="display:none;margin-top:16px;padding:16px;background:var(--bg-secondary);border-radius:10px">
                                    <div style="font-size:13px;font-weight:700;margin-bottom:12px">Neue Nummern anfordern</div>
                                    <div style="display:flex;gap:12px;align-items:end">
                                        <div class="form-group" style="margin:0"><label class="form-label">Anzahl</label><select class="form-select" id="number_count"><option>1</option><option>2</option><option>3</option><option>5</option><option>10</option><option>20</option></select></div>
                                        <div class="form-group" style="margin:0;flex:1"><label class="form-label">Nachricht (optional)</label><input class="form-input" id="number_message" placeholder="z.B. Nummern für Standort Berlin"></div>
                                        <button class="btn btn-primary" onclick="App.requestNumbers()">Anfrage senden</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ Passwort ══ -->
                    <div class="card">
                        <div class="card-header"><span class="card-title">Passwort ändern</span></div>
                        <div class="card-body">
                            <div class="form-group"><label class="form-label">Aktuelles Passwort</label><input class="form-input" type="password" id="pw_current"></div>
                            <div class="form-group"><label class="form-label">Neues Passwort</label><input class="form-input" type="password" id="pw_new"></div>
                            <button class="btn btn-primary" onclick="App.changePassword()">Passwort ändern</button>
                        </div>
                    </div>

                    <!-- ══ Chat Bot ══ -->
                    ${hasAccess('users') ? '<div class="card"><div class="card-header"><span class="card-title">Chat Bot</span></div><div class="card-body"><div class="form-group"><label class="form-label">Bot aktiviert</label><select class="form-select" id="s_chat_bot_enabled"><option value="1" ' + (data.chat_bot_enabled==='1'?'selected':'') + '>Ja</option><option value="0" ' + (data.chat_bot_enabled==='0'?'selected':'') + '>Nein</option></select></div><div class="form-group"><label class="form-label">Begrüßung</label><textarea class="form-textarea" id="s_chat_bot_greeting" rows="3">' + esc(data.chat_bot_greeting || '') + '</textarea></div><button class="btn btn-primary" onclick="App.saveSettings()">Speichern</button></div></div>' : ''}

                    ${hasAccess('users') ? '<div class="card" id="updateCard"><div class="card-header"><span class="card-title">System-Update</span></div><div class="card-body" id="updateBody"><div class="spinner" style="margin:20px auto"></div></div></div>' : ''}
                </div>
            `;
            if (hasAccess('users')) checkForUpdate();
        } catch (e) { toast(e.message, 'error'); }
    }

    async function checkForUpdate() {
        var body = document.getElementById('updateBody');
        if (!body) return;
        try {
            var data = await api('check-update');
            if (data.update_available) {
                body.innerHTML = `
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
                        <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div>
                            <div style="font-size:15px;font-weight:700;color:var(--success)">Update verfügbar!</div>
                            <div style="font-size:13px;color:var(--text-light)">Version <strong>${esc(data.local)}</strong> → <strong>${esc(data.remote)}</strong> (${esc(data.build)})</div>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="doUpdateBtn" onclick="App.doUpdate()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Jetzt aktualisieren
                    </button>
                `;
            } else {
                body.innerHTML = `
                    <div style="display:flex;align-items:center;gap:16px">
                        <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div>
                            <div style="font-size:15px;font-weight:700">System ist aktuell</div>
                            <div style="font-size:13px;color:var(--text-light)">Version <strong>${esc(data.local)}</strong> — ${esc(data.channel)}</div>
                        </div>
                    </div>
                    <button class="btn btn-secondary btn-sm" style="margin-top:14px" onclick="App.checkForUpdate()">Erneut prüfen</button>
                `;
            }
        } catch (e) {
            body.innerHTML = '<div style="color:var(--danger);font-size:13px">Update-Check fehlgeschlagen: ' + esc(e.message) + '</div><button class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="App.checkForUpdate()">Erneut prüfen</button>';
        }
    }

    async function doUpdate() {
        var btn = document.getElementById('doUpdateBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;margin:0"></div> Wird aktualisiert...';
        }
        try {
            var data = await api('do-update', { method: 'POST' });
            var body = document.getElementById('updateBody');
            if (body) {
                body.innerHTML = `
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
                        <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div>
                            <div style="font-size:15px;font-weight:700;color:var(--success)">Update erfolgreich!</div>
                            <div style="font-size:13px;color:var(--text-light)">Version <strong>${esc(data.version)}</strong> — ${data.files_updated} Dateien aktualisiert</div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="location.reload()">Seite neu laden</button>
                `;
            }
            toast('Update auf v' + data.version + ' erfolgreich!', 'success');
        } catch (e) {
            toast('Update fehlgeschlagen: ' + e.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Erneut versuchen';
            }
        }
    }

    // ══════════════════════════════════════
    // LICENSES PAGE (user sees own license)
    // ══════════════════════════════════════
    async function renderLicenses() {
        try {
            var data = await api('my-license');
            var c = document.getElementById('pageContent');
            var planLabels = { starter: 'Starter (Kostenlos)', professional: 'Professional', enterprise: 'Enterprise' };
            var planColors = { starter: '#64748b', professional: '#059669', enterprise: '#0066a1' };
            var planIcons = { starter: '🆓', professional: '⭐', enterprise: '👑' };
            var statusLabels = { active: 'Aktiv', expired: 'Abgelaufen', suspended: 'Gesperrt', trial: 'Testphase' };
            var statusColors = { active: '#059669', expired: '#dc2626', suspended: '#d97706', trial: '#7c3aed' };
            var plan = data.plan || 'starter';
            var status = data.status || 'trial';

            // Remaining time
            var remaining = '';
            var expiresAt = data.expires_at || data.trial_ends;
            if (expiresAt && plan !== 'starter') {
                var diff = new Date(expiresAt) - new Date();
                if (diff > 0) {
                    var days = Math.ceil(diff / (1000*60*60*24));
                    remaining = days + ' Tage verbleibend';
                } else {
                    remaining = 'Abgelaufen';
                }
            }

            c.innerHTML = '<div class="page-header"><h1 class="page-title">Lizenzverwaltung</h1></div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">' +

            // License Info Card
            '<div class="card"><div class="card-header"><span class="card-title">Ihre Lizenz</span></div><div class="card-body">' +
            '<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px">' +
            '<div style="width:64px;height:64px;border-radius:16px;background:' + planColors[plan] + '15;display:flex;align-items:center;justify-content:center;font-size:32px">' + planIcons[plan] + '</div>' +
            '<div><div style="font-size:22px;font-weight:800;color:' + planColors[plan] + '">' + esc(planLabels[plan] || plan) + '</div>' +
            '<span class="badge" style="background:' + statusColors[status] + '20;color:' + statusColors[status] + '">' + esc(statusLabels[status] || status) + '</span></div></div>' +

            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">' +
            '<div style="padding:14px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:11px;color:var(--text-light);text-transform:uppercase;font-weight:600">Lizenzschlüssel</div><div style="font-size:13px;font-weight:700;font-family:monospace;margin-top:4px">' + esc(data.license_key || '-') + '</div></div>' +
            '<div style="padding:14px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:11px;color:var(--text-light);text-transform:uppercase;font-weight:600">Benutzer</div><div style="font-size:13px;font-weight:700;margin-top:4px">' + esc(data.name || '-') + '</div></div>' +
            '<div style="padding:14px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:11px;color:var(--text-light);text-transform:uppercase;font-weight:600">E-Mail</div><div style="font-size:13px;font-weight:600;margin-top:4px">' + esc(data.email || '-') + '</div></div>' +
            '<div style="padding:14px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:11px;color:var(--text-light);text-transform:uppercase;font-weight:600">Firma</div><div style="font-size:13px;font-weight:600;margin-top:4px">' + esc(data.company || '-') + '</div></div>' +
            (expiresAt && plan !== 'starter' ? '<div style="padding:14px;background:var(--bg-secondary);border-radius:10px;grid-column:span 2"><div style="font-size:11px;color:var(--text-light);text-transform:uppercase;font-weight:600">Gültig bis</div><div style="font-size:14px;font-weight:700;margin-top:4px">' + new Date(expiresAt).toLocaleDateString('de-DE') + ' <span style="font-size:12px;font-weight:500;color:var(--text-light)">(' + remaining + ')</span></div></div>' : '') +
            '</div></div></div>' +

            // Upgrade Card
            '<div class="card"><div class="card-header"><span class="card-title">Paket upgraden</span></div><div class="card-body">' +
            '<p style="color:var(--text-light);font-size:13px;margin-bottom:20px">Wählen Sie Ihr gewünschtes Paket und senden Sie eine Upgrade-Anfrage.</p>' +

            (plan !== 'enterprise' ? '<div style="margin-bottom:16px"><label class="form-label">Gewünschtes Paket</label>' +
            '<select class="form-select" id="upgrade_plan">' +
            (plan === 'starter' ? '<option value="professional">Professional — 49€/Monat</option>' : '') +
            '<option value="enterprise">Enterprise — 99€/Monat</option>' +
            '</select></div>' +
            '<div style="margin-bottom:16px"><label class="form-label">Nachricht (optional)</label>' +
            '<textarea class="form-textarea" id="upgrade_message" rows="3" placeholder="Zusätzliche Informationen..."></textarea></div>' +
            '<button class="btn btn-primary" onclick="App.requestUpgrade()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5m-7 7l7-7 7 7"/></svg> Upgrade anfragen</button>'
            : '<div style="padding:24px;text-align:center;background:var(--bg-secondary);border-radius:12px"><div style="font-size:32px;margin-bottom:8px">👑</div><div style="font-size:16px;font-weight:700">Sie nutzen bereits Enterprise</div><div style="font-size:13px;color:var(--text-light);margin-top:4px">Das ist unser höchstes Paket mit allen Funktionen.</div></div>') +

            '</div></div></div>';

        } catch (e) { toast(e.message, 'error'); }
    }

    async function requestUpgrade() {
        var plan = document.getElementById('upgrade_plan');
        var msg = document.getElementById('upgrade_message');
        if (!plan) return;
        try {
            await api('request-upgrade', { method: 'POST', body: { requested_plan: plan.value, message: msg ? msg.value : '' } });
            toast('Upgrade-Anfrage wurde gesendet! Wir melden uns per E-Mail.', 'success');
            plan.disabled = true;
            if (msg) msg.disabled = true;
        } catch (e) { toast(e.message, 'error'); }
    }

    async function requestNumbers() {
        var count = document.getElementById('number_count');
        var msg = document.getElementById('number_message');
        if (!count) return;
        try {
            await api('request-numbers', { method: 'POST', body: { count: parseInt(count.value), message: msg ? msg.value : '' } });
            toast('Nummer-Anfrage wurde gesendet!', 'success');
            count.disabled = true;
            if (msg) msg.disabled = true;
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // REQUESTS PAGE (Admin — view all requests)
    // ══════════════════════════════════════
    async function renderRequests() {
        try {
            var data = await api('requests');
            var c = document.getElementById('pageContent');
            var reqs = data.requests || [];

            var typeLabels = { package_upgrade: '📦 Paket-Upgrade', number_request: '📞 Nummer-Anfrage' };
            var statusBadges = {
                pending: '<span class="badge badge-warning">Ausstehend</span>',
                approved: '<span class="badge badge-success">Genehmigt</span>',
                rejected: '<span class="badge badge-danger">Abgelehnt</span>'
            };
            var planLabels = { starter: 'Starter', professional: 'Professional', enterprise: 'Enterprise' };

            var rows = '';
            reqs.forEach(function(r) {
                var details = '';
                if (r.type === 'package_upgrade') {
                    details = esc(planLabels[r.current_plan] || r.current_plan || '-') + ' → <strong>' + esc(planLabels[r.requested_plan] || r.requested_plan || '-') + '</strong>';
                } else {
                    details = '<strong>' + r.number_count + '</strong> Nummern';
                }
                rows += '<tr>' +
                    '<td>' + (typeLabels[r.type] || r.type) + '</td>' +
                    '<td><strong>' + esc(r.user_name) + '</strong><br><span style="font-size:12px;color:var(--text-light)">' + esc(r.user_email) + '</span>' + (r.user_company ? '<br><span style="font-size:12px;color:var(--text-light)">' + esc(r.user_company) + '</span>' : '') + '</td>' +
                    '<td>' + details + '</td>' +
                    '<td>' + (r.message ? '<span style="font-size:12px">' + esc(r.message).substring(0,60) + '</span>' : '-') + '</td>' +
                    '<td>' + (statusBadges[r.status] || r.status) + '</td>' +
                    '<td style="font-size:12px">' + new Date(r.created_at).toLocaleDateString('de-DE') + '</td>' +
                    '<td>' + (r.status === 'pending' ?
                        '<button class="btn btn-sm btn-primary" onclick="App.updateRequest(' + r.id + ',\'approved\')" style="margin-right:4px">✓</button>' +
                        '<button class="btn btn-sm btn-secondary" onclick="App.updateRequest(' + r.id + ',\'rejected\')">✗</button>'
                        : (r.admin_note ? '<span style="font-size:12px">' + esc(r.admin_note) + '</span>' : '-')) +
                    '</td></tr>';
            });

            c.innerHTML = '<div class="page-header"><h1 class="page-title">Anfragen</h1><span class="badge badge-info">' + reqs.length + '</span></div>' +
                '<div class="card"><div class="card-body" style="padding:0">' +
                (reqs.length ? '<div class="table-wrap"><table class="data-table"><thead><tr><th>Typ</th><th>Benutzer</th><th>Details</th><th>Nachricht</th><th>Status</th><th>Datum</th><th>Aktion</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                : '<div class="empty-state" style="padding:60px 20px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg><p>Keine Anfragen vorhanden</p></div>') +
                '</div></div>';
        } catch (e) { toast(e.message, 'error'); }
    }

    async function updateRequest(id, status) {
        var note = '';
        if (status === 'rejected') {
            note = prompt('Grund für Ablehnung (optional):') || '';
        }
        try {
            await api('requests', { method: 'PUT', body: { id: id, status: status, admin_note: note } });
            toast(status === 'approved' ? 'Anfrage genehmigt' : 'Anfrage abgelehnt', status === 'approved' ? 'success' : 'info');
            renderRequests();
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // FILE UPLOADS (Avatar / Logo)
    // ══════════════════════════════════════
    function uploadAvatar() {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = async function() {
            if (!input.files[0]) return;
            var fd = new FormData();
            fd.append('file', input.files[0]);
            try {
                var res = await fetch('api.php?action=upload-avatar', { method: 'POST', body: fd });
                var data = await res.json();
                if (!res.ok) throw new Error(data.error);
                toast('Profilbild aktualisiert', 'success');
                var img = document.getElementById('profileAvatar');
                if (img) img.src = data.url + '?' + Date.now();
                var sidebarImg = document.querySelector('.user-avatar img');
                if (sidebarImg) sidebarImg.src = data.url + '?' + Date.now();
            } catch (e) { toast(e.message, 'error'); }
        };
        input.click();
    }

    function uploadLogo() {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = async function() {
            if (!input.files[0]) return;
            var fd = new FormData();
            fd.append('file', input.files[0]);
            try {
                var res = await fetch('api.php?action=upload-logo', { method: 'POST', body: fd });
                var data = await res.json();
                if (!res.ok) throw new Error(data.error);
                toast('Firmenlogo aktualisiert', 'success');
                var img = document.getElementById('companyLogo');
                if (img) img.src = data.url + '?' + Date.now();
            } catch (e) { toast(e.message, 'error'); }
        };
        input.click();
    }

    function toggleSipgate(has) {
        var panel1 = document.getElementById('sipgateHasPanel');
        var panel2 = document.getElementById('sipgateNoPanel');
        if (has) {
            if (panel1) panel1.style.display = 'block';
            if (panel2) panel2.style.display = 'none';
        } else {
            if (panel1) panel1.style.display = 'none';
            if (panel2) panel2.style.display = 'block';
        }
        // Save preference
        api('my-profile', { method: 'POST', body: { has_sipgate: has ? 1 : 0 } });
    }

    async function saveSettings() {
        var body = {};
        var allKeys = [
            // settings table
            'company_name', 'company_email', 'company_phone',
            'company_address', 'company_zip', 'company_city',
            'chat_bot_enabled', 'chat_bot_greeting',
            // users table (prefixed with user_)
            'user_name', 'user_company', 'user_email'
        ];
        allKeys.forEach(function(k) {
            var el = document.getElementById('s_' + k);
            if (el) body[k] = el.value;
        });
        try {
            await api('settings', { method: 'POST', body: body });
            toast('Einstellungen gespeichert', 'success');
            // Update sidebar name
            var nameEl = document.getElementById('s_user_name');
            if (nameEl && nameEl.value) {
                var sidebarName = document.querySelector('.user-name');
                if (sidebarName) sidebarName.textContent = nameEl.value;
            }
        } catch (e) { toast(e.message, 'error'); }
    }

    async function changePassword() {
        var current = document.getElementById('pw_current').value;
        var newPw = document.getElementById('pw_new').value;
        if (!current || !newPw) { toast('Alle Felder ausfüllen', 'error'); return; }
        try {
            await api('change-password', { method: 'POST', body: { current: current, 'new': newPw } });
            toast('Passwort geändert', 'success');
            document.getElementById('pw_current').value = '';
            document.getElementById('pw_new').value = '';
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // PROFILE MODAL (all users)
    // ══════════════════════════════════════
    function openProfile() {
        var modal = document.getElementById('profileModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeProfile() {
        var modal = document.getElementById('profileModal');
        if (modal) modal.style.display = 'none';
    }

    async function saveProfileInfo() {
        var name  = document.getElementById('profile_name').value.trim();
        var email = document.getElementById('profile_email').value.trim();
        if (!name) { toast('Name darf nicht leer sein', 'error'); return; }
        try {
            await api('me', { method: 'POST', body: { name: name, email: email } });
            // Update sidebar display
            var nameEl = document.querySelector('.sidebar-footer .user-name');
            if (nameEl) nameEl.textContent = name;
            var avatarEl = document.querySelector('.sidebar-footer .user-avatar');
            if (avatarEl) avatarEl.textContent = name.charAt(0).toUpperCase();
            window.ROHRAPP_USER.name = name;
            toast('Profil gespeichert', 'success');
        } catch (e) { toast(e.message, 'error'); }
    }

    async function saveProfilePassword() {
        var current = document.getElementById('profile_pw_current').value;
        var newPw   = document.getElementById('profile_pw_new').value;
        var confirm = document.getElementById('profile_pw_confirm').value;
        if (!current || !newPw || !confirm) { toast('Alle Felder ausfüllen', 'error'); return; }
        if (newPw.length < 8) { toast('Mindestens 8 Zeichen', 'error'); return; }
        if (newPw !== confirm) { toast('Passwörter stimmen nicht überein', 'error'); return; }
        try {
            await api('change-password', { method: 'POST', body: { current: current, 'new': newPw } });
            toast('Passwort geändert ✓', 'success');
            document.getElementById('profile_pw_current').value = '';
            document.getElementById('profile_pw_new').value = '';
            document.getElementById('profile_pw_confirm').value = '';
            setTimeout(closeProfile, 1200);
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // USERS (Admin only)
    // ══════════════════════════════════════
    var _editingUserId = null;
    var _sipgateNumbers = [];

    function getLicenseBadge(plan, status) {
        if (!plan) return '<span class="badge badge-muted">Keine Lizenz</span>';
        var planColors = { starter: 'badge-muted', professional: 'badge-success', enterprise: 'badge-info' };
        var statusIcons = { active: '✓', trial: '⏱', expired: '✗', suspended: '⊘' };
        var statusColors = { active: '#059669', trial: '#d97706', expired: '#dc2626', suspended: '#94a3b8' };
        var cls = planColors[plan] || 'badge-muted';
        var icon = statusIcons[status] || '';
        var col = statusColors[status] || '';
        return '<span class="badge ' + cls + '" style="gap:4px">' + esc(plan.charAt(0).toUpperCase() + plan.slice(1)) +
               ' <span style="color:' + col + ';font-size:10px">' + icon + '</span></span>';
    }

    async function renderUsers() {
        if (!hasAccess('users')) return;
        _editingUserId = null;
        _sipgateNumbers = [];
        try {
            var data = await api('users');
            var c = document.getElementById('pageContent');
            var regUrl = window.location.origin + window.location.pathname.replace(/[^/]*$/, '') + 'register.php';
            c.innerHTML =
                '<div class="page-header">' +
                  '<div><h1 class="page-title">Lizenzverwaltung</h1>' +
                  '<p class="page-subtitle">' + data.users.length + ' Lizenzen aktiv</p></div>' +
                  '<div style="display:flex;gap:8px;align-items:center">' +
                    '<div style="display:flex;align-items:center;gap:6px;background:var(--bg-body);border:1px solid var(--border);border-radius:8px;padding:6px 10px;font-size:12px;color:var(--text-muted)">' +
                      '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>' +
                      '<span style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(regUrl) + '">' + esc(regUrl) + '</span>' +
                      '<button onclick="App.copyRegLink(\'' + esc(regUrl) + '\')" style="border:none;background:none;cursor:pointer;padding:2px 4px;color:var(--primary);font-size:11px;font-weight:600;flex-shrink:0" title="Kopieren">Kopieren</button>' +
                      '<a href="' + esc(regUrl) + '" target="_blank" style="color:var(--primary);font-size:11px;font-weight:600;text-decoration:none;flex-shrink:0">Öffnen ↗</a>' +
                    '</div>' +
                    '<button class="btn btn-primary" onclick="App.showAddUser()">' +
                      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                      ' Neue Lizenz' +
                    '</button>' +
                  '</div>' +
                '</div>' +
                '<div class="card"><table class="data-table">' +
                  '<thead><tr><th>Benutzer</th><th>Name &amp; E-Mail</th><th>Firma</th><th>Lizenz / Schlüssel</th><th>Erstellt</th><th>Letzter Login</th><th>Aktionen</th></tr></thead>' +
                  '<tbody id="userTableBody">' + renderUserRows(data.users) + '</tbody>' +
                '</table></div>';
        } catch (e) { toast(e.message, 'error'); }
    }

    function renderUserRows(users) {
        return users.map(function(u) {
            var lastLogin = u.last_login ? formatDate(u.last_login) : '<span style="color:var(--text-muted)">Nie</span>';
            var isCurrentUser = u.id === window.ROHRAPP_USER.id;
            var editIcon  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
            var trashIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
            var actions = isCurrentUser
                ? '<span style="font-size:11px;color:var(--text-muted)">Sie</span>'
                : '<button class="btn-icon" id="edit-btn-' + u.id + '" onclick="App.editUser(' + u.id + ')" title="Bearbeiten">' + editIcon + '</button> ' +
                  '<button class="btn-icon" onclick="App.deleteUser(' + u.id + ',\'' + esc(u.username) + '\')" title="Löschen" style="color:var(--danger)">' + trashIcon + '</button>';
            var nameEmail = '<div style="font-weight:500">' + esc(u.name || '—') + '</div>' +
                            '<div style="font-size:12px;color:var(--text-muted)">' + esc(u.email || '—') + '</div>';
            // Firma statt Rolle anzeigen
            var firmaCell = u.company
                ? '<div style="font-weight:600;font-size:13px">' + esc(u.company) + '</div>' +
                  '<div style="margin-top:2px">' + getRoleBadge(u.role) + '</div>'
                : getRoleBadge(u.role);
            var initial = (u.company || u.name || u.username || '?').charAt(0).toUpperCase();
            var avatar = '<div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;margin-right:8px;flex-shrink:0">' +
                         esc(initial) + '</div>';
            return '<tr id="user-row-' + u.id + '">' +
                '<td><div style="display:flex;align-items:center">' + avatar + '<span style="font-weight:600">' + esc(u.username) + '</span></div></td>' +
                '<td>' + nameEmail + '</td>' +
                '<td>' + firmaCell + '</td>' +
                '<td>' + getLicenseBadge(u.license_plan, u.license_status) +
                (u.license_key ? '<div style="font-family:monospace;font-size:11px;color:var(--text-muted);margin-top:4px;letter-spacing:0.5px">' + esc(u.license_key) + '</div>' : '') +
                '</td>' +
                '<td style="white-space:nowrap;font-size:12px">' + formatDate(u.created_at) + '</td>' +
                '<td>' + lastLogin + '</td>' +
                '<td>' + actions + '</td></tr>';
        }).join('');
    }

    function showAddUser() {
        showModal('Neue Lizenz erstellen', `
            <div style="margin-bottom:16px;padding:12px 14px;background:rgba(0,102,161,0.06);border-radius:8px;border-left:3px solid var(--primary);font-size:13px;color:var(--text-secondary)">
                <strong>Automatisch:</strong> Benutzername aus E-Mail · Zufälliges Passwort per E-Mail · Lizenzschlüssel (ROHR-XXXX-XXXX-XXXX) · Lizenz je nach Rolle aktiviert
            </div>
            <form id="addUserForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">E-Mail *</label>
                        <input class="form-input" name="email" type="email" placeholder="benutzer@firma.de" required autofocus>
                    </div>
                    <div class="form-group"><label class="form-label">Name</label><input class="form-input" name="name" placeholder="Vor- und Nachname"></div>
                    <div class="form-group"><label class="form-label">Firma</label><input class="form-input" name="company" placeholder="Firmenname"></div>
                    <div class="form-group" style="grid-column:1/-1"><label class="form-label">Rolle</label>
                        <select class="form-select" name="role">
                            <option value="starter">Starter</option>
                            <option value="professional">Professional</option>
                            <option value="enterprise">Enterprise</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
            </form>
        `, [
            { label: 'Abbrechen', cls: 'btn-secondary', action: 'closeModal()' },
            { label: 'Erstellen &amp; E-Mail senden', cls: 'btn-primary', action: 'App.saveUser()' }
        ]);
    }

    async function saveUser() {
        var form = document.getElementById('addUserForm');
        var fd = new FormData(form);
        var body = {};
        fd.forEach(function(v, k) { body[k] = v; });
        if (!body.email) { toast('E-Mail ist erforderlich', 'error'); return; }
        try {
            var result = await api('users', { method: 'POST', body: body });
            closeModal();
            // Show created credentials
            showModal('Benutzer erstellt ✓', `
                <div style="text-align:center;padding:8px 0">
                    <div style="width:56px;height:56px;border-radius:50%;background:rgba(5,150,105,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <p style="color:var(--text-secondary);font-size:14px;margin-bottom:20px">Zugangsdaten wurden per E-Mail gesendet</p>
                    <div style="background:var(--bg-body);border-radius:8px;padding:16px;text-align:left">
                        <div style="margin-bottom:12px">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px">Benutzername (Auto)</div>
                            <div style="font-size:15px;font-weight:600;color:var(--primary)">${esc(result.username || '')}</div>
                        </div>
                        <div style="margin-bottom:12px">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px">Passwort (einmalig)</div>
                            <div style="font-size:18px;font-weight:700;font-family:monospace;letter-spacing:2px;color:var(--text-primary)">${esc(result.password || '')}</div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px">Lizenzschlüssel</div>
                            <div style="font-size:14px;font-weight:700;font-family:monospace;letter-spacing:1px;color:var(--primary)">${esc(result.license_key || '')}</div>
                        </div>
                    </div>
                    <p style="color:var(--text-muted);font-size:12px;margin-top:14px">Benutzer sollte das Passwort nach der ersten Anmeldung ändern.</p>
                </div>
            `, [{ label: 'OK', cls: 'btn-primary', action: 'closeModal()' }]);
            renderUsers();
        } catch (e) { toast(e.message, 'error'); }
    }

    async function editUser(id) {
        // Toggle: if already open, close
        var existingRow = document.getElementById('user-edit-row-' + id);
        if (existingRow) {
            closeEditRow(id);
            return;
        }
        // Close any previously open edit row
        if (_editingUserId !== null) closeEditRow(_editingUserId);

        try {
            var data = await api('user', { params: { id: id } });
            _editingUserId = id;
            _sipgateNumbers = data.sipgate_number
                ? data.sipgate_number.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s; })
                : [];

            var userRow = document.getElementById('user-row-' + id);
            if (!userRow) return;
            userRow.classList.add('user-row-active');

            var licPlan   = data.license_plan   || 'starter';
            var licStatus = data.license_status || 'trial';
            var licTrialEnds = data.license_trial_ends ? data.license_trial_ends.substring(0,10) : '';
            var licExpiresAt = data.license_expires_at ? data.license_expires_at.substring(0,10) : '';

            var tr = document.createElement('tr');
            tr.id = 'user-edit-row-' + id;
            tr.className = 'user-edit-row';
            tr.innerHTML =
                '<td colspan="7" style="padding:0">' +
                  '<div class="user-edit-panel" id="user-edit-panel-' + id + '">' +
                    '<div class="user-edit-panel-inner">' +

                      // ── Header ──
                      '<div class="user-edit-header">' +
                        '<div style="display:flex;align-items:center;gap:12px">' +
                          '<div style="width:42px;height:42px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:17px;flex-shrink:0">' +
                            esc((data.name || data.username || '?').charAt(0).toUpperCase()) +
                          '</div>' +
                          '<div>' +
                            '<div class="user-edit-header-title" style="margin-bottom:2px">Benutzer bearbeiten: <span>' + esc(data.username) + '</span></div>' +
                            '<div style="font-size:12px;color:var(--text-muted)">' + esc(data.email || '—') + ' · Erstellt: ' + formatDate(data.created_at) + '</div>' + (data.license_key ? '<div style="font-family:monospace;font-size:12px;font-weight:600;color:var(--primary);margin-top:2px;letter-spacing:0.5px">' + esc(data.license_key) + '</div>' : '') +
                          '</div>' +
                        '</div>' +
                        '<button class="btn-icon" onclick="App.closeEditRow(' + id + ')" title="Schließen">' +
                          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                        '</button>' +
                      '</div>' +

                      // ── Abschnitt 1: Grunddaten ──
                      '<div class="user-edit-section-title">Grunddaten</div>' +
                      '<div class="user-edit-fields" style="grid-template-columns:1fr 1fr 1fr 1fr">' +
                        '<div class="form-group">' +
                          '<label class="form-label">Name</label>' +
                          '<input class="form-input" id="eu-name-' + id + '" value="' + esc(data.name || '') + '">' +
                        '</div>' +
                        '<div class="form-group">' +
                          '<label class="form-label">Firma</label>' +
                          '<input class="form-input" id="eu-company-' + id + '" value="' + esc(data.company || '') + '" placeholder="Firmenname">' +
                        '</div>' +
                        '<div class="form-group">' +
                          '<label class="form-label">E-Mail</label>' +
                          '<input class="form-input" id="eu-email-' + id + '" type="email" value="' + esc(data.email || '') + '">' +
                        '</div>' +
                        '<div class="form-group">' +
                          '<label class="form-label">Rolle</label>' +
                          '<select class="form-select" id="eu-role-' + id + '">' +
                            '<option value="starter"'      + (data.role === 'starter'      ? ' selected' : '') + '>Starter</option>' +
                            '<option value="professional"' + (data.role === 'professional' ? ' selected' : '') + '>Professional</option>' +
                            '<option value="enterprise"'   + (data.role === 'enterprise'   ? ' selected' : '') + '>Enterprise</option>' +
                            '<option value="admin"'        + (data.role === 'admin'        ? ' selected' : '') + '>Admin</option>' +
                          '</select>' +
                        '</div>' +
                      '</div>' +

                      // ── Abschnitt 2: Lizenz ──
                      '<div class="user-edit-section-title">Lizenz</div>' +
                      '<div class="user-edit-fields" style="grid-template-columns:1fr 1fr 1fr 1fr">' +
                        '<div class="form-group">' +
                          '<label class="form-label">Plan</label>' +
                          '<select class="form-select" id="eu-lic-plan-' + id + '">' +
                            '<option value="starter"'      + (licPlan === 'starter'      ? ' selected' : '') + '>Starter</option>' +
                            '<option value="professional"' + (licPlan === 'professional' ? ' selected' : '') + '>Professional</option>' +
                            '<option value="enterprise"'   + (licPlan === 'enterprise'   ? ' selected' : '') + '>Enterprise</option>' +
                          '</select>' +
                        '</div>' +
                        '<div class="form-group">' +
                          '<label class="form-label">Status</label>' +
                          '<select class="form-select" id="eu-lic-status-' + id + '">' +
                            '<option value="trial"'     + (licStatus === 'trial'     ? ' selected' : '') + '>Trial</option>' +
                            '<option value="active"'    + (licStatus === 'active'    ? ' selected' : '') + '>Aktiv</option>' +
                            '<option value="expired"'   + (licStatus === 'expired'   ? ' selected' : '') + '>Abgelaufen</option>' +
                            '<option value="suspended"' + (licStatus === 'suspended' ? ' selected' : '') + '>Gesperrt</option>' +
                          '</select>' +
                        '</div>' +
                        '<div class="form-group">' +
                          '<label class="form-label">Trial endet</label>' +
                          '<input class="form-input" type="date" id="eu-lic-trial-' + id + '" value="' + esc(licTrialEnds) + '">' +
                        '</div>' +
                        '<div class="form-group">' +
                          '<label class="form-label">Läuft ab am</label>' +
                          '<input class="form-input" type="date" id="eu-lic-expires-' + id + '" value="' + esc(licExpiresAt) + '">' +
                        '</div>' +
                      '</div>' +

                      // ── Abschnitt 3: Sipgate Nummern ──
                      '<div class="user-edit-section-title">Sipgate Nummern</div>' +
                      '<div class="form-group" style="margin-bottom:0">' +
                        '<div id="phone-tags-' + id + '" class="phone-tags"></div>' +
                        '<div class="phone-add-row">' +
                          '<input class="form-input" id="phone-add-input-' + id + '" placeholder="+49..." style="max-width:200px">' +
                          '<button class="btn btn-secondary" onclick="App.addPhoneTag(' + id + ')">' +
                            '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                            'Einzeln hinzufügen' +
                          '</button>' +
                        '</div>' +
                        '<div class="phone-range-row">' +
                          '<div class="phone-range-label">Bereich:</div>' +
                          '<input class="form-input" id="phone-range-start-' + id + '" placeholder="Von: +4915792503960" oninput="App.updateRangeCount(' + id + ')">' +
                          '<span class="phone-range-sep">—</span>' +
                          '<input class="form-input" id="phone-range-end-' + id + '" placeholder="Bis: +4915792503969" oninput="App.updateRangeCount(' + id + ')">' +
                          '<span class="phone-range-count" id="phone-range-count-' + id + '"></span>' +
                          '<button class="btn btn-secondary" id="phone-range-btn-' + id + '" onclick="App.addPhoneRange(' + id + ')" disabled>' +
                            '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                            'Alle hinzufügen' +
                          '</button>' +
                        '</div>' +
                      '</div>' +

                      // ── Abschnitt 4: Passwort ──
                      '<div class="user-edit-section-title" style="margin-top:20px">Passwort ändern</div>' +
                      '<div class="form-group">' +
                        '<input class="form-input" id="eu-password-' + id + '" type="password" placeholder="Neues Passwort (min. 8 Zeichen)" style="max-width:300px">' +
                      '</div>' +

                      // ── Footer ──
                      '<div class="user-edit-footer">' +
                        '<button class="btn btn-secondary" onclick="App.closeEditRow(' + id + ')">Abbrechen</button>' +
                        '<button class="btn btn-primary" onclick="App.updateUser(' + id + ')">' +
                          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><polyline points="20 6 9 17 4 12"/></svg>' +
                          'Speichern' +
                        '</button>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</td>';

            userRow.after(tr);
            renderPhoneTags(id);

            // Enter key on single add input
            var addInput = document.getElementById('phone-add-input-' + id);
            if (addInput) addInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); App.addPhoneTag(id); }
            });
            // Enter key on range end input
            var rangeEnd = document.getElementById('phone-range-end-' + id);
            if (rangeEnd) rangeEnd.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); App.addPhoneRange(id); }
            });

            // Animate open
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    var panel = document.getElementById('user-edit-panel-' + id);
                    if (panel) panel.classList.add('open');
                });
            });

            // Scroll into view
            setTimeout(function() {
                var editPanel = document.getElementById('user-edit-panel-' + id);
                if (editPanel) editPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 80);

        } catch (e) { toast(e.message, 'error'); }
    }

    function closeEditRow(id) {
        var panel = document.getElementById('user-edit-panel-' + id);
        if (panel) panel.classList.remove('open');
        var userRow = document.getElementById('user-row-' + id);
        if (userRow) userRow.classList.remove('user-row-active');
        setTimeout(function() {
            var row = document.getElementById('user-edit-row-' + id);
            if (row) row.remove();
        }, 380);
        if (_editingUserId === id) { _editingUserId = null; _sipgateNumbers = []; }
    }

    function renderPhoneTags(userId) {
        var container = document.getElementById('phone-tags-' + userId);
        if (!container) return;
        if (_sipgateNumbers.length === 0) {
            container.innerHTML = '<span class="phone-empty-note">Keine Nummern hinterlegt</span>';
            return;
        }
        var phoneIcon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.1 19.79 19.79 0 0 1 1.6 4.52 2 2 0 0 1 3.59 2.34h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.9a16 16 0 0 0 6.29 6.29l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
        var editIcon = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
        var xIcon = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        container.innerHTML = _sipgateNumbers.map(function(num, i) {
            return '<div class="phone-tag" id="phone-tag-' + userId + '-' + i + '">' +
                phoneIcon +
                '<span style="margin:0 2px">' + esc(num) + '</span>' +
                '<button class="phone-tag-btn" onclick="App.editPhoneTag(' + userId + ',' + i + ')" title="Bearbeiten">' + editIcon + '</button>' +
                '<button class="phone-tag-btn delete" onclick="App.deletePhoneTag(' + userId + ',' + i + ')" title="Entfernen">' + xIcon + '</button>' +
            '</div>';
        }).join('');
    }

    function addPhoneTag(userId) {
        var input = document.getElementById('phone-add-input-' + userId);
        if (!input) return;
        var val = input.value.trim();
        if (!val) { input.focus(); return; }
        if (_sipgateNumbers.indexOf(val) !== -1) { toast('Nummer bereits vorhanden', 'warning'); return; }
        _sipgateNumbers.push(val);
        input.value = '';
        renderPhoneTags(userId);
        input.focus();
    }

    // ── Gemeinsamen Präfix zweier Nummernstrings ermitteln ──
    function _phonePrefix(a, b) {
        var len = Math.min(a.length, b.length);
        var i = 0;
        while (i < len && a[i] === b[i]) i++;
        return i; // Länge des gemeinsamen Präfixes
    }

    function updateRangeCount(userId) {
        var startEl = document.getElementById('phone-range-start-' + userId);
        var endEl   = document.getElementById('phone-range-end-'   + userId);
        var countEl = document.getElementById('phone-range-count-' + userId);
        var btnEl   = document.getElementById('phone-range-btn-'   + userId);
        if (!startEl || !endEl || !countEl || !btnEl) return;

        var start = startEl.value.trim();
        var end   = endEl.value.trim();

        if (!start || !end) {
            countEl.textContent = '';
            countEl.className = 'phone-range-count';
            btnEl.disabled = true;
            return;
        }

        var pLen  = _phonePrefix(start, end);
        var sSuffix = parseInt(start.substring(pLen), 10);
        var eSuffix = parseInt(end.substring(pLen),   10);

        if (isNaN(sSuffix) || isNaN(eSuffix) || eSuffix < sSuffix) {
            countEl.textContent = 'Ungültig';
            countEl.className = 'phone-range-count error';
            btnEl.disabled = true;
            return;
        }

        var count = eSuffix - sSuffix + 1;
        if (count > 200) {
            countEl.textContent = count + ' — zu viele (max. 200)';
            countEl.className = 'phone-range-count error';
            btnEl.disabled = true;
            return;
        }

        countEl.textContent = count + ' Nummer' + (count !== 1 ? 'n' : '');
        countEl.className = 'phone-range-count ok';
        btnEl.disabled = false;
    }

    function addPhoneRange(userId) {
        var startEl = document.getElementById('phone-range-start-' + userId);
        var endEl   = document.getElementById('phone-range-end-'   + userId);
        if (!startEl || !endEl) return;

        var start = startEl.value.trim();
        var end   = endEl.value.trim();
        if (!start || !end) { toast('Bitte Start- und Endnummer eingeben', 'warning'); return; }

        var pLen    = _phonePrefix(start, end);
        var prefix  = start.substring(0, pLen);
        var sSuffix = start.substring(pLen);
        var eSuffix = end.substring(pLen);
        var sNum    = parseInt(sSuffix, 10);
        var eNum    = parseInt(eSuffix,   10);

        if (isNaN(sNum) || isNaN(eNum) || eNum < sNum) {
            toast('Ungültige Nummern oder Endnummer kleiner als Startnummer', 'error'); return;
        }

        var count = eNum - sNum + 1;
        if (count > 200) { toast('Maximal 200 Nummern auf einmal möglich', 'warning'); return; }

        // Länge des Suffix beibehalten (führende Nullen)
        var padLen = sSuffix.length;
        var added = 0;
        for (var n = sNum; n <= eNum; n++) {
            var num = prefix + String(n).padStart(padLen, '0');
            if (_sipgateNumbers.indexOf(num) === -1) {
                _sipgateNumbers.push(num);
                added++;
            }
        }

        startEl.value = '';
        endEl.value = '';
        var countEl = document.getElementById('phone-range-count-' + userId);
        if (countEl) { countEl.textContent = ''; countEl.className = 'phone-range-count'; }
        var btnEl = document.getElementById('phone-range-btn-' + userId);
        if (btnEl) btnEl.disabled = true;

        renderPhoneTags(userId);
        toast(added + ' Nummer' + (added !== 1 ? 'n' : '') + ' hinzugefügt', 'success');
    }

    function editPhoneTag(userId, index) {
        var tagEl = document.getElementById('phone-tag-' + userId + '-' + index);
        if (!tagEl) return;
        var oldVal = _sipgateNumbers[index];
        var checkIcon = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
        var xIcon = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        tagEl.className = 'phone-tag-editing';
        tagEl.innerHTML =
            '<input type="text" id="phone-tag-edit-' + userId + '-' + index + '" value="' + esc(oldVal) + '" style="width:160px">' +
            '<button class="phone-tag-btn save" onclick="App.savePhoneTagEdit(' + userId + ',' + index + ')" title="Speichern">' + checkIcon + '</button>' +
            '<button class="phone-tag-btn delete" onclick="App.cancelPhoneTagEdit(' + userId + ',' + index + ')" title="Abbrechen">' + xIcon + '</button>';
        var inp = document.getElementById('phone-tag-edit-' + userId + '-' + index);
        if (inp) {
            inp.focus(); inp.select();
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); App.savePhoneTagEdit(userId, index); }
                if (e.key === 'Escape') App.cancelPhoneTagEdit(userId, index);
            });
        }
    }

    function savePhoneTagEdit(userId, index) {
        var inp = document.getElementById('phone-tag-edit-' + userId + '-' + index);
        if (inp && inp.value.trim()) _sipgateNumbers[index] = inp.value.trim();
        renderPhoneTags(userId);
    }

    function cancelPhoneTagEdit(userId, index) {
        renderPhoneTags(userId);
    }

    function deletePhoneTag(userId, index) {
        _sipgateNumbers.splice(index, 1);
        renderPhoneTags(userId);
    }

    async function updateUser(id) {
        var name     = document.getElementById('eu-name-'       + id);
        var company  = document.getElementById('eu-company-'    + id);
        var email    = document.getElementById('eu-email-'      + id);
        var role     = document.getElementById('eu-role-'       + id);
        var password = document.getElementById('eu-password-'   + id);
        var licPlan  = document.getElementById('eu-lic-plan-'   + id);
        var licStat  = document.getElementById('eu-lic-status-' + id);
        var licTrial = document.getElementById('eu-lic-trial-'  + id);
        var licExp   = document.getElementById('eu-lic-expires-'+ id);

        if (password && password.value && password.value.length < 8) {
            toast('Passwort muss mindestens 8 Zeichen haben', 'error'); return;
        }
        var body = {
            name:           name    ? name.value    : '',
            company:        company ? company.value : '',
            email:          email   ? email.value   : '',
            role:           role    ? role.value    : '',
            sipgate_number: _sipgateNumbers.join(','),
            license_plan:   licPlan  ? licPlan.value  : '',
            license_status: licStat  ? licStat.value  : '',
            license_expires_at: licExp && licExp.value ? licExp.value : '',
        };
        if (password && password.value) body.password = password.value;
        try {
            await api('user', { method: 'POST', params: { id: id }, body: body });
            toast('Benutzer aktualisiert', 'success');
            closeEditRow(id);
            setTimeout(renderUsers, 400);
        } catch (e) { toast(e.message, 'error'); }
    }

    async function deleteUser(id, username) {
        if (!confirm('Benutzer "' + username + '" wirklich löschen?')) return;
        try {
            await api('user', { method: 'DELETE', params: { id: id } });
            toast('Benutzer gelöscht', 'success');
            renderUsers();
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // ADD CALL MODAL
    // ══════════════════════════════════════
    function showAddCall() {
        showModal('Neuer Anruf', `
            <form id="addCallForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div class="form-group"><label class="form-label">Anrufer</label><input class="form-input" name="caller_name"></div>
                    <div class="form-group"><label class="form-label">Telefon</label><input class="form-input" name="phone_number"></div>
                    <div class="form-group"><label class="form-label">Richtung</label>
                        <select class="form-select" name="direction"><option value="inbound">Eingehend</option><option value="outbound">Ausgehend</option></select>
                    </div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status"><option value="answered">Angenommen</option><option value="missed">Verpasst</option></select>
                    </div>
                    <div class="form-group"><label class="form-label">Dauer (Sek.)</label><input class="form-input" name="duration" type="number" value="0"></div>
                    <div class="form-group"><label class="form-label">Bearbeiter</label><input class="form-input" name="agent"></div>
                </div>
                <div class="form-group"><label class="form-label">Notizen</label><textarea class="form-textarea" name="notes" rows="3"></textarea></div>
            </form>
        `, [
            { label: 'Abbrechen', cls: 'btn-secondary', action: 'closeModal()' },
            { label: 'Speichern', cls: 'btn-primary', action: 'App.saveCall()' }
        ]);
    }

    async function saveCall() {
        var form = document.getElementById('addCallForm');
        var fd = new FormData(form);
        var body = {};
        fd.forEach(function(v, k) { body[k] = v; });
        try {
            await api('calls', { method: 'POST', body: body });
            closeModal();
            toast('Anruf gespeichert', 'success');
            renderCalls();
        } catch (e) { toast(e.message, 'error'); }
    }

    // ══════════════════════════════════════
    // MODAL SYSTEM
    // ══════════════════════════════════════
    function showModal(title, bodyHtml, buttons) {
        closeModal();
        var btns = '';
        if (buttons) {
            btns = '<div class="modal-footer">' + buttons.map(function(b) {
                return '<button class="btn ' + b.cls + '" onclick="' + b.action + '">' + b.label + '</button>';
            }).join('') + '</div>';
        }
        var overlay = document.createElement('div');
        overlay.className = 'modal-overlay show';
        overlay.id = 'modalOverlay';
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">${title}</span>
                    <button class="modal-close" onclick="closeModal()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="modal-body">${bodyHtml}</div>
                ${btns}
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });
    }

    window.closeModal = function() {
        var el = document.getElementById('modalOverlay');
        if (el) el.remove();
    };

    // ══════════════════════════════════════
    // UTILS
    // ══════════════════════════════════════
    function formatDate(dt) {
        if (!dt) return '-';
        var d = new Date(dt.replace(' ', 'T') + (dt.includes('+') ? '' : 'Z'));
        var now = new Date();
        var diff = now - d;
        if (diff < 86400000 && d.getDate() === now.getDate()) {
            return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
        }
        return d.getDate().toString().padStart(2, '0') + '.' + (d.getMonth() + 1).toString().padStart(2, '0') + '.' + d.getFullYear();
    }

    function formatTime(dt) {
        if (!dt) return '';
        var d = new Date(dt.replace(' ', 'T') + (dt.includes('+') ? '' : 'Z'));
        return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
    }

    function timeAgo(dt) {
        if (!dt) return '';
        var d = new Date(dt.replace(' ', 'T') + (dt.includes('+') ? '' : 'Z'));
        var diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'gerade eben';
        if (diff < 3600) return Math.floor(diff / 60) + ' Min.';
        if (diff < 86400) return Math.floor(diff / 3600) + ' Std.';
        return Math.floor(diff / 86400) + ' Tage';
    }

    // ══════════════════════════════════════
    // INIT
    // ══════════════════════════════════════
    function init() {
        // Sidebar navigation
        document.querySelectorAll('.nav-item').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                navigate(el.dataset.page);
                window.location.hash = el.dataset.page;
            });
        });

        // Mobile sidebar toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('show');
        });
        document.getElementById('sidebarOverlay').addEventListener('click', closeMobileSidebar);

        // Sidebar collapse toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Route from hash
        var hash = window.location.hash.replace('#', '') || 'dashboard';
        navigate(hash);

        window.addEventListener('hashchange', function() {
            var h = window.location.hash.replace('#', '') || 'dashboard';
            navigate(h);
        });
    }

    async function logout() {
        await api('logout');
        window.location.href = 'index.php';
    }

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    return {
        go: function(page) { window.location.hash = page; },
        logout: logout,
        showAddCall: showAddCall,
        saveCall: saveCall,
        showAddCustomer: showAddCustomer,
        saveCustomer: saveCustomer,
        viewCustomer: viewCustomer,
        searchCustomers: searchCustomers,
        viewEmail: viewEmail,
        toggleStar: toggleStar,
        openChat: openChat,
        sendChat: sendChat,
        startGame: startGame,
        stopGame: stopGame,
        saveSettings: saveSettings,
        changePassword: changePassword,
        showAddUser: showAddUser,
        saveUser: saveUser,
        editUser: editUser,
        closeEditRow: closeEditRow,
        updateUser: updateUser,
        deleteUser: deleteUser,
        copyRegLink: function(url) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() { toast('Registrierungslink kopiert!', 'success'); });
            } else {
                var ta = document.createElement('textarea');
                ta.value = url; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
                toast('Registrierungslink kopiert!', 'success');
            }
        },
        addPhoneTag: addPhoneTag,
        addPhoneRange: addPhoneRange,
        updateRangeCount: updateRangeCount,
        editPhoneTag: editPhoneTag,
        savePhoneTagEdit: savePhoneTagEdit,
        cancelPhoneTagEdit: cancelPhoneTagEdit,
        deletePhoneTag: deletePhoneTag,
        hasAccess: hasAccess,
        checkForUpdate: checkForUpdate,
        doUpdate: doUpdate,
        openProfile: openProfile,
        closeProfile: closeProfile,
        saveProfileInfo: saveProfileInfo,
        saveProfilePassword: saveProfilePassword,
        requestUpgrade: requestUpgrade,
        requestNumbers: requestNumbers,
        updateRequest: updateRequest,
        uploadAvatar: uploadAvatar,
        uploadLogo: uploadLogo,
        toggleSipgate: toggleSipgate,
        toast: toast
    };

})();
