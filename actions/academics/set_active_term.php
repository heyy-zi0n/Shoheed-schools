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

$termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
if (!$termId) {
    set_flash('error', 'Invalid Term ID.');
    redirect('admin/academics/sessions.php');
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    // We need to know which session this term belongs to
    $stmt = $pdo->prepare("SELECT academic_session_id FROM terms WHERE id = :id");
    $stmt->execute(['id' => $termId]);
    $sessionId = $stmt->fetchColumn();
    
    if (!$sessionId) {
        throw new Exception("Term not found.");
    }

    $activeSession = get_active_academic_session();
    if (!$activeSession || $activeSession['id'] != $sessionId) {
        throw new Exception("You can only activate terms belonging to the currently active session.");
    }
    
    // Deactivate all terms for this session
    $stmt = $pdo->prepare("UPDATE terms SET is_active = 0 WHERE academic_session_id = :session_id");
    $stmt->execute(['session_id' => $sessionId]);
    
    // Activate target
    $stmt = $pdo->prepare("UPDATE terms SET is_active = 1 WHERE id = :id");
    $stmt->execute(['id' => $termId]);
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
    $auditStmt->execute([
        'user_id' => current_user_id(),
        'action' => 'SET_ACTIVE_TERM',
        'type' => 'term',
        'eid' => $termId,
        'desc' => "Set term ID {$termId} as active."
    ]);

    $pdo->commit();
    set_flash('success', 'Active term updated successfully.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to update active term.');
}

redirect('admin/academics/sessions.php');
