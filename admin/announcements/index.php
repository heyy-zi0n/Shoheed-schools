<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Announcements';
$pdo = get_db_connection();

$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Announcements</h1>
                    <p class="text-sm text-slate-gray">Create and manage targeted announcements.</p>
                </div>
                <div>
                    <a href="<?= url('admin/announcements/create.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">
                        + New Announcement
                    </a>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Target Audience</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Date Posted</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($announcements as $ann):
                                $bodyText = (string)($ann['body'] ?? '');
                                $preview = mb_strlen($bodyText) > 80 ? mb_substr($bodyText, 0, 80) . '...' : $bodyText;
                                $isPub = ($ann['status'] ?? 'DRAFT') === 'PUBLISHED';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-charcoal"><?= e($ann['title']) ?></div>
                                    <div class="text-xs text-slate-gray mt-1 line-clamp-1"><?= e($preview) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= e($ann['audience'] ?? 'ALL') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                    <?= e(date('M j, Y', strtotime($ann['created_at']))) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $isPub ? 'bg-sage text-white' : 'bg-gray-200 text-gray-800' ?>">
                                        <?= $isPub ? 'Published' : 'Draft' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($announcements)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-slate-gray">No announcements created yet.</td>
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
