<?php

/**
 * core/finance.php
 * Canonical finance logic for the application.
 */

/**
 * Generates a deterministic, collision-free receipt number from an auto-increment payment ID.
 */
function format_receipt_number(int $paymentId): string {
    return 'REC-' . date('ym') . '-' . str_pad((string)$paymentId, 5, '0', STR_PAD_LEFT);
}

/**
 * Recalculates and updates a student invoice's status ('PENDING', 'PARTIAL', 'PAID')
 * based on verified payments vs. total_amount.
 */
function recalculate_invoice_status(PDO $pdo, int $invoiceId): string {
    $invStmt = $pdo->prepare("SELECT total_amount, status FROM student_invoices WHERE id = ?");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();
    if (!$inv) {
        throw new Exception("Invoice not found.");
    }

    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = ?");
    $paidStmt->execute([$invoiceId]);
    $paid = (float)$paidStmt->fetchColumn();
    $total = (float)$inv['total_amount'];

    if ($paid <= 0) {
        $newStatus = 'PENDING';
    } elseif ($paid < $total) {
        $newStatus = 'PARTIAL';
    } else {
        $newStatus = 'PAID';
    }

    if ($inv['status'] !== $newStatus) {
        $updStmt = $pdo->prepare("UPDATE student_invoices SET status = ? WHERE id = ?");
        $updStmt->execute([$newStatus, $invoiceId]);
    }

    return $newStatus;
}

/**
 * Records an official verified payment and generates a receipt.
 * 
 * Must be called within a database transaction context if caller is combining actions.
 * 
 * @param PDO $pdo
 * @param int $invoiceId The student_invoices.id
 * @param float $amount The payment amount to record
 * @param string $paymentMethod The payment method (e.g. 'Bank Transfer', 'Cash', 'POS')
 * @param string|null $paymentReference Optional payment reference
 * @param string $paymentDate The date of the payment (e.g. 'Y-m-d')
 * @param int $recordedBy The user ID of the staff member recording the payment
 * @return array Returns an array with ['payment_id' => int, 'receipt_number' => string] on success
 * @throws Exception If validation fails or amount exceeds outstanding balance
 */
function record_official_payment(PDO $pdo, int $invoiceId, float $amount, string $paymentMethod, ?string $paymentReference, string $paymentDate, int $recordedBy): array {
    // 1. Lock invoice (if not already locked by caller, this is a safe re-lock)
    $stmt = $pdo->prepare("SELECT * FROM student_invoices WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $invoiceId]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception("Invoice not found.");
    }
    
    // 2. Verify amount doesn't exceed balance
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = :id");
    $paidStmt->execute(['id' => $invoiceId]);
    $currentPaid = (float)$paidStmt->fetchColumn();
    $total = (float)$invoice['total_amount'];
    
    $balance = $total - $currentPaid;
    
    if ($amount <= 0 || $amount > $balance) {
        throw new Exception("Invalid payment amount. Maximum allowed is " . number_format($balance, 2) . ".");
    }
    
    // 3. Insert Payment
    $stmt = $pdo->prepare("INSERT INTO payments (student_invoice_id, amount, payment_method, payment_reference, payment_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$invoiceId, $amount, $paymentMethod, $paymentReference !== '' ? $paymentReference : null, $paymentDate, $recordedBy]);
    $paymentId = $pdo->lastInsertId();
    
    // 4. Generate Receipt
    $receiptNo = format_receipt_number((int)$paymentId);
    $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)");
    $stmt->execute([$paymentId, $receiptNo]);
    
    // 5. Update Invoice Status
    recalculate_invoice_status($pdo, $invoiceId);
    
    // 6. Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $recordedBy,
        'RECORD_PAYMENT',
        'payment',
        $paymentId,
        "Recorded payment of {$amount} for invoice {$invoiceId}. Receipt: {$receiptNo}"
    ]);
    
    // 7. Notify Student Only
    $stStmt = $pdo->prepare("
        SELECT u.id as student_user_id
        FROM student_invoices si
        JOIN students st ON si.student_id = st.id
        LEFT JOIN users u ON st.user_id = u.id
        WHERE si.id = ?
    ");
    $stStmt->execute([$invoiceId]);
    $audience = $stStmt->fetch();
    
    if ($audience && $audience['student_user_id']) {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $notifStmt->execute([$audience['student_user_id'], "Payment Received", "A payment of ₦{$amount} was recorded. Receipt: {$receiptNo}"]);
    }
    
    return [
        'payment_id' => $paymentId,
        'receipt_number' => $receiptNo
    ];
}

/**
 * Evaluates whether a student has financial clearance to view their academic results.
 * 
 * Rules:
 *  1. Only "Tuition" fees are evaluated (items with name matching "tuition").
 *  2. Unpaid tuition debt from prior terms/sessions must be 100% settled.
 *  3. Current term tuition must be at least 70% paid.
 *  4. An active waiver granted by Proprietor (Admin), Principal, or Bursar
 *     overrides any financial restriction.
 * 
 * @param PDO $pdo
 * @param int $studentId
 * @param int $sessionId
 * @param int $termId
 * @return array
 */
function check_student_tuition_result_clearance(PDO $pdo, int $studentId, int $sessionId, int $termId): array {
    // 1. Check for Active Waiver first
    $waiverStmt = $pdo->prepare("
        SELECT raw.*, u.username as granted_by_username, r.name as granted_by_role
        FROM result_access_waivers raw
        JOIN users u ON raw.granted_by = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE raw.student_id = ? 
          AND raw.academic_session_id = ? 
          AND raw.term_id = ? 
          AND raw.is_active = 1
        LIMIT 1
    ");
    $waiverStmt->execute([$studentId, $sessionId, $termId]);
    $activeWaiver = $waiverStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch current session & term chronology (with static per-request cache)
    static $sessionsCache = [];
    static $termsCache = [];

    if (!isset($sessionsCache[$sessionId])) {
        $currSessStmt = $pdo->prepare("SELECT id, name, start_date FROM academic_sessions WHERE id = ?");
        $currSessStmt->execute([$sessionId]);
        $sessionsCache[$sessionId] = $currSessStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $currSession = $sessionsCache[$sessionId];

    if (!isset($termsCache[$termId])) {
        $currTermStmt = $pdo->prepare("SELECT id, name FROM terms WHERE id = ?");
        $currTermStmt->execute([$termId]);
        $termsCache[$termId] = $currTermStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $currTerm = $termsCache[$termId];

    $termOrderMap = [
        'first term' => 1,
        'second term' => 2,
        'third term' => 3
    ];
    $currTermOrder = $termOrderMap[strtolower(trim($currTerm['name'] ?? ''))] ?? 1;
    $currSessionStart = $currSession['start_date'] ?? '2000-01-01';

    // 3. Query all invoices for this student
    $invStmt = $pdo->prepare("
        SELECT si.id, si.academic_session_id, si.term_id, si.total_amount,
               sess.name as session_name, sess.start_date as session_start_date,
               t.name as term_name,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id) as total_paid
        FROM student_invoices si
        JOIN academic_sessions sess ON si.academic_session_id = sess.id
        JOIN terms t ON si.term_id = t.id
        WHERE si.student_id = ?
        ORDER BY sess.start_date ASC, t.id ASC
    ");
    $invStmt->execute([$studentId]);
    $allInvoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Query all invoice items for these invoices
    $itemsByInvoice = [];
    if (!empty($allInvoices)) {
        $invoiceIds = array_column($allInvoices, 'id');
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $itemStmt = $pdo->prepare("SELECT student_invoice_id, name, amount FROM invoice_items WHERE student_invoice_id IN ($placeholders)");
        $itemStmt->execute($invoiceIds);
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $itemsByInvoice[$row['student_invoice_id']][] = $row;
        }
    }

    $priorTuitionInvoiced = 0.0;
    $priorTuitionPaid = 0.0;
    $priorTuitionDebt = 0.0;

    $currentTuitionInvoiced = 0.0;
    $currentTuitionPaid = 0.0;

    foreach ($allInvoices as $inv) {
        $invId = (int)$inv['id'];
        $invSessId = (int)$inv['academic_session_id'];
        $invTermId = (int)$inv['term_id'];
        $invSessStart = $inv['session_start_date'] ?? '2000-01-01';
        $invTermName = strtolower(trim($inv['term_name'] ?? ''));
        $invTermOrder = $termOrderMap[$invTermName] ?? 1;

        // Is this the target term?
        $isCurrentTerm = ($invSessId === $sessionId && $invTermId === $termId);

        // Is this a prior term?
        $isPriorTerm = ($invSessStart < $currSessionStart) ||
                       ($invSessStart === $currSessionStart && $invTermOrder < $currTermOrder);

        // Calculate tuition portion on this invoice
        $items = $itemsByInvoice[$invId] ?? [];
        $tuitionPortion = 0.0;
        foreach ($items as $it) {
            if (stripos($it['name'], 'tuition') !== false) {
                $tuitionPortion += (float)$it['amount'];
            }
        }
        // Fallback: If no line item named 'tuition', and standard fee, treat total_amount as tuition
        if ($tuitionPortion <= 0 && empty($items)) {
            $tuitionPortion = (float)$inv['total_amount'];
        }

        $totalPaidOnInvoice = (float)$inv['total_paid'];
        // Payments apply to tuition first
        $tuitionPaidOnInvoice = min($tuitionPortion, $totalPaidOnInvoice);
        $tuitionDebtOnInvoice = max(0.0, $tuitionPortion - $tuitionPaidOnInvoice);

        if ($isCurrentTerm) {
            $currentTuitionInvoiced += $tuitionPortion;
            $currentTuitionPaid += $tuitionPaidOnInvoice;
        } elseif ($isPriorTerm) {
            $priorTuitionInvoiced += $tuitionPortion;
            $priorTuitionPaid += $tuitionPaidOnInvoice;
            $priorTuitionDebt += $tuitionDebtOnInvoice;
        }
    }

    $currentPercentage = $currentTuitionInvoiced > 0
        ? round(($currentTuitionPaid / $currentTuitionInvoiced) * 100, 1)
        : 100.0;

    $currentRequired70 = round($currentTuitionInvoiced * 0.70, 2);
    $neededForCurrent = max(0.0, $currentRequired70 - $currentTuitionPaid);
    $totalNeededToUnlock = round($priorTuitionDebt + $neededForCurrent, 2);

    $isThresholdMet = ($priorTuitionDebt <= 0.01) && ($currentPercentage >= 70.0 || $currentTuitionInvoiced <= 0);
    $isCleared = false;
    $clearanceReason = 'INSUFFICIENT_PAYMENT';

    if ($activeWaiver) {
        $isCleared = true;
        $clearanceReason = 'WAIVER_GRANTED';
    } elseif ($isThresholdMet) {
        $isCleared = true;
        $clearanceReason = ($currentTuitionInvoiced <= 0 && $priorTuitionInvoiced <= 0) ? 'NO_TUITION_INVOICED' : 'THRESHOLD_MET';
    }

    return [
        'is_cleared'               => $isCleared,
        'clearance_reason'         => $clearanceReason,
        'has_active_waiver'        => !empty($activeWaiver),
        'waiver'                   => $activeWaiver ?: null,
        'current_tuition_invoiced' => $currentTuitionInvoiced,
        'current_tuition_paid'     => $currentTuitionPaid,
        'current_percentage'       => $currentPercentage,
        'required_percentage'      => 70,
        'prior_tuition_debt'       => $priorTuitionDebt,
        'needed_for_prior'         => $priorTuitionDebt,
        'needed_for_current'       => $neededForCurrent,
        'total_needed_to_unlock'   => $totalNeededToUnlock,
    ];
}
