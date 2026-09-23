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

$pageTitle = 'Receipt ' . $receipt['receipt_number'];
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="bg-gray-100 min-h-screen py-10 no-print">
    <div class="max-w-3xl mx-auto flex justify-between items-center mb-6">
        <a href="<?= url('bursar/payments/index.php') ?>" class="text-school-blue hover:underline">&larr; Back to Payments</a>
        <button onclick="window.print()" class="bg-school-navy text-white px-4 py-2 rounded shadow flex items-center gap-2 hover:bg-school-blue transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Receipt
        </button>
    </div>

    <!-- Printable Area -->
    <div class="print-only max-w-3xl mx-auto bg-white p-12 shadow-lg border-t-8 border-t-school-blue rounded">
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-gray-200 pb-8 mb-8">
            <div class="flex items-center gap-4">
                <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Logo" class="h-20 w-auto">
                <div>
                    <h1 class="text-3xl font-serif font-bold text-school-navy uppercase tracking-wider">Shoheed Schools</h1>
                    <p class="text-sm font-bold tracking-[0.2em] text-school-gold uppercase mt-1">God Is Sufficient</p>
                    <p class="text-xs text-gray-500 mt-3">5, Olufunmilayo Close, Opposite Jendol Super Store,<br>Alakuko/Alagbado, Lagos.</p>
                    <p class="text-xs text-gray-500 mt-1">Tel: +234 802 872 1204</p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-4xl font-serif text-gray-200 font-bold uppercase tracking-widest mb-2">Receipt</h2>
                <p class="text-lg font-bold text-charcoal"><?= e($receipt['receipt_number']) ?></p>
                <p class="text-sm text-gray-500 mt-1">Date: <?= date('d M Y, h:i A', strtotime($receipt['generated_at'])) ?></p>
            </div>
        </div>

        <!-- Student & Academic Info -->
        <div class="grid grid-cols-2 gap-12 mb-10">
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Student Details</h3>
                <p class="font-bold text-lg text-charcoal"><?= e($receipt['last_name'] . ', ' . $receipt['first_name'] . ' ' . $receipt['middle_name']) ?></p>
                <p class="text-sm text-gray-600 mt-1">Admission No: <span class="font-medium text-charcoal"><?= e($receipt['admission_number']) ?></span></p>
                <p class="text-sm text-gray-600 mt-1">Class: <span class="font-medium text-charcoal"><?= e($receipt['class_name'] ?? 'N/A') ?></span></p>
            </div>
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Academic Context</h3>
                <p class="text-sm text-gray-600">Session: <span class="font-medium text-charcoal"><?= e($receipt['session_name']) ?></span></p>
                <p class="text-sm text-gray-600 mt-1">Term: <span class="font-medium text-charcoal"><?= e($receipt['term_name']) ?></span></p>
                <p class="text-sm text-gray-600 mt-1">Invoice Ref: <span class="font-medium text-charcoal">INV-<?= str_pad($receipt['invoice_id'], 6, '0', STR_PAD_LEFT) ?></span></p>
            </div>
        </div>

        <!-- Payment Details -->
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Payment Details</h3>
        <table class="w-full mb-10 border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                    <th class="py-3 px-4 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100">
                    <td class="py-4 px-4">
                        <p class="font-medium text-charcoal">Payment for <?= e($receipt['fee_structure_name'] ?? 'School Fees') ?></p>
                        <p class="text-xs text-gray-500 mt-1">Method: <?= e($receipt['payment_method']) ?> <?= $receipt['payment_reference'] ? '| Ref: ' . e($receipt['payment_reference']) : '' ?></p>
                    </td>
                    <td class="py-4 px-4 text-right font-bold text-lg text-charcoal">
                        ₦<?= number_format((float)$receipt['payment_amount'], 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="flex justify-end mb-16">
            <div class="w-1/2 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Invoice Total:</span>
                    <span class="font-medium">₦<?= number_format((float)$receipt['invoice_total'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Total Paid (including this receipt):</span>
                    <span class="font-medium text-green-600">₦<?= number_format((float)$receipt['paid_to_date'], 2) ?></span>
                </div>
                <div class="flex justify-between text-base font-bold pt-3 border-t border-gray-200">
                    <span class="text-gray-700">Remaining Balance:</span>
                    <span class="<?= $balance > 0 ? 'text-red-600' : 'text-gray-800' ?>">₦<?= number_format($balance, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 pt-8 flex justify-between text-xs text-gray-400">
            <p>Recorded by: <?= e($receipt['recorded_by_user'] ?? 'System') ?></p>
            <p>Generated automatically by Shoheed Schools Portal</p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
