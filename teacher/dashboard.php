<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER]);

$pageTitle = 'Teacher Dashboard';
require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Teacher Dashboard</h1>
                <p class="text-sm text-slate-gray">Your classes, subjects, and pending tasks.</p>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <p class="text-charcoal">Welcome to the Teacher Dashboard. Your assigned classes and score entry tasks will appear here.</p>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
