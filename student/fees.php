<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$pageTitle = 'My Fees & Payments';
$pdo = get_db_connection();
$userId = current_user_id();

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

// 1. Get Invoices & Summary
$stmt = $pdo->prepare("
    SELECT si.*,
           a.name as session_name, t.name as term_name,
           (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.student_invoice_id = si.id) as amount_paid,
           (SELECT COALESCE(SUM(ps.amount), 0) FROM payment_submissions ps WHERE ps.invoice_id = si.id AND ps.status = 'PENDING') as amount_pending
    FROM student_invoices si
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    WHERE si.student_id = :sid
    ORDER BY si.id DESC
");
$stmt->execute(['sid' => $student['id']]);
$invoices = $stmt->fetchAll();

$totalFees = 0;
$totalPaid = 0;
$totalPending = 0;
$outstandingInvoices = [];

foreach ($invoices as $inv) {
    $totalFees += $inv['total_amount'];
    $totalPaid += $inv['amount_paid'];
    $totalPending += $inv['amount_pending'];
    if ($inv['total_amount'] - $inv['amount_paid'] > 0) {
        $outstandingInvoices[] = $inv;
    }
}
$outstandingBalance = max(0, $totalFees - $totalPaid);

// 2. Get Bank Accounts
$bankAccounts = $pdo->query("SELECT * FROM school_bank_accounts WHERE is_active = 1 ORDER BY display_order ASC, bank_name ASC")->fetchAll();

// 3. Get Payment Submissions (Pending/Rejected)
$subStmt = $pdo->prepare("
    SELECT ps.*, si.total_amount, a.name as session_name, t.name as term_name
    FROM payment_submissions ps
    JOIN student_invoices si ON ps.invoice_id = si.id
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    WHERE ps.student_id = :sid AND ps.status IN ('PENDING', 'REJECTED')
    ORDER BY ps.submitted_at DESC
");
$subStmt->execute(['sid' => $student['id']]);
$submissions = $subStmt->fetchAll();

// 4. Get Verified Payments (and Receipts)
$payStmt = $pdo->prepare("
    SELECT p.*, si.total_amount, a.name as session_name, t.name as term_name, r.receipt_number
    FROM payments p
    JOIN student_invoices si ON p.student_invoice_id = si.id
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    WHERE si.student_id = :sid
    ORDER BY p.payment_date DESC, p.created_at DESC
");
$payStmt->execute(['sid' => $student['id']]);
$payments = $payStmt->fetchAll();

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">My Fees & Payments</h1>
                <p class="text-sm text-slate-gray">View fee summaries, submit payment proofs, and download receipts.</p>
            </div>
            
            <!-- 1. Fee Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-t-4 border-t-school-navy">
                    <p class="text-sm font-semibold text-slate-gray uppercase tracking-wider mb-1">Total Fees</p>
                    <p class="text-2xl font-bold text-school-navy">&#8358;<?= number_format($totalFees, 2) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-t-4 border-t-sage">
                    <p class="text-sm font-semibold text-slate-gray uppercase tracking-wider mb-1">Amount Paid</p>
                    <p class="text-2xl font-bold text-sage">&#8358;<?= number_format($totalPaid, 2) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-t-4 <?= $outstandingBalance > 0 ? 'border-t-school-red' : 'border-t-sage' ?>">
                    <p class="text-sm font-semibold text-slate-gray uppercase tracking-wider mb-1">Outstanding Balance</p>
                    <p class="text-2xl font-bold <?= $outstandingBalance > 0 ? 'text-school-red' : 'text-sage' ?>">&#8358;<?= number_format($outstandingBalance, 2) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-t-4 border-t-school-gold">
                    <p class="text-sm font-semibold text-slate-gray uppercase tracking-wider mb-1">Pending Verification</p>
                    <p class="text-2xl font-bold text-school-gold">&#8358;<?= number_format($totalPending, 2) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div>
                    <!-- 2. Outstanding Invoices -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Outstanding Invoices</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($outstandingInvoices)): ?>
                                <p class="text-sage font-medium">All invoices are fully paid.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($outstandingInvoices as $inv): ?>
                                        <div class="border border-gray-100 rounded-lg p-4 bg-gray-50">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <p class="font-bold text-school-navy"><?= e($inv['term_name']) ?> School Fees</p>
                                                    <p class="text-xs text-slate-gray">Session: <?= e($inv['session_name']) ?></p>
                                                </div>
                                                <span class="text-school-red font-bold">&#8358;<?= number_format($inv['total_amount'] - $inv['amount_paid'], 2) ?></span>
                                            </div>
                                            <div class="flex justify-between text-xs text-slate-gray">
                                                <span>Total: &#8358;<?= number_format($inv['total_amount'], 2) ?></span>
                                                <span>Paid: &#8358;<?= number_format($inv['amount_paid'], 2) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 3. School Bank Account Details -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Pay School Fees Via Bank Transfer</h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <?php if (empty($bankAccounts)): ?>
                                <p class="text-slate-gray">No bank accounts configured.</p>
                            <?php else: ?>
                                <?php foreach ($bankAccounts as $bank): ?>
                                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-5">
                                        <div class="mb-3">
                                            <p class="text-xs font-bold text-school-blue uppercase tracking-wider">Bank Name</p>
                                            <p class="font-bold text-charcoal"><?= e($bank['bank_name']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <p class="text-xs font-bold text-school-blue uppercase tracking-wider">Account Name</p>
                                            <p class="font-bold text-charcoal"><?= e($bank['account_name']) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-school-blue uppercase tracking-wider">Account Number</p>
                                            <div class="flex items-center space-x-3 mt-1">
                                                <p class="text-xl font-bold text-school-navy tracking-widest" id="acc-<?= $bank['id'] ?>"><?= e($bank['account_number']) ?></p>
                                                <button onclick="copyAccount('<?= e($bank['account_number']) ?>', this)" class="bg-white border border-school-blue text-school-blue text-xs font-bold px-3 py-1 rounded hover:bg-school-blue hover:text-white transition-colors">Copy</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- 4. Simple Payment Instructions -->
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 text-sm text-charcoal space-y-2 mt-6">
                                <p class="font-bold mb-2">Instructions:</p>
                                <ol class="list-decimal list-inside space-y-1">
                                    <li>Transfer the required amount to one of the accounts above.</li>
                                    <li>Keep your transfer receipt/screenshot.</li>
                                    <li>Select the invoice you paid for below.</li>
                                    <li>Upload the proof of payment.</li>
                                    <li>The Bursar will verify the payment.</li>
                                    <li>Your official receipt becomes available after verification.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <!-- 5. Submit Payment Proof -->
                    <div id="submit-proof" class="bg-paper rounded-lg shadow-sm border border-gray-100 mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Submit Payment Proof</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($outstandingInvoices)): ?>
                                <p class="text-sage font-medium">You have no outstanding invoices to pay for.</p>
                            <?php else: ?>
                                <form action="<?= url('actions/payments/submit_proof.php') ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                                    <?= csrf_field() ?>
                                    
                                    <div>
                                        <label for="invoice_id" class="block text-sm font-medium text-charcoal mb-1">Payment For (Select Invoice) *</label>
                                        <select name="invoice_id" id="invoice_id" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                            <option value="">-- Select Invoice --</option>
                                            <?php foreach ($outstandingInvoices as $inv): ?>
                                                <option value="<?= $inv['id'] ?>"><?= e($inv['term_name']) ?> School Fees (Outstanding: &#8358;<?= number_format($inv['total_amount'] - $inv['amount_paid'], 2) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="amount" class="block text-sm font-medium text-charcoal mb-1">Amount Paid (&#8358;) *</label>
                                            <input type="number" step="0.01" name="amount" id="amount" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                        </div>
                                        <div>
                                            <label for="payment_date" class="block text-sm font-medium text-charcoal mb-1">Payment Date *</label>
                                            <input type="date" name="payment_date" id="payment_date" max="<?= date('Y-m-d') ?>" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="sender_bank" class="block text-sm font-medium text-charcoal mb-1">Sender Bank *</label>
                                            <input type="text" name="sender_bank" id="sender_bank" required placeholder="e.g. GTBank, FirstBank" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                        </div>
                                        <div>
                                            <label for="transfer_reference" class="block text-sm font-medium text-charcoal mb-1">Transfer Reference</label>
                                            <input type="text" name="transfer_reference" id="transfer_reference" placeholder="Optional" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="proof" class="block text-sm font-medium text-charcoal mb-1">Proof of Payment (Image/PDF) *</label>
                                        <input type="file" name="proof" id="proof" accept="image/jpeg,image/png,application/pdf" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                        <p class="text-xs text-slate-gray mt-1">Max size: 5MB. Formats: JPG, PNG, PDF.</p>
                                    </div>
                                    
                                    <div>
                                        <label for="note" class="block text-sm font-medium text-charcoal mb-1">Optional Note</label>
                                        <textarea name="note" id="note" rows="2" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-school-blue focus:border-school-blue sm:text-sm"></textarea>
                                    </div>
                                    
                                    <div class="pt-2">
                                        <button type="submit" class="w-full bg-school-blue text-white px-4 py-3 rounded-md font-bold hover:bg-school-navy transition-colors shadow-sm">
                                            Submit Payment Proof
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Pending / Rejected Submissions -->
            <?php if (!empty($submissions)): ?>
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 mb-8">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Pending & Rejected Submissions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                        <?= date('M d, Y', strtotime($sub['submitted_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                        <?= e($sub['term_name']) ?> Fees
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-charcoal">
                                        &#8358;<?= number_format($sub['amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if ($sub['status'] === 'PENDING'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Waiting for Verification
                                            </span>
                                        <?php else: ?>
                                            <div class="flex flex-col">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 w-max">
                                                    Rejected
                                                </span>
                                                <?php if ($sub['review_note']): ?>
                                                    <span class="text-xs text-school-red mt-1 font-medium">Reason: <?= e($sub['review_note']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- 7. Verified Payments & Receipts -->
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 mb-8">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Verified Payments & Receipts</h3>
                </div>
                <?php if (empty($payments)): ?>
                    <div class="p-8 text-center text-slate-gray">
                        <p>No verified payments found.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Method</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-school-navy uppercase tracking-wider">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($payments as $pay): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                            <?= date('M d, Y', strtotime($pay['payment_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                            <?= e($pay['term_name']) ?> Fees
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-sage">
                                            &#8358;<?= number_format($pay['amount'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                            <?= e($pay['payment_method']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <?php if ($pay['receipt_number']): ?>
                                                <a href="<?= url('student/receipt.php?id=' . $pay['id']) ?>" class="text-school-blue hover:text-school-navy font-medium">View Receipt</a>
                                            <?php else: ?>
                                                <span class="text-slate-gray">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
    </div>
</div>

<script>
function copyAccount(number, btn) {
    navigator.clipboard.writeText(number).then(() => {
        const originalText = btn.innerText;
        btn.innerText = 'Copied!';
        btn.classList.add('bg-school-blue', 'text-white');
        btn.classList.remove('bg-white', 'text-school-blue');
        setTimeout(() => {
            btn.innerText = originalText;
            btn.classList.remove('bg-school-blue', 'text-white');
            btn.classList.add('bg-white', 'text-school-blue');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy', err);
    });
}
</script>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
