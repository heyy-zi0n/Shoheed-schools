<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Add Student';
$pdo = get_db_connection();

$classes = $pdo->query("SELECT * FROM classes ORDER BY level")->fetchAll();
$sessions = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1")->fetchAll();
$activeSession = $sessions[0] ?? null;

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Add Student</h1>
                    <p class="text-sm text-slate-gray">Register a new student and enroll them in a class.</p>
                </div>
                <div>
                    <a href="<?= url('admin/students/index.php') ?>" class="text-slate-gray hover:text-school-navy text-sm font-medium transition-colors">&larr; Back to Directory</a>
                </div>
            </div>
            
            <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 max-w-3xl">
                <form action="<?= url('actions/students/create.php') ?>" method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    
                    <div class="border-b border-gray-200 pb-4 mb-4">
                        <h3 class="text-lg font-medium text-school-navy">Personal Information</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="admission_number" class="block text-sm font-medium text-charcoal">Admission Number *</label>
                            <input type="text" name="admission_number" id="admission_number" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="admission_date" class="block text-sm font-medium text-charcoal">Admission Date *</label>
                            <input type="date" name="admission_date" id="admission_date" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-charcoal">First Name *</label>
                            <input type="text" name="first_name" id="first_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-charcoal">Last Name *</label>
                            <input type="text" name="last_name" id="last_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-charcoal">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="gender" class="block text-sm font-medium text-charcoal">Gender *</label>
                            <select id="gender" name="gender" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                <option value="">Select gender...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-charcoal">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                    </div>
                    
                    <div class="border-b border-gray-200 pb-4 mb-4 mt-8">
                        <h3 class="text-lg font-medium text-school-navy">Initial Enrollment</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-charcoal">Academic Session *</label>
                            <?php if ($activeSession): ?>
                                <input type="hidden" name="academic_session_id" value="<?= $activeSession['id'] ?>">
                                <input type="text" disabled value="<?= e($activeSession['name']) ?> (Active)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-50 text-gray-500 sm:text-sm">
                            <?php else: ?>
                                <p class="text-sm text-school-red mt-1">No active academic session. Set one in Academic Setup first.</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="class_id" class="block text-sm font-medium text-charcoal">Class *</label>
                            <select id="class_id" name="class_id" required <?= !$activeSession ? 'disabled' : '' ?> class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                <option value="">Select a class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>"><?= e($class['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="pt-5 border-t border-gray-200 mt-8 flex justify-end">
                        <button type="submit" <?= !$activeSession ? 'disabled' : '' ?> class="bg-school-blue text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm disabled:opacity-50">Save Student</button>
                    </div>
                </form>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
