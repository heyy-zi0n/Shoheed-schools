<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pageTitle = 'Student Invoices';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
$activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();

$invoices = [];
if ($activeSession && $activeTerm) {
    $stmt = $pdo->prepare("
        SELECT si.*, s.first_name, s.last_name, s.admission_number, c.name as class_name,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = si.id) as amount_paid
        FROM student_invoices si
        JOIN students s ON si.student_id = s.id
        JOIN student_enrollments se ON s.id = se.student_id AND se.academic_session_id = si.academic_session_id
        JOIN classes c ON se.class_id = c.id
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
                                    <?= e($invoice['class_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-charcoal">
                                    &#8358;<?= number_format($total, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                                    &#8358;<?= number_format($paid, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-bold">
                                    &#8358;<?= number_format($balance, 2) ?>
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
                                    <?php if ($invoice['status'] !== 'PAID'): ?>
                                    <a href="<?= url('bursar/payments/create.php?invoice=' . $invoice['id']) ?>" class="text-school-blue hover:text-school-navy mr-3">Record Payment</a>
                                    <?php endif; ?>

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
