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
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'ACTIVE') {
            set_flash('error', 'Your account is inactive. Please contact the administrator.');
            redirect('login.php');
        }

        login_user($user['id'], $user['role_name']);
        
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
        if ($role === ROLE_ADMIN) redirect('admin/dashboard.php');
        if ($role === ROLE_PRINCIPAL) redirect('principal/dashboard.php');
        if (in_array($role, [ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER], true)) redirect('teacher/dashboard.php');
        if ($role === ROLE_BURSAR) redirect('bursar/dashboard.php');
        if ($role === ROLE_STUDENT) redirect('student/dashboard.php');
        if ($role === ROLE_PARENT) redirect('parent/dashboard.php');
        
        redirect('index.php');
    } else {
        set_flash('error', 'Invalid username or password.');
        redirect('login.php');
    }
} catch (PDOException $e) {
    set_flash('error', 'A database error occurred. Please try again later.');
    redirect('login.php');
}
