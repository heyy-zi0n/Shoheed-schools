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
    die("Student profile not found.");
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
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-4">
                <a href="<?= url('student/fees.php') ?>" class="text-school-blue hover:underline text-sm">&larr; Back to My Fees</a>
            </div>
            
            <div class="max-w-2xl mx-auto bg-white border border-gray-200 p-10 shadow-sm relative">
                <!-- Header -->
                <div class="border-b-2 border-school-navy pb-6 mb-8 flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-serif font-bold text-school-navy uppercase tracking-wider mb-2">Payment Receipt</h1>
                        <p class="text-sm text-slate-gray font-bold">Shoheed Schools</p>
                        <p class="text-xs text-slate-gray">Excellence in Education</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-slate-gray uppercase tracking-wider mb-1">Receipt No.</p>
                        <p class="text-xl font-bold text-school-navy font-mono"><?= e($receipt['receipt_number']) ?></p>
                        <p class="text-sm text-slate-gray mt-2">Date: <?= date('F j, Y', strtotime($receipt['payment_date'])) ?></p>
                    </div>
                </div>
                
                <!-- Student Details -->
                <div class="mb-8 bg-gray-50 p-4 border border-gray-100 rounded">
                    <p class="text-xs font-bold text-school-blue uppercase tracking-wider mb-2">Received From</p>
                    <p class="text-lg font-bold text-charcoal"><?= e($receipt['first_name'] . ' ' . $receipt['last_name']) ?></p>
                    <p class="text-sm text-slate-gray">Admission Number: <?= e($receipt['admission_number']) ?></p>
                </div>
                
                <!-- Payment Details -->
                <div class="mb-12">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="py-3 text-xs font-bold text-slate-gray uppercase tracking-wider w-2/3">Description</th>
                                <th class="py-3 text-xs font-bold text-slate-gray uppercase tracking-wider text-right w-1/3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100">
                                <td class="py-4">
                                    <p class="font-bold text-charcoal"><?= e($receipt['term_name']) ?> School Fees</p>
                                    <p class="text-xs text-slate-gray">Session: <?= e($receipt['session_name']) ?></p>
                                </td>
                                <td class="py-4 text-right">
                                    <p class="font-bold text-charcoal">₦<?= number_format($receipt['amount'], 2) ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="flex justify-end mb-12">
                    <div class="w-1/2">
                        <div class="flex justify-between border-b border-gray-200 py-2">
                            <span class="text-sm text-slate-gray font-bold uppercase">Payment Method</span>
                            <span class="text-sm text-charcoal font-medium"><?= e($receipt['payment_method']) ?></span>
                        </div>
                        <?php if ($receipt['payment_reference']): ?>
                        <div class="flex justify-between border-b border-gray-200 py-2">
                            <span class="text-sm text-slate-gray font-bold uppercase">Reference</span>
                            <span class="text-sm text-charcoal font-medium"><?= e($receipt['payment_reference']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between bg-gray-50 px-4 py-3 border border-gray-200 mt-4 rounded">
                            <span class="text-sm text-school-navy font-bold uppercase tracking-wider">Total Paid</span>
                            <span class="text-lg text-sage font-bold">₦<?= number_format($receipt['amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center text-xs text-slate-gray border-t border-gray-200 pt-8 mt-8">
                    <p>This is a computer-generated receipt and requires no physical signature.</p>
                    <p class="mt-1">Generated by Shoheed Schools Portal on <?= date('Y-m-d H:i:s') ?></p>
                </div>
                
                <div class="mt-8 text-center print:hidden">
                    <button onclick="window.print()" class="bg-school-blue text-white px-6 py-2 rounded font-bold shadow-sm hover:bg-school-navy transition-colors">Print Receipt</button>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>

