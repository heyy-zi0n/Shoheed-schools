<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/finance/fee_structures_create.php');
}

$name = trim($_POST['name'] ?? '');
$sessionId = filter_var($_POST['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
$termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$items = $_POST['items'] ?? [];

$errors = validate_required([
    'name' => $name,
    'academic_session_id' => $sessionId,
    'term_id' => $termId,
    'class_id' => $classId
], ['name', 'academic_session_id', 'term_id', 'class_id']);

if (empty($items) || !is_array($items)) {
    $errors[] = "At least one fee item is required.";
} else {
    foreach ($items as $idx => $item) {
        if (empty(trim($item['name'] ?? ''))) {
            $errors[] = "Item name is required for all fee items.";
            break;
        }
        if (!isset($item['amount']) || !is_numeric($item['amount']) || (float)$item['amount'] < 0) {
            $errors[] = "A valid positive amount is required for all fee items.";
            break;
        }
    }
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/finance/fee_structures_create.php');
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 1. Check for duplicates
    $checkStmt = $pdo->prepare("SELECT id FROM fee_structures WHERE academic_session_id = ? AND term_id = ? AND class_id = ?");
    $checkStmt->execute([$sessionId, $termId, $classId]);
    if ($checkStmt->fetch()) {
        throw new Exception("A fee structure already exists for this Session, Term, and Class combination.");
    }

    // 1b. Verify term belongs to session
    $termStmt = $pdo->prepare("SELECT academic_session_id FROM terms WHERE id = ?");
    $termStmt->execute([$termId]);
    if ($termStmt->fetchColumn() != $sessionId) {
        throw new Exception("The selected term does not belong to the selected academic session.");
    }

    // 2. Calculate total (derived from items as per requirements)
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += (float)$item['amount'];
    }

    // 3. Insert Fee Structure
    $stmt = $pdo->prepare("INSERT INTO fee_structures (academic_session_id, term_id, class_id, name, total_amount, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$sessionId, $termId, $classId, $name, $totalAmount]);
    $structureId = $pdo->lastInsertId();

    // 4. Insert Items
    $itemStmt = $pdo->prepare("INSERT INTO fee_items (fee_structure_id, name, amount) VALUES (?, ?, ?)");
    foreach ($items as $item) {
        $itemStmt->execute([$structureId, trim($item['name']), (float)$item['amount']]);
    }

    // 5. Audit Log
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'FEE_STRUCTURE_CREATED', 'fee_structure', ?, ?)")
        ->execute([current_user_id(), $structureId, "Created fee structure '{$name}' totaling {$totalAmount}"]);

    $pdo->commit();
    set_flash('success', "Fee structure '{$name}' created successfully.");
    redirect('admin/finance/fee_structures.php');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect('admin/finance/fee_structures_create.php');
}
