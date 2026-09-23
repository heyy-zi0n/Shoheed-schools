<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Admin Dashboard';
$pdo = get_db_connection();
$userId = current_user_id();

// Fetch stats
$studentsCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$staffCount = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();

$activeSession = get_active_academic_session();
$activeTerm = $pdo->query("SELECT name FROM terms WHERE is_active = 1 LIMIT 1")->fetchColumn() ?: 'None';

// Calculate outstanding fees (Mocked if actual invoice tables don't exist, but prompt says "Use real data. If data is unavailable, either calculate it correctly or omit that metric... Never fabricate dashboard data").
// Let's check if invoices exist. Assuming `invoices` table exists with `status` and `amount`.
$outstandingFees = 0;
$feesAvailable = true;
try {
    $totalInvoiced = $pdo->query("SELECT SUM(total_amount) FROM student_invoices")->fetchColumn() ?: 0;
    $totalPaid = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
    $outstandingFees = $totalInvoiced - $totalPaid;
} catch (PDOException $e) {
    error_log("Dashboard Error (Invoices): " . $e->getMessage());
    $feesAvailable = false;
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
                    <h1 class="text-3xl font-serif font-bold mb-2">Administrator Command Center</h1>
                    <p class="text-blue-200">Manage students, staff, academics, and operations.</p>
                </div>
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-48 h-48 bg-school-gold opacity-10 rounded-full blur-2xl"></div>
                <div class="absolute bottom-0 right-1/4 w-32 h-32 bg-school-blue opacity-20 rounded-full blur-xl"></div>
            </div>
            
            <!-- Metrics Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                <?php 
                render_metric('Total Students', $studentsCount, null, 'school-blue');
                render_metric('Total Staff', $staffCount, null, 'school-gold');
                if ($feesAvailable) {
                    render_metric('Outstanding Fees', '₦' . number_format((float)$outstandingFees), null, 'school-red');
                } else {
                    render_metric('Outstanding Fees', 'Unavailable', 'Data error', 'school-red');
                }
                render_metric('Unread Notifications', $unreadNotifs, null, 'school-navy', url('notifications.php'));
                
                
                ?>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Main Column -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Quick Actions -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Quick Actions</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
                            <?php 
                            render_quick_action('Add Student', url('admin/students/create.php'), 'S', 'school-blue');
                            render_quick_action('Add Staff', url('admin/staff/create.php'), 'T', 'school-gold');
                            render_quick_action('Manage Sessions', url('admin/academics/sessions.php'), 'A', 'school-red');
                            if ($feesAvailable) {
                                render_quick_action('Fee Structures', url('admin/finance/fee_structures.php'), 'F', 'sage');
                                render_quick_action('Bank Accounts', url('admin/finance/bank_accounts.php'), '₦', 'school-blue');
                            }
                            render_quick_action('Announcements', url('admin/announcements/index.php'), 'B', 'school-navy');
                            
                            
                            ?>
                        </div>
                    </div>

                    <!-- Recent Audit Logs -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Recent System Activity</h3>
                            <a href="<?= url('admin/reports/audit.php') ?>" class="text-xs text-school-blue hover:underline font-medium">View Audit Log</a>
                        </div>
                        <?php
                        $logs = [];
                        $auditAvailable = true;
                        try {
                            $auditStmt = $pdo->query("
                                SELECT a.*, u.username 
                                FROM audit_logs a 
                                LEFT JOIN users u ON a.user_id = u.id 
                                ORDER BY a.created_at DESC LIMIT 5
                            ");
                            $logs = $auditStmt->fetchAll();
                        } catch (PDOException $e) {
                            error_log("Dashboard Error (Audit Logs): " . $e->getMessage());
                            $auditAvailable = false;
                        }
                        
                        if ($auditAvailable):
                        ?>
                            <?php if (empty($logs)): ?>
                                <?php 
                                $title = "No recent activity";
                                $message = "No actions have been recorded yet.";
                                require __DIR__ . '/../templates/components/dashboard/empty_state.php'; 
                                ?>
                            <?php else: ?>
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach ($logs as $log): ?>
                                        <li class="px-6 py-4 hover:bg-ivory/50 transition-colors">
                                            <div class="flex items-start">
                                                <div class="w-8 h-8 rounded bg-gray-100 text-slate-gray flex items-center justify-center text-xs font-bold mr-3 flex-shrink-0">
                                                    <?= e(strtoupper(substr($log['action'], 0, 1))) ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-school-navy"><?= e($log['description']) ?></p>
                                                    <p class="text-xs text-slate-gray mt-1">
                                                        <span class="font-medium text-charcoal"><?= e($log['username'] ?? 'System') ?></span> &bull; 
                                                        <?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?> &bull; 
                                                        <span class="text-gray-400"><?= e($log['action']) ?></span>
                                                    </p>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="p-6 text-slate-gray text-sm text-center">Audit log system not initialized.</div>
                        <?php endif; ?>
                    </div>

                </div>
                
                <!-- Secondary Column -->
                <div class="lg:col-span-1 space-y-8">
                    <!-- Notifications -->
                    <?php require __DIR__ . '/../templates/components/dashboard/notifications_widget.php'; ?>
                    
                    <!-- Announcements -->
                    <?php 
                        $announcementsAudience = 'ALL';
                        require __DIR__ . '/../templates/components/announcements_widget.php'; 
                    ?>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>

