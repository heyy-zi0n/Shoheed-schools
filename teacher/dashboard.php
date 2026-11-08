<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER]);

$pageTitle = 'Teacher Dashboard';
$pdo = get_db_connection();
$staffId = current_staff_id();
$userId = current_user_id();
$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

if (!$staffId || !$activeSession) {
    set_flash('error', 'Active academic session or staff profile is missing.');
    redirect('profile/index.php');
}

// 1. Fetch Class Teacher Assignments
$ctStmt = $pdo->prepare("
    SELECT c.id, c.name, c.level,
           (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = c.id AND se.academic_session_id = ? AND se.status IN ('ACTIVE', 'PROMOTED', 'REPEATED')) as student_count
    FROM class_teacher_assignments cta
    JOIN classes c ON cta.class_id = c.id
    WHERE cta.staff_id = ? AND cta.academic_session_id = ?
");
$ctStmt->execute([$activeSession['id'], $staffId, $activeSession['id']]);
$classAssignments = $ctStmt->fetchAll();

// 2. Fetch Subject Teacher Assignments with batch status for the active term
$activeTermId = $activeTerm ? (int)$activeTerm['id'] : 0;
$stStmt = $pdo->prepare("
    SELECT c.id as class_id, c.name as class_name, s.id as subject_id, s.name as subject_name,
           rb.id as batch_id, rb.status as batch_status,
           ss.status as subject_status, ss.comments as subject_comments
    FROM subject_teacher_assignments sta
    JOIN classes c ON sta.class_id = c.id
    JOIN subjects s ON sta.subject_id = s.id
    LEFT JOIN result_batches rb ON rb.class_id = c.id AND rb.academic_session_id = sta.academic_session_id AND rb.term_id = ?
    LEFT JOIN subject_submissions ss ON ss.result_batch_id = rb.id AND ss.subject_id = s.id
    WHERE sta.staff_id = ? AND sta.academic_session_id = ?
    ORDER BY c.level ASC, s.name ASC
");
$stStmt->execute([$activeTermId, $staffId, $activeSession['id']]);
$subjectAssignments = $stStmt->fetchAll();

// 3. Pending Tasks Calculation
$pendingTasks = [];
// Revision Required Tasks
foreach ($subjectAssignments as $sub) {
    if (($sub['subject_status'] ?? '') === 'REVISION_REQUIRED') {
        $pendingTasks[] = [
            'type' => 'revision',
            'title' => 'Revision Required: ' . $sub['subject_name'] . ' (' . $sub['class_name'] . ')',
            'desc' => $sub['subject_comments'] ?? 'Please review the result scores.',
            'url' => url("teacher/results/enter.php?class={$sub['class_id']}&subject={$sub['subject_id']}")
        ];
    }
}
// Unsubmitted Drafts
foreach ($subjectAssignments as $sub) {
    if (($sub['subject_status'] ?? 'DRAFT') === 'DRAFT') {
        $pendingTasks[] = [
            'type' => 'draft',
            'title' => 'Pending Entry: ' . $sub['subject_name'] . ' (' . $sub['class_name'] . ')',
            'desc' => 'Scores have not been submitted for verification.',
            'url' => url("teacher/results/enter.php?class={$sub['class_id']}&subject={$sub['subject_id']}")
        ];
    }
}

// 4. Metrics
$totalClasses = count($classAssignments);
$totalSubjects = count($subjectAssignments);
$revisionCount = count(array_filter($subjectAssignments, fn($s) => ($s['subject_status'] ?? '') === 'REVISION_REQUIRED'));

$unreadNotifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadNotifStmt->execute([$userId]);
$unreadNotifs = (int)$unreadNotifStmt->fetchColumn();

// Helper to determine status pill with semantic badge + non-color status dot
function get_status_pill($status) {
    $status = $status ?? 'DRAFT';
    switch ($status) {
        case 'DRAFT':
            return '<span class="status-badge status-badge-neutral"><span class="w-1.5 h-1.5 rounded-full bg-slate-gray" aria-hidden="true"></span>Draft</span>';
        case 'SUBMITTED':
            return '<span class="status-badge status-badge-info"><span class="w-1.5 h-1.5 rounded-full bg-school-blue" aria-hidden="true"></span>Submitted</span>';
        case 'REVISION_REQUIRED':
            return '<span class="status-badge status-badge-warning"><span class="w-1.5 h-1.5 rounded-full bg-amber" aria-hidden="true"></span>Revision Required</span>';
        case 'CLASS_TEACHER_VERIFIED':
            return '<span class="status-badge status-badge-success"><span class="w-1.5 h-1.5 rounded-full bg-sage" aria-hidden="true"></span>CT Verified</span>';
        case 'PRINCIPAL_APPROVED':
            return '<span class="status-badge status-badge-success"><span class="w-1.5 h-1.5 rounded-full bg-sage" aria-hidden="true"></span>Approved</span>';
        case 'PUBLISHED':
            return '<span class="status-badge status-badge-success"><span class="w-1.5 h-1.5 rounded-full bg-sage" aria-hidden="true"></span>Published</span>';
        default:
            return '<span class="status-badge status-badge-neutral">' . e($status) . '</span>';
    }
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <!-- Institutional Workspace Header -->
            <div class="page-header">
                <div>
                    <p class="page-eyebrow">Teaching Workspace</p>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-school-navy mt-1">Welcome back, <?= e(explode(' ', get_user_display_name($userId, current_user_role()))[0]) ?>.</h1>
                    <p class="text-sm text-slate-gray mt-1">Teaching assignments, score submissions, and class responsibilities for the active term.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <?php if ($totalSubjects > 0): ?>
                        <a href="<?= url('teacher/results/index.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded-md text-xs sm:text-sm font-semibold hover:bg-school-navy transition-colors shadow-sm">Score Entry</a>
                    <?php endif; ?>
                    <?php if ($totalClasses > 0): ?>
                        <a href="<?= url('teacher/attendance/index.php') ?>" class="bg-paper text-school-navy border border-gray-300 px-4 py-2 rounded-md text-xs sm:text-sm font-semibold hover:bg-gray-50 transition-colors">Mark Attendance</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Metrics Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <?php 
                render_metric('Assigned Subjects', $totalSubjects, 'Active session', 'school-blue', url('teacher/results/index.php'));
                render_metric('Classes Assigned', $totalClasses, 'Class teacher role', 'school-gold');
                render_metric('Revision Requests', $revisionCount, 'Returned for correction', $revisionCount > 0 ? 'school-red' : 'sage');
                render_metric('Unread Notifications', $unreadNotifs, 'System alerts', $unreadNotifs > 0 ? 'school-navy' : 'slate-gray', url('notifications.php'));
                ?>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Main Column -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Pending Tasks -->
                    <?php if (!empty($pendingTasks)): ?>
                    <div class="bg-paper rounded-lg shadow-sm border border-school-red/20 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-red-50/50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-school-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                Action Required (<?= count($pendingTasks) ?>)
                            </h3>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <?php foreach (array_slice($pendingTasks, 0, 5) as $task): ?>
                                <li class="p-6 hover:bg-ivory/50 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-school-navy text-sm"><?= e($task['title']) ?></p>
                                            <p class="text-xs text-slate-gray mt-1"><?= e($task['desc']) ?></p>
                                        </div>
                                        <a href="<?= e($task['url']) ?>" class="px-3 py-1 bg-school-blue text-white text-xs font-medium rounded hover:bg-school-navy transition-colors">Resolve</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Subject Assignments -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">My Subject Assignments</h3>
                        </div>
                        <?php if (empty($subjectAssignments)): ?>
                            <?php 
                            $title = "No subjects assigned";
                            $message = "You have not been assigned to teach any subjects for the current session.";
                            require __DIR__ . '/../templates/components/dashboard/empty_state.php'; 
                            ?>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50/50 text-xs font-bold text-slate-gray uppercase tracking-wider border-b border-gray-100">
                                            <th class="p-4 pl-6">Subject</th>
                                            <th class="p-4">Class</th>
                                            <th class="p-4">Status</th>
                                            <th class="p-4 pr-6 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <?php foreach ($subjectAssignments as $sa): ?>
                                            <tr class="hover:bg-ivory/50 transition-colors">
                                                <td class="p-4 pl-6 font-bold text-school-navy"><?= e($sa['subject_name']) ?></td>
                                                <td class="p-4 text-charcoal"><?= e($sa['class_name']) ?></td>
                                                <td class="p-4">
                                                    <?php
                                                        // Overall batch status overrides subject status if it's verified/published
                                                        $bs = $sa['batch_status'] ?? 'DRAFT';
                                                        $sts = $sa['subject_status'] ?? 'DRAFT';
                                                        $displayStatus = in_array($bs, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED']) ? $bs : $sts;
                                                        echo get_status_pill($displayStatus);
                                                    ?>
                                                </td>
                                                <td class="p-4 pr-6 text-right">
                                                    <a href="<?= url("teacher/results/enter.php?class={$sa['class_id']}&subject={$sa['subject_id']}") ?>" class="text-school-blue hover:text-school-navy font-medium text-xs">Enter Scores &rarr;</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Class Teacher Responsibilities -->
                    <?php if (!empty($classAssignments)): ?>
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">My Class Responsibility</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <?php foreach ($classAssignments as $ca): ?>
                                <div class="border border-gray-200 rounded-lg p-5 hover:border-school-blue hover:shadow-sm transition-all">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <h4 class="font-bold text-lg text-school-navy"><?= e($ca['name']) ?></h4>
                                            <p class="text-xs text-slate-gray mt-1">Class Teacher &bull; <?= (int)$ca['student_count'] ?> Students</p>
                                        </div>
                                        <div class="w-10 h-10 bg-blue-50 text-school-blue rounded flex items-center justify-center font-bold text-xl">
                                            <?= e($ca['level']) ?>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mt-4">
                                        <a href="<?= url('teacher/attendance/index.php?class=' . (int)$ca['id']) ?>" class="text-center py-2 px-3 bg-gray-50 hover:bg-school-blue hover:text-white border border-gray-200 rounded text-xs font-bold text-charcoal transition-colors">Attendance</a>
                                        <a href="<?= url('teacher/results/class_verification.php?class=' . (int)$ca['id']) ?>" class="text-center py-2 px-3 bg-gray-50 hover:bg-school-gold hover:text-school-navy border border-gray-200 rounded text-xs font-bold text-charcoal transition-colors">Verify Results</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                
                <!-- Secondary Column -->
                <div class="lg:col-span-1 space-y-8">
                    <!-- Notifications -->
                    <?php require __DIR__ . '/../templates/components/dashboard/notifications_widget.php'; ?>
                    
                    <!-- Announcements -->
                    <?php 
                        $announcementsAudience = 'STAFF';
                        require __DIR__ . '/../templates/components/announcements_widget.php'; 
                    ?>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>

