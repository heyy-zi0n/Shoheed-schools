<?php
$roleGroup = 'ALL';
if (has_role([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER, ROLE_BURSAR])) {
    $roleGroup = 'STAFF';
} elseif (has_role([ROLE_STUDENT])) {
    $roleGroup = 'STUDENTS';
} elseif (has_role([ROLE_PARENT])) {
    $roleGroup = 'PARENTS';
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE status = 'PUBLISHED' AND audience IN ('ALL', :role_group)
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute(['role_group' => $roleGroup]);
$announcements = $stmt->fetchAll();
?>

<div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 mt-8">
    <h3 class="text-lg font-bold text-school-navy mb-4">Latest Announcements</h3>
    <?php if (empty($announcements)): ?>
        <p class="text-sm text-slate-gray">No new announcements at this time.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($announcements as $ann): ?>
                <li class="py-4">
                    <div class="flex justify-between items-start mb-1">
                        <h4 class="font-bold text-charcoal"><?= e($ann['title']) ?></h4>
                        <span class="text-xs text-slate-gray bg-gray-100 px-2 py-1 rounded-full"><?= e(date('M j', strtotime($ann['created_at']))) ?></span>
                    </div>
                    <p class="text-sm text-slate-gray mt-2"><?= nl2br(e($ann['body'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
