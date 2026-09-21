<?php
/**
 * config/bootstrap.php
 *
 * Single centralized bootstrap for the entire application.
 * Load this ONE file at the top of every page and action script.
 *
 * Load order:
 *   1. Environment / Error reporting
 *   2. Session (guarded - started once)
 *   3. Constants (roles, states)
 *   4. Core helpers (e, url, redirect)
 *   5. Flash messages
 *   6. CSRF
 *   7. Database connection factory
 *   8. Validation helpers
 *   9. Upload helpers
 *  10. Authentication helpers
 *  11. Authorization helpers
 */

// Prevent re-loading if already bootstrapped
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

// ─── 1. Environment / Error Reporting ────────────────────────────────────────
require_once dirname(__DIR__) . '/core/env.php';

$_app_config = require __DIR__ . '/app.php';

define('APP_ENV', $_app_config['env'] ?? 'production');
define('APP_NAME', $_app_config['name'] ?? 'Shoheed Schools');
define('APP_URL', rtrim($_app_config['url'] ?? '', '/'));

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);          // still log everything
    ini_set('log_errors', '1');
}

date_default_timezone_set($_app_config['timezone'] ?? 'Africa/Lagos');

// ─── 2. Session (guarded) ─────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── 3. Constants (roles, result states, attendance, invoice) ─────────────────
require_once __DIR__ . '/constants.php';

// ─── 4. Core helpers ──────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/helpers.php';   // e()
require_once dirname(__DIR__) . '/core/url.php';        // url(), redirect()

// ─── 5. Flash messages ────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/flash.php';      // set_flash(), get_flash()

// ─── 6. CSRF ──────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/csrf.php';       // csrf_field(), csrf_token(), verify_csrf_token()

// ─── 7. Database ──────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/db.php';         // get_db_connection()

// ─── 8. Validation ────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/validation.php'; // validate_required(), validate_email()

// ─── 9. Upload helpers ────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/upload.php';     // handle_profile_upload(), delete_profile_image()

// ─── 10. Authentication ───────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/auth.php';       // is_logged_in(), current_user_id(), login_user(), etc.

// ─── 11. Authorization ────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/authorization.php'; // require_auth(), require_role()

// ─── 12. Teaching Capabilities & Authorization ────────────────────────────────
require_once dirname(__DIR__) . '/core/teaching.php';      // current_staff_id(), staff_has_subject_assignments(), etc.
