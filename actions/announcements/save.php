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
$audience = strtoupper(trim($_POST['target_audience'] ?? $_POST['audience'] ?? 'ALL'));
$body = trim($_POST['content'] ?? $_POST['body'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$errors = validate_required([
    'title' => $title,
    'target_audience' => $audience,
    'content' => $body
], ['title', 'target_audience', 'content']);

$validAudiences = ['ALL', 'STAFF', 'STUDENTS', 'PARENTS', 'PUBLIC'];
if (!in_array($audience, $validAudiences, true)) {
    $errors[] = 'Invalid target audience selected.';
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/announcements/create.php');
}

try {
    $pdo = get_db_connection();
    $isPublic = ($audience === 'PUBLIC') ? 1 : 0;
    $status = $isPublished ? 'PUBLISHED' : 'DRAFT';
    $publishDate = $isPublished ? date('Y-m-d') : null;

    $stmt = $pdo->prepare("
        INSERT INTO announcements (title, body, author_id, audience, is_public, status, publish_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title,
        $body,
        current_user_id(),
        $audience,
        $isPublic,
        $status,
        $publishDate
    ]);
    $announcementId = (int)$pdo->lastInsertId();

    log_audit(
        $pdo,
        current_user_id(),
        'CREATE_ANNOUNCEMENT',
        'announcement',
        $announcementId,
        "Created {$status} announcement '{$title}' targeting {$audience}."
    );

    set_flash('success', 'Announcement created successfully.');
    redirect('admin/announcements/index.php');

} catch (Exception $e) {
    error_log('Error saving announcement: ' . $e->getMessage());
    set_flash('error', 'Unable to save announcement due to a database error.');
    redirect('admin/announcements/create.php');
}
