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

$pdo = get_db_connection();

// Determine if used
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_invoices WHERE fee_structure_id = ?");
$stmt->execute([$id]);
$isUsed = $stmt->fetchColumn() > 0;

if ($isUsed) {
    // If used, fetch old values for locked fields
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE id = ?");
    $stmt->execute([$id]);
    $oldStructure = $stmt->fetch();

    $name = $oldStructure['name'];
    $sessionId = $oldStructure['academic_session_id'];
    $termId = $oldStructure['term_id'];
    $classId = $oldStructure['class_id'];
} else {
    $name = trim($_POST['name'] ?? '');
    $sessionId = filter_var($_POST['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
    $termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
    $classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);

    if (empty($name) || !$sessionId || !$termId || !$classId) {
        set_flash('error', 'Please fill all required fields.');
        redirect("admin/finance/fee_structure_edit.php?id=$id");
    }

    // Verify term belongs to session
    $termStmt = $pdo->prepare("SELECT academic_session_id FROM terms WHERE id = ?");
    $termStmt->execute([$termId]);
    if ($termStmt->fetchColumn() != $sessionId) {
        set_flash('error', 'The selected term does not belong to the selected academic session.');
        redirect("admin/finance/fee_structure_edit.php?id=$id");
    }
}

$items = $_POST['items'] ?? [];
if (empty($items) || !is_array($items)) {
    set_flash('error', 'At least one fee item is required.');
    redirect("admin/finance/fee_structure_edit.php?id=$id");
}

$totalAmount = 0.0;
$validItems = [];

foreach ($items as $item) {
    $itemName = trim($item['name'] ?? '');
    $itemAmount = filter_var($item['amount'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (empty($itemName) || $itemAmount === false || $itemAmount < 0) {
        set_flash('error', 'Invalid item name or amount provided.');
        redirect("admin/finance/fee_structure_edit.php?id=$id");
    }

    $totalAmount += $itemAmount;
    $validItems[] = [
        'id' => filter_var($item['id'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'name' => $itemName,
        'amount' => $itemAmount
    ];
}

try {
    $pdo->beginTransaction();

    if (!$isUsed) {
        // Update structure identity
        $stmt = $pdo->prepare("UPDATE fee_structures SET name = ?, academic_session_id = ?, term_id = ?, class_id = ?, total_amount = ? WHERE id = ?");
        $stmt->execute([$name, $sessionId, $termId, $classId, $totalAmount, $id]);
    } else {
        // Only update total amount
        $stmt = $pdo->prepare("UPDATE fee_structures SET total_amount = ? WHERE id = ?");
        $stmt->execute([$totalAmount, $id]);
    }

    // Replace items
    // First, identify items not in the new valid items list (if they had IDs)
    $validIds = array_filter(array_column($validItems, 'id'));

    // Select items to be removed
    if (empty($validIds)) {
        $itemsToRemoveStmt = $pdo->prepare("SELECT id FROM fee_items WHERE fee_structure_id = ?");
        $itemsToRemoveStmt->execute([$id]);
    } else {
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $itemsToRemoveStmt = $pdo->prepare("SELECT id FROM fee_items WHERE fee_structure_id = ? AND id NOT IN ($placeholders)");
        $params = array_merge([$id], $validIds);
        $itemsToRemoveStmt->execute($params);
    }

    $itemsToRemove = $itemsToRemoveStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($itemsToRemove)) {
        $checkBilledStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE source_fee_item_id = ?");
        $deleteItemStmt = $pdo->prepare("DELETE FROM fee_items WHERE id = ?");
        $archiveItemStmt = $pdo->prepare("UPDATE fee_items SET is_active = 0 WHERE id = ?");

        foreach ($itemsToRemove as $itemId) {
            $checkBilledStmt->execute([$itemId]);
            if ($checkBilledStmt->fetchColumn() > 0) {
                // Already billed, archive it
                $archiveItemStmt->execute([$itemId]);
            } else {
                // Never billed, safe to hard delete
                $deleteItemStmt->execute([$itemId]);
            }
        }
    }

    // Insert or update valid items
    foreach ($validItems as $item) {
        if ($item['id']) {
            $stmt = $pdo->prepare("UPDATE fee_items SET name = ?, amount = ?, is_active = 1 WHERE id = ? AND fee_structure_id = ?");
            $stmt->execute([$item['name'], $item['amount'], $item['id'], $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO fee_items (fee_structure_id, name, amount) VALUES (?, ?, ?)");
            $stmt->execute([$id, $item['name'], $item['amount']]);
        }
    }

    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'FEE_STRUCTURE_UPDATED',
        'fee_structure',
        $id,
        "Edited fee structure ID {$id} ({$name}). Total is now {$totalAmount}."
    ]);

    $pdo->commit();
    set_flash('success', 'Fee structure updated successfully.');
    redirect('admin/finance/fee_structures.php');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Database error: ' . $e->getMessage());
    redirect("admin/finance/fee_structure_edit.php?id=$id");
}
