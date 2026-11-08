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

            <div class="page-header">
                <div>
                    <p class="page-eyebrow">Institutional Accounts</p>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-school-navy mt-1">Finance Overview</h1>
                    <p class="text-sm text-slate-gray mt-1">High-level fee revenue, collections, and outstanding student balances.</p>
                </div>
            </div>

            <?php
            $pdo = get_db_connection();
            $sessions = get_all_academic_sessions();
            $terms = $pdo->query("SELECT * FROM terms ORDER BY name ASC")->fetchAll();

            $sessionId = filter_var($_GET['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
            $termId = filter_var($_GET['term_id'] ?? null, FILTER_VALIDATE_INT);
            $termName = $_GET['term_name'] ?? '';

            // Clean combinations
            if ($sessionId && $termName) { $termName = ''; }
            if (!$sessionId && $termId) { $termId = null; }
            if ($sessionId && $termId) {
                $checkTerm = $pdo->prepare("SELECT id FROM terms WHERE id = ? AND academic_session_id = ?");
                $checkTerm->execute([$termId, $sessionId]);
                if (!$checkTerm->fetch()) {
                    $termId = null;
                }
            }

            $invSql = "SELECT COALESCE(SUM(si.total_amount), 0) FROM student_invoices si WHERE 1=1";
            $paySql = "SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN student_invoices si ON p.student_invoice_id = si.id WHERE 1=1";
            $params = [];

            if ($sessionId) {
                $invSql .= " AND si.academic_session_id = :session";
                $paySql .= " AND si.academic_session_id = :session";
                $params['session'] = $sessionId;
            }
            if ($termId) {
                $invSql .= " AND si.term_id = :term";
                $paySql .= " AND si.term_id = :term";
                $params['term'] = $termId;
            } elseif ($termName) {
                $invSql .= " AND si.term_id IN (SELECT id FROM terms WHERE name = :term_name)";
                $paySql .= " AND si.term_id IN (SELECT id FROM terms WHERE name = :term_name)";
                $params['term_name'] = $termName;
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
                        <select name="<?= $sessionId ? 'term_id' : 'term_name' ?>" id="termSelect" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">All Terms</option>
                            <?php if ($sessionId): ?>
                                <?php
                                $sessionTerms = $pdo->prepare("SELECT id, name FROM terms WHERE academic_session_id = ? ORDER BY id ASC");
                                $sessionTerms->execute([$sessionId]);
                                foreach ($sessionTerms->fetchAll() as $t):
                                ?>
                                    <option value="<?= $t['id'] ?>" <?= $t['id'] == $termId ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach (['First Term', 'Second Term', 'Third Term'] as $name): ?>
                                    <option value="<?= e($name) ?>" <?= $name === $termName ? 'selected' : '' ?>><?= e($name) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-auto">
                        <button type="submit" class="w-full bg-school-navy text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-blue transition-colors">Filter Revenue</button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php
                render_metric('Expected Revenue', '₦' . number_format((float)$totalInvoices, 2), 'Total invoiced', 'school-blue');
                render_metric('Total Received', '₦' . number_format((float)$totalPayments, 2), 'Verified payments', 'sage');
                render_metric('Outstanding', '₦' . number_format((float)$outstanding, 2), 'Uncollected balance', $outstanding > 0 ? 'school-red' : 'sage');
                ?>
            </div>

            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-200/80">
                <p class="text-charcoal text-sm leading-relaxed">Detailed finance management is handled by the Bursar. Please login with a Bursar account to generate invoices and record payments.</p>
            </div>

        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sessionSelect = document.querySelector('select[name="academic_session_id"]');
    const termSelect = document.getElementById('termSelect');

    sessionSelect.addEventListener('change', function() {
        const sessionId = this.value;
        if (!sessionId) {
            termSelect.name = 'term_name';
            termSelect.innerHTML = '<option value="">All Terms</option>' +
                '<option value="First Term">First Term</option>' +
                '<option value="Second Term">Second Term</option>' +
                '<option value="Third Term">Third Term</option>';
        } else {
            termSelect.name = 'term_id';
            termSelect.innerHTML = '<option value="">Loading...</option>';

            fetch(`<?= url('actions/academics/get_terms.php') ?>?session_id=${sessionId}`)
                .then(res => res.json())
                .then(data => {
                    termSelect.innerHTML = '<option value="">All Terms</option>';
                    if (data.terms) {
                        data.terms.forEach(term => {
                            const option = document.createElement('option');
                            option.value = term.id;
                            option.textContent = term.name;
                            termSelect.appendChild(option);
                        });
                    }
                })
                .catch(() => {
                    termSelect.innerHTML = '<option value="">Error loading terms</option>';
                });
        }
    });
});
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>

