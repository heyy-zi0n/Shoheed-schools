<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_STUDENT]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid request.');
    redirect('student/fees.php');
}

$userId = current_user_id();
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    set_flash('error', 'Student profile not found.');
    redirect('student/dashboard.php');
}

$invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
$amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
$paymentDate = trim($_POST['payment_date'] ?? '');
$senderBank = trim($_POST['sender_bank'] ?? '');
$transferRef = trim($_POST['transfer_reference'] ?? '');
$note = trim($_POST['note'] ?? '');

$errors = validate_required([
    'invoice_id' => $invoiceId,
    'amount' => $amount,
    'payment_date' => $paymentDate,
    'sender_bank' => $senderBank
], ['invoice_id', 'amount', 'payment_date', 'sender_bank']);

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('student/fees.php#submit-proof');
}

// Ensure invoice belongs to student
$invStmt = $pdo->prepare("SELECT total_amount, (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = student_invoices.id) as amount_paid FROM student_invoices WHERE id = ? AND student_id = ?");
$invStmt->execute([$invoiceId, $studentId]);
$invoice = $invStmt->fetch();

if (!$invoice) {
    set_flash('error', 'Invalid invoice selected.');
    redirect('student/fees.php#submit-proof');
}

if ($amount <= 0) {
    set_flash('error', 'Amount must be greater than zero.');
    redirect('student/fees.php#submit-proof');
}

// Check for duplicate obvious submissions
if ($transferRef !== '') {
    $dupStmt = $pdo->prepare("SELECT id FROM payment_submissions WHERE student_id = ? AND invoice_id = ? AND transfer_reference = ? AND status = 'PENDING'");
    $dupStmt->execute([$studentId, $invoiceId, $transferRef]);
    if ($dupStmt->fetch()) {
        set_flash('error', 'A pending submission with this transfer reference already exists for this invoice.');
        redirect('student/fees.php#submit-proof');
    }
}

// Handle File Upload
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'Please upload a valid proof of payment file.');
    redirect('student/fees.php#submit-proof');
}

$file = $_FILES['proof'];
$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($file['type'], $allowedTypes)) {
    set_flash('error', 'Invalid file type. Only JPG, PNG, and PDF are allowed.');
    redirect('student/fees.php#submit-proof');
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB
    set_flash('error', 'File size exceeds 5MB limit.');
    redirect('student/fees.php#submit-proof');
}

$uploadDir = dirname(__DIR__, 2) . '/storage/payment_proofs';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('proof_') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    set_flash('error', 'Failed to save uploaded file.');
    redirect('student/fees.php#submit-proof');
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO payment_submissions (student_id, invoice_id, amount, payment_date, sender_bank, transfer_reference, proof_path, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
    $stmt->execute([
        $studentId,
        $invoiceId,
        $amount,
        $paymentDate,
        $senderBank,
        $transferRef !== '' ? $transferRef : null,
        $filename,
        $note !== '' ? $note : null
    ]);
    
    $submissionId = $pdo->lastInsertId();
    
    // Notify Student
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $notifStmt->execute([
        $userId,
        "Payment Proof Submitted",
        "Your payment proof of NGN {$amount} has been submitted and is pending Bursar verification."
    ]);
    
    $pdo->commit();
    set_flash('success', 'Payment proof submitted successfully. It will be reviewed by the Bursar.');
} catch (Exception $e) {
    $pdo->rollBack();
    // Cleanup file on DB failure
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    error_log("Payment submission failed: " . $e->getMessage());
    set_flash('error', 'A database error occurred. Your submission was not saved.');
}

redirect('student/fees.php');
