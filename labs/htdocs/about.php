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
    <meta name="theme-color" content="#0a1220">
    <meta name="description" content="<?= Session::get('seo_description') ?>">
    <meta name="keywords" content="<?= Session::get('seo_keywords') ?>">
    <title><?= Session::$pageTitle ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $favicon ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $favicon ?>">

    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <link rel="stylesheet" href="/assets/css/legal.css?v=<?= time() ?>">
</head>
<body>

<header class="portfolio-header" style="border-radius: 20px; display: flex; align-items: center; width: 95%; max-width: 1400px;">
    <a href="/" class="header-logo" style="display: flex; align-items: center; gap: 12px; text-decoration: none; flex-shrink: 0;">
        <img src="<?= $favicon ?>" width="44" height="44" alt="<?= htmlspecialchars($siteName) ?> Icon" style="border-radius: 8px; object-fit: contain;">
        <div style="display: flex; flex-direction: column; line-height: 1.1;">
            <span style="font-size: 1.2rem; font-weight: 800; color: white; text-transform: uppercase; letter-spacing: 0.5px;">Tom <span style="color: var(--primary-color);">Labs</span></span>
            <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 500;">Virtual Innovation Hub</span>
        </div>
    </a>
    
    <button class="nav-toggle-button" id="navToggle" aria-label="Toggle navigation">
        <i class='bx bx-menu'></i>
    </button>

    <div class="header-nav-container" id="navContainer" style="flex: 1; justify-content: center;">
        <nav class="header-nav" style="display: flex; gap: 2.5rem; align-items: center;">
            <a href="/" class="nav-link" style="font-weight: 600;">Home</a>
            <a href="/about" class="nav-link" style="font-weight: 600;">About us</a>
            <a href="/dashboard" class="nav-link" style="font-weight: 600;">Lab Listing</a>
            <a href="/#contact" class="nav-link" style="font-weight: 600;">Contact us</a>
            <a href="https://blog.tomweb.fun/" class="nav-link" style="font-weight: 600;">Blog</a>
        </nav>
    </div> 
    
    <div class="header-right-actions" style="flex-shrink: 0;">
        <?php if ($isLoggedIn): ?>
            <div class="user-profile logged-in-public" id="userProfileToggle" style="cursor: pointer; position: relative;">
                <div class="avatar-container" style="background: rgba(255, 255, 255, 0.08); border-radius: 999px; padding: 4px 12px 4px 4px; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255, 255, 255, 0.05); transition: background 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'">
                    <img src="<?= Session::getAvatar(); ?>" alt="User Avatar" class="avatar" style="width: 32px; height: 32px; border-radius: 50%; border: none; object-fit: cover; <?= Session::getAvatarStyle(); ?>">
                    <i class='bx bx-chevron-down' style="color: #cbd5e1; font-size: 1.2rem;"></i>
                </div>
                
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header" style="padding: 10px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 5px;">
                        <h4 style="margin: 0; font-size: 0.9rem; color: white; text-align: left;"><?php echo htmlspecialchars(Session::getUser()?->getUsername() ?? 'Guest'); ?></h4>
                    </div>
                    <ul class="dropdown-menu-list" style="list-style: none; padding: 5px; margin: 0;">
                        <li>
                            <a href="/dashboard" style="color: #cbd5e1; display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.color='#f97316'; this.style.background='rgba(255,255,255,0.05)';" onmouseout="this.style.color='#cbd5e1'; this.style.background='transparent';">
                                <i class='bx bxs-dashboard'></i> 
                                <span>Labs</span>
                            </a>
                        </li>
                        <li>
                            <a href="/logout" style="color: #cbd5e1; display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.color='#ef4444'; this.style.background='rgba(255,255,255,0.05)';" onmouseout="this.style.color='#cbd5e1'; this.style.background='transparent';">
                                <i class='bx bx-log-out'></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <a href="/signin" class="login-button" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 999px; padding: 0.5rem 1.5rem; transition: all 0.3s; color: white; text-decoration: none; font-weight: 600;" onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'">
                Login
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="page page-wide">
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

    <div class="contact-strip" id="contact">
        <div>
            <h2>Get in touch</h2>
            <p>Questions, help, collaboration, or you think the contact icons are trying too hard. We're listening. Usually within a day (coffee permitting).</p>
        </div>
        <div class="contact-actions vertical">
            <a class="contact-card icon-only" href="mailto:sathishp4223@gmail.com" aria-label="Email">
                <span class="cc-icon glow"><i class='bx bx-envelope'></i></span>
            </a>
            <a class="contact-card icon-only" href="https://github.com/sathish46-lab/tom-cloud-labs" target="_blank" rel="noopener" aria-label="GitHub">
                <span class="cc-icon glow"><i class='bx bxl-github'></i></span>
            </a>
        </div>
    </div>
</div>

<footer>
    <div class="footer-wrap">
        <div class="footer-brand">
            <div class="logo" style="font-size: 1.1rem;">
                <img src="<?= $logo ?>" alt="<?= htmlspecialchars($siteName) ?>" style="width:28px;height:28px;">
                <span><?= htmlspecialchars($siteName) ?> <em>Labs</em></span></div>
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
    // Mobile nav toggle (same as landing)
    const navToggle = document.getElementById('navToggle');
    const navContainer = document.getElementById('navContainer');
    if (navToggle && navContainer) {
        navToggle.addEventListener('click', () => navContainer.classList.toggle('open'));
        navContainer.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => navContainer.classList.remove('open'));
        });
    }

    // Profile dropdown click toggle
    const userProfile = document.querySelector('.user-profile');
    if (userProfile) {
        userProfile.addEventListener('click', function (e) {
            if (e.target.closest('.profile-dropdown')) return;
            this.classList.toggle('active');
        });
        document.addEventListener('click', function (e) {
            if (!userProfile.contains(e.target)) userProfile.classList.remove('active');
        });
    }
</script>

</body>
</html>
