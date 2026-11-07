<?php
// Internal guards: bootstrap loads these first, but keep require_once for safety
// when this file is ever loaded in isolation (e.g. CLI scripts).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/url.php';

function require_auth(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    enforce_session_timeout();
    verify_active_session_account();
}

function require_role(array $allowedRoles): void {
    require_auth();
    $role = current_user_role();
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        $dashboardUrl = function_exists('get_dashboard_url_for_role')
            ? get_dashboard_url_for_role((string)$role)
            : url('index.php');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>403 Forbidden | Shoheed Schools</title>'
            . '<style>body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#F5F1E8;color:#20242C;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1.5rem}.card{max-width:28rem;background:#FFFDFC;border:1px solid #E5E7EB;border-top:4px solid #C62828;border-radius:.75rem;padding:2rem;box-shadow:0 4px 12px rgba(17,27,63,.08);text-align:center}h1{margin:0 0 .5rem;color:#111B3F;font-size:1.5rem}p{margin:0 0 1.5rem;color:#697386;font-size:.95rem;line-height:1.5}a{display:inline-block;background:#263B8F;color:#fff;text-decoration:none;padding:.65rem 1.25rem;border-radius:.375rem;font-weight:600;font-size:.875rem}a:hover{background:#111B3F}</style>'
            . '</head><body><main class="card"><h1>403 &mdash; Access Denied</h1><p>You do not have permission to view this page with your current account role.</p>'
            . '<a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '">Return to My Dashboard</a></main></body></html>';
        exit;
    }
}

/**
 * Check if the current user has any of the specified roles.
 * Used for conditional UI rendering (not for access enforcement).
 * For access enforcement, use require_role() instead.
 */
function has_role(array $allowedRoles): bool {
    $role = current_user_role();
    return $role !== null && in_array($role, $allowedRoles, true);
}
