<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/staff/index.php');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('error', 'Invalid staff ID.');
    redirect('admin/staff/index.php');
}

$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$status = trim($_POST['status'] ?? 'ACTIVE');
$canTeachSubjects = isset($_POST['can_teach_subjects']) ? 1 : 0;
$canBeClassTeacher = isset($_POST['can_be_class_teacher']) ? 1 : 0;

if (empty($firstName) || empty($lastName) || empty($gender)) {
    set_flash('error', 'First name, last name, and gender are required.');
    redirect('admin/staff/edit.php?id=' . $id);
}

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // Get user_id from staff table
    $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        throw new Exception("Staff record has no associated user.");
    }

    $stmt = $pdo->prepare("SELECT can_teach_subjects, can_be_class_teacher FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $currentCaps = $stmt->fetch();

    $activeSession = get_active_academic_session();
    
    if ($activeSession) {
        if ($currentCaps['can_teach_subjects'] && !$canTeachSubjects) {
            if (staff_has_subject_assignments($id, $activeSession['id'])) {
                throw new Exception("This staff member currently has active subject teaching assignments for {$activeSession['name']}. Remove or reassign those assignments before disabling subject-teaching eligibility.");
            }
        }
        
        if ($currentCaps['can_be_class_teacher'] && !$canBeClassTeacher) {
            if (staff_has_class_teacher_assignment($id, $activeSession['id'])) {
                throw new Exception("This staff member currently has an active Class Teacher assignment for {$activeSession['name']}. Remove or reassign that assignment before disabling Class Teacher eligibility.");
            }
        }
    }

    // Check if email is unique (if changed and not empty)
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            set_flash('error', 'This email is already in use by another account.');
            redirect('admin/staff/edit.php?id=' . $id);
        }
    }

    // Update staff table
    $stmt = $pdo->prepare("
        UPDATE staff 
        SET first_name = ?, middle_name = ?, last_name = ?, gender = ?, phone = ?, can_teach_subjects = ?, can_be_class_teacher = ?
        WHERE id = ?
    ");
    $stmt->execute([$firstName, $middleName, $lastName, $gender, $phone, $canTeachSubjects, $canBeClassTeacher, $id]);

    // Update users table (email and status)
    $stmt = $pdo->prepare("
        UPDATE users 
        SET email = ?, status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$email, $status, $userId]);

    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'UPDATE_STAFF',
        'staff',
        $id,
        "Updated staff member details for ID {$id}."
    ]);

    $pdo->commit();
    set_flash('success', 'Staff details updated successfully.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Database error: ' . $e->getMessage());
}

redirect('admin/staff/edit.php?id=' . $id);
