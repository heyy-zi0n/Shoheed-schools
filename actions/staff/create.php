<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/staff/create.php');
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$errors = validate_required([
    'first_name' => $firstName,
    'last_name' => $lastName,
    'role_id' => $roleId,
    'username' => $username,
    'password' => $password
], ['first_name', 'last_name', 'role_id', 'username', 'password']);

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/staff/create.php');
}

if ($email !== '' && !validate_email($email)) {
    set_flash('error', 'Invalid email address.');
    redirect('admin/staff/create.php');
}

try {
    $pdo = get_db_connection();
    
    // Check username uniqueness
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :user");
    $stmt->execute(['user' => $username]);
    if ($stmt->fetchColumn() > 0) {
        set_flash('error', 'Username is already taken.');
        redirect('admin/staff/create.php');
    }
    
    $pdo->beginTransaction();
    
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    
    // 1. Create User
    $stmt = $pdo->prepare("INSERT INTO users (role_id, username, email, password_hash) VALUES (:role, :user, :email, :pass)");
    $stmt->execute([
        'role' => $roleId,
        'user' => $username,
        'email' => $email !== '' ? $email : null,
        'pass' => $passHash
    ]);
    $userId = $pdo->lastInsertId();
    
    // 2. Create Staff Profile
    $stmt = $pdo->prepare("INSERT INTO staff (user_id, first_name, last_name, phone, address) VALUES (:user_id, :first, :last, :phone, :address)");
    $stmt->execute([
        'user_id' => $userId,
        'first' => $firstName,
        'last' => $lastName,
        'phone' => $phone !== '' ? $phone : null,
        'address' => $address !== '' ? $address : null
    ]);
    $staffId = $pdo->lastInsertId();
    
    // 3. Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
    $auditStmt->execute([
        'user_id' => current_user_id(),
        'action' => 'CREATE_STAFF',
        'type' => 'staff',
        'eid' => $staffId,
        'desc' => "Created staff member {$firstName} {$lastName} with username {$username}."
    ]);

    $pdo->commit();
    set_flash('success', 'Staff member created successfully.');
    redirect('admin/staff/index.php');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to create staff member. ' . $e->getMessage());
    redirect('admin/staff/create.php');
}
