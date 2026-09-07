<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/academics/sessions.php');
}

$sessionId = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
if (!$sessionId) {
    set_flash('error', 'Invalid Session ID.');
    redirect('admin/academics/sessions.php');
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    // Deactivate all
    $pdo->exec("UPDATE academic_sessions SET is_active = 0");
    
    // Activate target
    $stmt = $pdo->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = :id");
    $stmt->execute(['id' => $sessionId]);
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
    $auditStmt->execute([
        'user_id' => current_user_id(),
        'action' => 'SET_ACTIVE_SESSION',
        'type' => 'academic_session',
        'eid' => $sessionId,
        'desc' => "Set session ID {$sessionId} as active."
    ]);

    $pdo->commit();
    set_flash('success', 'Active session updated successfully.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to update active session.');
}

redirect('admin/academics/sessions.php');
