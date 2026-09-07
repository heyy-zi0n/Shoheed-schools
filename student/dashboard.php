<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$pageTitle = 'Student Dashboard';
require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Student Dashboard</h1>
                <p class="text-sm text-slate-gray">Welcome to your portal.</p>
            </div>
            
            <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100">
                <p class="text-charcoal mb-4">Welcome back to your dashboard. Use the sidebar to navigate your academic records, attendance, and finances.</p>
                <div class="flex space-x-4">
                    <a href="<?= url('student/results.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded text-sm hover:bg-school-navy transition-colors">View Results</a>
                    <a href="<?= url('student/attendance.php') ?>" class="bg-gray-200 text-charcoal px-4 py-2 rounded text-sm hover:bg-gray-300 transition-colors">View Attendance</a>
                </div>
            </div>
            
            <?php require __DIR__ . '/../templates/components/announcements_widget.php'; ?>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
