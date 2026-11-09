<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('error', 'Invalid payment ID.');
    redirect('student/fees.php');
}

$pdo = get_db_connection();
$userId = current_user_id();

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$userId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    set_flash('error', 'Student profile not found.');
    redirect('student/dashboard.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, r.receipt_number, si.total_amount, 
           a.name as session_name, t.name as term_name,
           s.first_name, s.last_name, s.admission_number
    FROM payments p
    JOIN student_invoices si ON p.student_invoice_id = si.id
    JOIN receipts r ON p.id = r.payment_id
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    JOIN students s ON si.student_id = s.id
    WHERE p.id = ? AND si.student_id = ?
");
$stmt->execute([$id, $studentId]);
$receipt = $stmt->fetch();

if (!$receipt) {
    set_flash('error', 'Receipt not found or access denied.');
    redirect('student/fees.php');
}

$pageTitle = 'Payment Receipt';
require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden print-layout-root">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden print-layout-content">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-3 sm:p-6 lg:p-8">
            <div class="max-w-2xl mx-auto mb-4 flex items-center justify-between no-print">
                <a href="<?= url('student/fees.php') ?>" class="text-school-blue hover:underline text-xs sm:text-sm font-semibold inline-flex items-center gap-1.5">
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to My Fees</span>
                </a>
                <button type="button" onclick="window.print()" class="sm:hidden inline-flex items-center gap-1.5 bg-school-navy text-white px-3 py-1.5 rounded text-xs font-semibold hover:bg-school-blue transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print</span>
                </button>
            </div>
            
            <div class="max-w-2xl w-full mx-auto bg-white border border-gray-200/90 rounded-xl p-4 sm:p-8 md:p-10 shadow-sm relative print:shadow-none print:border-none print:p-0 print:rounded-none">
                <!-- Header -->
                <div class="border-b-2 border-school-navy pb-5 sm:pb-6 mb-6 sm:mb-8 flex flex-col sm:flex-row justify-between sm:items-start gap-4 sm:gap-6">
                    <div class="flex items-start gap-3.5">
                        <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Crest" width="48" height="48" class="h-12 w-12 sm:h-14 sm:w-14 object-contain flex-shrink-0">
                        <div>
                            <h1 class="text-xl sm:text-2xl md:text-3xl font-serif font-bold text-school-navy uppercase tracking-wider leading-tight">Payment Receipt</h1>
                            <p class="text-xs sm:text-sm text-slate-gray font-bold mt-1">Shoheed Schools</p>
                            <p class="text-[11px] sm:text-xs text-slate-gray">Excellence in Education &bull; God Is Sufficient</p>
                        </div>
                    </div>
                    <div class="sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-gray-100 flex flex-row sm:flex-col justify-between sm:justify-start items-center sm:items-end gap-1">
                        <div>
                            <p class="text-[10px] sm:text-xs font-bold text-slate-gray uppercase tracking-wider">Receipt No.</p>
                            <p class="text-sm sm:text-base md:text-lg font-bold text-school-navy font-mono tracking-tight"><?= e($receipt['receipt_number']) ?></p>
                        </div>
                        <div class="sm:mt-2 text-right">
                            <p class="text-xs text-slate-gray">Date: <span class="font-medium text-charcoal"><?= date('M j, Y', strtotime($receipt['payment_date'])) ?></span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Student Details -->
                <div class="mb-6 sm:mb-8 bg-gray-50/80 p-4 sm:p-5 border border-gray-200/80 rounded-lg">
                    <p class="text-[11px] font-bold text-school-blue uppercase tracking-wider mb-1.5">Received From</p>
                    <p class="text-base sm:text-lg font-bold text-charcoal"><?= e($receipt['first_name'] . ' ' . $receipt['last_name']) ?></p>
                    <p class="text-xs sm:text-sm text-slate-gray mt-0.5">Admission Number: <span class="font-medium text-charcoal"><?= e($receipt['admission_number']) ?></span></p>
                </div>
                
                <!-- Payment Details -->
                <div class="mb-6 sm:mb-8 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="py-2.5 sm:py-3 text-[11px] sm:text-xs font-bold text-slate-gray uppercase tracking-wider pr-3">Description</th>
                                <th class="py-2.5 sm:py-3 text-[11px] sm:text-xs font-bold text-slate-gray uppercase tracking-wider text-right pl-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100">
                                <td class="py-3.5 sm:py-4 pr-3">
                                    <p class="font-bold text-sm sm:text-base text-charcoal"><?= e($receipt['term_name']) ?> School Fees</p>
                                    <p class="text-xs text-slate-gray mt-0.5">Session: <?= e($receipt['session_name']) ?></p>
                                </td>
                                <td class="py-3.5 sm:py-4 pl-3 text-right align-top">
                                    <p class="font-bold text-base sm:text-lg text-charcoal tabular-nums">₦<?= number_format($receipt['amount'], 2) ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="flex justify-end mb-8 sm:mb-10">
                    <div class="w-full sm:w-3/4 md:w-3/5 space-y-2">
                        <div class="flex items-center justify-between gap-4 border-b border-gray-200 py-2">
                            <span class="text-xs sm:text-sm text-slate-gray font-bold uppercase tracking-wider">Payment Method</span>
                            <span class="text-xs sm:text-sm text-charcoal font-medium text-right"><?= e($receipt['payment_method']) ?></span>
                        </div>
                        <?php if (!empty($receipt['payment_reference'])): ?>
                        <div class="flex items-center justify-between gap-4 border-b border-gray-200 py-2">
                            <span class="text-xs sm:text-sm text-slate-gray font-bold uppercase tracking-wider">Reference</span>
                            <span class="text-xs sm:text-sm text-charcoal font-medium font-mono text-right break-all"><?= e($receipt['payment_reference']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between gap-4 bg-gray-50 px-4 py-3 border border-gray-200/90 mt-3 rounded-lg">
                            <span class="text-xs sm:text-sm text-school-navy font-bold uppercase tracking-wider">Total Paid</span>
                            <span class="text-base sm:text-xl text-sage font-bold tabular-nums">₦<?= number_format($receipt['amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center text-xs text-slate-gray border-t border-gray-200 pt-6 mt-6 space-y-1">
                    <p>This is a computer-generated receipt and requires no physical signature.</p>
                    <p class="text-[11px] text-gray-400">Generated by Shoheed Schools Portal on <?= date('Y-m-d H:i:s') ?></p>
                </div>
                
                <div class="mt-8 text-center no-print">
                    <button type="button" onclick="window.print()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-school-blue text-white px-8 py-2.5 rounded-md font-bold shadow-sm hover:bg-school-navy transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Print Receipt</span>
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>
<style>
@media print {
    aside, header, nav, button, .no-print {
        display: none !important;
    }
    body, .print-layout-root, .print-layout-content, main {
        height: auto !important;
        min-height: 0 !important;
        display: block !important;
        overflow: visible !important;
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }
}
</style>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>

