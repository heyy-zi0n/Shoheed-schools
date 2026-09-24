<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/finance/generate_invoices.php');
}

$structureId = filter_var($_POST['fee_structure_id'] ?? null, FILTER_VALIDATE_INT);
$dueDate = trim($_POST['due_date'] ?? '');

if (!$structureId) {
    set_flash('error', 'Please select a fee structure.');
    redirect('admin/finance/generate_invoices.php');
}

try {
    $pdo = get_db_connection();

    // 1. Fetch Fee Structure & Items
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE id = ? AND is_active = 1");
    $stmt->execute([$structureId]);
    $structure = $stmt->fetch();

    if (!$structure) {
        throw new Exception("Invalid or inactive fee structure.");
    }

    $termStmt = $pdo->prepare("SELECT academic_session_id FROM terms WHERE id = ?");
    $termStmt->execute([$structure['term_id']]);
    if ($termStmt->fetchColumn() != $structure['academic_session_id']) {
        throw new Exception("Fee structure is invalid: its term does not belong to its academic session.");
    }

    $itemStmt = $pdo->prepare("SELECT * FROM fee_items WHERE fee_structure_id = ? AND is_active = 1");
    $itemStmt->execute([$structureId]);
    $items = $itemStmt->fetchAll();

    if (empty($items)) {
        throw new Exception("Fee structure has no items. Cannot generate invoices.");
    }

    // 2. Fetch Eligible Students based on Enrollment matching the session and class.
    // Ensure student is also ACTIVE globally in students table.
    $studentStmt = $pdo->prepare("
        SELECT s.id
        FROM students s
        JOIN student_enrollments se ON s.id = se.student_id
        WHERE s.status = 'ACTIVE'
          AND se.status = 'ACTIVE'
          AND se.academic_session_id = ?
          AND se.class_id = ?
    ");
    $studentStmt->execute([$structure['academic_session_id'], $structure['class_id']]);
    $students = $studentStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($students)) {
        throw new Exception("No active students found matching the structure's session and class.");
    }

    $created_standard = 0;
    $created_supplementary = 0;
    $skipped = 0;
    $failed = 0;

    // Check for existing invoices using the composite unique key logic
    $checkStmt = $pdo->prepare("
        SELECT id FROM student_invoices
        WHERE student_id = ?
          AND academic_session_id = ?
          AND term_id = ?
          AND fee_structure_id = ?
    ");

    $billedItemsStmt = $pdo->prepare("
        SELECT ii.source_fee_item_id
        FROM invoice_items ii
        JOIN student_invoices si ON ii.student_invoice_id = si.id
        WHERE si.student_id = ? AND si.academic_session_id = ? AND si.term_id = ? AND si.fee_structure_id = ?
        AND ii.source_fee_item_id IS NOT NULL
    ");

    $insertInvoiceStmt = $pdo->prepare("
        INSERT INTO student_invoices (student_id, academic_session_id, term_id, fee_structure_id, total_amount, status, due_date, invoice_type)
        VALUES (?, ?, ?, ?, ?, 'PENDING', ?, ?)
    ");

    $insertItemStmt = $pdo->prepare("
        INSERT INTO invoice_items (student_invoice_id, name, amount, source_fee_item_id)
        VALUES (?, ?, ?, ?)
    ");

    $studentLockStmt = $pdo->prepare("SELECT id FROM students WHERE id = ? FOR UPDATE");

    $pdo->beginTransaction();

    foreach ($students as $studentId) {
        try {
            // Obtain a stable lock for this student to prevent concurrent double-billing
            $studentLockStmt->execute([$studentId]);

            $billedItemsStmt->execute([$studentId, $structure['academic_session_id'], $structure['term_id'], $structureId]);
            $billedIds = $billedItemsStmt->fetchAll(PDO::FETCH_COLUMN);

            $unbilledItems = [];
            $unbilledTotal = 0;
            foreach ($items as $item) {
                if (!in_array($item['id'], $billedIds)) {
                    $unbilledItems[] = $item;
                    $unbilledTotal += $item['amount'];
                }
            }

            $checkStmt->execute([$studentId, $structure['academic_session_id'], $structure['term_id'], $structureId]);
            $hasAnyInvoice = $checkStmt->fetch();

            if (empty($unbilledItems)) {
                $skipped++;
                continue;
            }

            $invoiceType = $hasAnyInvoice ? 'SUPPLEMENTARY' : 'STANDARD';

            $due = empty($dueDate) ? null : $dueDate;
            $insertInvoiceStmt->execute([
                $studentId,
                $structure['academic_session_id'],
                $structure['term_id'],
                $structureId,
                $unbilledTotal,
                $due,
                $invoiceType
            ]);

            $invoiceId = $pdo->lastInsertId();

            foreach ($unbilledItems as $item) {
                $insertItemStmt->execute([$invoiceId, $item['name'], $item['amount'], $item['id']]);
            }

            if ($invoiceType === 'STANDARD') {
                $created_standard++;
            } else {
                $created_supplementary++;
            }
        } catch (Exception $e) {
            $failed++;
        }
    }

    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'GENERATE_BULK_INVOICES', 'fee_structure', ?, ?)")
        ->execute([current_user_id(), $structureId, "Generated bulk invoices. Standard: {$created_standard}, Supplementary: {$created_supplementary}, Skipped: {$skipped}, Failed: {$failed}"]);

    $pdo->commit();

    $msg = "Standard invoices created: {$created_standard}<br>Supplementary invoices created: {$created_supplementary}<br>Already fully invoiced: {$skipped}<br>Failed: {$failed}";
    set_flash('success', $msg);
    redirect('admin/finance/generate_invoices.php');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect('admin/finance/generate_invoices.php');
}
