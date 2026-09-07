<?php
// Internal guards: bootstrap loads these first, but keep require_once for safety
// when this file is ever loaded in isolation (e.g. CLI scripts).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/url.php';

function require_auth(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_role(array $allowedRoles): void {
    require_auth();
    $role = current_user_role();
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        die('403 Forbidden: You do not have permission to access this page.');
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
