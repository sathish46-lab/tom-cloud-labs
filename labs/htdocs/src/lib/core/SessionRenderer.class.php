<?php
/**
 * SessionRenderer — Template loading, master layout, page rendering.
 * Extracted from Session.class.php to separate concerns.
 *
 * References Session's static properties for backward compatibility.
 */
class SessionRenderer {

    /**
     * Load the master layout (handles HTMX SPA interception).
     */
    public static function loadMaster() {
        if (Session::get('master_rendered', false)) {
            if (Session::get('brokenPage', false)) {
                self::loadTemplate('_error');
            }
            return;
        }
        Session::set('master_rendered', true);

        if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN && !defined('IS_LOGIN_PAGE') && !defined('IS_LANDING_PAGE')) {
            self::handleSessionExpired();
        }

        // HTMX SPA Interception
        if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] == 'true') {
            if (defined('IS_LANDING_PAGE') || defined('IS_LOGIN_PAGE') || defined('IS_HOME_PAGE')) {
                header('HX-Redirect: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
            if (!empty(Session::$pageTitle)) {
                echo "<title>" . htmlspecialchars(Session::$pageTitle) . "</title>";
            }

            if (!Session::get('brokenPage', false)) {
                self::generatePageBody();
            } else {
                self::loadTemplate('_error');
            }

            if (!Session::get('footer', false) && !defined('IS_HOME_PAGE') && !Session::get('show_session_expired', false)) {
                self::generateFooter(true);
            }

            // Build htmx-page-bootstrap JSON for breadcrumb + page metadata
            $pageTitle = Session::$pageTitle ?? 'Dashboard';
            $titleParts = array_map('trim', explode(' / ', $pageTitle));
            $labHash = Session::get('full_instance_hash');
            $challengeHash = Session::get('challenge_instance_hash');
            $breadcrumbs = [];
            $pathSoFar = '';
            $hasLabsContext = false;
            $hasChallengesContext = false;

            foreach ($titleParts as $index => $part) {
                $lowerPart = strtolower($part);
                $url = null;

                if (stripos($lowerPart, 'lab') !== false) $hasLabsContext = true;
                if (stripos($lowerPart, 'challenge') !== false) $hasChallengesContext = true;

                $quizParent = Session::get('parent_topic');
                $quizSubtopic = Session::get('current_subtopic');
                $quizCategory = Session::get('current_topic');

                if (strcasecmp($lowerPart, 'quiz') === 0) {
                    $url = '/quiz';
                } elseif (strcasecmp($lowerPart, 'spot quiz') === 0) {
                    $quiz = Session::get('current_quiz');
                    $url = $quiz ? "/quiz/v/" . $quiz['hash'] : '/quiz';
                } elseif (($quizParent && strcasecmp($part, trim($quizParent['title'])) === 0) ||
                          ($quizCategory && strcasecmp($part, trim($quizCategory['title'])) === 0)) {
                    $cat = $quizParent ?? $quizCategory;
                    $url = "/quiz/" . ($cat['id'] ?? $cat['_id']);
                } elseif ($quizSubtopic && strcasecmp($part, trim($quizSubtopic['title'])) === 0) {
                    $catId = $quizParent ? ($quizParent['id'] ?? $quizParent['_id']) : ($quizCategory ? ($quizCategory['id'] ?? $quizCategory['_id']) : 'all');
                    $url = "/quiz/$catId/Recent/" . ($quizSubtopic['id'] ?? $quizSubtopic['_id']);
                } elseif (stripos($lowerPart, 'home') !== false) {
                    $url = '/';
                } elseif (stripos($lowerPart, 'dashboard') !== false) {
                    if ($hasLabsContext && $labHash) $url = "/labs/dashboard/$labHash";
                    elseif ($hasChallengesContext && $challengeHash) $url = "/challenges/dashboard/$challengeHash";
                    else $url = '/dashboard';
                } elseif ($lowerPart === 'lab' || $lowerPart === 'labs') {
                    $url = '/labs';
                } elseif ($lowerPart === 'challenge' || $lowerPart === 'challenges') {
                    $url = '/challenges';
                } elseif ($lowerPart === 'service' || $lowerPart === 'services') {
                    $url = '/services';
                } elseif (stripos($lowerPart, 'mysql server') !== false) { $url = '/services/mysql';
                } elseif (stripos($lowerPart, 'mariadb server') !== false) { $url = '/services/mariadb';
                } elseif (stripos($lowerPart, 'postgresql server') !== false) { $url = '/services/postgresql';
                } elseif (stripos($lowerPart, 'mongodb server') !== false) { $url = '/services/mongodb';
                } elseif (stripos($lowerPart, 'rabbitmq server') !== false) { $url = '/services/rabbitmq';
                } elseif (stripos($lowerPart, 'redis server') !== false) { $url = '/services/redis';
                } elseif (stripos($lowerPart, 'device') !== false) { $url = '/devices';
                } elseif (stripos($lowerPart, 'network') !== false) { $url = '/network';
                } elseif (stripos($lowerPart, 'domain') !== false) {
                    if ($hasLabsContext && $labHash) $url = "/labs/domains/$labHash";
                    else $url = '/domains';
                } elseif (stripos($lowerPart, 'pref') !== false && $hasLabsContext && $labHash) {
                    $url = "/labs/preferences/$labHash";
                } elseif (stripos($lowerPart, 'account') !== false) { $url = '/account';
                } elseif (stripos($lowerPart, 'ssl') !== false) { $url = '/ssl';
                } elseif (stripos($lowerPart, 'achieve') !== false && $challengeHash) {
                    $url = "/challenges/achievements/$challengeHash";
                } elseif (stripos($lowerPart, 'leader') !== false && $challengeHash) {
                    $url = "/challenges/leaderboard/$challengeHash";
                }

                if ($url === null) {
                    $pathSoFar .= '/' . $lowerPart;
                    $url = $pathSoFar;
                }

                $breadcrumbs[] = ['name' => $part, 'uri' => $url];
            }

            $pageBootstrap = [
                'breadcrumbs' => $breadcrumbs,
                'title' => $pageTitle,
                'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                'pageInit' => false,
            ];
            echo '<script id="htmx-page-bootstrap" type="application/json">' . json_encode($pageBootstrap, JSON_UNESCAPED_SLASHES) . '</script>';
            return;
        }

        include __DIR__ . '/../../template/_master.php';
    }

    /**
     * Handle session expired redirect/HTMX response.
     */
    public static function handleSessionExpired() {
        setcookie('show_session_expired', '1', time() + 30, '/');
        Session::set('show_session_expired', true);
        if (isset($_SESSION)) {
            $_SESSION['show_session_expired'] = true;
        }

        if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] == 'true') {
            header('HX-Trigger: {"tomNotify": {"message": "Your session has expired. Please sign in again.", "title": "Authentication Required", "type": "warning"}}');
            echo '<div class="alert alert-warning border-0 rounded-4 p-3 my-2 d-flex align-items-center gap-3 shadow-sm" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3) !important;">
                <i class="bx bx-error-circle fs-3 text-warning"></i>
                <div>
                    <strong class="text-white d-block">Session Expired</strong>
                    <span class="small text-secondary">Your authentication session has timed out. <a href="/signin" class="text-warning fw-bold text-decoration-underline" data-no-boost="true">Sign in</a> to continue.</span>
                </div>
            </div>
            <script>
            if (window.TomNotify) {
                TomNotify.show("Your session has expired. Please sign in again.", "Authentication Required", "warning", 5000);
            }
            setTimeout(function() {
                window.location.href = "/";
            }, 2000);
            </script>';
            exit;
        } else {
            header('Location: /');
            exit;
        }
    }

    /**
     * Generate footer output.
     */
    public static function generateFooter($isOob = false) {
        include __DIR__ . '/../../template/_footer.php';
    }

    /**
     * Load a template file.
     */
    public static function loadTemplate($template, $general = false, $customFile = null) {
        $customFile = self::getCurrentFile($customFile);

        if ($template === '_error') {
            include __DIR__ . '/../../template/' . $template . '.php';
            return;
        }

        if ($general) {
            $path = __DIR__ . '/../../template/' . $template . '.php';
            if (!file_exists($path)) {
                throw new TemplateUnavailableException('Template not found: ' . $template);
            }
            include $path;
        } else {
            $path = __DIR__ . '/../../template/' . $customFile . '/' . $template . '.php';
            if (!file_exists($path)) {
                throw new TemplateUnavailableException(
                    'Template not found: ' . $template . ' in ' . $customFile
                );
            }
            include $path;
        }
    }

    /**
     * Check if a template exists.
     */
    public static function templateExists($template, $general = false, $customFile = null) {
        $customFile = self::getCurrentFile($customFile);

        if ($template === Constants::TEMPLATE_ERROR) {
            return true;
        }

        if ($general) {
            return file_exists(__DIR__ . '/../../template/' . $template . '.php');
        }

        return file_exists(
            __DIR__ . '/../../template/' . $customFile . '/' . $template . '.php'
        );
    }

    /**
     * Load the standard error page inside the master layout.
     */
    public static function loadErrorPage() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Session::set('brokenPage', true);
        Session::set('footer', false);
        self::loadMaster();
    }

    /**
     * Generate the page body based on current route.
     */
    public static function generatePageBody() {
        $self = $_SERVER['PHP_SELF'];
        $relPath = str_replace(['/app/', '.php'], '', $self);
        $templateRoot = __DIR__ . '/../../template/pages/';

        try {
            if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN) {
                $file = $templateRoot . $relPath . '.php';
                if (file_exists($file)) {
                    include $file;
                    return;
                }

                if (strpos($relPath, '/') !== false) {
                    $parts = explode('/', $relPath);
                    $catFile = $templateRoot . $parts[0] . '.php';
                    if (file_exists($catFile)) {
                        include $catFile;
                        return;
                    }
                }

                if (file_exists($templateRoot . 'dashboard.php')) {
                    include $templateRoot . 'dashboard.php';
                } else {
                    echo '<div class="alert alert-danger">Error: Template not found for ' . htmlspecialchars($relPath) . '</div>';
                }
            } else {
                self::handleSessionExpired();
            }
        } catch (Throwable $e) {
            Session::set('error_exception', $e);
            Session::set('brokenPage', true);
            self::loadTemplate('_error');
        }
    }

    /**
     * Return the current script name without extension.
     */
    public static function getCurrentFile($file = null) {
        if ($file === null) {
            $path = str_replace(['/app/', '.php'], '', $_SERVER['PHP_SELF']);
            if (strpos($path, '/') !== false) {
                $parts = explode('/', $path);
                return $parts[0];
            }
            return $path;
        }
        return basename($file, '.php');
    }

    /**
     * Navigation include.
     */
    public static function getNav() {
        include __DIR__ . '/../../template/_nav.php';
    }

    /**
     * Site navigation include.
     */
    public static function getSiteNav() {
        include __DIR__ . '/../../template/_sitenav.php';
    }
}
