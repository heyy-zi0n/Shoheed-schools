<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Audit Logs';
$pdo = get_db_connection();

// Simple pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT a.*, u.username 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$totalRows = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">System Audit Logs</h1>
                <p class="text-sm text-slate-gray">Read-only view of critical system events.</p>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Timestamp</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">User</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Action</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Target</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                    <?= e($log['created_at']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-school-navy">
                                    <?= e($log['username'] ?? 'System') ?> (ID: <?= e($log['user_id']) ?>)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal">
                                    <?= e($log['entity_type']) ?> #<?= e($log['entity_id']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-charcoal">
                                    <?= e($log['description']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-slate-gray">No audit logs found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-slate-gray">Page <?= $page ?> of <?= $totalPages ?></div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= url("admin/reports/audit.php?page=" . ($page - 1)) ?>" class="px-3 py-1 bg-gray-200 text-charcoal rounded text-sm hover:bg-gray-300">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= url("admin/reports/audit.php?page=" . ($page + 1)) ?>" class="px-3 py-1 bg-school-blue text-white rounded text-sm hover:bg-school-navy">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
