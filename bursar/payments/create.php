<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pageTitle = 'Record Payment';
$pdo = get_db_connection();

$invoiceId = filter_input(INPUT_GET, 'invoice', FILTER_VALIDATE_INT);
if (!$invoiceId) {
    redirect('bursar/invoices/index.php');
}

$stmt = $pdo->prepare("
    SELECT si.*, s.first_name, s.last_name, s.admission_number, c.name as class_name,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id) as amount_paid
    FROM student_invoices si
    JOIN students s ON si.student_id = s.id
    JOIN student_enrollments se ON s.id = se.student_id AND se.academic_session_id = si.academic_session_id
    JOIN classes c ON se.class_id = c.id
    WHERE si.id = :id
");
$stmt->execute(['id' => $invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    redirect('bursar/invoices/index.php');
}

$total = (float)$invoice['total_amount'];
$paid = (float)$invoice['amount_paid'];
$balance = max(0, $total - $paid);

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Record Payment</h1>
                    <p class="text-sm text-slate-gray">Log a new payment and generate a receipt.</p>
                </div>
                <div>
                    <a href="<?= url('bursar/invoices/index.php') ?>" class="text-slate-gray hover:text-school-navy text-sm font-medium transition-colors">&larr; Back to Invoices</a>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Summary -->
                <div class="md:col-span-1">
                    <div class="bg-school-navy text-white p-6 rounded-lg shadow-sm border border-school-navy/80 mb-6">
                        <h3 class="text-sm tracking-widest uppercase font-bold text-school-gold mb-4">Student Details</h3>
                        <p class="font-serif font-bold text-xl mb-1"><?= e($invoice['last_name'] . ' ' . $invoice['first_name']) ?></p>
                        <p class="text-sm text-gray-300 mb-1"><?= e($invoice['admission_number']) ?></p>
                        <p class="text-sm text-gray-300"><?= e($invoice['class_name']) ?></p>
                        
                        <div class="mt-6 pt-6 border-t border-gray-700">
                            <div class="flex justify-between mb-2 text-sm">
                                <span class="text-gray-300">Total Invoice:</span>
                                <span>&#8358;<?= number_format($total, 2) ?></span>
                            </div>
                            <div class="flex justify-between mb-2 text-sm">
                                <span class="text-gray-300">Amount Paid:</span>
                                <span class="text-green-400">&#8358;<?= number_format($paid, 2) ?></span>
                            </div>
                            <div class="flex justify-between mt-4 pt-4 border-t border-gray-700 font-bold text-lg">
                                <span class="text-school-gold">Balance Due:</span>
                                <span>&#8358;<?= number_format($balance, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Form -->
                <div class="md:col-span-2">
                    <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100">
                        <?php if ($balance <= 0): ?>
                            <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded text-sm mb-4">
                                This invoice is fully paid. No further payments can be recorded.
                            </div>
                        <?php else: ?>
                        <form action="<?= url('actions/finance/record_payment.php') ?>" method="POST" class="space-y-6">
                            <?= csrf_field() ?>
                            <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-charcoal">Payment Amount (&#8358;) *</label>
                                    <input type="number" step="0.01" max="<?= $balance ?>" name="amount" id="amount" value="<?= $balance ?>" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm font-bold text-school-navy">
                                </div>
                                <div>
                                    <label for="payment_date" class="block text-sm font-medium text-charcoal">Payment Date *</label>
                                    <input type="date" name="payment_date" id="payment_date" value="<?= date('Y-m-d') ?>" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="payment_method" class="block text-sm font-medium text-charcoal">Payment Method *</label>
                                    <select id="payment_method" name="payment_method" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="POS">POS Terminal</option>
                                        <option value="Bank Deposit">Bank Deposit</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_reference" class="block text-sm font-medium text-charcoal">Reference / Teller No.</label>
                                    <input type="text" name="payment_reference" id="payment_reference" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                </div>
                            </div>
                            
                            <div class="pt-5 border-t border-gray-200 mt-8 flex justify-end">
                                <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm inline-flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Log Payment & Generate Receipt
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
