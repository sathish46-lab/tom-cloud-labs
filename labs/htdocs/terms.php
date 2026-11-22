<?php
require_once 'src/load.php';

Session::$pageTitle = 'Terms of Service | ' . Session::siteName();
Session::set('seo_description', 'Terms of Service for ' . Session::siteName() . ' Labs. By using our platform you agree to these terms.');
Session::set('seo_keywords', 'Terms of Service, ' . Session::siteName() . ', legal, terms');

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
            <li><a href="/terms" class="nav-link active">Terms of Service</a></li>
            <li><a href="/privacy" class="nav-link">Privacy Policy</a></li>
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
        <h1>Terms of Service</h1>
        <div class="meta">
            <span>Last updated: <?= htmlspecialchars($effectiveDate) ?></span>
            <span>Effective date: <?= htmlspecialchars($effectiveDate) ?></span>
        </div>
    </div>

    <div class="legal-card">
        <p>Welcome to <?= htmlspecialchars($siteName) ?> Labs ("we", "us", "our"). These Terms of Service ("Terms") govern your use of our website, platform, and related services (the "Service"). By accessing or using the Service, you agree to these Terms. If you do not agree, you may not use the Service.</p>

        <h2><i class='bx bx-user-check'></i> 1. Acceptance of Terms</h2>
        <p>By creating an account or using the Service, you confirm that you are at least 13 years old (or the minimum age required in your jurisdiction) and have the legal capacity to enter into a binding agreement. If you are accepting these Terms on behalf of an organization, you represent that you have authority to bind that organization.</p>

        <h2><i class='bx bx-user-plus'></i> 2. Accounts</h2>
        <ul>
            <li>You must provide accurate and complete information when registering.</li>
            <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
            <li>You must notify us immediately of any unauthorized use of your account.</li>
            <li>We may suspend or terminate accounts that violate these Terms.</li>
        </ul>

        <h2><i class='bx bx-terminal'></i> 3. Use of the Service</h2>
        <p>The Service provides isolated development environments ("labs"), managed services (databases, message brokers), VPN/WireGuard networking, browser IDEs and related tooling. You agree to use the Service only for lawful purposes and in accordance with these Terms.</p>

        <h3>Prohibited activities include:</h3>
        <ul>
            <li>Hosting or distributing illegal, harmful, or infringing content.</li>
            <li>Attempting to bypass security controls, scan, or attack other systems.</li>
            <li>Sending spam, phishing, or engaging in abusive behavior.</li>
            <li>Running cryptocurrency miners or other resource-abuse workloads.</li>
            <li>Using the Service to launch denial-of-service attacks or otherwise disrupt networks.</li>
        </ul>

        <h2><i class='bx bx-user-x'></i> 4. Termination</h2>
        <p>We may suspend or terminate your account and access to the Service at any time, without notice, for conduct that we believe violates these Terms or is harmful to other users, the Service, or third parties. Upon termination, your right to use the Service immediately ceases.</p>

        <h2><i class='bx bx-shield-x'></i> 5. No Warranty</h2>
        <p>The Service is provided "as is" and "as available" without warranties of any kind, express or implied, including warranties of merchantability, fitness for a particular purpose, title, or non-infringement. We do not guarantee that the Service will be error-free, uninterrupted, secure, or free of viruses or other harmful components.</p>

        <h2><i class='bx bx-ban'></i> 6. Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, we are not liable for any indirect, incidental, special, consequential, or punitive damages, or for any loss of profits, revenue, data, or use, arising out of or related to your use of the Service, even if we have been advised of the possibility of such damages. Our aggregate liability for any claim arising under these Terms shall not exceed the amount you paid us, if any, in the twelve (12) months preceding the claim.</p>

        <h2><i class='bx bx-shield'></i> 7. Indemnification</h2>
        <p>You agree to defend, indemnify, and hold harmless us, our affiliates, and our respective officers, directors, employees, and agents from and against any claims, liabilities, damages, losses, or expenses (including reasonable attorneys' fees) arising out of your use of the Service, your violation of these Terms, or your violation of any rights of another party.</p>

        <h2><i class='bx bx-globe'></i> 8. Governing Law</h2>
        <p>These Terms are governed by and construed in accordance with the laws of the jurisdiction in which we operate, without regard to conflict of law principles. Any disputes arising under these Terms shall be resolved in the courts of that jurisdiction.</p>

        <h2><i class='bx bx-edit'></i> 9. Changes to These Terms</h2>
        <p>We may update these Terms from time to time. We will notify you of material changes by posting the updated Terms on the Service with a revised effective date. Your continued use of the Service after such changes constitutes acceptance of the updated Terms.</p>

        <h2><i class='bx bx-mail-open'></i> 10. Contact Us</h2>
        <p>If you have questions about these Terms, please contact us at <a href="mailto:sathishp3223@gmail.com">sathishp3223@gmail.com</a>.</p>

        <div class="notice">
            <div class="notice-title"><i class='bx bx-info-circle'></i> Important Notice</div>
            <p>These Terms apply to your use of the Service. If you are under 13, you must not use the Service without parental consent. By using the Service, you agree to receive service-related communications, including security and account notices, at the email address associated with your account.</p>
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
