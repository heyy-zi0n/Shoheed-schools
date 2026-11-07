<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../core/upload.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile/index.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid security token.');
    redirect('profile/index.php');
}

$userId = current_user_id();
$userRole = current_user_role();

// Only staff can update this info currently
if (!in_array($userRole, [ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER, ROLE_BURSAR], true)) {
    set_flash('error', 'Unauthorized.');
    redirect('profile/index.php');
}

$phone = trim($_POST['phone'] ?? '');
$shortBio = trim($_POST['short_bio'] ?? '');

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // Fetch existing staff record
    $stmt = $pdo->prepare("SELECT id, profile_image FROM staff WHERE user_id = ?");
    $stmt->execute([$userId]);
    $staff = $stmt->fetch();
    
    if (!$staff) {
        throw new Exception("Staff record not found.");
    }
    
    $staffId = $staff['id'];
    
    // Handle profile image upload
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $profileImagePath = handle_profile_upload($_FILES['profile_image']);
        
        // Fetch old image to delete it later if upload succeeds
        $oldImage = $staff['profile_image'];
        if ($oldImage) {
            delete_profile_image($oldImage);
        }
    }

    if ($profileImagePath) {
        $stmt = $pdo->prepare("UPDATE staff SET phone = ?, short_bio = ?, profile_image = ? WHERE user_id = ?");
        $stmt->execute([$phone, $shortBio, $profileImagePath, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE staff SET phone = ?, short_bio = ? WHERE user_id = ?");
        $stmt->execute([$phone, $shortBio, $userId]);
    }

    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $userId,
        'UPDATE_PROFILE',
        'staff',
        $staffId,
        "User successfully updated their profile information."
    ]);
    
    $pdo->commit();
    set_flash('success', 'Your profile information has been successfully updated.');
    redirect('profile/index.php');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Profile info update error: " . $e->getMessage());
    set_flash('error', $e->getMessage());
    redirect('profile/index.php');
}
