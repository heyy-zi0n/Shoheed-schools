<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$pageTitle = 'My Fees';
$pdo = get_db_connection();
$userId = current_user_id();

// Get own student record — strict ownership check
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

// Get invoices — only for this student
$stmt = $pdo->prepare("
    SELECT si.*,
           a.name as session_name, t.name as term_name,
           (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.student_invoice_id = si.id) as amount_paid
    FROM student_invoices si
    JOIN academic_sessions a ON si.academic_session_id = a.id
    JOIN terms t ON si.term_id = t.id
    WHERE si.student_id = :sid
    ORDER BY si.created_at DESC
");
$stmt->execute(['sid' => $student['id']]);
$invoices = $stmt->fetchAll();

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">My Fees</h1>
                <p class="text-sm text-slate-gray">Your fee invoices and payment history.</p>
            </div>

            <?php if (empty($invoices)): ?>
                <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 text-center">
                    <p class="text-slate-gray">No fee invoices have been generated for your account yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($invoices as $inv):
                        $total  = (float)$inv['total_amount'];
                        $paid   = (float)$inv['amount_paid'];
                        $balance = max(0, $total - $paid);
                        $pct    = $total > 0 ? min(100, round($paid / $total * 100)) : 0;
                    ?>
                    <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-school-navy"><?= e($inv['term_name']) ?></h3>
                                <p class="text-sm text-slate-gray"><?= e($inv['session_name']) ?></p>
                            </div>
                            <?php
                                $statusColor = 'bg-red-100 text-red-800';
                                if ($inv['status'] === 'PARTIAL') $statusColor = 'bg-yellow-100 text-yellow-800';
                                if ($inv['status'] === 'PAID')    $statusColor = 'bg-sage text-white';
                            ?>
                            <span class="px-3 py-1 text-xs font-bold rounded-full <?= $statusColor ?>">
                                <?= e($inv['status']) ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
                            <div>
                                <p class="text-slate-gray">Total</p>
                                <p class="font-bold text-charcoal">&#8358;<?= number_format($total, 2) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Paid</p>
                                <p class="font-bold text-green-600">&#8358;<?= number_format($paid, 2) ?></p>
                            </div>
                            <div>
                                <p class="text-slate-gray">Balance</p>
                                <p class="font-bold text-red-600">&#8358;<?= number_format($balance, 2) ?></p>
                            </div>
                        </div>

                        <!-- Progress bar -->
                        <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                            <div class="bg-school-blue h-2 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                        </div>
                        <p class="text-xs text-slate-gray"><?= $pct ?>% paid</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
