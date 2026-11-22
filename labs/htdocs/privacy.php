<?php
require_once 'src/load.php';

Session::$pageTitle = 'Privacy Policy | ' . Session::siteName();
Session::set('seo_description', 'Privacy Policy for ' . Session::siteName() . ' Labs. Learn how we collect, use and protect your personal information.');
Session::set('seo_keywords', 'Privacy Policy, ' . Session::siteName() . ', data protection, privacy');

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
            <li><a href="/privacy" class="nav-link active">Privacy Policy</a></li>
            <li><a href="/cookies" class="nav-link">Cookie Policy</a></li>
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
        <h1>Privacy Policy</h1>
        <div class="meta">
            <span>Last updated: <?= htmlspecialchars($effectiveDate) ?></span>
            <span>Effective date: <?= htmlspecialchars($effectiveDate) ?></span>
        </div>
    </div>

    <div class="legal-card">
        <p>At <?= htmlspecialchars($siteName) ?> Labs ("we", "us", "our"), we respect your privacy. This Privacy Policy explains what information we collect, how we use it, and your rights regarding your personal information when you use our website and services (the "Service").</p>

        <h2><i class='bx bx-user'></i> 1. Information We Collect</h2>
        <h3>Information you provide directly:</h3>
        <ul>
            <li>Account information: email address, username, first name, last name and password (stored as a hash).</li>
            <li>Profile information: avatar image and theme preferences.</li>
            <li>Two-factor authentication data: email-based OTP codes and verification state.</li>
            <li>Communications: messages you send to us via support or contact forms.</li>
        </ul>
        <h3>Information collected automatically:</h3>
        <ul>
            <li>Technical data: IP address, browser type, operating system, device information and referring pages.</li>
            <li>Usage data: pages visited, features accessed, actions performed within the Service.</li>
            <li>Session data: authentication tokens, last activity timestamps and login history.</li>
        </ul>
        <h3>Information from third parties:</h3>
        <ul>
            <li>Google OAuth profile data (name, email, profile picture) if you sign in with Google.</li>
        </ul>

        <h2><i class='bx bx-bullseye'></i> 2. How We Use Your Information</h2>
        <p>We use your information to:</p>
        <ul>
            <li>Create, maintain and secure your account.</li>
            <li>Authenticate you and manage sessions, including "remember me" functionality.</li>
            <li>Provide and operate the Service, including provisioning labs and managed services.</li>
            <li>Send service-related notices, security alerts and account updates.</li>
            <li>Respond to your support requests and inquiries.</li>
            <li>Monitor and prevent abuse, fraud, and security incidents.</li>
            <li>Improve and develop the Service based on usage patterns.</li>
        </ul>

        <h2><i class='bx bx-share-alt'></i> 3. Sharing and Disclosure</h2>
        <p>We do not sell your personal information. We may share information in the following limited circumstances:</p>
        <ul>
            <li><strong>Service providers:</strong> With trusted third parties who process data on our behalf (e.g., email delivery, hosting, analytics), strictly for the purposes we instruct.</li>
            <li><strong>Legal requirements:</strong> When we believe disclosure is necessary to comply with a legal obligation, court order, or government request.</li>
            <li><strong>Safety:</strong> To protect the rights, property, or safety of us, our users, or the public.</li>
            <li><strong>Business transfers:</strong> In connection with a merger, acquisition, or sale of assets, subject to continued confidentiality obligations.</li>
        </ul>

        <h2><i class='bx bx-lock'></i> 4. Data Security</h2>
        <p>We implement reasonable administrative, technical, and physical safeguards to protect your information, including:</p>
        <ul>
            <li>Passwords stored using strong cryptographic hashing (never stored in plain text).</li>
            <li>HTTPS/TLS encryption for data in transit.</li>
            <li>Session tokens stored as hashes with automatic rotation and expiration.</li>
            <li>Access controls limiting employee access to personal data.</li>
        </ul>
        <p class="notice-text" style="font-size:0.85rem; color:var(--text-muted);">No method of transmission over the internet or electronic storage is 100% secure. We cannot guarantee absolute security.</p>

        <h2><i class='bx bx-globe'></i> 5. Data Retention</h2>
        <p>We retain personal information only as long as necessary to fulfill the purposes described in this Policy, including for legal, tax, and reporting purposes. When you delete your account, we will delete or anonymize your personal information unless retention is required by law.</p>

        <h2><i class='bx bx-user-check'></i> 6. Your Rights</h2>
        <p>Depending on your jurisdiction, you may have the right to:</p>
        <ul>
            <li>Access the personal information we hold about you.</li>
            <li>Request correction of inaccurate or incomplete information.</li>
            <li>Request deletion of your personal information ("right to be forgotten").</li>
            <li>Object to or restrict certain processing of your information.</li>
            <li>Receive a copy of your data in a portable format.</li>
            <li>Withdraw consent where processing is based on consent.</li>
        </ul>
        <p>To exercise these rights, contact us at <a href="mailto:sathishp3223@gmail.com">sathishp3223@gmail.com</a>.</p>

        <h2><i class='bx bx-cookie'></i> 7. Cookies and Tracking</h2>
        <p>We use cookies and similar technologies to operate the Service, remember your preferences, and analyze usage. For details, please see our <a href="/cookies">Cookie Policy</a>.</p>

        <h2><i class='bx bx-child'></i> 8. Children's Privacy</h2>
        <p>The Service is not directed to children under 13. We do not knowingly collect personal information from children under 13. If you believe a child under 13 has provided personal information to us, please contact us so we can delete it.</p>

        <h2><i class='bx bx-globe'></i> 9. International Data Transfers</h2>
        <p>Your information may be processed and stored in the United States or other countries whose data protection laws differ from your own. By using the Service, you consent to such transfers.</p>

        <h2><i class='bx bx-edit'></i> 10. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will notify you of material changes by posting the updated Policy on the Service with a revised effective date. Your continued use of the Service constitutes acceptance of the updated Policy.</p>

        <h2><i class='bx bx-mail-open'></i> 11. Contact Us</h2>
        <p>If you have questions about this Privacy Policy or your personal information, please contact us at <a href="mailto:sathishp3223@gmail.com">sathishp3223@gmail.com</a>.</p>

        <div class="notice">
            <div class="notice-title"><i class='bx bx-info-circle'></i> Important Notice</div>
            <p>This Privacy Policy does not cover third-party websites or services linked from the Service. We encourage you to read the privacy policies of any third-party services you use.</p>
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
