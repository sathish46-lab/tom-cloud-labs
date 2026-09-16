<?php

/**
 * Session class — Backward-compatible facade.
 *
 * Delegates to focused classes:
 *   SessionAuth     — authentication, user, avatar
 *   SessionCsrf     — CSRF token generation/validation
 *   SessionRenderer — templates, master layout, page rendering
 *   SessionEnv      — environment, versioning, CDN, meta, utilities
 *
 * Static properties remain here for backward compatibility with
 * templates and external code that reference Session::$pageTitle etc.
 */
class Session
{
    /* --- Page presentation fields --- */
    public static $pageTitle          = 'Welcome to Tom Lab ';
    public static $subLogo            = '';
    public static $meta               = array();
    public static $customCss          = array();
    public static $customJs          = array();
    public static $localPack          = null;
    public static $ConsoleLogs        = array();

    /* --- Environment / version info --- */
    public static $csrfToken          = '';
    public static $fullVersion        = '';
    public static $versionDescription = '';
    public static $environment        = '';
    public static $cacheCDN           = null;

    /* --- User / auth flags --- */
    public static $userSession        = null;
    public static $authStatus         = null;
    public static $isModerator        = false;
    public static $isSuperUser        = false;
    public static $privileges         = null;
    public static $privilegesGroup    = null;

    /* --- Generic key/value storage --- */
    public static $property           = array();

    /* ==================================================================
     * CSRF — delegates to SessionCsrf
     * ------------------------------------------------------------------ */

    public static function csrfToken()    { return SessionCsrf::token(); }
    public static function validateCsrf($token) { return SessionCsrf::validate($token); }
    public static function csrfField()    { return SessionCsrf::field(); }

    /* ==================================================================
     * Key/value store
     * ------------------------------------------------------------------ */

    public static function toast($message, $type = 'success') {
        self::set("toast_$type", $message);
    }

    public static function set($key, $value) {
        self::$property[$key] = $value;
    }

    public static function get($key, $default = false) {
        return self::$property[$key] ?? $default;
    }

    /* ==================================================================
     * Auth — delegates to SessionAuth
     * ------------------------------------------------------------------ */

    public static function getUserSession()  { return SessionAuth::getUserSession(); }
    public static function getUser()         { return SessionAuth::getUser(); }
    public static function getAuthStatus()   { return SessionAuth::getAuthStatus(); }
    public static function getAvatar()       { return SessionAuth::getAvatar(); }
    public static function getAvatarForUsername($u) { return SessionAuth::getAvatarForUsername($u); }
    public static function getAvatarStyle()  { return SessionAuth::getAvatarStyle(); }

    /* ==================================================================
     * Meta tags / render time — delegates to SessionEnv
     * ------------------------------------------------------------------ */

    public static function addMetaTag($tag)  { SessionEnv::addMetaTag($tag); }
    public static function getRenderTime()   { return SessionEnv::getRenderTime(); }

    /* ==================================================================
     * Navigation — delegates to SessionRenderer
     * ------------------------------------------------------------------ */

    public static function getNav()     { SessionRenderer::getNav(); }
    public static function getSiteNav() { SessionRenderer::getSiteNav(); }

    /* ==================================================================
     * Version / CDN — delegates to SessionEnv
     * ------------------------------------------------------------------ */

    public static function getVersion()       { return SessionEnv::getVersion(); }
    public static function cacheCDN($url)     { return SessionEnv::cacheCDN($url); }
    public static function cdn3($path)        { return SessionEnv::cdn3($path); }
    public static function getProcessorCount(){ return SessionEnv::getProcessorCount(); }

    /* ==================================================================
     * Assets — delegates to SessionEnv
     * ------------------------------------------------------------------ */

    public static function addCustomCss($css) { SessionEnv::addCustomCss($css); }
    public static function addCustomJs($js)   { SessionEnv::addCustomJs($js); }

    /* ==================================================================
     * URL helper — delegates to SessionEnv
     * ------------------------------------------------------------------ */

    public static function url($route) { return SessionEnv::url($route); }

    /* ==================================================================
     * Master layout / rendering — delegates to SessionRenderer
     * ------------------------------------------------------------------ */

    public static function loadMaster()                                     { SessionRenderer::loadMaster(); }
    public static function handleSessionExpired()                           { SessionRenderer::handleSessionExpired(); }
    public static function generateFooter($isOob = false)                  { SessionRenderer::generateFooter($isOob); }
    public static function loadTemplate($t, $g = false, $c = null)         { SessionRenderer::loadTemplate($t, $g, $c); }
    public static function templateExists($t, $g = false, $c = null)       { return SessionRenderer::templateExists($t, $g, $c); }
    public static function loadErrorPage()                                  { SessionRenderer::loadErrorPage(); }
    public static function generatePageBody()                               { SessionRenderer::generatePageBody(); }
    public static function getCurrentFile($f = null)                        { return SessionRenderer::getCurrentFile($f); }

    /* ==================================================================
     * Utility
     * ------------------------------------------------------------------ */

    public static function generatePseudoRandomHash($length = 10) {
        return SessionEnv::generatePseudoRandomHash($length);
    }

    /* ==================================================================
     * Environment helpers (instance-style, kept for backward compat)
     * ------------------------------------------------------------------ */

    public function getEnvironment() { return SessionEnv::getEnvironment(); }
    public function isBeta()         { return SessionEnv::isBeta(); }
    public function isProd()         { return SessionEnv::isProd(); }
    public function isAlpha()        { return SessionEnv::isAlpha(); }
    public function getEvironment()  { return SessionEnv::getEnvironment(); }
}
