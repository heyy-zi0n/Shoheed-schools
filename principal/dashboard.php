<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_PRINCIPAL]);

$pageTitle = 'Principal Dashboard';
$pdo = get_db_connection();
$userId = current_user_id();
$staffId = current_staff_id();

$activeSession = get_active_academic_session();
$sessionId = $activeSession ? $activeSession['id'] : null;

// Fetch Stats
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$assignedCTs = 0;
$totalSubjectAssignments = 0;
$pendingApprovals = 0;
$resultApprovalsQueue = [];
$totalStudents = 0;

if ($sessionId) {
    $assignedCTs = $pdo->prepare("SELECT COUNT(DISTINCT class_id) FROM class_teacher_assignments WHERE academic_session_id = ?");
    $assignedCTs->execute([$sessionId]);
    $assignedCTs = $assignedCTs->fetchColumn();
    
    $totalSubjectAssignments = $pdo->prepare("SELECT COUNT(*) FROM subject_teacher_assignments WHERE academic_session_id = ?");
    $totalSubjectAssignments->execute([$sessionId]);
    $totalSubjectAssignments = $totalSubjectAssignments->fetchColumn();
    
    $totalStudents = $pdo->prepare("SELECT COUNT(*) FROM student_enrollments WHERE academic_session_id = ? AND status = 'ACTIVE'");
    $totalStudents->execute([$sessionId]);
    $totalStudents = $totalStudents->fetchColumn();
    
    // Result Approvals Queue (CLASS_TEACHER_VERIFIED)
    $stmt = $pdo->prepare("
        SELECT rb.id, c.name as class_name, 
               (SELECT COUNT(*) FROM subject_submissions ss WHERE ss.result_batch_id = rb.id AND ss.status IN ('CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED')) as verified_subjects
        FROM result_batches rb
        JOIN classes c ON rb.class_id = c.id
        WHERE rb.academic_session_id = ? AND rb.status = 'CLASS_TEACHER_VERIFIED'
    ");
    $stmt->execute([$sessionId]);
    $resultApprovalsQueue = $stmt->fetchAll();
    $pendingApprovals = count($resultApprovalsQueue);
}

// Check if Principal is also teaching
$myTeachingSubjects = [];
if ($staffId && $sessionId) {
    $stmt = $pdo->prepare("
        SELECT c.id as class_id, c.name as class_name, s.id as subject_id, s.name as subject_name
        FROM subject_teacher_assignments sta
        JOIN classes c ON sta.class_id = c.id
        JOIN subjects s ON sta.subject_id = s.id
        WHERE sta.staff_id = ? AND sta.academic_session_id = ?
    ");
    $stmt->execute([$staffId, $sessionId]);
    $myTeachingSubjects = $stmt->fetchAll();
}

$unreadNotifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadNotifStmt->execute([$userId]);
$unreadNotifs = (int)$unreadNotifStmt->fetchColumn();

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <!-- Hero Section -->
            <div class="mb-8 bg-school-navy rounded-xl p-8 text-white shadow-md relative overflow-hidden">
                <div class="relative z-10">
                    <h1 class="text-3xl font-serif font-bold mb-2">Academic Oversight</h1>
                    <p class="text-blue-200">Monitor approvals, assignments, and school performance.</p>
                </div>
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-48 h-48 bg-white opacity-5 rounded-full blur-2xl"></div>
            </div>
            
            <!-- Metrics Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php 
                render_metric('Pending Result Approvals', $pendingApprovals, null, $pendingApprovals > 0 ? 'school-red' : 'sage');
                render_metric('Classes with Teachers', $assignedCTs . ' / ' . $totalClasses, null, 'school-blue', url('principal/assignments/classes.php'));
                render_metric('Subject Assignments', $totalSubjectAssignments, 'Active allocations', 'school-gold', url('principal/assignments/subjects.php'));
                render_metric('Enrolled Students', $totalStudents, 'Current Session', 'school-navy', url('principal/students/index.php'));
                
                
                ?>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Main Column -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Approvals Queue -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Results Awaiting Principal Action</h3>
                            <span class="bg-school-red text-white text-xs font-bold px-2 py-1 rounded-full"><?= $pendingApprovals ?></span>
                        </div>
                        <?php if (empty($resultApprovalsQueue)): ?>
                            <?php 
                            $title = "No pending approvals";
                            $message = "There are no result batches awaiting your approval at this time.";
                            require __DIR__ . '/../templates/components/dashboard/empty_state.php'; 
                            ?>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($resultApprovalsQueue as $batch): ?>
                                    <li class="p-6 hover:bg-ivory/50 transition-colors">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="font-bold text-school-navy text-sm"><?= e($batch['class_name']) ?> Results</p>
                                                <p class="text-xs text-slate-gray mt-1"><?= (int)$batch['verified_subjects'] ?> Verified Subjects in this Batch</p>
                                            </div>
                                            <a href="<?= url("principal/results/index.php?batch={$batch['id']}") ?>" class="px-3 py-2 bg-school-blue text-white text-xs font-medium rounded shadow hover:bg-school-navy transition-colors">Review & Approve</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Principal's Own Teaching Duties -->
                    <?php if (!empty($myTeachingSubjects)): ?>
                    <div class="bg-paper rounded-lg shadow-sm border border-school-gold overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-yellow-50/30 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">My Personal Teaching Assignments</h3>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <?php foreach ($myTeachingSubjects as $sub): ?>
                                <li class="p-4 pl-6 hover:bg-ivory/50 transition-colors flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-school-navy text-sm"><?= e($sub['subject_name']) ?></p>
                                        <p class="text-xs text-slate-gray"><?= e($sub['class_name']) ?></p>
                                    </div>
                                    <a href="<?= url("teacher/results/enter.php?class={$sub['class_id']}&subject={$sub['subject_id']}") ?>" class="text-xs font-medium text-school-blue hover:underline">Enter Scores</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Quick Actions</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
                            <?php 
                            render_quick_action('Assign Class Teachers', url('principal/assignments/classes.php'), 'C', 'school-blue');
                            render_quick_action('Assign Subjects', url('principal/assignments/subjects.php'), 'S', 'school-gold');
                            render_quick_action('View Broadsheet', url('admin/reports/broadsheet.php'), 'B', 'sage');
                            
                            
                            ?>
                        </div>
                    </div>

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

