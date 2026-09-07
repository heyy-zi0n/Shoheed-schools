<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_PARENT]);

$pageTitle = 'Fee Balances';
$pdo = get_db_connection();
$userId = current_user_id();

// Get guardian — strict ownership
$stmt = $pdo->prepare("SELECT id FROM guardians WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$guardianId = $stmt->fetchColumn();

if (!$guardianId) {
    die("Guardian profile not found.");
}

// Get only children linked to this guardian
$stmt = $pdo->prepare("
    SELECT s.id as student_id, s.first_name, s.last_name, s.admission_number,
           si.id as invoice_id, si.total_amount, si.status, si.term_id, si.academic_session_id,
           a.name as session_name, t.name as term_name,
           (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.student_invoice_id = si.id) as amount_paid
    FROM students s
    JOIN student_guardians sg ON sg.student_id = s.id AND sg.guardian_id = :gid
    LEFT JOIN student_invoices si ON si.student_id = s.id
    LEFT JOIN academic_sessions a ON si.academic_session_id = a.id
    LEFT JOIN terms t ON si.term_id = t.id
    ORDER BY s.last_name, si.created_at DESC
");
$stmt->execute(['gid' => $guardianId]);
$rows = $stmt->fetchAll();

// Group by student
$byStudent = [];
foreach ($rows as $r) {
    $byStudent[$r['student_id']]['name'] = $r['last_name'] . ', ' . $r['first_name'];
    $byStudent[$r['student_id']]['admission'] = $r['admission_number'];
    if ($r['invoice_id']) {
        $byStudent[$r['student_id']]['invoices'][] = $r;
    }
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Fee Balances</h1>
                <p class="text-sm text-slate-gray">Fee status for your linked wards.</p>
            </div>

            <?php if (empty($byStudent)): ?>
                <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 text-center">
                    <p class="text-slate-gray">No children are linked to your account, or no invoices have been generated.</p>
                </div>
            <?php else: ?>
                <?php foreach ($byStudent as $studentData):
                    $invoices = $studentData['invoices'] ?? [];
                ?>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 mb-6">
                    <div class="flex items-center mb-4 pb-4 border-b border-gray-100">
                        <div class="w-10 h-10 bg-school-blue rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                            <?= e(strtoupper(substr($studentData['name'], 0, 1))) ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-school-navy"><?= e($studentData['name']) ?></h3>
                            <p class="text-xs text-slate-gray"><?= e($studentData['admission']) ?></p>
                        </div>
                    </div>

                    <?php if (empty($invoices)): ?>
                        <p class="text-sm text-slate-gray">No invoices for this student.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($invoices as $inv):
                                $total   = (float)$inv['total_amount'];
                                $paid    = (float)$inv['amount_paid'];
                                $balance = max(0, $total - $paid);
                            ?>
                            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-bold text-charcoal"><?= e($inv['term_name'] . ' — ' . $inv['session_name']) ?></span>
                                    <?php
                                        $sc = 'bg-red-100 text-red-800';
                                        if ($inv['status']==='PARTIAL') $sc = 'bg-yellow-100 text-yellow-800';
                                        if ($inv['status']==='PAID')    $sc = 'bg-sage text-white';
                                    ?>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $sc ?>"><?= e($inv['status']) ?></span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div><p class="text-slate-gray text-xs">Total</p><p class="font-bold">&#8358;<?= number_format($total,2) ?></p></div>
                                    <div><p class="text-slate-gray text-xs">Paid</p><p class="font-bold text-green-600">&#8358;<?= number_format($paid,2) ?></p></div>
                                    <div><p class="text-slate-gray text-xs">Balance</p><p class="font-bold text-red-600">&#8358;<?= number_format($balance,2) ?></p></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
