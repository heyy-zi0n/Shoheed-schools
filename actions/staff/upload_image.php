<?php
/**
 * Staff profile image upload action.
 * POST: admin/staff → actions/staff/upload_image.php
 * RBAC: Admin only
 */
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

$staffId = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);
if (!$staffId) {
    set_flash('error', 'Invalid staff ID.');
    redirect('admin/staff/index.php');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT id, profile_image FROM staff WHERE id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash('error', 'Staff member not found.');
    redirect('admin/staff/index.php');
}

if (empty($_FILES['profile_image']['name'])) {
    set_flash('error', 'No image file was selected.');
    redirect('admin/staff/view.php?id=' . $staffId);
}

try {
    $newPath = handle_profile_upload($_FILES['profile_image']);

    if (!empty($staff['profile_image'])) {
        delete_profile_image($staff['profile_image']);
    }

    $stmt = $pdo->prepare("UPDATE staff SET profile_image = ? WHERE id = ?");
    $stmt->execute([$newPath, $staffId]);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'UPLOAD_PROFILE_IMAGE', 'staff', ?, ?)")
        ->execute([current_user_id(), $staffId, "Uploaded new profile image: {$newPath}"]);

    set_flash('success', 'Profile image updated successfully.');
} catch (RuntimeException $e) {
    set_flash('error', 'Upload failed: ' . $e->getMessage());
}

redirect('admin/staff/view.php?id=' . $staffId);
