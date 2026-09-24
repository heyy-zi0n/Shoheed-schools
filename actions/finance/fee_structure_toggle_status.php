<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/finance/fee_structures.php');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$status = filter_var($_POST['status'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $status === null) {
    set_flash('error', 'Invalid parameters.');
    redirect('admin/finance/fee_structures.php');
}

try {
    $pdo = get_db_connection();

    // Check if the fee structure exists
    $stmt = $pdo->prepare("SELECT name FROM fee_structures WHERE id = ?");
    $stmt->execute([$id]);
    $fsName = $stmt->fetchColumn();
    if (!$fsName) {
        throw new Exception("Fee structure not found.");
    }

    $stmt = $pdo->prepare("UPDATE fee_structures SET is_active = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        $status ? 'FEE_STRUCTURE_REACTIVATED' : 'FEE_STRUCTURE_ARCHIVED',
        'fee_structure',
        $id,
        ($status ? 'Reactivated' : 'Archived') . " fee structure ID {$id} ({$fsName})."
    ]);

    set_flash('success', "Fee structure has been " . ($status ? 'reactivated' : 'archived') . ".");
} catch (Exception $e) {
    set_flash('error', $e->getMessage());
}

redirect('admin/finance/fee_structures.php');
