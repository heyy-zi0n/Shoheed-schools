<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pageTitle = 'Student Invoices';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));

$invoices = [];
if ($activeSession && $activeTerm) {
    $statusWhere = '';
    $params = [
        'session_id' => $activeSession['id'],
        'term_id' => $activeTerm['id']
    ];
    if ($statusFilter === 'outstanding') {
        $statusWhere = " AND si.status IN ('PENDING', 'PARTIAL')";
    } elseif (in_array(strtoupper($statusFilter), ['PENDING', 'PARTIAL', 'PAID'], true)) {
        $statusWhere = " AND si.status = :status_filter";
        $params['status_filter'] = strtoupper($statusFilter);
    }

    $stmt = $pdo->prepare("
        SELECT si.*, s.first_name, s.last_name, s.admission_number, c.name as class_name,
               fs.total_amount as fee_structure_total,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id) as amount_paid,
               (SELECT COUNT(*) FROM payment_submissions ps WHERE ps.invoice_id = si.id AND ps.status IN ('PENDING', 'VERIFIED')) as blocking_submissions_count
        FROM student_invoices si
        JOIN students s ON si.student_id = s.id
        JOIN fee_structures fs ON si.fee_structure_id = fs.id
        JOIN classes c ON fs.class_id = c.id
        WHERE si.academic_session_id = :session_id AND si.term_id = :term_id{$statusWhere}
        ORDER BY si.status ASC, s.last_name ASC
    ");
    $stmt->execute($params);
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

            <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Student Invoices</h1>
                    <p class="text-sm text-slate-gray">
                        Active Term: <span class="font-bold text-school-navy"><?= $activeTerm ? e($activeTerm['name']) : 'None' ?></span> |
                        Session: <span class="font-bold text-school-navy"><?= $activeSession ? e($activeSession['name']) : 'None' ?></span>
                    </p>
                </div>
                <div>
                    <a href="<?= url('admin/finance/generate_invoices.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm inline-block">Generate Invoices</a>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <?php
                $filters = [
                    'all' => 'All Invoices',
                    'outstanding' => 'Outstanding',
                    'pending' => 'Pending',
                    'partial' => 'Partial',
                    'paid' => 'Paid',
                ];
                foreach ($filters as $fKey => $fLabel):
                    $activePill = ($statusFilter === $fKey);
                ?>
                    <a href="<?= url('bursar/invoices/index.php' . ($fKey === 'all' ? '' : '?status=' . $fKey)) ?>"
                       class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors <?= $activePill ? 'bg-school-navy text-white' : 'bg-white text-charcoal border border-gray-200 hover:bg-gray-50' ?>">
                        <?= e($fLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-200/80">
                <div class="overflow-x-auto" role="region" aria-label="Student invoices table" tabindex="0">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-ivory/70">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-gray uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-gray uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-gray uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-gray uppercase tracking-wider">Paid</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-gray uppercase tracking-wider">Balance</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-gray uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-gray uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice):
                                $total = (float)$invoice['total_amount'];
                                $paid = (float)$invoice['amount_paid'];
                                $balance = max(0, $total - $paid);
                            ?>
                            <tr class="hover:bg-ivory/40 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-charcoal"><?= e($invoice['last_name'] . ', ' . $invoice['first_name']) ?></div>
                                    <div class="text-xs text-slate-gray font-mono"><?= e($invoice['admission_number']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                    <?= e($invoice['class_name']) ?><br>
                                    <?php if ($invoice['invoice_type'] === 'SUPPLEMENTARY'): ?>
                                        <span class="text-[10px] font-bold text-amber uppercase tracking-wide">Supplementary</span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold text-slate-gray uppercase tracking-wide">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-charcoal tabular-nums">
                                    ₦<?= number_format($total, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-sage font-semibold tabular-nums">
                                    ₦<?= number_format($paid, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm <?= $balance > 0 ? 'text-school-red' : 'text-charcoal' ?> font-bold tabular-nums">
                                    ₦<?= number_format($balance, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($invoice['status'] === 'PAID'): ?>
                                        <span class="status-badge status-badge-success"><span class="w-1.5 h-1.5 rounded-full bg-sage" aria-hidden="true"></span>Paid</span>
                                    <?php elseif ($invoice['status'] === 'PARTIAL'): ?>
                                        <span class="status-badge status-badge-warning"><span class="w-1.5 h-1.5 rounded-full bg-amber" aria-hidden="true"></span>Partial</span>
                                    <?php else: ?>
                                        <span class="status-badge status-badge-danger"><span class="w-1.5 h-1.5 rounded-full bg-dusty-red" aria-hidden="true"></span><?= e(ucfirst(strtolower($invoice['status']))) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-3">
                                        <?php if ($invoice['status'] !== 'PAID'): ?>
                                        <a href="<?= url('bursar/payments/create.php?invoice=' . $invoice['id']) ?>" class="text-school-blue hover:text-school-navy font-semibold">Record Payment</a>
                                        <?php endif; ?>

                                        <?php
                                            $outOfSync = ((float)$invoice['total_amount'] !== (float)$invoice['fee_structure_total']);
                                        ?>
                                        <?php if ($invoice['status'] === 'PENDING' && $paid == 0 && $invoice['invoice_type'] === 'STANDARD'): ?>
                                            <?php if ($invoice['blocking_submissions_count'] > 0): ?>
                                                <span class="text-xs text-slate-gray cursor-help" title="Pending or verified transfer exists">Blocked (Transfer)</span>
                                            <?php else: ?>
                                                <form action="<?= url('actions/invoices/refresh.php') ?>" method="POST" class="inline" data-confirm="Refresh this invoice from the latest Fee Structure? Current invoice: ₦<?= number_format($total, 2) ?> | Fee Structure: ₦<?= number_format($invoice['fee_structure_total'], 2) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                    <button type="submit" class="text-xs <?= $outOfSync ? 'text-amber font-bold hover:text-school-navy' : 'text-slate-gray hover:text-charcoal' ?>">
                                                        Refresh <?= $outOfSync ? '(Out of Sync)' : '' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 whitespace-nowrap text-sm text-slate-gray text-center">No invoices generated for the current term.</td>
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

