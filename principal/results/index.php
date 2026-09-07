<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_PRINCIPAL]);

$pageTitle = 'Result Approvals';
$pdo = get_db_connection();

$activeSession = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1")->fetch();
$activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();

$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
$terms = $pdo->query("SELECT * FROM terms ORDER BY name ASC")->fetchAll();

$sessionId = filter_var($_GET['academic_session_id'] ?? ($activeSession['id'] ?? null), FILTER_VALIDATE_INT);
$termId = filter_var($_GET['term_id'] ?? ($activeTerm['id'] ?? null), FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: '';

$batches = [];
if ($sessionId && $termId) {
    // Principal can see everything SUBMITTED, CLASS_TEACHER_VERIFIED, PRINCIPAL_APPROVED, PUBLISHED
    $sql = "
        SELECT rb.*, c.name as class_name
        FROM result_batches rb
        JOIN classes c ON rb.class_id = c.id
        WHERE rb.academic_session_id = :session_id AND rb.term_id = :term_id
    ";
    
    if ($status && in_array($status, ['SUBMITTED', 'CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED'])) {
        $sql .= " AND rb.status = :status";
    } else {
        $sql .= " AND rb.status IN ('SUBMITTED', 'CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED')";
    }
    
    $sql .= " ORDER BY c.level ASC";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        'session_id' => $sessionId,
        'term_id' => $termId
    ];
    if ($status && in_array($status, ['SUBMITTED', 'CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED'])) {
        $params['status'] = $status;
    }
    
    $stmt->execute($params);
    $batches = $stmt->fetchAll();
}

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Result Approvals</h1>
                    <p class="text-sm text-slate-gray">Review and approve submitted class results.</p>
                </div>
            </div>

            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
                <form action="<?= url('principal/results/index.php') ?>" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Session</label>
                        <select name="academic_session_id" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $sessionId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Term</label>
                        <select name="term_id" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] == $termId ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">All Relevant</option>
                            <option value="SUBMITTED" <?= $status === 'SUBMITTED' ? 'selected' : '' ?>>Submitted</option>
                            <option value="CLASS_TEACHER_VERIFIED" <?= $status === 'CLASS_TEACHER_VERIFIED' ? 'selected' : '' ?>>Teacher Verified</option>
                            <option value="PRINCIPAL_APPROVED" <?= $status === 'PRINCIPAL_APPROVED' ? 'selected' : '' ?>>Principal Approved</option>
                            <option value="PUBLISHED" <?= $status === 'PUBLISHED' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                    <div class="w-full md:w-auto">
                        <button type="submit" class="w-full bg-school-navy text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-blue transition-colors">Filter</button>
                    </div>
                </form>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($batches as $batch): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-charcoal">
                                    <?= e($batch['class_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                        $statusColor = 'bg-gray-100 text-gray-800';
                                        if ($batch['status'] === 'SUBMITTED') $statusColor = 'bg-blue-100 text-blue-800';
                                        if ($batch['status'] === 'CLASS_TEACHER_VERIFIED') $statusColor = 'bg-yellow-100 text-yellow-800';
                                        if ($batch['status'] === 'PRINCIPAL_APPROVED') $statusColor = 'bg-green-100 text-green-800';
                                        if ($batch['status'] === 'PUBLISHED') $statusColor = 'bg-sage text-white';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor ?>">
                                        <?= e(str_replace('_', ' ', $batch['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-3">
                                        <?php if ($batch['status'] !== 'PUBLISHED'): ?>
                                        <form action="<?= url('actions/results/principal_approve.php') ?>" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
                                            
                                            <?php if ($batch['status'] === 'CLASS_TEACHER_VERIFIED'): ?>
                                                <input type="hidden" name="action_type" value="approve">
                                                <button type="submit" class="text-green-600 hover:text-green-900">Approve</button>
                                            <?php elseif ($batch['status'] === 'PRINCIPAL_APPROVED'): ?>
                                                <input type="hidden" name="action_type" value="publish">
                                                <button type="submit" class="text-school-blue hover:text-school-navy">Publish</button>
                                            <?php endif; ?>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-gray-400">Published</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray text-center">No submitted batches found.</td>
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
