<?php
/**
 * Student profile image upload action.
 * POST: admin/students → actions/students/upload_image.php
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
    redirect('admin/students/index.php');
}

$studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('error', 'Invalid student ID.');
    redirect('admin/students/index.php');
}

// Verify student exists
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT id, profile_image FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('error', 'Student not found.');
    redirect('admin/students/index.php');
}

if (empty($_FILES['profile_image']['name'])) {
    set_flash('error', 'No image file was selected.');
    redirect('admin/students/view.php?id=' . $studentId);
}

try {
    $newPath = handle_profile_upload($_FILES['profile_image']);

    // Remove old image if present
    if (!empty($student['profile_image'])) {
        delete_profile_image($student['profile_image']);
    }

    // Persist new path
    $stmt = $pdo->prepare("UPDATE students SET profile_image = ? WHERE id = ?");
    $stmt->execute([$newPath, $studentId]);

    // Audit
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'UPLOAD_PROFILE_IMAGE', 'student', ?, ?)")
        ->execute([current_user_id(), $studentId, "Uploaded new profile image: {$newPath}"]);

    set_flash('success', 'Profile image updated successfully.');
} catch (RuntimeException $e) {
    set_flash('error', 'Upload failed: ' . $e->getMessage());
}

redirect('admin/students/view.php?id=' . $studentId);
