<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/finance/bank_accounts.php');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$bankName = trim($_POST['bank_name'] ?? '');
$accountName = trim($_POST['account_name'] ?? '');
$accountNumber = trim($_POST['account_number'] ?? '');
$isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

$errors = validate_required([
    'bank_name' => $bankName,
    'account_name' => $accountName,
    'account_number' => $accountNumber
], ['bank_name', 'account_name', 'account_number']);

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/finance/bank_accounts.php');
}

try {
    $pdo = get_db_connection();
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE school_bank_accounts SET bank_name = ?, account_name = ?, account_number = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$bankName, $accountName, $accountNumber, $isActive, $id]);
        $message = "Bank account updated successfully.";
        $action = 'UPDATE_BANK_ACCOUNT';
    } else {
        $stmt = $pdo->prepare("INSERT INTO school_bank_accounts (bank_name, account_name, account_number, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bankName, $accountName, $accountNumber, $isActive]);
        $id = $pdo->lastInsertId();
        $message = "Bank account added successfully.";
        $action = 'CREATE_BANK_ACCOUNT';
    }
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        $action,
        'bank_account',
        $id,
        $message
    ]);

    set_flash('success', $message);
} catch (PDOException $e) {
    error_log("Database error in save_bank_account: " . $e->getMessage());
    set_flash('error', 'A database error occurred while saving the bank account.');
}

redirect('admin/finance/bank_accounts.php');
