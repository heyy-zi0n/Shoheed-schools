<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Finance Overview';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Finance Overview</h1>
                <p class="text-sm text-slate-gray">High-level financial metrics.</p>
            </div>
            
            <?php
            $pdo = get_db_connection();
            $sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
            $terms = $pdo->query("SELECT * FROM terms ORDER BY name ASC")->fetchAll();
            
            $sessionId = filter_var($_GET['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
            $termId = filter_var($_GET['term_id'] ?? null, FILTER_VALIDATE_INT);
            
            $invSql = "SELECT COALESCE(SUM(total_amount), 0) FROM student_invoices WHERE 1=1";
            $paySql = "SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN student_invoices si ON p.student_invoice_id = si.id WHERE 1=1";
            $params = [];
            
            if ($sessionId) {
                $invSql .= " AND academic_session_id = :session";
                $paySql .= " AND si.academic_session_id = :session";
                $params['session'] = $sessionId;
            }
            if ($termId) {
                $invSql .= " AND term_id = :term";
                $paySql .= " AND si.term_id = :term";
                $params['term'] = $termId;
            }
            
            $invStmt = $pdo->prepare($invSql);
            $invStmt->execute($params);
            $totalInvoices = $invStmt->fetchColumn();
            
            $payStmt = $pdo->prepare($paySql);
            $payStmt->execute($params);
            $totalPayments = $payStmt->fetchColumn();
            
            $outstanding = max(0, $totalInvoices - $totalPayments);
            ?>

            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
                <form action="<?= url('admin/finance/index.php') ?>" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="w-full md:w-1/3">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Session</label>
                        <select name="academic_session_id" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">All Sessions</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $sessionId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/3">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Term</label>
                        <select name="term_id" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">All Terms</option>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] == $termId ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-auto">
                        <button type="submit" class="w-full bg-school-navy text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-blue transition-colors">Filter Revenue</button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-school-blue">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Expected Revenue</p>
                    <p class="text-3xl font-serif font-bold text-school-navy">&#8358;<?= number_format((float)$totalInvoices, 2) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-sage">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Total Received</p>
                    <p class="text-3xl font-serif font-bold text-green-600">&#8358;<?= number_format((float)$totalPayments, 2) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-school-red">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Outstanding</p>
                    <p class="text-3xl font-serif font-bold text-red-600">&#8358;<?= number_format((float)$outstanding, 2) ?></p>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <p class="text-charcoal mb-4">Detailed finance management is handled by the Bursar. Please login with a Bursar account to generate invoices and record payments.</p>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
