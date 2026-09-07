<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('bursar/invoices/index.php');
}

$invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
$amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
$paymentDate = trim($_POST['payment_date'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$paymentReference = trim($_POST['payment_reference'] ?? '');

$errors = validate_required([
    'invoice_id' => $invoiceId,
    'amount' => $amount,
    'payment_date' => $paymentDate,
    'payment_method' => $paymentMethod
], ['invoice_id', 'amount', 'payment_date', 'payment_method']);

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('bursar/payments/create.php?invoice=' . $invoiceId);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    // Lock invoice
    $stmt = $pdo->prepare("SELECT * FROM student_invoices WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $invoiceId]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception("Invoice not found.");
    }
    
    // Verify amount doesn't exceed balance
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = :id");
    $paidStmt->execute(['id' => $invoiceId]);
    $currentPaid = (float)$paidStmt->fetchColumn();
    $total = (float)$invoice['total_amount'];
    
    $balance = $total - $currentPaid;
    
    if ($amount <= 0 || $amount > $balance) {
        throw new Exception("Invalid payment amount. Maximum allowed is {$balance}.");
    }
    
    // 1. Insert Payment
    $stmt = $pdo->prepare("INSERT INTO payments (student_invoice_id, amount, payment_method, payment_reference, payment_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$invoiceId, $amount, $paymentMethod, $paymentReference !== '' ? $paymentReference : null, $paymentDate, current_user_id()]);
    $paymentId = $pdo->lastInsertId();
    
    // 2. Generate Receipt
    $receiptNo = 'REC-' . date('ym') . '-' . str_pad($paymentId, 5, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)");
    $stmt->execute([$paymentId, $receiptNo]);
    
    // 3. Update Invoice Status
    $newPaid = $currentPaid + $amount;
    $newStatus = ($newPaid >= $total) ? 'PAID' : 'PARTIAL';
    
    if ($invoice['status'] !== $newStatus) {
        $stmt = $pdo->prepare("UPDATE student_invoices SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $invoiceId]);
    }
    
    // 4. Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'RECORD_PAYMENT',
        'payment',
        $paymentId,
        "Recorded payment of {$amount} for invoice {$invoiceId}. Receipt: {$receiptNo}"
    ]);
    
    // Notify Student and Parent
    $stStmt = $pdo->prepare("
        SELECT u.id as student_user_id, gu.id as guardian_user_id
        FROM student_invoices si
        JOIN students st ON si.student_id = st.id
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN student_guardians sg ON st.id = sg.student_id
        LEFT JOIN guardians g ON sg.guardian_id = g.id
        LEFT JOIN users gu ON g.user_id = gu.id
        WHERE si.id = ?
    ");
    $stStmt->execute([$invoiceId]);
    $audiences = $stStmt->fetchAll();
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'PAYMENT_RECORDED')");
    $notifiedUsers = [];
    foreach ($audiences as $aud) {
        if ($aud['student_user_id'] && !in_array($aud['student_user_id'], $notifiedUsers)) {
            $notifStmt->execute([$aud['student_user_id'], "Payment Received", "A payment of NGN {$amount} was recorded. Receipt: {$receiptNo}"]);
            $notifiedUsers[] = $aud['student_user_id'];
        }
        if ($aud['guardian_user_id'] && !in_array($aud['guardian_user_id'], $notifiedUsers)) {
            $notifStmt->execute([$aud['guardian_user_id'], "Payment Received", "A payment of NGN {$amount} was recorded for your ward. Receipt: {$receiptNo}"]);
            $notifiedUsers[] = $aud['guardian_user_id'];
        }
    }

    $pdo->commit();
    set_flash('success', "Payment logged successfully. Receipt generated: {$receiptNo}");
    redirect('bursar/invoices/index.php');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect('bursar/payments/create.php?invoice=' . $invoiceId);
}
