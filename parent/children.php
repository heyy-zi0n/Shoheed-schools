<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_PARENT]);

$pageTitle = 'My Children';
$pdo = get_db_connection();
$userId = current_user_id();

$stmt = $pdo->prepare("SELECT id FROM guardians WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$guardianId = $stmt->fetchColumn();

$children = [];
if ($guardianId) {
    $stmt = $pdo->prepare("
        SELECT s.*, sg.relationship, 
               (SELECT c.name FROM student_enrollments se JOIN classes c ON se.class_id = c.id WHERE se.student_id = s.id AND se.status = 'ACTIVE' ORDER BY se.id DESC LIMIT 1) as current_class
        FROM students s
        JOIN student_guardians sg ON s.id = sg.student_id
        WHERE sg.guardian_id = :gid
    ");
    $stmt->execute(['gid' => $guardianId]);
    $children = $stmt->fetchAll();
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">My Children</h1>
                <p class="text-sm text-slate-gray">View your linked wards and their academic profiles.</p>
            </div>
            
            <?php if (empty($children)): ?>
                <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 text-center">
                    <p class="text-charcoal mb-4">No children are currently linked to your account.</p>
                    <p class="text-sm text-slate-gray">Please contact the school administration to link your wards.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($children as $child): ?>
                        <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 flex flex-col">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 font-bold text-lg mr-4">
                                    <?= e(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-school-navy"><?= e($child['last_name'] . ' ' . $child['first_name']) ?></h3>
                                    <p class="text-sm text-slate-gray"><?= e($child['admission_number']) ?></p>
                                </div>
                            </div>
                            
                            <div class="text-sm space-y-2 mb-6">
                                <div class="flex justify-between border-b border-gray-50 pb-2">
                                    <span class="text-slate-gray">Class:</span>
                                    <span class="font-medium text-charcoal"><?= e($child['current_class'] ?? 'Unassigned') ?></span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-2">
                                    <span class="text-slate-gray">Relationship:</span>
                                    <span class="font-medium text-charcoal"><?= e($child['relationship'] ?? 'Guardian') ?></span>
                                </div>
                                <div class="flex justify-between pb-2">
                                    <span class="text-slate-gray">Status:</span>
                                    <span class="font-medium text-sage"><?= e($child['status']) ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <a href="#" class="text-center bg-gray-100 hover:bg-gray-200 text-charcoal px-3 py-2 rounded text-sm font-medium transition-colors">
                                    Results
                                </a>
                                <a href="#" class="text-center bg-gray-100 hover:bg-gray-200 text-charcoal px-3 py-2 rounded text-sm font-medium transition-colors">
                                    Finance
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
