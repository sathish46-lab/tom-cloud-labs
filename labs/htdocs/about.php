<?php
require_once 'src/load.php';

Session::$pageTitle = 'About | ' . Session::siteName();
Session::set('seo_description', 'Learn about ' . Session::siteName() . ' Labs — a self-hosted cloud lab platform for isolated dev environments, VPN, browser IDEs and automated lifecycle.');
Session::set('seo_keywords', 'About, ' . Session::siteName() . ', cloud lab platform, isolated dev environments, VPN');

$siteName = Session::siteName();
$favicon = Session::favicon();
$logo = Session::logo();
$authStatus = Session::getAuthStatus();
$isLoggedIn = ($authStatus ?? '') === 'loggedin' || $authStatus === Constants::STATUS_LOGGEDIN;
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
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

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
        .nav-menu a.active { color: var(--primary); }

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

        /* Page shell */
        .page { max-width: 900px; margin: 0 auto; padding: 100px 1.5rem 4rem; }
        .page-head { text-align: center; margin-bottom: 3rem; }
        .page-head .kicker {
            font-size: 0.75rem; font-weight: 700; color: var(--primary);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .page-head h1 {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 800;
            letter-spacing: -0.02em; margin-bottom: 12px;
        }
        .page-head p { color: var(--text-muted); font-size: 1rem; max-width: 60ch; margin: 0 auto; line-height: 1.6; }

        .content-card {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 16px; padding: 2.5rem 2rem;
            backdrop-filter: blur(12px); margin-bottom: 2rem;
        }
        .content-card h2 {
            font-size: 1.4rem; font-weight: 800; color: var(--text);
            margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;
        }
        .content-card h2 i { color: var(--primary); }
        .content-card p {
            color: var(--text-muted); font-size: 0.95rem; line-height: 1.7;
            margin-bottom: 1rem;
        }
        .content-card ul {
            list-style: none; padding-left: 0; margin: 1rem 0;
        }
        .content-card ul li {
            display: flex; align-items: flex-start; gap: 10px;
            color: var(--text-muted); font-size: 0.95rem;
            margin-bottom: 8px; line-height: 1.5;
        }
        .content-card ul li i { color: var(--primary); margin-top: 2px; flex-shrink: 0; }

        .team-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem; margin-top: 1rem;
        }
        .team-card {
            background: var(--surface); border: 1px solid var(--glass-border);
            border-radius: 14px; padding: 1.5rem; text-align: center;
        }
        .team-card .avatar {
            width: 48px; height: 48px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #fb923c);
            margin: 0 auto 12px; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 1.1rem;
        }
        .team-card h3 { font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .team-card p { font-size: 0.85rem; color: var(--text-muted); }

        .divider {
            height: 1px; background: var(--glass-border);
            margin: 2.5rem 0;
        }

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
            <li><a href="/about" class="nav-link active">About</a></li>
            <li><a href="/dashboard" class="nav-link">Lab Listing</a></li>
            <li><a href="#contact" class="nav-link">Contact</a></li>
            <li><a href="/terms" class="nav-link">Terms of Service</a></li>
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
        <div class="kicker">About Us</div>
        <h1>Building labs for engineers</h1>
        <p>TomCloudLab is a self-hosted cloud lab platform that gives you isolated dev environments, VPN reachability, browser IDEs and an automated lifecycle engine — deploy, share and teach with one command.</p>
    </div>

    <div class="content-card">
        <h2><i class='bx bx-info-circle'></i> Our story</h2>
        <p>TomCloudLab started as a simple idea: developers shouldn't spend hours configuring environments before they can write code. We built a deterministic engine over Docker that provisions real domains with automatic TLS, private WireGuard networking and managed services — all from a single command.</p>
        <p>Today it powers learners, educators and engineers who need reproducible, disposable labs without the operational overhead. Whether you're teaching a class, preparing for a CTF or prototyping a new service, TomCloudLab gives you infrastructure that works the first time.</p>
    </div>

    <div class="content-card">
        <h2><i class='bx bx-layer'></i> What we provide</h2>
        <ul>
            <li><i class='bx bx-code-alt'></i> <strong>Browser IDE</strong> — code-server in every lab, no local setup.</li>
            <li><i class='bx bx-globe'></i> <strong>Real domains with TLS</strong> — Traefik edge + Let's Encrypt for every lab.</li>
            <li><i class='bx bx-shield'></i> <strong>WireGuard mesh</strong> — private VPN IPs for SSH, databases and ports.</li>
            <li><i class='bx bx-database'></i> <strong>Six managed services</strong> — MySQL, MariaDB, MongoDB, PostgreSQL, Redis, RabbitMQ.</li>
            <li><i class='bx bx-rocket'></i> <strong>Lifecycle engine</strong> — labsctl for build, deploy, redeploy and teardown.</li>
            <li><i class='bx bx-graduation'></i> <strong>Full learning stack</strong> — Learn AI, Roadmaps, Quiz, Code Arena, CTF, Clans.</li>
        </ul>
    </div>

    <div class="content-card">
        <h2><i class='bx bx-group'></i> The team</h2>
        <div class="team-grid">
            <div class="team-card">
                <div class="avatar">S</div>
                <h3>Sathish46</h3>
                <p>CEO &amp; Founder</p>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="content-card" style="text-align:center;">
        <h2 style="margin-bottom:1rem;">Get in touch</h2>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">Have questions, need help, or want to collaborate? Reach out.</p>
        <a href="mailto:sathishp3223@gmail.com" class="btn-primary">Email us</a>
        <a href="https://github.com/sathish46-lab/tom-cloud-labs" target="_blank" rel="noopener" class="btn-ghost">GitHub</a>
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
