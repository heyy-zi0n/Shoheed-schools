<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/academics/session_create.php');
}

$name = trim($_POST['name'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');

if (empty($name) || empty($startDate) || empty($endDate)) {
    set_flash('error', 'All fields are required.');
    redirect('admin/academics/session_create.php');
}

if (strtotime($startDate) >= strtotime($endDate)) {
    set_flash('error', 'End date must be after start date.');
    redirect('admin/academics/session_create.php');
}

$pdo = get_db_connection();

// Check unique name
$stmt = $pdo->prepare("SELECT COUNT(*) FROM academic_sessions WHERE name = ?");
$stmt->execute([$name]);
if ($stmt->fetchColumn() > 0) {
    set_flash('error', 'A session with this name already exists.');
    redirect('admin/academics/session_create.php');
}

try {
    $pdo->beginTransaction();

    // Default active state is 0 for newly created sessions per requirements
    $stmt = $pdo->prepare("INSERT INTO academic_sessions (name, is_active, start_date, end_date) VALUES (?, 0, ?, ?)");
    $stmt->execute([$name, $startDate, $endDate]);
    $sessionId = $pdo->lastInsertId();

    // Create default terms for the new session
    $terms = ['First Term', 'Second Term', 'Third Term'];
    $termStmt = $pdo->prepare("INSERT INTO terms (name, academic_session_id, is_active) VALUES (?, ?, ?)");
    foreach ($terms as $index => $termName) {
        $termStmt->execute([$termName, $sessionId, $index === 0 ? 1 : 0]);
    }

    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'CREATE_SESSION',
        'academic_session',
        $sessionId,
        "Created new academic session {$name} with 3 default terms."
    ]);

    $pdo->commit();
    set_flash('success', 'Academic session created successfully as Inactive.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Database error: ' . $e->getMessage());
}

redirect('admin/academics/sessions.php');
