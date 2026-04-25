<?php
require_once 'src/load.php';

// 1. If already logged in, go straight to dashboard
if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN) {
    header("Location: app/dashboard.php");
    exit;
}

// 2. Set Page Metadata
Session::$pageTitle = "Tom Labs | Automated Lab Environments";
define('IS_LANDING_PAGE', true);
Session::addCustomCss('/css/landing.css');

ob_start();
?>
<body class="page-portfolio"> 

<!-- <header class="portfolio-header">
    <a href="/auth/signin.php" class="header-logo">
        Tom Labs
    </a>
    
    <button class="nav-toggle-button" id="navToggle">
        </button>

    <div class="header-nav-container" id="navContainer">
        <nav class="header-nav">
            <a href="/" class="nav-link">Home</a>
            <a href="#features" class="nav-link">About us</a>
            <a href="https://blog.tomweb.fun/" class="nav-link">Blog</a>
        </nav>
        </div> 
    
    <div class="header-right-actions">
        <?php if (isset($_SESSION['user_id'])): 
            // Logic to set avatar path (must be available here)
            $userAvatar = (!empty($_SESSION['user_avatar'])) ? htmlspecialchars($_SESSION['user_avatar']) : '/assets/avatars/default.png';
        ?>
            <div class="user-profile logged-in-public" id="userProfileToggle">
                
                <div class="avatar-container">
                    <img src="<?= $userAvatar; ?>" alt="User Avatar" class="avatar">
                </div>
                
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <h4><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></h4>
                    </div>
                    <ul class="dropdown-menu-list">
                        <li>
                            <a href="/dashboard">
                                <i class='bx bxs-dashboard'></i> 
                                <span>Labs</span>
                            </a>
                        </li>
                        
                        <li>
                            <a href="/logout">
                                <i class='bx bx-log-out'></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        <?php else: ?>
            <a href="/auth/signin.php" class="login-button">
                Login
            </a>
        <?php endif; ?>
    </div>
</header> -->

<header class="portfolio-header">
    <a href="/" class="header-logo">Tom Labs</a>
    
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
                <div class="avatar-container">
                    <img src="<?= $userAvatar ?>" alt="Profile" class="avatar">
                </div>
                <div class="profile-dropdown">
                    <a href="<?= Session::url('dashboard') ?>">Dashboard</a>
                    <a href="<?= Session::url('logout') ?>">Logout</a>
                </div>
            </div>

        <?php else: ?>
            <a href="<?= Session::url('signin') ?>" class="login-button">Login</a>
            
        <?php endif; ?>
    </div>
</header>


    <section class="hero-section-wrapper">
        <div class="hero-bg"></div>
        
            
        <div class="hero-container">
            <div class="hero-content-left">
                <h1 class="hero-heading">
                    Innovate Fearlessly - <span style="color:var(--primary-color);">Your Virtual Innovation Hub</span>
                </h1>
                <p class="hero-lead">
                    ⭐ Empowering developers and innovators with a world-class digital lab experience.
                </p>
                <p class="hero-lead">
                    Credit goes to:
                    <span
                        id="multi-link"
                        style="color: Orange; cursor: pointer;">
                        SELFMADE NINJA ACADEMY
                    </span>
                </p>
                <div class="hero-button-group">
                    <a href="/feature" class="btn btn-primary">
                        Learn More
                    </a>
                    <a href="/signup" class="btn btn-secondary">
                        Try For Free →
                    </a>
                </div>
            </div>

            <div class="hero-image-container">
                <div class="floating-tech-wrapper">
                    <div class="bg-circle-lg"></div>
                    <div class="bg-circle-md"></div>

                    <div class="floating-box box-1"></div>
                    <div class="floating-box box-2"></div>
                    <div class="floating-box box-3"></div>
                    <div class="floating-box box-4"></div>

                    <div class="floating-sphere sphere-1"></div>
                    <div class="floating-sphere sphere-2"></div>
                    <div class="floating-sphere sphere-3"></div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="feature-section" id="features">
        <div class="feature-heading-group">
            <h2 class="feature-heading">
                🚀 Explore <span style="color:var(--primary-color);">Next-Gen</span> Capabilities
            </h2>
            <p class="feature-lead">
               Unlock a powerful set of tools designed for effortless development 
               and secure experimentation. From rapid virtual lab creation to dynamic 
               GUI-based environments, experience a smarter, faster, and more connected way to innovate.
            </p>
        </div>
        
        <div class="feature-content-grid">
            <div class="feature-list-column">
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 0-7.3 16.7c1.7 1.4 3.7 2.3 6 2.3s4.3-.9 6-2.3A10 10 0 0 0 12 2z"></path><path d="M8 13s2-2 4-2 4 2 4 2"></path></svg>
                    <div>
                        <h3 class="feature-title">Smart Cloud Infrastructure for Developers</h3>
                        <p class="feature-description"> 
                            Experience a powerful and scalable cloud ecosystem built 
                            to support your projects — featuring real-time synchronization, 
                            encrypted data storage, and seamless server environment management for modern 
                            learning and innovation.
                        </p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <div>
                        <h3 class="feature-title">Instant & Effortless Deployment</h3>
                        <p class="feature-description">
                            Launch or reset your servers in just one click — start 
                            fresh projects instantly and restore environments to their 
                            default state anytime with ease and reliability.
                        </p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 13l-3 3-3-3"></path><path d="M15 11l-3-3-3 3"></path><path d="M12 2v20"></path></svg>
                    <div>
                        <h3 class="feature-title">All-in-One Development Environment</h3>
                        <p class="feature-description">
                            Work seamlessly with a suite of pre-configured 
                            tools and frameworks — code, test, 
                            and deploy applications instantly without manual setup,
                             all within your secure virtual server workspace.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="feature-cards-column">
                <div class="feature-card">
                    <div class="feature-card-image">
                        <img src="/assets/images/portfolio/lab.svg" alt="Code Arena Interface">
                    </div>
                    <h3 class="feature-card-title">Virtual Lab</h3>
                    <p class="feature-description">The Virtual Cloud Lab is a secure, cloud-based development
                         and testing environment that allows users to access their workspaces 
                        from anywhere through an encrypted VPN connection. It is designed for developers, students, 
                        and cybersecurity learners who need a 
                        flexible, always-available virtual lab with high-level data protection and isolation.</p>
                    <a href="/signin" class="btn btn-primary" style="align-self: flex-start;">Explore</a>
                </div>
            </div>
        </div>
        
        <div class="feature-content-grid" style="margin-top: 5rem;">
            <div class="feature-cards-column">
                <div class="feature-card">
                    <div class="feature-card-image">
                        <img src="/assets/images/portfolio/domain.svg" alt="Spot Quiz Interface">
                    </div>
                    <h3 class="feature-card-title">🌍 Domain Development</h3>
                    <p class="feature-description">
                        This module enables users to develop, host, and publish their own web 
                        applications with fully customizable domains directly from the virtual lab environment. 
                        It integrates a secure backend with automated deployment tools to simplify domain 
                        configuration, SSL setup, and live hosting — all managed within a private cloud infrastructure.</p>
                    <a href="/portfolio" class="btn btn-primary" style="align-self: flex-start;">Explore</a>
                </div>
            </div>
            
            <div class="feature-list-column">
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8"></path><path d="M12 8v8"></path></svg>
                    <div>
                        <h3 class="feature-title">Isolated Environments</h3>
                        <p class="feature-description">Each server operates in a fully isolated environment, ensuring data privacy and security.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15s4-2 8-2 8 2 8 2V8s-4 2-8 2-8-2-8-2z"></path><path d="M3 15v-7"></path><path d="M21 15v-7"></path><path d="M12 21V12"></path><path d="M12 12V3"></path></svg>
                    <div>
                        <h3 class="feature-title">Real-Time Monitoring</h3>
                        <p class="feature-description">Monitor live metrics such as CPU, RAM, and IO usage directly on your server's dashboard for full performance visibility.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12h18"></path><path d="M12 3v18"></path>
                    </svg>
                    <div>
                        <h3 class="feature-title">Domain Development & Custom Deployment</h3>
                        <p class="feature-description">
                            Build, host, and publish your own web applications with full custom domain integration, 
                            automated SSL setup, and one-click deployment — all managed securely from the virtual cloud lab.
                        </p>
                    </div>
                </div>

            </div>
        </div>
        <div class="feature-content-grid" style="margin-top: 5rem;">
            <div class="feature-cards-column">
                <div class="feature-card" id="confetti-target">
                    <div class="feature-card-image">
                        <img src="/assets/images/portfolio/services.svg" alt="Spot Quiz Interface">
                    </div>
                    <h3 class="feature-card-title">Database Services & Management</h3>
                    <p class="feature-description">
                        Launch and manage dedicated database instances including MySQL and PostgreSQL 
                        with integrated <b>Adminer</b> access for real-time administration. 
                        Seamlessly create, configure, and monitor databases — all secured within the virtual lab environment.
                    </p>
                    <a href="#" class="btn btn-primary" style="align-self: flex-start;">Explore</a>
                </div>
            </div>
            
            <div class="feature-list-column">
                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                        stroke-linecap="round" stroke-linejoin="round">
                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                        <path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"></path>
                    </svg>
                    <div>
                        <h3 class="feature-title">Secure Database Access</h3>
                        <p class="feature-description">
                            Seamlessly connect to MySQL and PostgreSQL databases from your virtual Ubuntu lab 
                            through a secure VPN tunnel, ensuring encrypted and private data communication.
                        </p>
                    </div>
                </div>

                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16v16H4z"></path>
                        <path d="M9 4v16"></path>
                        <path d="M15 10h5"></path>
                    </svg>
                    <div>
                        <h3 class="feature-title">Unlimited Database Creation</h3>
                        <p class="feature-description">
                            Easily create and manage unlimited MySQL or PostgreSQL databases 
                            directly from the virtual cloud interface — with full control and isolation per project.
                        </p>
                    </div>
                </div>

                <div class="feature-item">
                    <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12l9-9 9 9"></path>
                        <path d="M9 21V9h6v12"></path>
                    </svg>
                    <div>
                        <h3 class="feature-title">Database Integration for Projects</h3>
                        <p class="feature-description">
                            Use your hosted databases directly in development projects to power 
                            web applications and deployed websites with real-time, reliable backend connectivity.
                        </p>
                    </div>
                </div>


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
                        <img src="/assets/images/portfolio/ticker/Sibidharan.png" alt="Sibidharan" class="card-img-avatar">
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
                        
                        <img src="/assets/images/portfolio/ticker/Anish.jpeg" alt="AnishKumar" class="card-img-avatar">
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
                        <img src="/assets/images/portfolio/ticker/Sna.jpeg" alt="Selfmade Ninja Academy" class="card-img-avatar">
                    </div>
                    <h4>Selfmade Ninja Academy</h4>
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
                            <a href="https://selfmade.ninja" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/company/selfmade-ninja-academy" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-rocket' style="color: #4ade80;"></i>
                        <h3>The Result</h3>
                        <img src="/assets/images/portfolio/ticker/TomLabs.png" alt="Tom Labs" class="card-img-avatar">
                    </div>
                    <h4>Tom Labs</h4>
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
                        <img src="/assets/images/portfolio/ticker/Sibidharan.png" alt="Sibidharan" class="card-img-avatar">
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
                        <img src="/assets/images/portfolio/ticker/Anish.jpeg" alt="AnishKumar" class="card-img-avatar">
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
                        <img src="/assets/images/portfolio/ticker/Sna.jpeg" alt="Selfmade Ninja Academy" class="card-img-avatar">
                    </div>
                    <h4>Selfmade Ninja Academy</h4>
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
                            <a href="https://selfmade.ninja" target="_blank" class="social-btn" title="Website">
                                <i class='bx bx-world'></i>
                            </a>
                            <a href="https://www.linkedin.com/company/selfmade-ninja-academy" target="_blank" class="social-btn" title="LinkedIn">
                                <i class='bx bxl-linkedin'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ticker-card">
                    <div class="card-icon">
                        <i class='bx bxs-rocket' style="color: #4ade80;"></i>
                        <h3>The Result</h3>
                        <img src="/assets/images/portfolio/ticker/TomLabs.png" alt="Tom Labs" class="card-img-avatar">
                    </div>
                    <h4>Tom Labs</h4>
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
        console.log('DOM Content Loaded. Scroll-triggered animation active.');
    
        
        // --- RESTORE SCROLL TRIGGER ---
        const confettiTriggerElement = document.getElementById('confetti-target');
        
        // Use Intersection Observer for a smooth, single-fire triggered animation
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

        if (confettiTriggerElement) {
            observer.observe(confettiTriggerElement);
        }
    });
    document.getElementById("multi-link").addEventListener("click", function () {
    // Opens both sites in new tabs
    window.open("https://labs.selfmade.ninja/", "_blank");
    window.open("https://selfmade.ninja/", "_blank");
  });
</script>
<?php
Session::set('page_content', ob_get_clean());
Session::loadMaster();