<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('error', 'Invalid submission ID.');
    redirect('bursar/payments/submissions.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT ps.*, 
           s.first_name, s.last_name, s.admission_number, 
           si.total_amount, 
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id) as amount_paid,
           a.name as session_name, t.name as term_name
    FROM payment_submissions ps
    JOIN students s ON ps.student_id = s.id
    JOIN student_invoices si ON ps.invoice_id = si.id
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    WHERE ps.id = ?
");
$stmt->execute([$id]);
$sub = $stmt->fetch();

if (!$sub) {
    set_flash('error', 'Submission not found.');
    redirect('bursar/payments/submissions.php');
}

if ($sub['status'] !== 'PENDING') {
    set_flash('info', 'This submission has already been reviewed.');
    redirect('bursar/payments/submissions.php');
}

$outstanding = max(0, $sub['total_amount'] - $sub['amount_paid']);

$pageTitle = 'Review Payment Submission';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <a href="<?= url('bursar/payments/submissions.php') ?>" class="text-school-blue hover:underline text-sm mb-4 inline-block">&larr; Back to Queue</a>
                <h1 class="text-2xl font-serif font-bold text-school-navy">Review Bank Transfer</h1>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Details -->
                <div class="space-y-6">
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Student & Invoice Details</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                            <div>
                                <p class="text-slate-gray">Student Name</p>
                                <p class="font-bold text-charcoal"><?= e($sub['first_name'] . ' ' . $sub['last_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Admission No.</p>
                                <p class="font-bold text-charcoal"><?= e($sub['admission_number']) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Term & Session</p>
                                <p class="font-bold text-charcoal"><?= e($sub['term_name']) ?>, <?= e($sub['session_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Outstanding Balance</p>
                                <p class="font-bold <?= $outstanding > 0 ? 'text-school-red' : 'text-sage' ?>">₦<?= number_format($outstanding, 2) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Transfer Details Claimed</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-slate-gray">Amount</p>
                                <p class="text-xl font-bold text-school-navy">₦<?= number_format($sub['amount'], 2) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Payment Date</p>
                                <p class="font-bold text-charcoal"><?= date('M d, Y', strtotime($sub['payment_date'])) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Sender Bank</p>
                                <p class="font-bold text-charcoal"><?= e($sub['sender_bank']) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Transfer Reference</p>
                                <p class="font-bold text-charcoal"><?= e($sub['transfer_reference'] ?: 'None provided') ?></p>
                            </div>
                            <?php if ($sub['note']): ?>
                            <div class="col-span-2">
                                <p class="text-slate-gray">Student Note</p>
                                <p class="text-charcoal bg-gray-50 p-2 rounded border border-gray-200 mt-1"><?= nl2br(e($sub['note'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Actions</h3>
                        
                        <?php if ($sub['amount'] > $outstanding): ?>
                            <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded mb-4 text-sm">
                                <strong>Warning:</strong> The claimed amount (₦<?= number_format($sub['amount'], 2) ?>) is greater than the current outstanding balance (₦<?= number_format($outstanding, 2) ?>). 
                                You cannot verify an amount larger than the outstanding balance.
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex space-x-4">
                            <form action="<?= url('actions/payments/verify_submission.php') ?>" method="POST" class="flex-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                <button type="submit" class="w-full bg-sage text-white px-4 py-3 rounded font-bold hover:bg-green-700 transition-colors shadow-sm" onclick="return confirm('Verify this payment? This will record an official payment and generate a receipt.')" <?= ($sub['amount'] > $outstanding) ? 'disabled class="w-full bg-gray-400 text-white px-4 py-3 rounded font-bold cursor-not-allowed shadow-sm"' : '' ?>>
                                    Verify & Record Payment
                                </button>
                            </form>
                            
                            <button type="button" onclick="document.getElementById('reject-modal').classList.remove('hidden')" class="flex-1 bg-white border border-school-red text-school-red hover:bg-red-50 px-4 py-3 rounded font-bold transition-colors shadow-sm">
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Proof Image/PDF -->
                <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 flex flex-col h-full">
                    <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Proof of Payment</h3>
                    <div class="flex-1 bg-gray-50 rounded border border-gray-200 flex items-center justify-center overflow-hidden min-h-[500px]">
                        <?php 
                        $proofUrl = url('bursar/payments/proof.php?id=' . $sub['id']);
                        $ext = strtolower(pathinfo($sub['proof_path'], PATHINFO_EXTENSION));
                        if ($ext === 'pdf'): ?>
                            <iframe src="<?= $proofUrl ?>" class="w-full h-full min-h-[500px] border-0"></iframe>
                        <?php else: ?>
                            <a href="<?= $proofUrl ?>" target="_blank">
                                <img src="<?= $proofUrl ?>" alt="Payment Proof" class="max-w-full max-h-[800px] object-contain">
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>

<!-- Reject Modal -->
<div id="reject-modal" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50">
    <div class="relative w-full max-w-md mx-auto bg-white rounded-xl shadow-2xl p-8 border border-gray-100">
        <h3 id="rejectModalTitle" class="text-xl font-bold text-school-navy mb-4">Reject Submission</h3>
        <form action="<?= url('actions/payments/reject_submission.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
            
            <div>
                <label for="review_note" class="block text-sm font-medium text-charcoal mb-1">Reason for Rejection *</label>
                <textarea name="review_note" id="review_note" rows="3" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-red focus:border-school-red sm:text-sm" placeholder="Please provide a reason to the student (e.g. Image blurry, amount mismatched)"></textarea>
            </div>
            
            <div class="pt-4 flex justify-end">
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="mr-3 bg-white text-slate-gray px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="bg-school-red text-white px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium hover:bg-red-700">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>

