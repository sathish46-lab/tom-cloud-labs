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
