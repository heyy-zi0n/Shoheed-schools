<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$staffId = current_staff_id();
if (!$staffId) {
    set_flash('error', 'Access denied. You do not have a staff profile.');
    redirect('');
}

$pageTitle = 'Score Entry';
$pdo = get_db_connection();

// Get active session and term
$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

$assignments = [];
if ($activeSession && $activeTerm) {
    if ($staffId) {
        $stmt = $pdo->prepare("
            SELECT sta.id, c.name as class_name, c.id as class_id, s.name as subject_name, s.id as subject_id,
                   COALESCE(ss.status, 'NOT_STARTED') as subject_status,
                   rb.status as parent_batch_status,
                   rb.id as batch_id
            FROM subject_teacher_assignments sta
            JOIN classes c ON sta.class_id = c.id
            JOIN subjects s ON sta.subject_id = s.id
            LEFT JOIN result_batches rb ON rb.class_id = c.id 
                                        AND rb.academic_session_id = sta.academic_session_id 
                                        AND rb.term_id = :term_id
            LEFT JOIN subject_submissions ss ON ss.result_batch_id = rb.id AND ss.subject_id = s.id
            WHERE sta.staff_id = :staff_id AND sta.academic_session_id = :session_id
            ORDER BY c.level ASC, s.name ASC
        ");
        $stmt->execute([
            'staff_id' => $staffId,
            'session_id' => $activeSession['id'],
            'term_id' => $activeTerm['id']
        ]);
        $assignments = $stmt->fetchAll();
    }
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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">My Subjects & Classes</h1>
                    <p class="text-sm text-slate-gray">
                        Active Term: <span class="font-bold text-school-navy"><?= $activeTerm ? e($activeTerm['name']) : 'None' ?></span> | 
                        Session: <span class="font-bold text-school-navy"><?= $activeSession ? e($activeSession['name']) : 'None' ?></span>
                    </p>
                </div>
            </div>
            
            <?php if (!$activeSession || !$activeTerm): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-900 p-4 rounded-lg">
                    <p class="text-sm">The academic session or term is not set. Please contact the administrator.</p>
                </div>
            <?php elseif (empty($assignments)): ?>
                <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 text-center">
                    <p class="text-charcoal mb-4">You have no subject assignments for the current session.</p>
                    <p class="text-sm text-slate-gray">If you believe this is an error, please contact the administrator.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 flex flex-col relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-school-blue"></div>
                            <h3 class="text-lg font-serif font-bold text-school-navy mt-2 mb-1"><?= e($assignment['subject_name']) ?></h3>
                            <p class="text-school-red font-bold text-sm mb-4"><?= e($assignment['class_name']) ?></p>
                            
                            <div class="mt-auto pt-4 border-t border-gray-100 flex items-center justify-between">
                                <?php 
                                    $parentStatus = $assignment['parent_batch_status'] ?? null;
                                    $effectiveStatus = in_array($parentStatus, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED'], true)
                                        ? $parentStatus
                                        : ($assignment['subject_status'] ?? 'NOT_STARTED');
                                    $statusColor = 'bg-gray-100 text-gray-800';
                                    if ($effectiveStatus === 'DRAFT' || $effectiveStatus === 'REVISION_REQUIRED') $statusColor = 'bg-yellow-100 text-yellow-800';
                                    if ($effectiveStatus === 'SUBMITTED') $statusColor = 'bg-blue-100 text-blue-800';
                                    if (in_array($effectiveStatus, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED'], true)) $statusColor = 'bg-sage text-white';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor ?>">
                                    <?= e(str_replace('_', ' ', $effectiveStatus)) ?>
                                </span>
                                
                                <a href="<?= url('teacher/results/enter.php?class=' . $assignment['class_id'] . '&subject=' . $assignment['subject_id']) ?>" class="text-school-blue hover:text-school-navy text-sm font-medium">
                                    Enter Scores &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
