<?php
require_once 'src/load.php';

/* Allow both logged-in and guest users to view the landing page. */
$isSessionExpired = (isset($_GET['session_expired']) && $_GET['session_expired'] == '1')
    || !empty($_COOKIE['show_session_expired'])
    || !empty($_SESSION['show_session_expired']);

if ($isSessionExpired) {
    if (!empty($_COOKIE['show_session_expired'])) {
        setcookie('show_session_expired', '', time() - 3600, '/');
    }
    if (!empty($_SESSION['show_session_expired'])) {
        unset($_SESSION['show_session_expired']);
    }
    Session::$pageTitle = 'Authentication Required | ' . Session::siteName();
    Session::set('show_session_expired', true);
    ?>
    <!DOCTYPE html>
    <html lang="en" data-coreui-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= Session::$pageTitle ?></title>
        <link rel="icon" type="image/png" href="<?= Session::favicon() ?>">
    </head>
    <body style="margin:0; padding:0; background:#05070d;">
    <?php
    include __DIR__ . '/src/template/_session_expired_popup.php';
    ?>
    </body>
    </html>
    <?php
    exit;
}

/* 2. Set Page Metadata */
Session::$pageTitle = Session::siteName() . ' | Virtual Innovation Hub';
Session::set('seo_description', Session::siteName() . ' is a premier virtual innovation hub offering advanced cloud environments, scalable VPS, and secure VPN workspaces for elite developers and engineers.');
Session::set('seo_keywords', 'Virtual Innovation Hub, ' . Session::siteName() . ', Advanced Cloud Infrastructure, Digital Workspace, VPS, Developer Environment');
define('IS_LANDING_PAGE', true);
Session::addCustomCss('/assets/css/landing.css');

$siteName = Session::siteName();
$favicon = Session::favicon();
$logo = Session::logo();
$authStatus = Session::getAuthStatus();
$isLoggedIn = ($authStatus ?? '') === 'loggedin' || $authStatus === Constants::STATUS_LOGGEDIN;
$heroQuotes = [
    ['p1' => 'Innovate Fearlessly', 'p2' => 'Your Virtual Innovation Hub'],
    ['p1' => 'Code Without Limits', 'p2' => 'The Ultimate Cloud IDE'],
    ['p1' => 'Deploy in Seconds', 'p2' => 'Next-Gen Infrastructure'],
    ['p1' => 'Secure by Design', 'p2' => 'Isolated Lab Environments'],
    ['p1' => 'Master the Cloud', 'p2' => 'Your Technical Playground'],
];
$initialQuote = $heroQuotes[array_rand($heroQuotes)];
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
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $favicon ?>">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        :root {
            --bg: #05070d;
            --surface: #0b0f1a;
            --glass: rgba(255, 255, 255, 0.04);
            --glass-border: rgba(255, 255, 255, 0.08);
            --primary: #0ea5e9;
            --primary-hover: #38bdf8;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #fb923c;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        /* ---------- Header ---------- */
        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            background: rgba(5, 7, 13, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
        }
        .nav-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .logo {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; font-size: 1.25rem;
            letter-spacing: -0.5px;
        }
        .logo img { width: 32px; height: 32px; border-radius: 6px; }
        .logo span { color: var(--text); }
        .logo span em { font-style: normal; color: var(--primary); }

        .nav-menu {
            display: none;
            align-items: center;
            gap: 2.5rem;
            list-style: none;
        }
        .nav-menu a {
            font-weight: 500; font-size: 0.9rem; color: var(--text-muted);
            transition: color 0.2s;
        }
        .nav-menu a:hover, .nav-menu a.active { color: var(--text); }
        .nav-menu a.active { color: var(--primary); }

        .nav-actions {
            display: flex; align-items: center; gap: 10px;
        }
        .btn-ghost {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: var(--text);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.08); border-color: var(--primary); color: var(--primary); }
        .btn-primary {
            background: var(--primary);
            border: none; color: white;
            padding: 9px 20px; border-radius: 8px;
            font-weight: 700; font-size: 0.9rem;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(14,165,233,0.35); }

        .menu-btn {
            display: none; background: none; border: none;
            color: var(--text); font-size: 1.25rem; cursor: pointer;
        }

        /* ---------- Mobile menu ---------- */
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

        /* ---------- Hero ---------- */
        .hero {
            min-height: 100vh;
            display: flex; align-items: center;
            padding-top: 80px; padding-bottom: 4rem;
            position: relative; overflow: hidden;
        }
        .hero .container {
            max-width: 1100px; margin: 0 auto; padding: 0 1.5rem;
            position: relative; z-index: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem; align-items: center;
        }
        .hero h1 {
            font-size: clamp(2.2rem, 5vw, 3.5rem);
            font-weight: 800; line-height: 1.1;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }
        .hero h1 em { font-style: normal; color: var(--primary); }
        .hero p {
            font-size: 1.05rem; color: var(--text-muted);
            max-width: 44ch; margin-bottom: 1.75rem; line-height: 1.6;
        }
        .hero-actions {
            display: flex; gap: 10px; flex-wrap: wrap;
        }

        /* Hero visual */
        .hero-visual {
            position: relative; height: 420px; display: flex;
            align-items: center; justify-content: center;
        }
        .hero-visual::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 40% 40%, rgba(14,165,233,0.15), transparent 60%);
            z-index: 0; animation: pulse 6s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.15); }
        }
        .terminal-card {
            position: relative; z-index: 2;
            background: #03050b; border: 1px solid var(--glass-border);
            border-radius: 16px; width: 100%; max-width: 460px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            backdrop-filter: blur(20px);
            overflow: hidden;
        }
        .terminal-bar {
            background: #0f172a; padding: 9px 12px; display: flex;
            align-items: center; gap: 8px; border-bottom: 1px solid var(--glass-border);
        }
        .terminal-dots { display: flex; gap: 6px; }
        .terminal-dots span { width: 10px; height: 10px; border-radius: 50%; }
        .terminal-dots .r { background: #ff5f56; box-shadow: 0 0 8px #ff5f56; }
        .terminal-dots .y { background: #ffbd2e; box-shadow: 0 0 8px #ffbd2e; }
        .terminal-dots .g { background: #27c93f; box-shadow: 0 0 8px #27c93f; }
        .terminal-title {
            font-size: 0.72rem; color: #64748b; font-weight: 600;
            letter-spacing: 0.3px; margin-left: auto;
        }
        .terminal-body {
            padding: 14px 16px; font-family: 'Inter', ui-monospace, SFMono-Regular, monospace;
            font-size: 0.82rem; line-height: 1.6; color: #cbd5e1;
            background: #02040a;
        }
        .terminal-body .prompt { color: var(--primary); font-weight: 700; margin-right: 6px; }
        .terminal-body .line { margin: 4px 0; white-space: pre-wrap; }
        .terminal-body .cursor { color: var(--primary); animation: blink 1s infinite; }
        @keyframes blink { 0%,50% { opacity: 1; } 51%,100% { opacity: 0; } }

        /* ---------- Stats ---------- */
        .stats {
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1.5rem 2rem;
            margin-top: 3rem;
            display: flex; flex-wrap: wrap; gap: 1.5rem;
            justify-content: center;
            backdrop-filter: blur(12px);
        }
        .stat {
            display: flex; flex-direction: column; gap: 4px;
            min-width: 110px; text-align: center;
        }
        .stat .num {
            font-size: 1.35rem; font-weight: 800; color: var(--text);
            letter-spacing: -0.5px;
        }
        .stat .lbl {
            font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        /* ---------- Features ---------- */
        .features {
            padding: 5rem 1.5rem; max-width: 1100px; margin: 0 auto;
        }
        .section-head {
            text-align: center; margin-bottom: 3rem;
        }
        .section-head .kicker {
            font-size: 0.75rem; font-weight: 700; color: var(--primary);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .section-head h2 {
            font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 800;
            letter-spacing: -0.02em; margin-bottom: 10px;
        }
        .section-head p {
            color: var(--text-muted); font-size: 1rem; max-width: 560px; margin: 0 auto;
        }
        .grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
        }
        .feature-card {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 14px; padding: 1.75rem;
            backdrop-filter: blur(10px); transition: all 0.3s;
            display: flex; flex-direction: column; gap: 1rem;
        }
        .feature-card:hover {
            transform: translateY(-4px); border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .feature-card .icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(14,165,233,0.1); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }
        .feature-card h3 {
            font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 6px;
        }
        .feature-card p {
            font-size: 0.88rem; color: var(--text-muted); line-height: 1.55;
        }

        /* ---------- CTA ---------- */
        .cta {
            padding: 5rem 1.5rem; max-width: 1100px; margin: 0 auto;
            text-align: center;
        }
        .cta-card {
            background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(249,146,60,0.08));
            border: 1px solid rgba(14,165,233,0.18);
            border-radius: 20px; padding: 3rem 2rem;
            backdrop-filter: blur(20px);
        }
        .cta h2 {
            font-size: clamp(1.7rem, 3vw, 2.2rem); font-weight: 800;
            letter-spacing: -0.02em; margin-bottom: 10px;
        }
        .cta p {
            color: var(--text-muted); font-size: 1rem; max-width: 500px;
            margin: 0 auto 1.75rem; line-height: 1.6;
        }
        .cta-actions {
            display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;
        }

        /* ---------- Footer ---------- */
        footer {
            border-top: 1px solid var(--glass-border);
            margin-top: auto; background: #02040a;
        }
        .footer-wrap {
            max-width: 1100px; margin: 0 auto; padding: 3rem 1.5rem 2rem;
            display: grid; grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 2rem;
        }
        .footer-brand { max-width: 260px; }
        .footer-brand .logo { font-size: 1.1rem; margin-bottom: 1rem; }
        .footer-brand p {
            font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;
        }
        .footer-col h4 {
            font-size: 0.85rem; font-weight: 700; color: var(--text);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.25rem;
        }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .footer-col a {
            font-size: 0.9rem; color: var(--text-muted); transition: color 0.2s;
        }
        .footer-col a:hover { color: var(--primary); }
        .footer-bottom {
            max-width: 1100px; margin: 0 auto; padding: 1.5rem;
            border-top: 1px solid var(--glass-border);
            display: flex; flex-direction: column; gap: 10px; text-align: center;
        }
        .footer-bottom p {
            font-size: 0.8rem; color: var(--text-muted);
        }
        .footer-bottom .legal {
            display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;
        }
        .footer-bottom .legal a {
            font-size: 0.8rem; color: var(--text-muted); transition: color 0.2s;
        }
        .footer-bottom .legal a:hover { color: var(--primary); }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .hero .container { grid-template-columns: 1fr; gap: 2rem; }
            .hero-visual { order: -1; height: 340px; }
            .hero h1 { font-size: 2rem; }
            .footer-wrap { grid-template-columns: 1fr 1fr; gap: 2rem; }
            .footer-brand { grid-column: 1 / -1; max-width: none; }
        }
        @media (max-width: 480px) {
            .footer-wrap { grid-template-columns: 1fr; }
            .hero-actions { flex-direction: column; }
            .btn-primary { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header>
    <div class="nav-wrap">
        <a href="/" class="logo">
            <img src="<?= $logo ?>" alt="<?= htmlspecialchars($siteName) ?>">
            <span><?= htmlspecialchars($siteName) ?> <em>Labs</em></span>
        </a>

        <ul class="nav-menu" id="navMenu">
            <li><a href="/" class="nav-link active">Home</a></li>
            <li><a href="/about" class="nav-link">About</a></li>
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

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="hero-visual">
            <div class="terminal-card">
                <div class="terminal-bar">
                    <div class="terminal-dots">
                        <span class="r"></span><span class="y"></span><span class="g"></span>
                    </div>
                    <div class="terminal-title">labsctl — live</div>
                </div>
                <div class="terminal-body">
                    <div class="line"><span class="prompt">→</span><span class="cursor">_</span></div>
                    <div class="line">build&nbsp;&nbsp;&nbsp;image&nbsp;&nbsp;essentials&nbsp;built</div>
                    <div class="line">deploy&nbsp;&nbsp;instance&nbsp;&nbsp;up&nbsp;&nbsp;·&nbsp;&nbsp;2&nbsp;GB&nbsp;/&nbsp;0.5&nbsp;CPU</div>
                    <div class="line">domain&nbsp;&nbsp;&nbsp;code.**,&nbsp;web.**&nbsp;routed&nbsp;@&nbsp;:443</div>
                    <div class="line">tls&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;let's&nbsp;encrypt&nbsp;issued</div>
                    <div class="line">wg&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;peer&nbsp;registered&nbsp;·&nbsp;10.20.x.x</div>
                </div>
            </div>
        </div>

        <div>
            <h1><?= $initialQuote['p1'] ?> <em><?= $initialQuote['p2'] ?></em></h1>
            <p>Isolated dev environments with VPN, browser IDE and an automated lifecycle engine — deploy, share and teach with one command.</p>
            <div class="hero-actions">
                <a href="/signup" class="btn-primary">Start building</a>
                <a href="/signin" class="btn-ghost">Sign in to dashboard</a>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="num">3</div>
                    <div class="lbl">Deploy modes</div>
                </div>
                <div class="stat">
                    <div class="num">6</div>
                    <div class="lbl">Managed services</div>
                </div>
                <div class="stat">
                    <div class="num">1 cmd</div>
                    <div class="lbl">Zero setup</div>
                </div>
                <div class="stat">
                    <div class="num">∞</div>
                    <div class="lbl">Labs</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features">
    <div class="section-head">
        <div class="kicker">Capabilities</div>
        <h2>Everything you need to run labs</h2>
        <p>One reproducible install. Deterministic engine over Docker, real domains with TLS, VPN reachability and a full learning stack.</p>
    </div>
    <div class="grid">
        <div class="feature-card">
            <div class="icon"><i class='bx bx-code-alt'></i></div>
            <h3>Browser IDE</h3>
            <p>Every lab ships a browser code editor (code-server) — open a URL and code, no local setup needed.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class='bx bx-globe'></i></div>
            <h3>Every domain served</h3>
            <p>Traefik at the edge with automatic Let's Encrypt TLS for every lab domain.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class='bx bx-shield'></i></div>
            <h3>WireGuard mesh</h3>
            <p>Labs join a private VPN network — SSH, databases and ports stay reachable from enrolled devices.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class='bx bx-layer'></i></div>
            <h3>Lifecycle engine</h3>
            <p>Build, deploy, redeploy and tear down isolated containers from one CLI (labsctl).</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class='bx bx-database'></i></div>
            <h3>Managed data</h3>
            <p>MySQL, MariaDB, MongoDB, PostgreSQL, Redis & RabbitMQ with per-user access.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class='bx bx-rocket'></i></div>
            <h3>Full learning stack</h3>
            <p>Learn AI, Roadmaps, Quiz, Code Arena, CTF missions, Clans and discussion feed.</p>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="cta-card">
        <h2>Ready to run your own?</h2>
        <p>Three install modes cover a Mac, a home server behind a tunnel, or a clean VPS.</p>
        <div class="cta-actions">
            <a href="/signup" class="btn-primary">Start building</a>
            <a href="/signin" class="btn-ghost">Sign in</a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="footer-wrap">
        <div class="footer-brand">
            <div class="logo" style="font-size: 1.1rem;">
                <img src="<?= $logo ?>" alt="<?= htmlspecialchars($siteName) ?>" style="width:28px;height:28px;">
                <span><?= htmlspecialchars($siteName) ?> <em>Labs</span></em></div>
            <p style="margin-top:0.75rem;">Self-hosted cloud lab platform — isolated dev environments, VPN, browser IDEs and an automated lifecycle engine.</p>
        </div>
        <div class="footer-col">
            <h4>Product</h4>
            <ul>
                <li><a href="/dashboard">Lab Listing</a></li>
                <li><a href="/about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Legal</h4>
            <ul>
                <li><a href="/terms">Terms of Service</a></li>
                <li><a href="/privacy">Privacy Policy</a></li>
                <li><a href="/cookies">Cookie Policy</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Resources</h4>
            <ul>
                <li><a href="https://blog.tomweb.fun/" target="_blank" rel="noopener">Blog</a></li>
                <li><a href="https://github.com/sathish46-lab/tom-cloud-labs" target="_blank" rel="noopener">GitHub</a></li>
                <li><a href="#contact">Support</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p><?= htmlspecialchars($siteName) ?> Labs. Built for engineers.</p>
        <div class="legal">
            <a href="/terms">Terms of Service</a>
            <a href="/privacy">Privacy Policy</a>
            <a href="/cookies">Cookie Policy</a>
        </div>
    </div>
</footer>

<script>
    const menuBtn = document.getElementById('menuBtn');
    const navMenu = document.getElementById('navMenu');
    menuBtn.addEventListener('click', () => {
        navMenu.classList.toggle('open');
    });
    // Close menu on link click
    document.querySelectorAll('.nav-menu a').forEach(a => {
        a.addEventListener('click', () => navMenu.classList.remove('open'));
    });
</script>

</body>
</html>
