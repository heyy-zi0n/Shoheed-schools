<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Administrator Overview</h1>
                <p class="text-sm text-slate-gray">System metrics and active configuration.</p>
            </div>

            <?php
            $pdo = get_db_connection();
            // Fetch stats
            $studentsCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
            $staffCount = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
            
            $activeSession = (get_active_academic_session()['name'] ?? null) ?: 'None';
            $activeTerm = $pdo->query("SELECT name FROM terms WHERE is_active = 1 LIMIT 1")->fetchColumn() ?: 'None';
            ?>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-school-blue">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Total Students</p>
                    <p class="text-3xl font-serif font-bold text-school-navy"><?= number_format((float)$studentsCount) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-school-gold">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Total Staff</p>
                    <p class="text-3xl font-serif font-bold text-school-navy"><?= number_format((float)$staffCount) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-school-red">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Active Session</p>
                    <p class="text-xl font-bold text-school-navy mt-2"><?= e($activeSession) ?></p>
                </div>
                <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-sage">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Active Term</p>
                    <p class="text-xl font-bold text-school-navy mt-2"><?= e($activeTerm) ?></p>
                </div>
            </div>

            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Quick Actions</h3>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="<?= url('admin/students/index.php') ?>" class="flex items-center p-4 border border-gray-200 rounded hover:bg-blue-50 hover:border-school-blue transition-colors">
                        <div class="w-10 h-10 rounded bg-school-blue text-white flex items-center justify-center mr-4">S</div>
                        <div>
                            <p class="font-bold text-sm text-school-navy">Manage Students</p>
                        </div>
                    </a>
                    <a href="<?= url('admin/staff/index.php') ?>" class="flex items-center p-4 border border-gray-200 rounded hover:bg-blue-50 hover:border-school-blue transition-colors">
                        <div class="w-10 h-10 rounded bg-school-gold text-white flex items-center justify-center mr-4">T</div>
                        <div>
                            <p class="font-bold text-sm text-school-navy">Manage Staff</p>
                        </div>
                    </a>
                    <a href="<?= url('admin/academics/sessions.php') ?>" class="flex items-center p-4 border border-gray-200 rounded hover:bg-blue-50 hover:border-school-blue transition-colors">
                        <div class="w-10 h-10 rounded bg-school-red text-white flex items-center justify-center mr-4">A</div>
                        <div>
                            <p class="font-bold text-sm text-school-navy">Academic Setup</p>
                        </div>
                    </a>
                    <a href="<?= url('admin/finance/index.php') ?>" class="flex items-center p-4 border border-gray-200 rounded hover:bg-blue-50 hover:border-school-blue transition-colors">
                        <div class="w-10 h-10 rounded bg-sage text-white flex items-center justify-center mr-4">F</div>
                        <div>
                            <p class="font-bold text-sm text-school-navy">Finance Overview</p>
                        </div>
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
