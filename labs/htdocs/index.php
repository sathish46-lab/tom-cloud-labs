<?php
require_once 'src/load.php';

// Allow both logged-in and guest users to view the landing page.
$isSessionExpired = (isset($_GET['session_expired']) && $_GET['session_expired'] == '1') || 
                    !empty($_COOKIE['show_session_expired']) || 
                    !empty($_SESSION['show_session_expired']);

if ($isSessionExpired) {
    if (!empty($_COOKIE['show_session_expired'])) {
        setcookie('show_session_expired', '', time() - 3600, '/');
    }
    if (!empty($_SESSION['show_session_expired'])) {
        unset($_SESSION['show_session_expired']);
    }
    Session::$pageTitle = "Authentication Required | " . Session::siteName();
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
    <body style="margin:0; padding:0; background:#0f172a;">
    <?php
    include __DIR__ . '/src/template/_session_expired_popup.php';
    ?>
    </body>
    </html>
    <?php
    exit;
}

// 2. Set Page Metadata
Session::$pageTitle = Session::siteName() . " | Virtual Innovation Hub";
Session::set('seo_description', Session::siteName() . ' is a premier virtual innovation hub offering advanced cloud environments, scalable VPS, and secure VPN workspaces for elite developers and engineers.');
Session::set('seo_keywords', 'Virtual Innovation Hub, ' . Session::siteName() . ', Advanced Cloud Infrastructure, Digital Workspace, VPS, Developer Environment');
define('IS_LANDING_PAGE', true);
Session::addCustomCss('/assets/css/landing.css');

// Initialize random quote for SEO rendering
$heroQuotes = [
    [ "p1" => "Innovate Fearlessly", "p2" => "Your Virtual Innovation Hub" ],
    [ "p1" => "Code Without Limits", "p2" => "The Ultimate Cloud IDE" ],
    [ "p1" => "Deploy in Seconds", "p2" => "Next-Gen Infrastructure" ],
    [ "p1" => "Secure by Design", "p2" => "Isolated Lab Environments" ],
    [ "p1" => "Master the Cloud", "p2" => "Your Technical Playground" ]
];
$initialQuote = $heroQuotes[array_rand($heroQuotes)];
?>
<!DOCTYPE html>
<html lang="en" data-coreui-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="description" content="<?= Session::get('seo_description') ?>">
    <meta name="keywords" content="<?= Session::get('seo_keywords') ?>">
    <title><?= Session::$pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= Session::favicon() ?>">
    
    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Landing Page CSS -->
    <link rel="stylesheet" href="/assets/css/landing.css?v=<?= time() ?>">
    <style>
        body { margin: 0; padding: 0; background-color: #0a1220; color: #e8eef7; font-family: 'Space Grotesk', sans-serif; overflow-x: hidden; }
        a { text-decoration: none; }
    </style>
</head>
<body>
<div class="page-portfolio" style="width: 100%;">

<header class="portfolio-header" style="border-radius: 20px; display: flex; align-items: center; width: 95%; max-width: 1400px;">
    <a href="/" class="header-logo" style="display: flex; align-items: center; gap: 12px; text-decoration: none; flex-shrink: 0;">
        <img src="<?= Session::favicon() ?>" width="44" height="44" alt="<?= htmlspecialchars(Session::siteName()) ?> Icon" style="border-radius: 8px; object-fit: contain;">
        <div style="display: flex; flex-direction: column; line-height: 1.1;">
            <span style="font-size: 1.2rem; font-weight: 800; color: white; text-transform: uppercase; letter-spacing: 0.5px;">Tom <span style="color: var(--primary-color);">Labs</span></span>
            <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 500;">Virtual Innovation Hub</span>
        </div>
    </a>
    
    <button class="nav-toggle-button" id="navToggle">
        <i class='bx bx-menu'></i>
    </button>

    <div class="header-nav-container" id="navContainer" style="flex: 1; justify-content: center;">
        <nav class="header-nav" style="display: flex; gap: 2.5rem; align-items: center;">
            <a href="/" class="nav-link active" style="font-weight: 600;">Home</a>
            <a href="#features" class="nav-link" style="font-weight: 600;">About us</a>
            <a href="/dashboard" class="nav-link" style="font-weight: 600;">Lab Listing</a>
            <a href="#contact" class="nav-link" style="font-weight: 600;">Contact us</a>
            <a href="https://blog.tomweb.fun/" class="nav-link" style="font-weight: 600;">Blog</a>
        </nav>
    </div> 
    
    <div class="header-right-actions" style="flex-shrink: 0;">
        <?php if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN): ?>
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

<!-- <header class="portfolio-header">
    <a href="/" class="header-logo" style="gap: 12px;">
        <img src="/assets/logo/logo.png" width="44" height="44" alt="<?= htmlspecialchars(Session::siteName()) ?> Icon" style="border-radius: 8px;">
        <span style="font-size: 1.5rem;"><?= htmlspecialchars(Session::siteName()) ?></span>
    </a>
    
    <div class="header-nav-container">
        <nav class="header-nav">
            <a href="/" class="nav-link">Home</a>
            <a href="#features" class="nav-link">About us</a>
        </nav>
    </div> 

    <div class="header-right-actions">
        <?php 
        // Use Session class to determine header type
        if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN): 
            $user = Session::getUser(); //
            $userAvatar = $user ? $user->getAvatarUrl() : '/assets/avatars/default.png';
        ?>
            <div class="user-profile">
                <div class="avatar-container d-flex align-items-center" style="background: rgba(255, 255, 255, 0.1); border-radius: 999px; padding: 4px 12px 4px 4px; gap: 8px;">
                    <img src="<?= $userAvatar ?>" alt="Profile" class="avatar" style="width: 32px; height: 32px; border: none;">
                    <i class='bx bx-chevron-down text-white' style="font-size: 1.2rem; opacity: 0.8;"></i>
                </div>
                <div class="profile-dropdown">
                    <div class="dropdown-header d-flex align-items-center justify-content-center" style="padding: 0 16px 8px 16px; margin-bottom: 4px;">
                        <span class="fw-bold text-white" style="letter-spacing: 0.5px; font-size: 0.85rem;"><?= htmlspecialchars($user->getUserName()) ?></span>
                    </div>
                    <a href="<?= Session::url('dashboard') ?>"><i class='bx bx-tachometer'></i> Labs</a>
                    <a href="#"><i class='bx bx-shield-alt-2'></i> My Account</a>
                    <a href="<?= Session::url('logout') ?>"><i class='bx bx-log-in-circle' style="transform: scaleX(-1);"></i> Logout</a>
                </div>
            </div>

        <?php else: ?>
            <a href="<?= Session::url('signin') ?>" class="login-button">Login</a>
            
        <?php endif; ?>
    </div>
</header> -->


    <section class="hero-section-wrapper" id="home">
        <div class="hero-grid-bg" aria-hidden="true"></div>

        <div class="hero-container">
            <div class="hero-content-left">
                <div class="hero-status-pill mono" aria-hidden="true">
                    <span class="status-dot"></span>
                    <span>Labs online</span>
                </div>
                <h1 class="hero-heading" id="dynamic-hero-heading" style="transition: opacity 0.3s ease-in-out;">
                    <span id="hero-part1"><?= htmlspecialchars($initialQuote['p1']) ?></span> — <span id="hero-part2" class="hero-accent"><?= htmlspecialchars($initialQuote['p2']) ?></span>
                </h1>
                <p class="hero-lead">
                    Empowering developers and innovators with a world-class digital lab experience.
                </p>
                <div class="hero-credits-container">
                    <a href="https://sathish46.in" target="_blank" rel="noopener" class="credit-chip-link">
                        <i class='bx bx-code-alt'></i>
                        <span>Engineered by</span>
                        <span class="chip-name">Sathish46</span>
                    </a>
                    <a id="multi-link" href="https://labs.selfmade.ninja" target="_blank" rel="noopener" class="credit-chip" role="button" tabindex="0">
                        <i class='bx bxs-graduation'></i>
                        <span>Credits to</span>
                        <span class="chip-name">selfmade.ninja Academy</span>
                    </a>
                </div>
                <div class="hero-button-group">
                    <a href="/about" class="btn btn-primary">
                        Learn More <i class='bx bx-right-arrow-alt'></i>
                    </a>
                    <a href="/signup" class="btn btn-secondary">
                        Try For Free
                    </a>
                </div>
            </div>

            <div class="hero-image-container">
                <div class="lab-scene" aria-hidden="true">
                    <div class="lab-rack">
                        <div class="rack-frame">
                            <div class="rack-brand">
                                <span>Tom Labs · Rack 01</span>
                                <span class="rack-live">Live</span>
                            </div>
                            <div class="rack-unit">
                                <span class="led led-live"></span>
                                <span class="unit-label">lab-01 · ubuntu-22.04</span>
                                <span class="unit-bars"><i></i><i></i><i></i></span>
                            </div>
                            <div class="rack-unit">
                                <span class="led led-live"></span>
                                <span class="unit-label">lab-02 · vpn-tunnel up</span>
                                <span class="unit-bars"><i></i><i></i><i></i></span>
                            </div>
                            <div class="rack-unit">
                                <span class="led led-amber"></span>
                                <span class="unit-label">db-01 · postgres-16</span>
                                <span class="unit-bars"><i></i><i></i><i></i></span>
                            </div>
                            <div class="rack-unit">
                                <span class="led led-live"></span>
                                <span class="unit-label">db-02 · mysql-8.0</span>
                                <span class="unit-bars"><i></i><i></i><i></i></span>
                            </div>
                            <div class="rack-unit">
                                <span class="led led-idle"></span>
                                <span class="unit-label">edge-01 · tls-active</span>
                                <span class="unit-bars"><i></i><i></i><i></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="metric-chip chip-1">
                        <span class="chip-key">CPU</span>
                        <span class="chip-val info">42%</span>
                    </div>
                    <div class="metric-chip chip-2">
                        <span class="chip-key">VPN</span>
                        <span class="chip-val ok">UP</span>
                    </div>
                    <div class="metric-chip chip-3">
                        <span class="chip-key">TLS</span>
                        <span class="chip-val warn">VALID</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="feature-section" id="features">
        <div class="feature-heading-group">
            <h2 class="feature-heading">
                Explore <span class="accent">Next-Gen</span> Capabilities
            </h2>
            <p class="feature-lead">
               Unlock a powerful set of tools designed for effortless development 
               and secure experimentation. From rapid virtual lab creation to dynamic 
               GUI-based environments, experience a smarter, faster, and more connected way to innovate.
            </p>
        </div>

        <div class="pillar-grid">
            <article class="pillar-card pillar-featured">
                <div class="pillar-media">
                    <img src="<?= Session::cdn3('main/lab.svg') ?>" alt="Virtual Lab">
                </div>
                <div class="pillar-body">
                    <span class="pillar-tag">01 / Virtual Lab</span>
                    <h3>Secure cloud workspaces, anywhere</h3>
                    <p>The Virtual Cloud Lab is a secure, cloud-based development
                         and testing environment that allows users to access their workspaces 
                        from anywhere through an encrypted VPN connection. It is designed for developers, students, 
                        and cybersecurity learners who need a 
                        flexible, always-available virtual lab with high-level data protection and isolation.</p>
                    <a href="/signin" class="btn btn-primary">Explore <i class='bx bx-right-arrow-alt'></i></a>
                </div>
            </article>

            <article class="pillar-card">
                <div class="pillar-media">
                    <img src="<?= Session::cdn3('main/domain.svg') ?>" alt="Domain Development">
                </div>
                <div class="pillar-body">
                    <span class="pillar-tag">02 / Domains</span>
                    <h3>Domain Development</h3>
                    <p>This module enables users to develop, host, and publish their own web 
                        applications with fully customizable domains directly from the virtual lab environment. 
                        It integrates a secure backend with automated deployment tools to simplify domain 
                        configuration, SSL setup, and live hosting — all managed within a private cloud infrastructure.</p>
                    <a href="/dashboard" class="btn btn-primary">Explore <i class='bx bx-right-arrow-alt'></i></a>
                </div>
            </article>

            <article class="pillar-card" id="confetti-target">
                <div class="pillar-media">
                    <img src="<?= Session::cdn3('main/services.svg') ?>" alt="Database Services">
                </div>
                <div class="pillar-body">
                    <span class="pillar-tag">03 / Databases</span>
                    <h3>Database Services &amp; Management</h3>
                    <p>Launch and manage dedicated database instances including MySQL and PostgreSQL 
                        with integrated <b>Adminer</b> access for real-time administration. 
                        Seamlessly create, configure, and monitor databases — all secured within the virtual lab environment.</p>
                    <a href="/dashboard" class="btn btn-primary">Explore <i class='bx bx-right-arrow-alt'></i></a>
                </div>
            </article>
        </div>

        <div class="capability-grid">
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 0-7.3 16.7c1.7 1.4 3.7 2.3 6 2.3s4.3-.9 6-2.3A10 10 0 0 0 12 2z"></path><path d="M8 13s2-2 4-2 4 2 4 2"></path></svg>
                <h4>Smart Cloud Infrastructure for Developers</h4>
                <p>Experience a powerful and scalable cloud ecosystem built to support your projects — featuring real-time synchronization, encrypted data storage, and seamless server environment management for modern learning and innovation.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                <h4>Instant &amp; Effortless Deployment</h4>
                <p>Launch or reset your servers in just one click — start fresh projects instantly and restore environments to their default state anytime with ease and reliability.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 13l-3 3-3-3"></path><path d="M15 11l-3-3-3 3"></path><path d="M12 2v20"></path></svg>
                <h4>All-in-One Development Environment</h4>
                <p>Work seamlessly with a suite of pre-configured tools and frameworks — code, test, and deploy applications instantly without manual setup, all within your secure virtual server workspace.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8"></path><path d="M12 8v8"></path></svg>
                <h4>Isolated Environments</h4>
                <p>Each server operates in a fully isolated environment, ensuring data privacy and security.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15s4-2 8-2 8 2 8 2V8s-4 2-8 2-8-2-8-2z"></path><path d="M3 15v-7"></path><path d="M21 15v-7"></path><path d="M12 21V12"></path><path d="M12 12V3"></path></svg>
                <h4>Real-Time Monitoring</h4>
                <p>Monitor live metrics such as CPU, RAM, and IO usage directly on your server's dashboard for full performance visibility.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"></path><path d="M12 3v18"></path></svg>
                <h4>Domain Development &amp; Custom Deployment</h4>
                <p>Build, host, and publish your own web applications with full custom domain integration, automated SSL setup, and one-click deployment — all managed securely from the virtual cloud lab.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"></path></svg>
                <h4>Secure Database Access</h4>
                <p>Seamlessly connect to MySQL and PostgreSQL databases from your virtual Ubuntu lab through a secure VPN tunnel, ensuring encrypted and private data communication.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"></path><path d="M9 4v16"></path><path d="M15 10h5"></path></svg>
                <h4>Unlimited Database Creation</h4>
                <p>Easily create and manage unlimited MySQL or PostgreSQL databases directly from the virtual cloud interface — with full control and isolation per project.</p>
            </div>
            <div class="cap-tile">
                <svg class="cap-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"></path><path d="M9 21V9h6v12"></path></svg>
                <h4>Database Integration for Projects</h4>
                <p>Use your hosted databases directly in development projects to power web applications and deployed websites with real-time, reliable backend connectivity.</p>
            </div>
        </div>
    </section>

    <section class="ticker-section">
        <div class="feature-heading-group">
            <h2 class="feature-heading">
                Built on <span style="color:var(--primary-color);">Great Foundations</span>
            </h2>
            <p class="feature-lead">
                Honoring the mentors, guides, and Academy that shaped this journey.
            </p>
        </div>

        <div class="ticker-container">
            <div class="ticker-track">
                
                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-bulb' style="color: #facc15;"></i> 
                        <h3>The Visionary</h3>
                        <img src="<?= Session::cdn3('main/ticker/Sibidharan.png') ?>" alt="Sibidharan" class="card-img-avatar">
                    </div>

                    <h4>Sibidharan Nandhakumar</h4>
                    <p>My Tech Guru and ultimate inspiration. I am building this today because of the knowledge and path he showed me.</p>
                    
                    <div class="card-action-row">
                        
                        <div class="card-stats">
                            <div class="stat">
                                <span class="stat-val" style="color: #facc15;">100%</span>
                                <span class="stat-label">Inspiration</span>
                            </div>
                            <div class="stat">
                                <span class="stat-val" style="color: #ffffff;">∞</span>
                                <span class="stat-label">Knowledge</span>
                            </div>
                        </div>

                        <div class="card-socials">
                            <a href="https://sibidharan.me/" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/in/sibidharan/" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>

                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-buoy' style="color: #38bdf8;"></i>
                        <h3>The Solver</h3>
                        
                        <img src="<?= Session::cdn3('main/ticker/Anish.jpeg') ?>" alt="AnishKumar" class="card-img-avatar">
                    </div>
                    <h4>Anish Kumar</h4>
                    <p>The pillar of support who never ignores a doubt. From complex logic to the smallest questions, he is always there to help.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #38bdf8;">24/7</span>
                            <span class="stat-label">Support</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #818cf8;">Zero</span>
                            <span class="stat-label">Doubts Left</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://anishkumar.cloud" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i> 
                            </a>
                            <a href="https://www.linkedin.com/in/anishkumar-originals/" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-graduation' style="color: #a78bfa;"></i>
                        <h3>The Forge</h3>
                        <img src="<?= Session::cdn3('main/ticker/Sna.jpeg') ?>" alt="TomCloudLab Academy" class="card-img-avatar">
                    </div>
                    <h4>TomCloudLab Academy</h4>
                    <p>The training ground where I learned everything. A community that transforms curiosity into elite engineering skills.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #c084fc;">Elite</span>
                            <span class="stat-label">Curriculum</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #e879f9;">Real</span>
                            <span class="stat-label">Impact</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://tomweb.in" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://tomweb.in" target="_blank" class="social-btn" title="Website">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-rocket' style="color: #4ade80;"></i>
                        <h3>The Result</h3>
                        <img src="<?= Session::logo() ?>" alt="<?= htmlspecialchars(Session::siteName()) ?>" class="card-img-avatar">
                    </div>
                    <h4><?= htmlspecialchars(Session::siteName()) ?></h4>
                    <p>The outcome of excellent mentorship and learning. A virtual innovation hub built to empower the next generation.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #4ade80;">Ready</span>
                            <span class="stat-label">To Scale</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #22d3ee;">100%</span>
                            <span class="stat-label">Commitment</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://lab.tomweb.fun" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/in/sathish46/" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-bulb' style="color: #facc15;"></i> 
                        <h3>The Visionary</h3>
                        <img src="<?= Session::cdn3('main/ticker/Sibidharan.png') ?>" alt="Sibidharan" class="card-img-avatar">
                    </div>
                    <h4>Sibidharan Nandhakumar</h4>
                    <p>My Tech Guru and ultimate inspiration. I am building this today because of the knowledge and path he showed me.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #facc15;">100%</span>
                            <span class="stat-label">Inspiration</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #ffffff;">∞</span>
                            <span class="stat-label">Knowledge</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://sibidharan.me/" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/in/sibidharan/" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-buoy' style="color: #38bdf8;"></i>
                        <h3>The Solver</h3>
                        <img src="<?= Session::cdn3('main/ticker/Anish.jpeg') ?>" alt="AnishKumar" class="card-img-avatar">
                    </div>
                    <h4>AnishKumar</h4>
                    <p>The pillar of support who never ignores a doubt. From complex logic to the smallest questions, he is always there to help.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #38bdf8;">24/7</span>
                            <span class="stat-label">Support</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #818cf8;">Zero</span>
                            <span class="stat-label">Doubts Left</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://anishkumar.cloud" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/in/anishkumar-originals/" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-graduation' style="color: #a78bfa;"></i>
                        <h3>The Forge</h3>
                        <img src="<?= Session::cdn3('main/ticker/Sna.jpeg') ?>" alt="TomCloudLab Academy" class="card-img-avatar">
                    </div>
                    <h4>TomCloudLab Academy</h4>
                    <p>The training ground where I learned everything. A community that transforms curiosity into elite engineering skills.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #c084fc;">Elite</span>
                            <span class="stat-label">Curriculum</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #e879f9;">Real</span>
                            <span class="stat-label">Impact</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://tomweb.in" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://tomweb.in" target="_blank" class="social-btn" title="Website">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-rocket' style="color: #4ade80;"></i>
                        <h3>The Result</h3>
                        <img src="<?= Session::logo() ?>" alt="<?= htmlspecialchars(Session::siteName()) ?>" class="card-img-avatar">
                    </div>
                    <h4><?= htmlspecialchars(Session::siteName()) ?></h4>
                    <p>The outcome of excellent mentorship and learning. A virtual innovation hub built to empower the next generation.</p>
                    <div class="card-stats">
                        <div class="stat">
                            <span class="stat-val" style="color: #4ade80;">Ready</span>
                            <span class="stat-label">To Scale</span>
                        </div>
                        <div class="stat">
                            <span class="stat-val" style="color: #22d3ee;">100%</span>
                            <span class="stat-label">Commitment</span>
                        </div>
                        <div class="card-socials">
                            <a href="https://lab.tomweb.fun" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/in/sathish46/" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="contact-section" id="contact">
        <div class="contact-inner">
            <div class="contact-copy">
                <h2>Ready to spin up your first lab?</h2>
                <p>VPN access, custom domains, managed databases — or you just want to roast the glowing ping icons. We read every message (after our third coffee). Usually within a day.</p>
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
    </section>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <img src="<?= Session::favicon() ?>" alt="<?= htmlspecialchars(Session::siteName()) ?>" class="fb-logo" width="36" height="36">
                <span class="fb-name">Tom <span class="accent">Labs</span></span>
                <span class="fb-tag mono">Virtual Innovation Hub</span>
            </div>
            <nav class="footer-links" aria-label="Footer">
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/dashboard">Lab Listing</a>
                <a href="/terms">Terms</a>
                <a href="/privacy">Privacy</a>
                <a href="/cookies">Cookies</a>
            </nav>
            <p class="footer-note">&copy; <?= date('Y') ?> Tom Labs. Built for engineers.</p>
        </div>
    </footer>


<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.js"></script>

<script>
    // --- CONFETTI FUNCTION (Enhanced parameters) ---
    function launchConfetti(originX) {
        confetti({
            particleCount: 200,     // Increased particles
            spread: 120,            // Increased spread
            startVelocity: 60,      // Increased speed for higher launch
            origin: { 
                x: originX,         
                y: 0.5              // Launch from the middle height of the screen
            },
            colors: ['#f47923', '#ffffff', '#000000', '#ea580c']
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // --- RESTORE SCROLL TRIGGER ---
        const confettiTriggerElement = document.getElementById('confetti-target');
        
        // Use Intersection Observer for a smooth, single-fire triggered animation
        if (confettiTriggerElement && !prefersReducedMotion) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    // If the element is intersecting (in view) AND hasn't fired yet
                    if (entry.isIntersecting && !entry.target.classList.contains('confetti-fired')) {
                        // Launch from the left and right
                        launchConfetti(0.25); 
                        launchConfetti(0.75); 
                        entry.target.classList.add('confetti-fired'); // Mark as fired
                        observer.unobserve(entry.target); // Stop watching the element
                    }
                });
            }, { threshold: 0.2 }); // Triggers when 20% of the element is visible
            observer.observe(confettiTriggerElement);
        }

        // --- PROFILE DROPDOWN CLICK TOGGLE ---
        const userProfile = document.querySelector('.user-profile');
        if (userProfile) {
            userProfile.addEventListener('click', function(e) {
                // If the user is clicking a link inside the dropdown, don't stop it
                if (e.target.closest('.profile-dropdown')) return;
                this.classList.toggle('active');
            });
            
            // Close dropdown if clicking outside
            document.addEventListener('click', function(e) {
                if (!userProfile.contains(e.target)) {
                    userProfile.classList.remove('active');
                }
            });
        }

        // --- MOBILE NAV TOGGLE ---
        const navToggle = document.getElementById('navToggle');
        const navContainer = document.getElementById('navContainer');
        if (navToggle && navContainer) {
            navToggle.addEventListener('click', () => {
                navContainer.classList.toggle('open');
            });
            navContainer.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => navContainer.classList.remove('open'));
            });
        }
    });

    // --- MULTI-LINK CREDIT CHIP (native anchor: labs.selfmade.ninja) ---

  // --- DYNAMIC HERO QUOTES VIA API (5s cadence) ---
  const headingContainer = document.getElementById("dynamic-hero-heading");
  const part1Element = document.getElementById("hero-part1");
  const part2Element = document.getElementById("hero-part2");

  if (headingContainer && part1Element && part2Element && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setInterval(() => {
          fetch('/api_quote.php')
              .then(response => response.json())
              .then(data => {
                  // Fade out
                  headingContainer.style.opacity = 0;
                  
                  setTimeout(() => {
                      part1Element.textContent = data.p1;
                      part2Element.textContent = data.p2;
                      
                      // Fade in
                      headingContainer.style.opacity = 1;
                  }, 300); // Wait for fade out to complete
              })
              .catch(err => console.error("Error fetching quote:", err));
      }, 5000);
  }

  // --- SCROLL SPY FOR NAVIGATION ---
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.header-nav .nav-link');

  window.addEventListener('scroll', () => {
      let current = '';
      const scrollY = window.pageYOffset;

      sections.forEach(section => {
          const sectionHeight = section.offsetHeight;
          const sectionTop = section.offsetTop - 150; // offset for fixed header
          if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
              current = section.getAttribute('id');
          }
      });

      if (scrollY < 100) {
          current = 'home'; // Default to home at very top
      }

      navLinks.forEach(link => {
          link.classList.remove('active');
          if (link.getAttribute('href') === '#' + current || (current === 'home' && link.getAttribute('href') === '/')) {
              link.classList.add('active');
          }
      });
  });
</script>
</body>
</html>
