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

$portalAccess = $_POST['portal_access'] ?? 'PORTAL_ENABLED';

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

// Directory fields
$staffCategory = $_POST['staff_category'] ?? 'TEACHING';
if (!in_array($staffCategory, ['TEACHING', 'NON_TEACHING'])) $staffCategory = 'TEACHING';

$publicDesignation = trim($_POST['public_designation'] ?? '');
$publicEmail = trim($_POST['public_email'] ?? '');
$publicPhone = trim($_POST['public_phone'] ?? '');
$qualification = trim($_POST['qualification'] ?? '');
$shortBio = trim($_POST['short_bio'] ?? '');
$showInDirectory = isset($_POST['show_in_directory']) ? 1 : 0;
$displayOrder = filter_var($_POST['display_order'] ?? 100, FILTER_VALIDATE_INT) ?: 100;

// Portal fields
$roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$canTeachSubjects = isset($_POST['can_teach_subjects']) ? 1 : 0;
$canBeClassTeacher = isset($_POST['can_be_class_teacher']) ? 1 : 0;

$requiredFields = ['first_name' => $firstName, 'last_name' => $lastName, 'public_designation' => $publicDesignation];
$requiredKeys = ['first_name', 'last_name', 'public_designation'];

if ($portalAccess === 'PORTAL_ENABLED') {
    $requiredFields['role_id'] = $roleId;
    $requiredFields['username'] = $username;
    $requiredFields['password'] = $password;
    $requiredKeys = array_merge($requiredKeys, ['role_id', 'username', 'password']);
} else {
    $canTeachSubjects = 0;
    $canBeClassTeacher = 0;
}

$errors = validate_required($requiredFields, $requiredKeys);

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
    
    // Check username uniqueness if portal enabled
    if ($portalAccess === 'PORTAL_ENABLED') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :user");
        $stmt->execute(['user' => $username]);
        if ($stmt->fetchColumn() > 0) {
            set_flash('error', 'Username is already taken.');
            redirect('admin/staff/create.php');
        }
    }
    
    $pdo->beginTransaction();
    
    $userId = null;
    
    if ($portalAccess === 'PORTAL_ENABLED') {
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
    }
    
    // 2. Create Staff Profile
    $stmt = $pdo->prepare("
        INSERT INTO staff (
            user_id, first_name, last_name, phone, address, 
            can_teach_subjects, can_be_class_teacher,
            staff_category, public_designation, short_bio, qualification, 
            public_email, public_phone, show_in_directory, display_order
        ) VALUES (
            :user_id, :first, :last, :phone, :address, 
            :teach, :class_t,
            :cat, :desig, :bio, :qual, 
            :p_email, :p_phone, :show, :d_order
        )
    ");
    $stmt->execute([
        'user_id' => $userId, // null for directory-only
        'first' => $firstName,
        'last' => $lastName,
        'phone' => $phone !== '' ? $phone : null,
        'address' => $address !== '' ? $address : null,
        'teach' => $canTeachSubjects,
        'class_t' => $canBeClassTeacher,
        'cat' => $staffCategory,
        'desig' => $publicDesignation,
        'bio' => $shortBio !== '' ? $shortBio : null,
        'qual' => $qualification !== '' ? $qualification : null,
        'p_email' => $publicEmail !== '' ? $publicEmail : null,
        'p_phone' => $publicPhone !== '' ? $publicPhone : null,
        'show' => $showInDirectory,
        'd_order' => $displayOrder
    ]);
    $staffId = $pdo->lastInsertId();
    
    // 3. Audit Log
    $auditDesc = $portalAccess === 'PORTAL_ENABLED' 
        ? "Created staff member {$firstName} {$lastName} with username {$username}."
        : "Created directory-only staff member {$firstName} {$lastName}.";
        
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
    $auditStmt->execute([
        'user_id' => current_user_id(),
        'action' => 'CREATE_STAFF',
        'type' => 'staff',
        'eid' => $staffId,
        'desc' => $auditDesc
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
