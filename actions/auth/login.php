<?php
require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('login.php');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$errors = validate_required(['username' => $username, 'password' => $password], ['username', 'password']);

if (!empty($errors)) {
    set_flash('error', 'Please provide both username and password.');
    redirect('login.php');
}

try {
    $pdo = get_db_connection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Probabilistic Garbage Collection for stale rate limits (1% chance)
    if (random_int(1, 100) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE last_failed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

    // Rate Limiting (Database Persistent — checks both username lock and per-IP burst)
    $lockStmt = $pdo->prepare("SELECT failed_attempts, locked_until FROM login_attempts WHERE username = :uname LIMIT 1");
    $lockStmt->execute(['uname' => $username]);
    $attemptRecord = $lockStmt->fetch();

    if ($attemptRecord && !empty($attemptRecord['locked_until'])) {
        if (strtotime($attemptRecord['locked_until']) > time()) {
            set_flash('error', 'Too many unsuccessful login attempts. Please try again later.');
            redirect('login.php');
        }
    }

    $ipLockStmt = $pdo->prepare("
        SELECT COALESCE(SUM(failed_attempts), 0)
        FROM login_attempts
        WHERE ip_address = :ip
          AND last_failed_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $ipLockStmt->execute(['ip' => $ip]);
    if ((int)$ipLockStmt->fetchColumn() >= 10) {
        set_flash('error', 'Too many unsuccessful login attempts from your network. Please try again in 15 minutes.');
        redirect('login.php');
    }

    $stmt = $pdo->prepare("
        SELECT u.*, r.name AS role_name,
               st.id AS staff_id, st.status AS staff_status,
               stu.id AS student_id, stu.status AS student_status
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN staff st ON st.user_id = u.id
        LEFT JOIN students stu ON stu.user_id = u.id
        WHERE u.username = :username
        LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'ACTIVE') {
            set_flash('error', 'Your account is inactive. Please contact the administrator.');
            redirect('login.php');
        }

        if ($user['role_name'] === ROLE_PARENT) {
            set_flash('error', "Parent/Guardian portal access is no longer available. Please use the student's portal account for academic and fee information.");
            redirect('login.php');
        }

        if (!empty($user['staff_id']) && ($user['staff_status'] ?? 'ACTIVE') !== 'ACTIVE') {
            set_flash('error', 'Your staff profile is currently inactive. Please contact the administrator.');
            redirect('login.php');
        }

        if ($user['role_name'] === ROLE_STUDENT && ($user['student_status'] ?? '') !== 'ACTIVE') {
            set_flash('error', 'Your student profile is not active. Please contact the school administration.');
            redirect('login.php');
        }

        login_user((int)$user['id'], $user['role_name']);

        $pdo->prepare("DELETE FROM login_attempts WHERE username = :uname")->execute(['uname' => $username]);

        $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $user['id']]);

        // Audit Log
        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (:user_id, :action, :type, :eid, :desc, :ip)");
        $auditStmt->execute([
            'user_id' => $user['id'],
            'action' => 'LOGIN',
            'type' => 'user',
            'eid' => $user['id'],
            'desc' => "User {$username} logged in successfully.",
            'ip' => $ip
        ]);

        $role = $user['role_name'];
        header('Location: ' . get_dashboard_url_for_role($role));
        exit;
    } else {
        $failStmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, failed_attempts, last_failed_at, locked_until)
            VALUES (:uname, :ip, 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                ip_address = VALUES(ip_address),
                failed_attempts = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, failed_attempts + 1),
                last_failed_at = NOW(),
                locked_until = IF(failed_attempts >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)
        ");
        $failStmt->execute(['uname' => $username, 'ip' => $ip]);

        set_flash('error', 'Invalid login credentials.');
        redirect('login.php');
    }
} catch (PDOException $e) {
    set_flash('error', 'A database error occurred. Please try again later.');
    redirect('login.php');
}
