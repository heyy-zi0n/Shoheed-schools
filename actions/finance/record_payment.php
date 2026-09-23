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
    
    // Record payment using canonical service
    $result = record_official_payment($pdo, $invoiceId, $amount, $paymentMethod, $paymentReference, $paymentDate, current_user_id());
    
    $pdo->commit();
    set_flash('success', "Payment logged successfully. Receipt generated: {$result['receipt_number']}");
    redirect('bursar/invoices/index.php');
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in record_payment: " . $e->getMessage());
    set_flash('error', 'Unable to record payment due to a database error.');
    redirect('bursar/payments/create.php?invoice=' . $invoiceId);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect('bursar/payments/create.php?invoice=' . $invoiceId);
}
