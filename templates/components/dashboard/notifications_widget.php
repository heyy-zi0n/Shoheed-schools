<?php
/**
 * @var PDO $pdo
 * @var int $userId
 */
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentNotifications = $stmt->fetchAll();
?>
<div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden flex flex-col h-full">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Recent Notifications</h3>
        <a href="<?= url('notifications.php') ?>" class="text-xs text-school-blue hover:underline font-medium">View All</a>
    </div>
    <div class="flex-1 p-0">
        <?php if (empty($recentNotifications)): ?>
            <div class="p-6">
                <?php 
                $title = "No notifications";
                $message = "You're all caught up.";
                require __DIR__ . '/empty_state.php'; 
                ?>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($recentNotifications as $n): ?>
                    <li class="px-6 py-4 <?= $n['is_read'] ? 'bg-white' : 'bg-blue-50/30' ?>">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <?php if (!$n['is_read']): ?>
                                    <div class="w-2 h-2 bg-school-blue rounded-full"></div>
                                <?php else: ?>
                                    <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-bold <?= $n['is_read'] ? 'text-charcoal' : 'text-school-navy' ?>">
                                    <?= e($n['title']) ?>
                                </p>
                                <p class="text-xs text-slate-gray mt-1 truncate" title="<?= e($n['message']) ?>">
                                    <?= e($n['message']) ?>
                                </p>
                                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">
                                    <?= e(date('M j, Y', strtotime($n['created_at']))) ?>
                                </p>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
