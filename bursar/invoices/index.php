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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-gray uppercase tracking-wider">Fee Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-gray uppercase tracking-wider">Result Access</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-gray uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice):
                                $total = (float)$invoice['total_amount'];
                                $paid = (float)$invoice['amount_paid'];
                                $balance = max(0, $total - $paid);
                                $clearance = check_student_tuition_result_clearance(
                                    $pdo,
                                    (int)$invoice['student_id'],
                                    (int)$activeSession['id'],
                                    (int)$activeTerm['id']
                                );
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($clearance['has_active_waiver']): ?>
                                        <span class="status-badge status-badge-warning inline-flex items-center gap-1 cursor-help" title="Granted by <?= e($clearance['waiver']['granted_by_role'] ? format_role_name($clearance['waiver']['granted_by_role']) : 'Admin') ?>: <?= e($clearance['waiver']['reason']) ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber" aria-hidden="true"></span>Waiver Active
                                        </span>
                                        <button type="button" 
                                                onclick="openRevokeModal(<?= (int)$invoice['student_id'] ?>, '<?= e(addslashes($invoice['last_name'] . ', ' . $invoice['first_name'])) ?>')"
                                                class="text-[11px] text-school-red hover:underline block mt-0.5 font-medium">
                                            Revoke Waiver
                                        </button>
                                    <?php elseif ($clearance['is_cleared']): ?>
                                        <span class="status-badge status-badge-success inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-sage" aria-hidden="true"></span>Cleared (<?= (int)$clearance['current_percentage'] ?>%)
                                        </span>
                                        <span class="text-[10px] text-slate-gray block mt-0.5">≥70% Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge status-badge-danger inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-dusty-red" aria-hidden="true"></span>Locked (<?= (int)$clearance['current_percentage'] ?>%)
                                        </span>
                                        <button type="button" 
                                                onclick="openGrantModal(<?= (int)$invoice['student_id'] ?>, '<?= e(addslashes($invoice['last_name'] . ', ' . $invoice['first_name'])) ?>', <?= (float)$clearance['current_percentage'] ?>, <?= (float)$clearance['prior_tuition_debt'] ?>)"
                                                class="text-[11px] text-school-blue hover:text-school-navy hover:underline block mt-0.5 font-medium">
                                            Grant Waiver
                                        </button>
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
                                <td colspan="8" class="px-6 py-8 whitespace-nowrap text-sm text-slate-gray text-center">No invoices generated for the current term.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grant Waiver Modal -->
            <div id="grant-waiver-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="grant-modal-title">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity" onclick="closeGrantModal()"></div>
                    <div class="inline-block bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md w-full p-6 relative z-10">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </div>
                                <h3 class="text-base font-bold text-school-navy" id="grant-modal-title">Grant Result Clearance Waiver</h3>
                            </div>
                            <button type="button" onclick="closeGrantModal()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                        </div>

                        <form action="<?= url('actions/finance/toggle_result_waiver.php') ?>" method="POST" class="mt-4 space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" id="grant_student_id" value="">
                            <input type="hidden" name="academic_session_id" value="<?= (int)($activeSession['id'] ?? 0) ?>">
                            <input type="hidden" name="term_id" value="<?= (int)($activeTerm['id'] ?? 0) ?>">
                            <input type="hidden" name="action" value="grant">
                            <input type="hidden" name="redirect_url" value="<?= e(current_url()) ?>">

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-slate-gray">Student:</span>
                                    <span class="font-bold text-charcoal" id="grant_student_name"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-gray">Current Tuition Paid:</span>
                                    <span class="font-bold text-charcoal" id="grant_tuition_progress"></span>
                                </div>
                                <div class="flex justify-between" id="grant_prior_debt_row">
                                    <span class="text-school-red">Prior Term Debt:</span>
                                    <span class="font-bold text-school-red" id="grant_prior_debt"></span>
                                </div>
                            </div>

                            <div>
                                <label for="grant_reason" class="block text-xs font-bold text-slate-gray mb-1">Reason / Management Note *</label>
                                <textarea id="grant_reason" name="reason" rows="3" required class="w-full border border-gray-300 rounded-md p-2.5 text-xs focus:ring-school-blue focus:border-school-blue" placeholder="e.g. Parent pleaded with school management; promissory note submitted to settle before mid-term."></textarea>
                                <p class="text-[11px] text-slate-gray mt-1">This clearance authorization will be permanently logged in the school audit log.</p>
                            </div>

                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <button type="button" onclick="closeGrantModal()" class="px-3.5 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-charcoal hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-1.5 bg-amber hover:bg-amber-600 text-white rounded-md text-xs font-semibold transition-colors shadow-sm">
                                    Authorize Result Waiver
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Revoke Waiver Modal -->
            <div id="revoke-waiver-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="revoke-modal-title">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity" onclick="closeRevokeModal()"></div>
                    <div class="inline-block bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md w-full p-6 relative z-10">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-red-50 text-school-red flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <h3 class="text-base font-bold text-school-navy" id="revoke-modal-title">Revoke Clearance Waiver</h3>
                            </div>
                            <button type="button" onclick="closeRevokeModal()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                        </div>

                        <form action="<?= url('actions/finance/toggle_result_waiver.php') ?>" method="POST" class="mt-4 space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" id="revoke_student_id" value="">
                            <input type="hidden" name="academic_session_id" value="<?= (int)($activeSession['id'] ?? 0) ?>">
                            <input type="hidden" name="term_id" value="<?= (int)($activeTerm['id'] ?? 0) ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="redirect_url" value="<?= e(current_url()) ?>">

                            <p class="text-xs sm:text-sm text-charcoal">
                                Are you sure you want to revoke the administrative result clearance for <strong id="revoke_student_name"></strong>?
                            </p>
                            <p class="text-xs text-school-red bg-red-50 p-2.5 rounded border border-red-200">
                                Their Continuous Assessment Report will be immediately locked on the student portal until the 70% tuition requirement is satisfied.
                            </p>

                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <button type="button" onclick="closeRevokeModal()" class="px-3.5 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-charcoal hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-1.5 bg-school-red hover:bg-red-800 text-white rounded-md text-xs font-semibold transition-colors shadow-sm">
                                    Revoke Clearance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            function openGrantModal(studentId, studentName, currentPct, priorDebt) {
                document.getElementById('grant_student_id').value = studentId;
                document.getElementById('grant_student_name').textContent = studentName;
                document.getElementById('grant_tuition_progress').textContent = currentPct.toFixed(1) + '%';
                const priorDebtRow = document.getElementById('grant_prior_debt_row');
                if (priorDebt > 0) {
                    priorDebtRow.classList.remove('hidden');
                    document.getElementById('grant_prior_debt').textContent = '₦' + priorDebt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                } else {
                    priorDebtRow.classList.add('hidden');
                }
                document.getElementById('grant-waiver-modal').classList.remove('hidden');
            }

            function closeGrantModal() {
                document.getElementById('grant-waiver-modal').classList.add('hidden');
            }

            function openRevokeModal(studentId, studentName) {
                document.getElementById('revoke_student_id').value = studentId;
                document.getElementById('revoke_student_name').textContent = studentName;
                document.getElementById('revoke-waiver-modal').classList.remove('hidden');
            }

            function closeRevokeModal() {
                document.getElementById('revoke-waiver-modal').classList.add('hidden');
            }
            </script>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>

