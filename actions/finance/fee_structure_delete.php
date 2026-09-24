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

if (!$id) {
    set_flash('error', 'Invalid Fee Structure ID.');
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

    // Check if it's used
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_invoices WHERE fee_structure_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        set_flash('error', 'This fee structure has been used to generate invoices and cannot be hard-deleted. Please use the Archive action instead.');
        redirect('admin/finance/fee_structures.php');
    }

    $pdo->beginTransaction();

    // Delete fee items
    $stmt = $pdo->prepare("DELETE FROM fee_items WHERE fee_structure_id = ?");
    $stmt->execute([$id]);

    // Delete structure
    $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
    $stmt->execute([$id]);

    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'FEE_STRUCTURE_DELETED',
        'fee_structure',
        $id,
        "Permanently deleted unused fee structure: {$fsName}."
    ]);

    $pdo->commit();
    set_flash('success', "Unused fee structure deleted successfully.");
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to delete fee structure: ' . $e->getMessage());
}

redirect('admin/finance/fee_structures.php');
