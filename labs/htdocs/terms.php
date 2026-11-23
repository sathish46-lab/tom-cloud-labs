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
