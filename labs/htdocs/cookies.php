<?php
require_once 'src/load.php';

Session::$pageTitle = 'Cookie Policy | ' . Session::siteName();
Session::set('seo_description', 'Cookie Policy for ' . Session::siteName() . ' Labs. Learn about the cookies and similar technologies we use.');
Session::set('seo_keywords', 'Cookie Policy, ' . Session::siteName() . ', cookies, privacy');

$siteName = Session::siteName();
$favicon = Session::favicon();
$logo = Session::logo();
$authStatus = Session::getAuthStatus();
$isLoggedIn = ($authStatus ?? '') === 'loggedin' || $authStatus === Constants::STATUS_LOGGEDIN;
$effectiveDate = 'November 19, 2026';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#05070d">
    <meta name="description" content="<?= Session::get('seo_description') ?>">
    <meta name="keywords" content="<?= Session::get('seo_keywords') ?>">
    <title><?= Session::$pageTitle ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $favicon ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $favicon ?>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        :root {
            --bg: #05070d;
            --surface: #0b0f1a;
            --glass: rgba(255, 255, 255, 0.04);
            --glass-border: rgba(255, 255, 255, 0.08);
            --primary: #0ea5e9;
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; overflow-x: hidden;
        }
        a { text-decoration: none; color: var(--primary); }
        a:hover { text-decoration: underline; }

        header {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 1000;
            background: rgba(5, 7, 13, 0.6);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
        }
        .nav-wrap {
            max-width: 1100px; margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .logo {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; font-size: 1.25rem; letter-spacing: -0.5px;
        }
        .logo img { width: 32px; height: 32px; border-radius: 6px; }
        .logo span { color: var(--text); }
        .logo span em { font-style: normal; color: var(--primary); }

        .nav-menu {
            display: none; align-items: center; gap: 2.5rem;
            list-style: none;
        }
        .nav-menu a {
            font-weight: 500; font-size: 0.9rem; color: var(--text-muted);
            transition: color 0.2s;
        }
        .nav-menu a:hover, .nav-menu a.active { color: var(--text); }

        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .btn-ghost {
            background: var(--glass); border: 1px solid var(--glass-border);
            color: var(--text); padding: 8px 16px; border-radius: 8px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.08); border-color: var(--primary); color: var(--primary); }
        .btn-primary {
            background: var(--primary); border: none; color: white;
            padding: 9px 20px; border-radius: 8px;
            font-weight: 700; font-size: 0.9rem;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-primary:hover { background: #38bdf8; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(14,165,233,0.35); }
        .menu-btn {
            display: none; background: none; border: none;
            color: var(--text); font-size: 1.25rem; cursor: pointer;
        }

        @media (max-width: 768px) {
            .nav-menu {
                position: fixed; top: 64px; left: 0; right: 0;
                background: var(--bg); flex-direction: column;
                padding: 2rem 1.5rem; gap: 1.5rem;
                border-bottom: 1px solid var(--glass-border);
                box-shadow: 0 10px 30px rgba(0,0,0,0.4);
                transform: translateY(-100%); opacity: 0;
                transition: all 0.3s ease;
            }
            .nav-menu.open { transform: translateY(0); opacity: 1; }
            .menu-btn { display: block; }
        }

        .page { max-width: 820px; margin: 0 auto; padding: 100px 1.5rem 4rem; }
        .page-head {
            text-align: center; margin-bottom: 2.5rem;
        }
        .page-head .kicker {
            font-size: 0.75rem; font-weight: 700; color: var(--primary);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .page-head h1 {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 800;
            letter-spacing: -0.02em; margin-bottom: 12px;
        }
        .page-head .meta {
            color: var(--text-muted); font-size: 0.85rem;
        }
        .page-head .meta span { margin-right: 12px; }

        .legal-card {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 16px; padding: 2.5rem 2rem;
            backdrop-filter: blur(12px);
        }
        .legal-card h2 {
            font-size: 1.35rem; font-weight: 800; color: var(--text);
            margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px;
        }
        .legal-card h2 i { color: var(--primary); }
        .legal-card h3 {
            font-size: 1.1rem; font-weight: 700; color: var(--text);
            margin: 1.5rem 0 0.75rem;
        }
        .legal-card p {
            color: var(--text-muted); font-size: 0.95rem; line-height: 1.7;
            margin-bottom: 1rem;
        }
        .legal-card ul {
            list-style: disc; padding-left: 1.25rem;
            color: var(--text-muted); font-size: 0.95rem; line-height: 1.7;
            margin-bottom: 1rem;
        }
        .legal-card ul li { margin-bottom: 0.5rem; }
        .legal-card table {
            width: 100%; border-collapse: collapse;
            margin: 1rem 0; font-size: 0.9rem;
        }
        .legal-card th {
            text-align: left; color: var(--text); font-weight: 700;
            padding: 8px 10px; border-bottom: 1px solid var(--glass-border);
        }
        .legal-card td {
            padding: 8px 10px; border-bottom: 1px solid var(--glass-border);
            color: var(--text-muted);
        }
        .legal-card tr:last-child td { border-bottom: none; }
        .legal-card code {
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 0.85rem; background: rgba(14,165,233,0.08);
            color: var(--primary); padding: 2px 6px; border-radius: 4px;
        }
        .legal-card .notice {
            background: rgba(249,146,60,0.08);
            border: 1px solid rgba(249,146,60,0.18);
            border-radius: 10px; padding: 1rem 1.25rem;
            margin: 1.5rem 0;
        }
        .legal-card .notice .notice-title {
            font-weight: 700; color: var(--text); font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
        }
        .legal-card .notice .notice-title i { color: var(--accent); }
        .legal-card .notice p { margin: 0; font-size: 0.9rem; }

        footer {
            border-top: 1px solid var(--glass-border);
            margin-top: 3rem; background: #02040a;
        }
        .footer-wrap {
            max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem;
            display: flex; flex-direction: column; gap: 1.5rem;
            text-align: center;
        }
        .footer-brand .logo { font-size: 1.1rem; font-weight: 800; letter-spacing: -0.5px; }
        .footer-brand .logo span em { font-style: normal; color: var(--primary); }
        .footer-brand p {
            font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;
            margin-top: 0.75rem; max-width: 400px;
        }
        .footer-bottom {
            padding-top: 1.5rem; border-top: 1px solid var(--glass-border);
            display: flex; flex-direction: column; gap: 10px;
        }
        .footer-bottom p { font-size: 0.8rem; color: var(--text-muted); }
        .footer-bottom .legal {
            display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;
        }
        .footer-bottom .legal a {
            font-size: 0.8rem; color: var(--text-muted); transition: color 0.2s;
        }
        .footer-bottom .legal a:hover { color: var(--primary); }
    </style>
</head>
<body>

<header>
    <div class="nav-wrap">
        <a href="/" class="logo">
            <img src="<?= $logo ?>" alt="<?= htmlspecialchars($siteName) ?>">
            <span><?= htmlspecialchars($siteName) ?> <em>Labs</em></span>
        </a>

        <ul class="nav-menu" id="navMenu">
            <li><a href="/" class="nav-link">Home</a></li>
            <li><a href="/about" class="nav-link">About</a></li>
            <li><a href="/dashboard" class="nav-link">Lab Listing</a></li>
            <li><a href="#contact" class="nav-link">Contact</a></li>
            <li><a href="/terms" class="nav-link">Terms of Service</a></li>
            <li><a href="/privacy" class="nav-link">Privacy Policy</a></li>
            <li><a href="/cookies" class="nav-link active">Cookie Policy</a></li>
        </ul>

        <div class="nav-actions">
            <?php if ($isLoggedIn): ?>
                <a href="/dashboard" class="btn-primary">Go to Dashboard</a>
            <?php else: ?>
                <a href="/signin" class="btn-ghost">Sign in</a>
                <a href="/signup" class="btn-primary">Get started</a>
            <?php endif; ?>
            <button class="menu-btn" id="menuBtn"><i class='bx bx-menu'></i></button>
        </div>
    </div>
</header>

<div class="page">
    <div class="page-head">
        <div class="kicker">Legal</div>
        <h1>Cookie Policy</h1>
        <div class="meta">
            <span>Last updated: <?= htmlspecialchars($effectiveDate) ?></span>
            <span>Effective date: <?= htmlspecialchars($effectiveDate) ?></span>
        </div>
    </div>

    <div class="legal-card">
        <p>This Cookie Policy explains what cookies are, how <?= htmlspecialchars($siteName) ?> Labs ("we", "us", "our") uses them on our website and services (the "Service"), and your choices regarding cookies.</p>

        <h2><i class='bx bx-cookie'></i> 1. What Are Cookies</h2>
        <p>Cookies are small text files placed on your device (computer, tablet, or mobile) when you visit a website. They allow the site to remember your actions and preferences over time, and can be used to enable functionality, analyze usage, and support security.</p>
        <p>Similar technologies include web beacons, pixels, and local storage. In this Policy, "cookies" refers to all such technologies.</p>

        <h2><i class='bx bx-layer'></i> 2. Types of Cookies We Use</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 22%;">Category</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Strictly necessary</strong></td>
                    <td>These cookies are essential for the Service to function. They enable core features such as user authentication, session management, and security. Without them, the Service cannot operate properly. Example: <code>PHPSESSID</code>, <code>session_token</code>.</td>
                </tr>
                <tr>
                    <td><strong>Functional</strong></td>
                    <td>These cookies remember your choices (such as language, theme, or dashboard preferences) to provide a more personalized experience. Example: theme preference, layout settings.</td>
                </tr>
                <tr>
                    <td><strong>Performance / Analytics</strong></td>
                    <td>These cookies help us understand how visitors interact with the Service by collecting anonymous usage data. They help us improve performance and fix issues.</td>
                </tr>
                <tr>
                    <td><strong>Security</strong></td>
                    <td>These cookies support authentication and fraud prevention, including CSRF protection and session integrity checks. Example: CSRF tokens.</td>
                </tr>
            </tbody>
        </table>

        <h2><i class='bx bx-list-check'></i> 3. Specific Cookies We Set</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Cookie name</th>
                    <th style="width: 30%;">Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>PHPSESSID</code></td>
                    <td>PHP session identifier used to maintain your authenticated session across requests.</td>
                    <td>Session (expires when browser closes, or as configured)</td>
                </tr>
                <tr>
                    <td><code>session_token</code></td>
                    <td>Secure, hashed token used for "remember me" functionality and API authentication.</td>
                    <td>Up to 30 days if "Remember me" is checked; otherwise session</td>
                </tr>
                <tr>
                    <td><code>oauth_state</code></td>
                    <td>OAuth state parameter used during Google sign-in to prevent CSRF attacks.</td>
                    <td>10 minutes</td>
                </tr>
                <tr>
                    <td><code>oauth_verifier</code></td>
                    <td>OAuth verifier used during the sign-in flow.</td>
                    <td>10 minutes</td>
                </tr>
                <tr>
                    <td><code>oauth_cache_key</code></td>
                    <td>OAuth cache key used during the sign-in flow.</td>
                    <td>10 minutes</td>
                </tr>
            </tbody>
        </table>

        <h2><i class='bx bx-select'></i> 4. Third-Party Cookies</h2>
        <p>We may allow certain third parties (such as analytics providers or authentication services) to place cookies on your device when you use the Service. These third parties may collect information about your online activities over time and across different websites.</p>
        <p>Examples include Google (for OAuth sign-in). We do not control third-party cookies; please refer to the respective provider's privacy and cookie policies.</p>

        <h2><i class='bx bx-slider'></i> 5. Your Choices</h2>
        <ul>
            <li><strong>Browser settings:</strong> You can configure your browser to accept, reject, or delete cookies. Most browsers allow you to manage cookies in their settings or preferences menu.</li>
            <li><strong>Strictly necessary cookies:</strong> These cannot be disabled without affecting the core functionality of the Service.</li>
            <li><strong>Analytics cookies:</strong> You may opt out where the provider offers an opt-out mechanism.</li>
        </ul>
        <p class="notice-text" style="font-size:0.85rem; color:var(--text-muted);">If you disable cookies, some features of the Service may not work properly, and you may need to re-enter information or sign in more frequently.</p>

        <h2><i class='bx bx-user-check'></i> 6. Your Rights</h2>
        <p>You may have the right to access, correct, delete, or restrict processing of your personal information, including information collected through cookies, depending on your jurisdiction. To exercise these rights, contact us at <a href="mailto:sathishp3223@gmail.com">sathishp3223@gmail.com</a>.</p>

        <h2><i class='bx bx-edit'></i> 7. Changes to This Policy</h2>
        <p>We may update this Cookie Policy from time to time. We will notify you of material changes by posting the updated Policy on the Service with a revised effective date. Your continued use of the Service constitutes acceptance of the updated Policy.</p>

        <h2><i class='bx bx-mail-open'></i> 8. Contact Us</h2>
        <p>If you have questions about this Cookie Policy, please contact us at <a href="mailto:sathishp3223@gmail.com">sathishp3223@gmail.com</a>.</p>

        <div class="notice">
            <div class="notice-title"><i class='bx bx-info-circle'></i> Important Notice</div>
            <p>This Cookie Policy does not cover third-party websites or services linked from the Service. We encourage you to read the cookie policies of any third-party services you use.</p>
        </div>
    </div>
</div>

<footer>
    <div class="footer-wrap">
        <div class="footer-brand">
            <div class="logo" style="font-size: 1.1rem;">
                <img src="<?= $logo ?>" alt="<?= htmlspecialchars($siteName) ?>" style="width:28px;height:28px;">
                <span><?= htmlspecialchars($siteName) ?> <em>Labs</span></em></div>
            <p style="margin-top:0.75rem;">Isolated dev environments with VPN, browser IDE and an automated lifecycle engine.</p>
        </div>
        <div class="footer-bottom">
            <p><?= htmlspecialchars($siteName) ?> Labs. Built for engineers.</p>
            <div class="legal">
                <a href="/terms">Terms of Service</a>
                <a href="/privacy">Privacy Policy</a>
                <a href="/cookies">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>

<script>
    const menuBtn = document.getElementById('menuBtn');
    const navMenu = document.getElementById('navMenu');
    menuBtn.addEventListener('click', () => { navMenu.classList.toggle('open'); });
    document.querySelectorAll('.nav-menu a').forEach(a => {
        a.addEventListener('click', () => navMenu.classList.remove('open'));
    });
</script>

</body>
</html>
