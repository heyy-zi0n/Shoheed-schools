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

// Force display_errors off globally to prevent sensitive data leakage.
// Error output should only go to the error log.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Global error handler to intercept warnings/notices and log them instead of displaying them
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // Check if the error is suppressed by the @ operator or error_reporting mask
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // Determine the error type string
    $errorType = match ($severity) {
        E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR => 'Fatal Error',
        E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
        E_NOTICE, E_USER_NOTICE => 'Notice',
        E_STRICT => 'Strict',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
        default => 'Unknown Error'
    };

    // Log the error natively
    error_log(sprintf(
        '[PHP %s] %s in %s:%d',
        $errorType,
        $message,
        $file,
        $line
    ));

    // Return true to prevent PHP from executing its internal error handler (which prints to output)
    // We only do this for non-fatal errors; fatal errors will naturally halt execution or be caught elsewhere
    return true;
});

// Global exception handler to intercept uncaught exceptions and render a safe generic page
set_exception_handler(function (Throwable $e): void {
    // 1. Log the exact technical exception natively
    error_log(sprintf(
        '[UNCAUGHT %s] %s in %s:%d%s%s',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    // 2. Clear any output buffers that might contain partial pages
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // 3. Set HTTP 500 status
    if (!headers_sent()) {
        http_response_code(500);
        
        // 4. Render safe generic error page
        $pageTitle = 'Server Error';
        require_once dirname(__DIR__) . '/templates/layouts/app-header.php';
        echo '<div class="flex-1 flex flex-col items-center justify-center p-6">';
        echo '  <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 max-w-md w-full text-center">';
        echo '      <div class="mx-auto w-16 h-16 bg-red-50 text-school-red rounded-full flex items-center justify-center mb-4">';
        echo '          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>';
        echo '      </div>';
        echo '      <h2 class="text-2xl font-serif font-bold text-school-navy mb-2">Something went wrong</h2>';
        echo '      <p class="text-slate-gray mb-6 text-sm">Something went wrong while processing your request. Please try again.</p>';
        echo '      <a href="' . (defined('APP_URL') ? APP_URL : '/') . '" class="inline-block bg-school-blue text-white px-6 py-2 rounded font-bold hover:bg-school-navy transition-colors">Return to Homepage</a>';
        echo '  </div>';
        echo '</div>';
        
        if (file_exists(dirname(__DIR__) . '/templates/layouts/app-footer.php')) {
            require_once dirname(__DIR__) . '/templates/layouts/app-footer.php';
        }
    } else {
        // Fallback if headers were already sent
        echo '<div style="padding: 20px; background: #fff3f3; color: #c53030; border: 1px solid #fecaca; margin: 20px; border-radius: 8px; font-family: system-ui, sans-serif; text-align: center;">';
        echo '<strong>An unexpected error occurred.</strong> Something went wrong while processing your request. Please try again.';
        echo '</div>';
    }
});

date_default_timezone_set($_app_config['timezone'] ?? 'Africa/Lagos');

// Emit baseline HTTP security headers for web responses
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ─── 2. Constants (roles, result states, attendance, invoice, session config) ───
require_once __DIR__ . '/constants.php';

// ─── 3. Session (guarded & secured) ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Determine if we are on HTTPS
    $isHttps = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $isHttps = true;
    }
    // Note: Do not blindly trust X-Forwarded-Proto from untrusted clients.
    // If deployed behind a trusted proxy in the future, add proxy validation here.

    // Configure secure session parameters before starting
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

// ─── 4. Core helpers ──────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/helpers.php';   // e()
require_once dirname(__DIR__) . '/core/url.php';        // url(), redirect()

// ─── 5. Flash messages ────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/flash.php';      // set_flash(), get_flash()

// ─── 6. CSRF ──────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/csrf.php';       // csrf_field(), generate_csrf_token(), verify_csrf_token()

// ─── 7. Database ──────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/db.php';         // get_db_connection()

// ─── 8. Validation ────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/validation.php'; // validate_required(), validate_email()

// ─── 9. Upload helpers ────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/upload.php';     // handle_profile_upload(), delete_profile_image()

// ─── 10. Authentication ───────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/core/auth.php';       // is_logged_in(), current_user_id(), login_user(), etc.

// ─── 11. Authorization & Domain Services ──────────────────────────────────────
require_once dirname(__DIR__) . '/core/authorization.php'; // require_auth(), require_role()
require_once dirname(__DIR__) . '/core/teaching.php';      // current_staff_id(), staff_has_subject_assignments(), etc.
require_once dirname(__DIR__) . '/core/finance.php';       // record_official_payment()
require_once dirname(__DIR__) . '/core/results.php';       // calculate_shoheed_grade(), get_sdba_categories(), build_student_report_card_data()
require_once dirname(__DIR__) . '/core/students.php';      // query_student_directory()
