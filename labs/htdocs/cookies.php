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
