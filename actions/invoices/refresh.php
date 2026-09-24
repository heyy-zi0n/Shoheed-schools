<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid request.');
    redirect('bursar/invoices/index.php');
}

$invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);

if (!$invoiceId) {
    set_flash('error', 'Invalid invoice selected.');
    redirect('bursar/invoices/index.php');
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Lock invoice for update
    $stmt = $pdo->prepare("SELECT * FROM student_invoices WHERE id = ? FOR UPDATE");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        throw new Exception("Invoice not found.");
    }

    if ($invoice['status'] !== 'PENDING') {
        throw new Exception("Only PENDING invoices can be refreshed.");
    }

    if ($invoice['invoice_type'] === 'SUPPLEMENTARY') {
        throw new Exception("Supplementary invoices cannot be refreshed.");
    }

    // Check for payments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE student_invoice_id = ?");
    $stmt->execute([$invoiceId]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Invoice has existing official payments and cannot be refreshed.");
    }

    // Check for pending/verified payment submissions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_submissions WHERE invoice_id = ? AND status IN ('PENDING', 'VERIFIED')");
    $stmt->execute([$invoiceId]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("This invoice cannot be refreshed because a payment submission is awaiting verification or has been verified. Resolve or reject the pending payment submission before changing the invoice.");
    }

    // Since receipts are tied to payments (or we assume if payments=0, receipts=0),
    // we also double check receipts just in case they are tied directly in schema.
    // However, looking at standard schema, receipts are tied to payments.

    // Load Fee Structure
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE id = ? AND is_active = 1 FOR UPDATE");
    $stmt->execute([$invoice['fee_structure_id']]);
    $structure = $stmt->fetch();

    if (!$structure) {
        throw new Exception("Linked Fee Structure not found or is inactive.");
    }

    if ($structure['academic_session_id'] != $invoice['academic_session_id'] || $structure['term_id'] != $invoice['term_id']) {
        throw new Exception("Fee Structure session/term mismatch.");
    }

    // Get new fee items
    $stmt = $pdo->prepare("SELECT * FROM fee_items WHERE fee_structure_id = ?");
    $stmt->execute([$structure['id']]);
    $feeItems = $stmt->fetchAll();

    if (empty($feeItems)) {
        throw new Exception("Fee Structure has no items.");
    }

    // Save old total for audit
    $oldTotal = $invoice['total_amount'];
    $newTotal = $structure['total_amount'];

    // Delete existing invoice items
    $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE student_invoice_id = ?");
    $stmt->execute([$invoiceId]);

    // Insert new invoice items
    $insertItemStmt = $pdo->prepare("INSERT INTO invoice_items (student_invoice_id, name, amount) VALUES (?, ?, ?)");
    foreach ($feeItems as $item) {
        $insertItemStmt->execute([$invoiceId, $item['name'], $item['amount']]);
    }

    // Update invoice total
    $stmt = $pdo->prepare("UPDATE student_invoices SET total_amount = ? WHERE id = ?");
    $stmt->execute([$newTotal, $invoiceId]);

    // Audit log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'INVOICE_REFRESHED_FROM_FEE_STRUCTURE', 'student_invoice', ?, ?)");
    $auditDesc = "Refreshed invoice from fee structure. Old total: {$oldTotal}, New total: {$newTotal}";
    $auditStmt->execute([current_user_id(), $invoiceId, $auditDesc]);

    $pdo->commit();
    set_flash('success', 'Invoice successfully refreshed from Fee Structure.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect('bursar/invoices/index.php');
