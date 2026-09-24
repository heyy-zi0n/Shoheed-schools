<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pageTitle = 'Student Invoices';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

$invoices = [];
if ($activeSession && $activeTerm) {
    $stmt = $pdo->prepare("
        SELECT si.*, s.first_name, s.last_name, s.admission_number, c.name as class_name,
               fs.total_amount as fee_structure_total,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id) as amount_paid,
               (SELECT COUNT(*) FROM payment_submissions ps WHERE ps.invoice_id = si.id AND ps.status IN ('PENDING', 'VERIFIED')) as blocking_submissions_count
        FROM student_invoices si
        JOIN students s ON si.student_id = s.id
        JOIN fee_structures fs ON si.fee_structure_id = fs.id
        JOIN classes c ON fs.class_id = c.id
        WHERE si.academic_session_id = :session_id AND si.term_id = :term_id
        ORDER BY si.status ASC, s.last_name ASC
    ");
    $stmt->execute([
        'session_id' => $activeSession['id'],
        'term_id' => $activeTerm['id']
    ]);
    $invoices = $stmt->fetchAll();
}

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Student Invoices</h1>
                    <p class="text-sm text-slate-gray">
                        Active Term: <span class="font-bold text-school-navy"><?= $activeTerm ? e($activeTerm['name']) : 'None' ?></span> |
                        Session: <span class="font-bold text-school-navy"><?= $activeSession ? e($activeSession['name']) : 'None' ?></span>
                    </p>
                </div>
                <div>
                    <button class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">Generate Invoices</button>
                </div>
            </div>

            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Paid</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Balance</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice):
                                $total = (float)$invoice['total_amount'];
                                $paid = (float)$invoice['amount_paid'];
                                $balance = max(0, $total - $paid);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-charcoal"><?= e($invoice['last_name'] . ', ' . $invoice['first_name']) ?></div>
                                    <div class="text-xs text-slate-gray"><?= e($invoice['admission_number']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                    <?= e($invoice['class_name']) ?><br>
                                    <?php if ($invoice['invoice_type'] === 'SUPPLEMENTARY'): ?>
                                        <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wide">Supplementary</span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold text-slate-gray uppercase tracking-wide">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-charcoal">
                                    ₦<?= number_format($total, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                                    ₦<?= number_format($paid, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-bold">
                                    ₦<?= number_format($balance, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $statusColor = 'bg-red-100 text-red-800';
                                        if ($invoice['status'] === 'PARTIAL') $statusColor = 'bg-yellow-100 text-yellow-800';
                                        if ($invoice['status'] === 'PAID') $statusColor = 'bg-sage text-white';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor ?>">
                                        <?= e($invoice['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-3">
                                        <?php if ($invoice['status'] !== 'PAID'): ?>
                                        <a href="<?= url('bursar/payments/create.php?invoice=' . $invoice['id']) ?>" class="text-school-blue hover:text-school-navy">Record Payment</a>
                                        <?php endif; ?>

                                        <?php
                                            $outOfSync = ((float)$invoice['total_amount'] !== (float)$invoice['fee_structure_total']);
                                        ?>
                                        <?php if ($invoice['status'] === 'PENDING' && $paid == 0 && $invoice['invoice_type'] === 'STANDARD'): ?>
                                            <?php if ($invoice['blocking_submissions_count'] > 0): ?>
                                                <span class="text-xs text-slate-gray cursor-help" title="Pending or verified transfer exists">Blocked (Transfer)</span>
                                            <?php else: ?>
                                                <form action="<?= url('actions/invoices/refresh.php') ?>" method="POST" class="inline" onsubmit="return confirm('Refresh this invoice from the latest Fee Structure?\n\nCurrent invoice: ₦<?= number_format($total, 2) ?>\nCurrent Fee Structure: ₦<?= number_format($invoice['fee_structure_total'], 2) ?>\n\nThis will replace the invoice items only if no payment activity exists.');">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                    <button type="submit" class="text-xs <?= $outOfSync ? 'text-amber-600 font-bold hover:text-amber-800' : 'text-slate-gray hover:text-charcoal' ?>">
                                                        Refresh <?= $outOfSync ? '(Out of Sync)' : '' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif ($invoice['status'] === 'PARTIAL' || $invoice['status'] === 'PAID'): ?>
                                            <!-- Paid invoices are immutable, do not show refresh -->
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray text-center">No invoices generated for the current term.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>

