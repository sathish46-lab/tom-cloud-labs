<?php

/**
 * Constants class
 * Globally accessible application constants for state and environment management.
 */
class Constants
{
    /* ----------------------------------------------------------------------
     * Authentication Statuses
     * Used in Session::generatePageBody() to resolve templates.
     * -------------------------------------------------------------------- */
    const STATUS_DEFAULT  = 'DEFAULT';   // Standard user or guest state
    const STATUS_LOGGEDIN = 'LOGGED_IN'; // User has a valid session

    /* ----------------------------------------------------------------------
     * Environment Definitions
     * Used in _master.php to toggle between app.js and app.o.js.
     * -------------------------------------------------------------------- */
    const ENV_LOCAL = 'local'; // Development environment
    const ENV_BETA  = 'beta';  // Staging/Beta environment
    const ENV_PROD  = 'prod';  // Live production environment
    const ENV_ALPHA = 'alpha'; // Early testing environment

    /* ----------------------------------------------------------------------
     * Template Fallbacks
     * Used in Session::templateExists() for error handling.
     * -------------------------------------------------------------------- */
    const TEMPLATE_ERROR = '_error'; // Default error template name

    /* ----------------------------------------------------------------------
     * Privilege Groups (Optional placeholders based on your Session flags)
     *
     * -------------------------------------------------------------------- */
    const GROUP_ADMIN      = 'admin';
    const GROUP_MODERATOR  = 'moderator'; //
    const GROUP_SUPERUSER  = 'superuser'; //

    /* ----------------------------------------------------------------------
     * Feature Flags
     * Used to easily toggle UI features across the application.
     * -------------------------------------------------------------------- */
    const FEATURE_HTTP_PROXIES = false;
}