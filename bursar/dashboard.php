<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pageTitle = 'Bursar Dashboard';
$pdo = get_db_connection();
$userId = current_user_id();

$totalInvoices = 0;
$outstandingAmount = 0;
$amountCollected = 0;
$recentPayments = [];
$financeAvailable = true;

try {
    // Total Invoices
    $totalInvoices = $pdo->query("SELECT COUNT(*) FROM student_invoices")->fetchColumn();
    // Outstanding Amount
    $totalInvoiced = $pdo->query("SELECT SUM(total_amount) FROM student_invoices")->fetchColumn() ?: 0;
    // Amount Collected
    $amountCollected = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
    $outstandingAmount = $totalInvoiced - $amountCollected;
    
    // Pending Verifications
    $pendingVerifications = $pdo->query("SELECT COUNT(*) FROM payment_submissions WHERE status = 'PENDING'")->fetchColumn() ?: 0;
    
    // Recent Payments
    $recentPayments = $pdo->query("
        SELECT p.*, CONCAT('INV-', si.id) as invoice_number, s.first_name, s.last_name 
        FROM payments p
        JOIN student_invoices si ON p.student_invoice_id = si.id
        JOIN students s ON si.student_id = s.id
        ORDER BY p.payment_date DESC 
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard Error (Finance): " . $e->getMessage());
    $financeAvailable = false;
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
                    <h1 class="text-3xl font-serif font-bold mb-2">Finance Overview</h1>
                    <p class="text-blue-200">Monitor collections, outstanding balances, and recent payments.</p>
                </div>
                <div class="absolute top-0 right-0 -mt-8 w-48 h-48 bg-sage opacity-20 rounded-full blur-2xl"></div>
            </div>
            
            <!-- Metrics Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php 
                if ($financeAvailable) {
                    render_metric('Total Invoices', $totalInvoices, null, 'school-blue', url('bursar/invoices/index.php'));
                    render_metric('Outstanding Amount', '₦' . number_format((float)$outstandingAmount), null, 'school-red', url('bursar/invoices/index.php?status=outstanding'));
                    render_metric('Amount Collected', '₦' . number_format((float)$amountCollected), null, 'sage', url('bursar/payments/index.php'));
                    render_metric('Pending Verification', $pendingVerifications, null, $pendingVerifications > 0 ? 'school-gold' : 'slate-gray', url('bursar/payments/submissions.php'));
                } else {
                    render_metric('Total Invoices', 'Error', 'Data unavailable', 'school-blue');
                    render_metric('Outstanding Amount', 'Error', 'Data unavailable', 'school-red');
                    render_metric('Amount Collected', 'Error', 'Data unavailable', 'sage');
                    render_metric('Pending Verification', 'Error', 'Data unavailable', 'school-gold');
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
                            if ($financeAvailable) {
                                render_quick_action('Manage Invoices', url('bursar/invoices/index.php'), 'I', 'school-navy');
                                render_quick_action('Record Payment', url('bursar/payments/create.php'), 'â‚¦', 'sage');
                                render_quick_action('Review Transfers', url('bursar/payments/submissions.php'), 'T', 'school-gold');
                            }
                            
                            
                            ?>
                        </div>
                    </div>

                    <!-- Recent Payments -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Recent Payments</h3>
                        </div>
                        <?php if (!$financeAvailable): ?>
                            <div class="p-6 text-slate-gray text-sm text-center">Payment data is currently unavailable.</div>
                        <?php elseif (empty($recentPayments)): ?>
                            <?php 
                            $title = "No recent payments";
                            $message = "No payments have been recorded recently.";
                            require __DIR__ . '/../templates/components/dashboard/empty_state.php'; 
                            ?>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach ($recentPayments as $payment): ?>
                                    <li class="p-6 hover:bg-ivory/50 transition-colors">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="font-bold text-school-navy text-sm">
                                                    <?= e($payment['first_name'] . ' ' . $payment['last_name']) ?>
                                                </p>
                                                <p class="text-xs text-slate-gray mt-1">
                                                    Invoice: <?= e($payment['invoice_number']) ?> &bull; 
                                                    <?= e(date('M j, Y', strtotime($payment['payment_date']))) ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-bold text-sage text-sm">â‚¦<?= number_format($payment['amount']) ?></p>
                                                <p class="text-[10px] text-gray-400 uppercase tracking-wider mt-1"><?= e($payment['payment_method']) ?></p>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
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


