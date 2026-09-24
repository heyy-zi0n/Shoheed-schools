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
$email = $email === '' ? null : $email;
$username = trim($_POST['username'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$status = trim($_POST['status'] ?? 'ACTIVE');
$canTeachSubjects = isset($_POST['can_teach_subjects']) ? 1 : 0;
$canBeClassTeacher = isset($_POST['can_be_class_teacher']) ? 1 : 0;

$staffStatus = trim($_POST['staff_status'] ?? 'ACTIVE');
$staffCategory = trim($_POST['staff_category'] ?? 'TEACHING');
$publicDesignation = trim($_POST['public_designation'] ?? '');
$publicEmail = trim($_POST['public_email'] ?? '');
$publicPhone = trim($_POST['public_phone'] ?? '');
$qualification = trim($_POST['qualification'] ?? '');
$shortBio = trim($_POST['short_bio'] ?? '');
$showInDirectory = isset($_POST['show_in_directory']) ? 1 : 0;
$displayOrder = filter_var($_POST['display_order'] ?? 100, FILTER_VALIDATE_INT) ?: 100;

if (empty($firstName) || empty($lastName) || empty($gender) || empty($publicDesignation)) {
    set_flash('error', 'First name, last name, gender, and public designation are required.');
    redirect('admin/staff/edit.php?id=' . $id);
}

// Validate passwords if provided
$hasNewPassword = false;
if (!empty($newPassword) || !empty($confirmPassword)) {
    if (empty($newPassword) || empty($confirmPassword) || $newPassword !== $confirmPassword) {
        set_flash('error', 'Enter and confirm the new password, and ensure both values match.');
        redirect('admin/staff/edit.php?id=' . $id);
    }
    if (strlen($newPassword) < 8) {
        set_flash('error', 'Password must be at least 8 characters long.');
        redirect('admin/staff/edit.php?id=' . $id);
    }
$hasNewPassword = true;
}

require_once __DIR__ . '/../../core/upload.php';

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // Get user_id from staff table
    $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $userId = $stmt->fetchColumn();
    
    $isPortalEnabled = $userId !== null && $userId !== false;
    $oldUsername = null;

    if ($isPortalEnabled) {
        if (empty($username)) {
            throw new Exception("Username is required for portal-enabled staff.");
        }
        if (!preg_match('/^[a-zA-Z0-9_\.\-]+$/', $username)) {
            throw new Exception("Username can only contain letters, numbers, underscores, dots, and hyphens.");
        }
        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception("Username must be between 3 and 50 characters.");
        }
        
        $userCheck = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $userCheck->execute([$userId]);
        $oldUsername = $userCheck->fetchColumn();
        if ($oldUsername === false) {
            throw new Exception("This staff record is linked to an invalid portal account.");
        }
        
        // Validate passwords if provided
        $hasNewPassword = false;
        if (!empty($newPassword) || !empty($confirmPassword)) {
            if (empty($newPassword) || empty($confirmPassword) || $newPassword !== $confirmPassword) {
                throw new Exception("Enter and confirm the new password, and ensure both values match.");
            }
            if (strlen($newPassword) < 8) {
                throw new Exception("Password must be at least 8 characters long.");
            }
            $hasNewPassword = true;
        }
        
        // Check if email is unique
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("This email is already in use by another account.");
            }
        }
        
        // Check if username is unique
        if (strtolower($oldUsername) !== strtolower($username)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                throw new Exception("This username is already in use. Please choose another username.");
            }
        }
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

    // Handle profile image upload
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $profileImagePath = handle_profile_upload($_FILES['profile_image']);
        
        // Fetch old image to delete it later if upload succeeds
        $stmt = $pdo->prepare("SELECT profile_image FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();
        if ($oldImage) {
            delete_profile_image($oldImage);
        }
    }

    // Update staff table
    if ($profileImagePath) {
        $stmt = $pdo->prepare("
            UPDATE staff 
            SET first_name = ?, middle_name = ?, last_name = ?, gender = ?, phone = ?, 
                can_teach_subjects = ?, can_be_class_teacher = ?,
                staff_category = ?, public_designation = ?, short_bio = ?, qualification = ?, 
                public_email = ?, public_phone = ?, show_in_directory = ?, display_order = ?, status = ?,
                profile_image = ?
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $middleName, $lastName, $gender, $phone, 
            $canTeachSubjects, $canBeClassTeacher,
            $staffCategory, $publicDesignation, $shortBio, $qualification, 
            $publicEmail, $publicPhone, $showInDirectory, $displayOrder, $staffStatus,
            $profileImagePath,
            $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE staff 
            SET first_name = ?, middle_name = ?, last_name = ?, gender = ?, phone = ?, 
                can_teach_subjects = ?, can_be_class_teacher = ?,
                staff_category = ?, public_designation = ?, short_bio = ?, qualification = ?, 
                public_email = ?, public_phone = ?, show_in_directory = ?, display_order = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $middleName, $lastName, $gender, $phone, 
            $canTeachSubjects, $canBeClassTeacher,
            $staffCategory, $publicDesignation, $shortBio, $qualification, 
            $publicEmail, $publicPhone, $showInDirectory, $displayOrder, $staffStatus,
            $id]);
    }

    // Update users table if applicable
    if ($isPortalEnabled) {
        $userStatus = trim($_POST['user_status'] ?? 'ACTIVE');
        $stmt = $pdo->prepare("
            UPDATE users 
            SET email = ?, username = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$email, $username, $userStatus, $userId]);
        
        // Update password if provided
        if ($hasNewPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
        }
    }

    $actorId = current_user_id();

    // Audit Log: General
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $actorId,
        'UPDATE_STAFF',
        'staff',
        $id,
        "Updated staff member details for ID {$id}."
    ]);
    
    // Audit Log: Username Changed
    if ($isPortalEnabled && $oldUsername !== $username) {
        $auditStmt->execute([
            $actorId,
            'STAFF_USERNAME_CHANGED',
            'user',
            $userId,
            "Username changed from '{$oldUsername}' to '{$username}' for staff ID {$id}."
        ]);
        
        // Handle self-edit session update
        if ($actorId == $userId && isset($_SESSION['user']) && isset($_SESSION['user']['username'])) {
            $_SESSION['user']['username'] = $username;
        }
    }
    
    // Audit Log: Password Reset
    if ($isPortalEnabled && $hasNewPassword) {
        $auditStmt->execute([
            $actorId,
            'STAFF_PASSWORD_RESET',
            'user',
            $userId,
            "Password reset for staff ID {$id}."
        ]);
    }

    $pdo->commit();
    set_flash('success', 'Staff details updated successfully.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof PDOException && $e->getCode() == 23000) {
        set_flash('error', 'This username or email is already in use by another account.');
    } else {
        set_flash('error', $e->getMessage());
    }
}

redirect('admin/staff/edit.php?id=' . $id);
