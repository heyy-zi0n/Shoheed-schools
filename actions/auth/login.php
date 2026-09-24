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

    // Probabilistic Garbage Collection for stale rate limits (1% chance)
    if (random_int(1, 100) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE last_failed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

    // Rate Limiting (Database Persistent)
    $lockStmt = $pdo->prepare("SELECT failed_attempts, locked_until FROM login_attempts WHERE username = :uname");
    $lockStmt->execute(['uname' => $username]);
    $attemptRecord = $lockStmt->fetch();

    if ($attemptRecord && $attemptRecord['locked_until']) {
        if (strtotime($attemptRecord['locked_until']) > time()) {
            set_flash('error', 'Too many unsuccessful login attempts. Please try again later.');
            redirect('login.php');
        }
    }

    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = :username LIMIT 1");
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

        login_user($user['id'], $user['role_name']);

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
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $role = $user['role_name'];
        header('Location: ' . get_dashboard_url_for_role($role));
        exit;
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $failStmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, failed_attempts, last_failed_at)
            VALUES (:uname, :ip, 1, NOW())
            ON DUPLICATE KEY UPDATE
                ip_address = VALUES(ip_address),
                failed_attempts = failed_attempts + 1,
                last_failed_at = NOW(),
                locked_until = IF(failed_attempts >= 5, DATE_ADD(NOW(), INTERVAL 5 MINUTE), locked_until)
        ");
        $failStmt->execute(['uname' => $username, 'ip' => $ip]);

        set_flash('error', 'Invalid login credentials.');
        redirect('login.php');
    }
} catch (PDOException $e) {
    set_flash('error', 'A database error occurred. Please try again later.');
    redirect('login.php');
}
