<?php
function is_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_id'] ?? null;
}

function current_user_role(): ?string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['role_name'] ?? null;
}

function login_user(int $userId, string $roleName): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role_name'] = $roleName;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['last_activity_at'] = time();
    $_SESSION['last_regenerated_at'] = time();
}

function logout_user(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function enforce_session_timeout(): void {
    if (!is_logged_in()) {
        return;
    }

    $now = time();
    
    // Safety check for legacy sessions or compromised states
    if (!isset($_SESSION['authenticated_at']) || !isset($_SESSION['last_activity_at'])) {
        logout_user();
        session_start();
        session_regenerate_id(true);
        set_flash('error', 'Your session has expired. Please sign in again.');
        redirect('login.php');
    }

    $lastActivity = (int)$_SESSION['last_activity_at'];
    $authTime = (int)$_SESSION['authenticated_at'];
    
    $idleExpired = ($now - $lastActivity) >= SESSION_IDLE_TIMEOUT;
    $absoluteExpired = ($now - $authTime) >= SESSION_ABSOLUTE_TIMEOUT;

    if ($idleExpired || $absoluteExpired) {
        $reason = $idleExpired ? 'Your session expired due to inactivity. Please sign in again.' : 'Your session has expired. Please sign in again.';
        
        $userId = current_user_id();
        $action = $idleExpired ? 'SESSION_IDLE_EXPIRED' : 'SESSION_ABSOLUTE_EXPIRED';
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (:u, :a, 'user', :e, :d, :ip)");
            $stmt->execute([
                'u' => $userId,
                'a' => $action,
                'e' => $userId,
                'd' => $reason,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Throwable $e) {
            // Ignore audit log failure here
        }

        logout_user();
        
        session_start();
        session_regenerate_id(true);
        set_flash('error', $reason);
        redirect('login.php');
    }

    // Handle periodic regeneration if applicable
    $lastRegen = (int)($_SESSION['last_regenerated_at'] ?? $authTime);
    if (($now - $lastRegen) >= SESSION_REGEN_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated_at'] = $now;
    }

    // Refresh last activity
    $_SESSION['last_activity_at'] = $now;
}
