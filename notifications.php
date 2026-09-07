<?php
require_once __DIR__ . '/config/bootstrap.php';

require_auth();

$pageTitle = 'Notifications';
$pdo = get_db_connection();
$userId = current_user_id();

// Mark all as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
    $stmt->execute(['uid' => $userId]);
    redirect('notifications.php');
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50");
$stmt->execute(['uid' => $userId]);
$notifications = $stmt->fetchAll();

require __DIR__ . '/templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Your Notifications</h1>
                    <p class="text-sm text-slate-gray">Stay updated on important system events.</p>
                </div>
                <?php if (!empty($notifications)): ?>
                <form method="POST" action="<?= url('notifications.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="text-school-blue hover:text-school-navy text-sm font-medium">Mark all as read</button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <?php if (empty($notifications)): ?>
                    <div class="p-8 text-center text-slate-gray">
                        <p>You have no notifications at this time.</p>
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($notifications as $n): ?>
                            <li class="p-6 transition-colors <?= $n['is_read'] ? 'bg-white' : 'bg-blue-50/50' ?>">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <?php if (!$n['is_read']): ?>
                                            <div class="w-3 h-3 bg-school-blue rounded-full"></div>
                                        <?php else: ?>
                                            <div class="w-3 h-3 bg-gray-300 rounded-full"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-bold <?= $n['is_read'] ? 'text-charcoal' : 'text-school-navy' ?>">
                                                <?= e($n['title']) ?>
                                            </p>
                                            <p class="text-xs text-slate-gray">
                                                <?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?>
                                            </p>
                                        </div>
                                        <div class="mt-1 text-sm <?= $n['is_read'] ? 'text-slate-gray' : 'text-charcoal' ?>">
                                            <p><?= e($n['message']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/templates/layouts/app-footer.php'; ?>
