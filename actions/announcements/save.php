<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/announcements/create.php');
}

$title = trim($_POST['title'] ?? '');
$audience = $_POST['target_audience'] ?? '';
$content = trim($_POST['content'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$errors = validate_required([
    'title' => $title,
    'target_audience' => $audience,
    'content' => $content
], ['title', 'target_audience', 'content']);

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/announcements/create.php');
}

try {
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, target_audience, is_published, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $title,
        $content,
        $audience,
        $isPublished,
        current_user_id()
    ]);
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([current_user_id(), 'CREATE_ANNOUNCEMENT', 'announcement', $pdo->lastInsertId(), "Created announcement targeting {$audience}"]);

    set_flash('success', "Announcement created successfully.");
    redirect('admin/announcements/index.php');
    
} catch (Exception $e) {
    set_flash('error', "Database error: " . $e->getMessage());
    redirect('admin/announcements/create.php');
}
