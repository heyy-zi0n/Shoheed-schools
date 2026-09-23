<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pageTitle = 'Payment Submissions Queue';
$pdo = get_db_connection();

$stmt = $pdo->query("
    SELECT ps.*, s.first_name, s.last_name, s.admission_number, si.total_amount, a.name as session_name, t.name as term_name
    FROM payment_submissions ps
    JOIN students s ON ps.student_id = s.id
    JOIN student_invoices si ON ps.invoice_id = si.id
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    WHERE ps.status = 'PENDING'
    ORDER BY ps.submitted_at ASC
");
$submissions = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Review Bank Transfers</h1>
                    <p class="text-sm text-slate-gray">Pending payment proofs submitted by students.</p>
                </div>
            </div>
            
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <?php if (empty($submissions)): ?>
                    <div class="p-8 text-center text-slate-gray">
                        <p>No pending payment submissions require review.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Date Submitted</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Amount Claimed</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-school-navy uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($submissions as $sub): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                            <?= date('M d, Y h:i A', strtotime($sub['submitted_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <p class="text-sm font-bold text-school-navy"><?= e($sub['first_name'] . ' ' . $sub['last_name']) ?></p>
                                            <p class="text-xs text-slate-gray"><?= e($sub['admission_number']) ?></p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                            <?= e($sub['term_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-school-gold">
                                            &#8358;<?= number_format($sub['amount'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="<?= url('bursar/payments/review.php?id=' . $sub['id']) ?>" class="inline-flex items-center px-3 py-1 border border-school-blue text-school-blue hover:bg-school-blue hover:text-white rounded text-xs font-bold transition-colors">Review</a>
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
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
