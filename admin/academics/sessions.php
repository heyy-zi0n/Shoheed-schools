<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Academic Setup';
$pdo = get_db_connection();

// Fetch all sessions
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll();

// Fetch terms for active session
$activeSessionId = null;
foreach ($sessions as $session) {
    if ($session['is_active']) {
        $activeSessionId = $session['id'];
        break;
    }
}

$terms = [];
if ($activeSessionId) {
    $stmt = $pdo->prepare("SELECT * FROM terms WHERE academic_session_id = :id ORDER BY start_date ASC");
    $stmt->execute(['id' => $activeSessionId]);
    $terms = $stmt->fetchAll();
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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Academic Setup</h1>
                    <p class="text-sm text-slate-gray">Manage sessions and terms.</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Sessions -->
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-school-navy mb-4">Academic Sessions</h3>
                    <div class="overflow-hidden border border-gray-200 rounded-md">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Session</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal">
                                        <?= e($session['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if ($session['is_active']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sage text-white">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if (!$session['is_active']): ?>
                                        <form action="<?= url('actions/academics/set_active_session.php') ?>" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="text-school-blue hover:text-school-navy">Set Active</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Terms -->
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-school-navy mb-4">Terms for Active Session</h3>
                    <?php if (empty($terms)): ?>
                        <p class="text-sm text-slate-gray">No terms found for the active session.</p>
                    <?php else: ?>
                    <div class="overflow-hidden border border-gray-200 rounded-md">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Term</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($terms as $term): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal">
                                        <?= e($term['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if ($term['is_active']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sage text-white">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if (!$term['is_active']): ?>
                                        <form action="<?= url('actions/academics/set_active_term.php') ?>" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="term_id" value="<?= $term['id'] ?>">
                                            <button type="submit" class="text-school-blue hover:text-school-navy">Set Active</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
