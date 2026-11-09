<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$pageTitle = 'Student Dashboard';
$pdo = get_db_connection();
$userId = current_user_id();

// Fetch the actual student record
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

$activeSession = get_active_academic_session();
$sessionId = $activeSession['id'] ?? null;
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;
$term = $activeTerm ? $activeTerm['name'] : 'Current Term';

$currentClass = null;
$publishedResults = 0;
$attendancePercentage = null;
$feeBalance = 0;

if ($student && $sessionId) {
    // Current Enrollment
    $enrollStmt = $pdo->prepare("
        SELECT c.name, c.level 
        FROM student_enrollments se 
        JOIN classes c ON se.class_id = c.id 
        WHERE se.student_id = ? AND se.academic_session_id = ? AND se.status = 'ACTIVE'
    ");
    $enrollStmt->execute([$student['id'], $sessionId]);
    $currentClass = $enrollStmt->fetch();
    
    // Published Results
    $resStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT rb.id)
        FROM scores s
        JOIN result_batches rb ON s.result_batch_id = rb.id
        WHERE s.student_id = ? AND rb.academic_session_id = ? AND rb.status = 'PUBLISHED'
    ");
    $resStmt->execute([$student['id'], $sessionId]);
    $publishedResults = $resStmt->fetchColumn();
    

    $attendanceAvailable = true;
    try {
        $attStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) as present_days
            FROM attendance 
            WHERE student_id = ? AND academic_session_id = ?
        ");
        $attStmt->execute([$student['id'], $sessionId]);
        $attData = $attStmt->fetch();
        if ($attData && $attData['total_days'] > 0) {
            $attendancePercentage = round(($attData['present_days'] / $attData['total_days']) * 100);
        }
    } catch (PDOException $e) {
        error_log("Dashboard Error (Attendance): " . $e->getMessage());
        $attendanceAvailable = false;
    }
    

    // Finance
    $financeAvailable = true;
    try {
        $feeStmt = $pdo->prepare("SELECT SUM(total_amount) FROM student_invoices WHERE student_id = ?");
        $feeStmt->execute([$student['id']]);
        $totalInv = $feeStmt->fetchColumn() ?: 0;
        
        $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments p JOIN student_invoices si ON p.student_invoice_id = si.id WHERE si.student_id = ?");
        $paidStmt->execute([$student['id']]);
        $totalPd = $paidStmt->fetchColumn() ?: 0;
        
        $pendingStmt = $pdo->prepare("SELECT SUM(amount) FROM payment_submissions WHERE student_id = ? AND status = 'PENDING'");
        $pendingStmt->execute([$student['id']]);
        $totalPending = $pendingStmt->fetchColumn() ?: 0;
        
        $feeBalance = $totalInv - $totalPd;
    } catch (PDOException $e) {
        error_log("Dashboard Error (Finance): " . $e->getMessage());
        $financeAvailable = false;
    }
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
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-3 sm:p-6 lg:p-8">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <?php if (!$student): ?>
                <div class="p-8 text-center">
                    <p class="text-school-red font-bold">Your student profile could not be found. Please contact administration.</p>
                </div>
            <?php else: ?>
            
                <!-- Institutional Workspace Header -->
                <div class="page-header">
                    <div>
                        <p class="page-eyebrow">Student Portal &bull; <?= e($student['admission_number']) ?></p>
                        <h1 class="text-2xl sm:text-3xl font-serif font-bold text-school-navy mt-1">Welcome back, <?= e($student['first_name']) ?>.</h1>
                        <p class="text-sm text-slate-gray mt-1">
                            <?= $currentClass ? 'Enrolled in ' . e($currentClass['name']) . ' for the ' . e($activeSession['name'] ?? '') . ' academic session.' : 'Your academic and financial summary.' ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <a href="<?= url('student/results.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded-md text-xs sm:text-sm font-semibold hover:bg-school-navy transition-colors shadow-sm">View Results</a>
                        <a href="<?= url('student/fees.php') ?>" class="bg-paper text-school-navy border border-gray-300 px-4 py-2 rounded-md text-xs sm:text-sm font-semibold hover:bg-gray-50 transition-colors">Fees &amp; Payments</a>
                    </div>
                </div>
                
                <!-- Metrics Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
                    <?php 
                    render_metric('Current Class', $currentClass['name'] ?? 'Not Enrolled', 'Session ' . ($activeSession['name'] ?? 'N/A'), 'school-blue');
                    render_metric('Published Results', $publishedResults, 'Available report cards', 'school-gold', url('student/results.php'));
                    
                    if (isset($attendanceAvailable) && $attendanceAvailable) {
                        if ($attendancePercentage !== null) {
                            render_metric('Attendance Rate', $attendancePercentage . '%', 'Current session', $attendancePercentage >= 75 ? 'sage' : 'school-red', url('student/attendance.php'));
                        } else {
                            render_metric('Attendance Rate', 'No records', 'Current session', 'slate-gray', url('student/attendance.php'));
                        }
                    } else {
                        render_metric('Attendance Rate', 'Error', 'Data unavailable', 'school-red');
                    }
                    
                    if (isset($financeAvailable) && $financeAvailable) {
                        render_metric('Outstanding Fees', '₦' . number_format((float)$feeBalance), 'Unpaid balance', $feeBalance > 0 ? 'school-red' : 'sage', url('student/fees.php'));
                        render_metric('Pending Verification', '₦' . number_format((float)$totalPending), 'Submitted transfers', $totalPending > 0 ? 'school-gold' : 'slate-gray', url('student/fees.php'));
                    } else {
                        render_metric('Outstanding Fees', 'Error', 'Data unavailable', 'school-red');
                    }
                    
                    render_metric('Unread Notifications', $unreadNotifs, 'Portal updates', $unreadNotifs > 0 ? 'school-navy' : 'slate-gray', url('notifications.php'));
                    ?>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Main Column -->
                    <div class="lg:col-span-2 space-y-8">
                        
                        <!-- Academic Snapshot -->
                        <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Academic Snapshot</h3>
                            </div>
                            <div class="p-6">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div class="w-12 h-12 bg-blue-50 text-school-blue rounded-full flex items-center justify-center font-bold text-xl">
                                        <?= e($currentClass['level'] ?? '?') ?>
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold text-school-navy"><?= e($currentClass['name'] ?? 'No Class Assigned') ?></p>
                                        <p class="text-sm text-slate-gray">Session: <?= e($activeSession['name'] ?? 'N/A') ?> &bull; <?= e($term) ?></p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-gray-100 pt-6">
                                    <a href="<?= url('student/results.php') ?>" class="block p-4 bg-gray-50 hover:bg-ivory border border-gray-200 rounded-lg text-center transition-colors">
                                        <p class="text-sm font-bold text-school-navy">My Results</p>
                                        <p class="text-xs text-slate-gray mt-1">View latest report cards</p>
                                    </a>
                                    <a href="<?= url('student/attendance.php') ?>" class="block p-4 bg-gray-50 hover:bg-ivory border border-gray-200 rounded-lg text-center transition-colors">
                                        <p class="text-sm font-bold text-school-navy">My Attendance</p>
                                        <p class="text-xs text-slate-gray mt-1">View daily records</p>
                                    </a>
                                    <?php if (isset($financeAvailable) && $financeAvailable): ?>
                                    <a href="<?= url('student/fees.php') ?>" class="block p-4 bg-gray-50 hover:bg-ivory border border-gray-200 rounded-lg text-center transition-colors">
                                        <p class="text-sm font-bold text-school-navy">View Fees & Payments</p>
                                        <p class="text-xs text-slate-gray mt-1">Manage school fees</p>
                                    </a>
                                    <a href="<?= url('student/fees.php#submit-proof') ?>" class="block p-4 bg-gray-50 hover:bg-ivory border border-gray-200 rounded-lg text-center transition-colors">
                                        <p class="text-sm font-bold text-school-navy">Submit Payment Proof</p>
                                        <p class="text-xs text-slate-gray mt-1">Upload bank transfer</p>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Secondary Column -->
                    <div class="lg:col-span-1 space-y-8">
                        <!-- Notifications -->
                        <?php require __DIR__ . '/../templates/components/dashboard/notifications_widget.php'; ?>
                        
                        <!-- Announcements -->
                        <?php 
                            $announcementsAudience = 'STUDENTS';
                            require __DIR__ . '/../templates/components/announcements_widget.php'; 
                        ?>
                    </div>
                </div>

            <?php endif; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>

