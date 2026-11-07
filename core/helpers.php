<?php
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get all academic sessions ordered by start date (newest first).
 */
function get_all_academic_sessions(): array {
    $pdo = get_db_connection();
    return $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
}

/**
 * Get the currently active academic session (memoized per request unless $forceRefresh is true).
 * Returns null if no session is active.
 */
function get_active_academic_session(bool $forceRefresh = false): ?array {
    static $cached = false;
    static $session = null;
    if (!$cached || $forceRefresh) {
        $pdo = get_db_connection();
        $row = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1")->fetch();
        $session = $row ?: null;
        $cached = true;
    }
    return $session;
}

/**
 * Get the currently active term for a specific academic session (memoized per request).
 * Returns null if no term is active for that session.
 */
function get_active_term(?int $academicSessionId, bool $forceRefresh = false): ?array {
    if (!$academicSessionId) {
        return null;
    }
    static $cache = [];
    if (!array_key_exists($academicSessionId, $cache) || $forceRefresh) {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM terms WHERE academic_session_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$academicSessionId]);
        $term = $stmt->fetch();
        $cache[$academicSessionId] = $term ?: null;
    }
    return $cache[$academicSessionId];
}

/**
 * Record an entry in the audit_logs table.
 */
function log_audit(PDO $pdo, ?int $userId, string $action, string $entityType, ?int $entityId, string $description): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (:uid, :action, :etype, :eid, :desc, :ip)
    ");
    $stmt->execute([
        'uid'    => $userId,
        'action' => $action,
        'etype'  => $entityType,
        'eid'    => $entityId,
        'desc'   => $description,
        'ip'     => $ip,
    ]);
}

/**
 * Get an academic session by its ID.
 */
function get_academic_session_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ?");
    $stmt->execute([$id]);
    $session = $stmt->fetch();
    return $session ?: null;
}


function format_role_name(?string $role): string {
    if (!$role) return 'User';
    $normalized = strtoupper(trim($role));
    $map = [
        ROLE_ADMIN           => 'Administrator',
        ROLE_PRINCIPAL       => 'Principal',
        ROLE_CLASS_TEACHER   => 'Class Teacher',
        ROLE_SUBJECT_TEACHER => 'Subject Teacher',
        ROLE_BURSAR          => 'Bursar',
        ROLE_STUDENT         => 'Student',
        ROLE_PARENT          => 'Parent',
        'ROLE_ADMIN'           => 'Administrator',
        'ROLE_PRINCIPAL'       => 'Principal',
        'ROLE_CLASS_TEACHER'   => 'Class Teacher',
        'ROLE_SUBJECT_TEACHER' => 'Subject Teacher',
        'ROLE_BURSAR'          => 'Bursar',
        'ROLE_STUDENT'         => 'Student',
        'ROLE_PARENT'          => 'Parent',
    ];
    return $map[$normalized] ?? ucwords(strtolower(str_replace('_', ' ', str_replace('ROLE_', '', $normalized))));
}

function get_user_initials(?string $name): string {
    if (!$name) return 'U';
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function get_dashboard_url_for_role(?string $role): string {
    if (!$role) return url('login.php');
    
    $role = strtoupper(trim($role));
    
    switch ($role) {
        case ROLE_ADMIN: 
            return url('admin/dashboard.php');
        case ROLE_PRINCIPAL: 
            return url('principal/dashboard.php');
        case ROLE_CLASS_TEACHER:
        case ROLE_SUBJECT_TEACHER: 
            return url('teacher/dashboard.php');
        case ROLE_BURSAR: 
            return url('bursar/dashboard.php');
        case ROLE_STUDENT: 
            return url('student/dashboard.php');
        default: 
            error_log("Unexpected role attempting to resolve dashboard URL: {$role}");
            return url('index.php');
    }
}


function get_user_display_name(int $userId, string $role): string {
    $pdo = get_db_connection();
    $name = null;
    if (in_array($role, [ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER, ROLE_BURSAR], true)) {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM staff WHERE user_id = ?");
        $stmt->execute([$userId]);
        $staff = $stmt->fetch();
        if ($staff) $name = trim($staff['first_name'] . ' ' . $staff['last_name']);
    } elseif ($role === ROLE_STUDENT) {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE user_id = ?");
        $stmt->execute([$userId]);
        $student = $stmt->fetch();
        if ($student) $name = trim($student['first_name'] . ' ' . $student['last_name']);
    } elseif ($role === ROLE_PARENT) {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM guardians WHERE user_id = ?");
        $stmt->execute([$userId]);
        $parent = $stmt->fetch();
        if ($parent) $name = trim($parent['first_name'] . ' ' . $parent['last_name']);
    }
    
    if (!$name) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn() ?: 'User';
    }
    
    return $name;
}

function get_user_profile_image(int $userId, string $role): ?string {
    $pdo = get_db_connection();
    $image = null;
    
    if (in_array($role, [ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER, ROLE_BURSAR], true)) {
        $stmt = $pdo->prepare("SELECT profile_image FROM staff WHERE user_id = ?");
        $stmt->execute([$userId]);
        $image = $stmt->fetchColumn();
    } elseif ($role === ROLE_STUDENT) {
        $stmt = $pdo->prepare("SELECT profile_image FROM students WHERE user_id = ?");
        $stmt->execute([$userId]);
        $image = $stmt->fetchColumn();
    }
    
    return $image ?: null;
}


/**
 * Renders a dashboard metric component.
 *
 * @param string $title Metric title (REQUIRED)
 * @param mixed $value Metric main value (REQUIRED)
 * @param string|null $subtitle Optional subtitle or context
 * @param string|null $color Color theme for the metric (e.g., 'school-blue', 'school-gold')
 * @param string|null $link Optional URL to link the "View details" action to
 */
function render_metric(string $title, $value, ?string $subtitle = null, ?string $color = null, ?string $link = null): void {
    require __DIR__ . '/../templates/components/dashboard/metric.php';
}

/**
 * Renders a dashboard quick action component.
 *
 * @param string $title Action title (REQUIRED)
 * @param string $url Target URL (REQUIRED)
 * @param string $icon Action icon text (usually 1-2 chars) (REQUIRED)
 * @param string|null $color Color theme for the action
 */
function render_quick_action(string $title, string $url, string $icon, ?string $color = null): void {
    require __DIR__ . '/../templates/components/dashboard/quick_action.php';
}
