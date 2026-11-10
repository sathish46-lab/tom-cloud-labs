<?php
/**
 * Main Loader: Handles environment, vendors, and core libraries.
 */

// Idempotency guard: composer.json's autoload.files also requires this exact
// file while we are mid-execution (reached via our own require of
// vendor/autoload.php), and that path is textually different from callers'
// require_once paths — so without this guard the whole loader (including
// Session::refreshAuthCookies at the bottom) ran twice per request.
if (defined('LABS_LOAD_BOOTSTRAPPED')) {
    return;
}
define('LABS_LOAD_BOOTSTRAPPED', true);

require_once __DIR__ . '/utils/config.php';

// 1. Start session first (without using any classes yet)
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = get_session_lifetime();
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);
    
    // Security: HTTPS-only cookies, prevent JS access, strict mode
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    
    // SE3+S22: Set Secure and SameSite flags before session_start()
    // Behind Cloudflare Flexible SSL, Apache receives HTTP from Cloudflare
    // but the browser connected via HTTPS. Detect Cloudflare via
    // CF-Connecting-IP header to treat the request as HTTPS.
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
               (isset($_SERVER['HTTP_CF_CONNECTING_IP']));
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    
    // Fix Ubuntu Cron Job Session Deletion Bug
    $sessionPath = '/var/cache/labs/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0733, true);
        @chown($sessionPath, 'www-data');
    }
    ini_set('session.save_path', $sessionPath);
    
    // Optional: increase probability to clean up old sessions
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    session_start();
}

// 1.5 Global Request Rate Limiter (Runs early to reject DDoS/flooding without database overhead)
require_once __DIR__ . '/utils/ratelimit.php';

// 1.6 Security Headers (sent before any output)
if (!function_exists('send_security_headers')) {
    function send_security_headers() {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME-type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (restrict browser features)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        
        // Determine WebSocket scheme and host for connect-src
        // Behind Cloudflare Flexible SSL, Apache receives HTTP from Cloudflare
        // but the browser connected via HTTPS. Detect Cloudflare via
        // CF-Connecting-IP header.
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                   (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
                   (isset($_SERVER['HTTP_CF_CONNECTING_IP']));
        $wsScheme = $isHttps ? 'wss:' : 'ws:';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        
        // Content Security Policy (restrictive default, allow self + common CDNs)
        // Note: 'unsafe-inline' required for inline event handlers (onclick, onmouseover, etc.)
        // which cannot use nonces. Modern browsers with nonce support will use nonce
        // for script tags that have it; older/inline scripts still work via unsafe-inline.
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'nonce-$cspNonce' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' $wsScheme $httpHost https:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        
        // Cross-Origin-Opener-Policy: prevent cross-origin windows from interacting with this page
        header('Cross-Origin-Opener-Policy: same-origin');
        
        // Cross-Origin-Embedder-Policy: credentialless allows cross-origin
        // resources without explicit CORP headers to load, avoiding breakage
        // while still isolating the document's browsing context group.
        header('Cross-Origin-Embedder-Policy: credentialless');
        
        // HSTS (only in production HTTPS)
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    send_security_headers();
}

// 2. Load Composer and Libraries FIRST (before using any classes)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/utils/common.php';
require_once __DIR__ . '/utils/currency.php';
require_once __DIR__ . '/utils/storage.php';
require_once __DIR__ . '/utils/errors.php';

// Define the global exception handler BEFORE any DB-dependent code can throw.
if (!function_exists('global_exception_handler')) {
    function global_exception_handler($e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Detect API routes: URL path starts with /api/ or Content-Type is JSON
        $isApi = false;
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($uri, '/api/') === 0 || strpos($contentType, 'application/json') !== false) {
            $isApi = true;
        }

        // Audit first: the raw failure goes to the admin queue, never to the user.
        // Wrapped in try/catch so a down database can never break the error page itself.
        $ref = '';
        if (function_exists('errors_report')) {
            try {
                $email = '';
                try {
                    if (class_exists('Session')) {
                        $u = Session::getUser();
                        if ($u && method_exists($u, 'getEmail')) $email = (string)$u->getEmail();
                    }
                } catch (Throwable $ignored) { /* session may be half-built */ }

                $ref = errors_report([
                    'context'    => $isApi ? 'exception.api' : 'exception.page',
                    'message'    => $e->getMessage(),
                    'user_email' => $email,
                    'extra'      => [
                        'class' => get_class($e),
                        'file'  => $e->getFile(),
                        'line'  => $e->getLine(),
                        'trace' => function_exists('errors_excerpt') ? errors_excerpt($e->getTraceAsString(), 2000) : '',
                    ],
                ]);
            } catch (Throwable $ignored) {
                // Reporting failed (probably DB down) — continue to show the error page.
            }
        }

        $public = function_exists('errors_public')
            ? errors_public($ref)
            : 'Something went wrong on our side. Please try again.';

        if ($isApi) {
            // API routes: always return JSON — never HTML, never internals
            header('Content-Type: application/json');
            // Database connection failures are service outages → 503, not 500
            $statusCode = strpos($e->getMessage(), 'Database connection failed') !== false ? 503 : 500;
            http_response_code($statusCode);
            echo json_encode(['status' => 'error', 'error' => $public, 'ref' => $ref]);
            exit;
        }

        // Non-API routes: render the error page
        if (class_exists('Session')) {
            Session::set('error_exception', $e);
            Session::set('error_ref', $ref);

            // Database connection failure: the error-page template chain itself
            // needs DB (Appearance::get() in _master.php), so serve the static
            // error-pages/503.html instead to avoid a second throw → blank screen.
            $isDbFailure = strpos($e->getMessage(), 'Database connection failed') !== false;
            if ($isDbFailure) {
                http_response_code(503);
                $staticPage = $_SERVER['DOCUMENT_ROOT'] . '/error-pages/503.html';
                if (is_file($staticPage)) {
                    header('Content-Type: text/html; charset=utf-8');
                    readfile($staticPage);
                    exit;
                }
                // Fall through to the normal error page if the static file is missing.
            }
            Session::loadErrorPage();
            exit;
        } else {
            // Fallback for extremely early fatal errors
            echo htmlspecialchars($public);
            exit;
        }
    }
}

// Install the handler as early as possible — before any DB-dependent code
// (WebAPI constructor in lib/load.php, Session below) can throw.
set_exception_handler('global_exception_handler');

require_once __DIR__ . '/lib/load.php';

// 3. NOW we can use classes - refresh auth cookies (single source of truth:
// keeps the host-only + Domain PHPSESSID variants convergent so the session
// id can never flip between requests, slides session_token expiry, and
// throttles the server-side token last_activity touch)
Session::refreshAuthCookies();

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}
/**
 * Global Helpers
 */
if (!function_exists('require_ui_component')) {
    function require_ui_component($file) {
        $path = __DIR__ . "/ui/" . $file . '.php';
        if (!file_exists($path)) {
            throw new Exception("UI Component not found: " . $file);
        }
        require_once $path;
    }
}
// 3. Sync PHP Session to Session Class (Safe now because Session class is loaded)
if (isset($_SESSION['auth_status'])) {
    Session::$authStatus = $_SESSION['auth_status'];
}

register_shutdown_function(function() {
    $error = error_get_last();
    // Catch fatal errors (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR)
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Convert to ErrorException to pass to our handler
        $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        global_exception_handler($e);
    }
});
if (!function_exists('cdn')) {
    function cdn($url) {
        return $url;
    }
}

// 2. Auto-add nonce to inline scripts for CSP compliance
// Removed: nonce-based CSP required editing ~77 inline script files individually.
// 'unsafe-inline' is required for inline event handlers (onclick, onmouseover, etc.)
// which cannot use nonces.