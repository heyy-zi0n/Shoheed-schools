<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_BURSAR, ROLE_STUDENT]);

$receiptId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$receiptId) {
    http_response_code(404);
    exit('Receipt not found.');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT r.id AS receipt_id, r.receipt_number, r.generated_at,
           p.amount AS payment_amount, p.payment_method, p.payment_reference, p.payment_date,
           u.username AS recorded_by_user,
           si.id AS invoice_id, si.total_amount AS invoice_total,
           st.first_name, st.middle_name, st.last_name, st.admission_number, st.id AS student_id,
           sess.name AS session_name,
           t.name AS term_name,
           fs.name AS fee_structure_name,
           c.name AS class_name,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id AND payment_date <= p.payment_date AND id <= p.id) AS paid_to_date
    FROM receipts r
    JOIN payments p ON r.payment_id = p.id
    JOIN student_invoices si ON p.student_invoice_id = si.id
    JOIN students st ON si.student_id = st.id
    JOIN academic_sessions sess ON si.academic_session_id = sess.id
    JOIN terms t ON si.term_id = t.id
    LEFT JOIN fee_structures fs ON si.fee_structure_id = fs.id
    LEFT JOIN classes c ON fs.class_id = c.id
    LEFT JOIN users u ON p.recorded_by = u.id
    WHERE r.id = ?
");
$stmt->execute([$receiptId]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

// Authorization check for Students/Parents
$role = current_user_role();
if ($role === ROLE_STUDENT) {
    // Must be their own receipt
    $userStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $userStmt->execute([current_user_id()]);
    $myStudentId = $userStmt->fetchColumn();
    if ($receipt['student_id'] != $myStudentId) {
        http_response_code(403);
        exit('Access Denied.');
    }
}

$balance = max(0, $receipt['invoice_total'] - $receipt['paid_to_date']);
$backUrl = ($role === ROLE_STUDENT) ? url('student/fees.php') : url('bursar/payments/index.php');

$pageTitle = 'Receipt ' . $receipt['receipt_number'];
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="bg-gray-100 min-h-screen py-6 sm:py-10 px-3 sm:px-6 print:bg-white print:py-0 print:px-0">
    <div class="max-w-3xl mx-auto flex justify-between items-center mb-4 sm:mb-6 px-1 sm:px-0 no-print">
        <a href="<?= e($backUrl) ?>" class="text-school-blue hover:underline text-xs sm:text-sm font-semibold inline-flex items-center gap-1.5">
            <span aria-hidden="true">&larr;</span>
            <span>Back to Payments</span>
        </a>
        <button type="button" onclick="window.print()" class="bg-school-navy text-white px-3 sm:px-4 py-1.5 sm:py-2 rounded-md shadow-sm flex items-center gap-2 hover:bg-school-blue transition-colors text-xs sm:text-sm font-semibold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            <span>Print Receipt</span>
        </button>
    </div>

    <!-- Printable Area -->
    <div class="print-only max-w-3xl w-full mx-auto bg-white p-4 sm:p-8 md:p-12 shadow-lg print:shadow-none border-t-8 border-t-school-blue rounded-xl print:rounded-none">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between sm:items-start gap-4 sm:gap-6 border-b border-gray-200 pb-6 sm:pb-8 mb-6 sm:mb-8">
            <div class="flex items-start gap-4">
                <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Logo" class="h-14 w-auto sm:h-20 object-contain flex-shrink-0">
                <div>
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-serif font-bold text-school-navy uppercase tracking-wider leading-tight">Shoheed Schools</h1>
                    <p class="text-xs sm:text-sm font-bold tracking-[0.2em] text-school-gold uppercase mt-1">God Is Sufficient</p>
                    <p class="text-xs text-gray-500 mt-2 sm:mt-3 leading-relaxed">5, Olufunmilayo Close, Opposite Jendol Super Store,<br>Alakuko/Alagbado, Lagos.</p>
                    <p class="text-xs text-gray-500 mt-1">Tel: +234 802 872 1204</p>
                </div>
            </div>
            <div class="sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-gray-100 flex flex-row sm:flex-col justify-between sm:justify-start items-center sm:items-end gap-1">
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-gray-400 uppercase tracking-wider">Receipt No.</h2>
                    <p class="text-base sm:text-lg font-bold text-charcoal font-mono tracking-tight"><?= e($receipt['receipt_number']) ?></p>
                </div>
                <div class="sm:mt-2 text-right">
                    <p class="text-xs text-gray-500">Date: <span class="font-medium text-charcoal"><?= date('d M Y, h:i A', strtotime($receipt['generated_at'])) ?></span></p>
                </div>
            </div>
        </div>

        <!-- Student & Academic Info -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-12 mb-8 sm:mb-10">
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 sm:mb-3">Student Details</h3>
                <p class="font-bold text-base sm:text-lg text-charcoal"><?= e($receipt['last_name'] . ', ' . $receipt['first_name'] . ' ' . $receipt['middle_name']) ?></p>
                <p class="text-xs sm:text-sm text-gray-600 mt-1">Admission No: <span class="font-medium text-charcoal"><?= e($receipt['admission_number']) ?></span></p>
                <p class="text-xs sm:text-sm text-gray-600 mt-1">Class: <span class="font-medium text-charcoal"><?= e($receipt['class_name'] ?? 'N/A') ?></span></p>
            </div>
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 sm:mb-3">Academic Context</h3>
                <p class="text-xs sm:text-sm text-gray-600">Session: <span class="font-medium text-charcoal"><?= e($receipt['session_name']) ?></span></p>
                <p class="text-xs sm:text-sm text-gray-600 mt-1">Term: <span class="font-medium text-charcoal"><?= e($receipt['term_name']) ?></span></p>
                <p class="text-xs sm:text-sm text-gray-600 mt-1">Invoice Ref: <span class="font-medium text-charcoal">INV-<?= str_pad($receipt['invoice_id'], 6, '0', STR_PAD_LEFT) ?></span></p>
            </div>
        </div>

        <!-- Payment Details -->
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 sm:mb-3">Payment Details</h3>
        <div class="overflow-x-auto mb-8 sm:mb-10">
            <table class="w-full border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2.5 sm:py-3 px-3 sm:px-4 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                        <th class="py-2.5 sm:py-3 px-3 sm:px-4 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-100">
                        <td class="py-3.5 sm:py-4 px-3 sm:px-4">
                            <p class="font-medium text-sm sm:text-base text-charcoal">Payment for <?= e($receipt['fee_structure_name'] ?? 'School Fees') ?></p>
                            <p class="text-xs text-gray-500 mt-1">Method: <?= e($receipt['payment_method']) ?> <?= $receipt['payment_reference'] ? '| Ref: ' . e($receipt['payment_reference']) : '' ?></p>
                        </td>
                        <td class="py-3.5 sm:py-4 px-3 sm:px-4 text-right font-bold text-base sm:text-lg text-charcoal tabular-nums">
                            ₦<?= number_format((float)$receipt['payment_amount'], 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="flex justify-end mb-10 sm:mb-16">
            <div class="w-full sm:w-3/4 md:w-1/2 space-y-2.5 sm:space-y-3">
                <div class="flex justify-between items-center text-xs sm:text-sm">
                    <span class="text-gray-500">Invoice Total:</span>
                    <span class="font-medium tabular-nums">₦<?= number_format((float)$receipt['invoice_total'], 2) ?></span>
                </div>
                <div class="flex justify-between items-center text-xs sm:text-sm">
                    <span class="text-gray-500">Total Paid:</span>
                    <span class="font-medium text-green-600 tabular-nums">₦<?= number_format((float)$receipt['paid_to_date'], 2) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm sm:text-base font-bold pt-3 border-t border-gray-200">
                    <span class="text-gray-700">Remaining Balance:</span>
                    <span class="<?= $balance > 0 ? 'text-red-600' : 'text-gray-800' ?> tabular-nums">₦<?= number_format($balance, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 pt-6 sm:pt-8 flex flex-col sm:flex-row justify-between gap-2 text-xs text-gray-400">
            <p>Recorded by: <?= e($receipt['recorded_by_user'] ?? 'System') ?></p>
            <p>Generated automatically by Shoheed Schools Portal</p>
        </div>
    </div>
</div>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
