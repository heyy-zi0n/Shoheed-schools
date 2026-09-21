<aside class="w-64 bg-school-navy text-white min-h-screen flex-shrink-0 flex flex-col shadow-lg z-30">
    <div class="p-6 border-b border-school-navy/80 bg-school-navy shadow-sm">
        <a href="<?= url() ?>" class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Logo" class="w-8 h-8 object-contain">
            </div>
            <div>
                <div class="font-bold tracking-wider text-sm leading-tight">SHOHEED</div>
                <div class="text-[10px] text-gray-400 uppercase tracking-widest">God Is Sufficient</div>
            </div>
        </a>
    </div>
    <nav class="flex-1 py-6 space-y-1 overflow-y-auto">
        <?php $role = current_user_role(); ?>
        <?php if ($role === ROLE_ADMIN): ?>
            <div class="px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Administration</div>
            <a href="<?= url('admin/dashboard.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Dashboard</a>
            <a href="<?= url('admin/students/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Students</a>
            <a href="<?= url('admin/students/promotion.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Promotions</a>
            <a href="<?= url('admin/staff/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Staff</a>
            <a href="<?= url('admin/academics/sessions.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Academics</a>
            <a href="<?= url('admin/reports/broadsheet.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Broadsheet</a>
            <div class="px-6 py-2 mt-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Finance & Fees</div>
            <a href="<?= url('admin/finance/fee_structures.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Fee Structures</a>
            <a href="<?= url('admin/finance/generate_invoices.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Generate Invoices</a>
            <a href="<?= url('admin/finance/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Transactions</a>
        <?php elseif ($role === ROLE_PRINCIPAL): ?>
            <div class="px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Academic Head</div>
            <a href="<?= url('principal/dashboard.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Dashboard</a>
            <a href="<?= url('principal/results/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Result Approvals</a>
            <a href="<?= url('principal/students/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Students</a>
            <a href="<?= url('admin/reports/broadsheet.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Broadsheet</a>
            <div class="px-6 py-2 mt-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Assignments</div>
            <a href="<?= url('principal/assignments/classes.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Class Teachers</a>
            <a href="<?= url('principal/assignments/subjects.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Subject Teachers</a>
        <?php elseif ($role === ROLE_CLASS_TEACHER || $role === ROLE_SUBJECT_TEACHER): ?>
            <div class="px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Teacher</div>
            <a href="<?= url('teacher/dashboard.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Dashboard</a>
        <?php elseif ($role === ROLE_BURSAR): ?>
            <div class="px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Finance</div>
            <a href="<?= url('bursar/dashboard.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Dashboard</a>
            <a href="<?= url('admin/finance/fee_structures.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Fee Structures</a>
            <a href="<?= url('admin/finance/generate_invoices.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Generate Invoices</a>
            <a href="<?= url('bursar/invoices/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Manage Invoices</a>
            <a href="<?= url('bursar/payments/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Payments</a>
        <?php elseif ($role === ROLE_STUDENT): ?>
            <div class="px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Student Area</div>
            <a href="<?= url('student/dashboard.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Dashboard</a>
            <a href="<?= url('student/results.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">My Results</a>
        <?php elseif ($role === ROLE_PARENT): ?>
            <div class="px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Parent Area</div>
            <a href="<?= url('parent/dashboard.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Dashboard</a>
            <a href="<?= url('parent/children.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">My Children</a>
        <?php endif; ?>

        <?php 
        $staffId = current_staff_id();
        $activeSession = get_active_academic_session();
        if ($staffId && $activeSession):
            $isClassTeacher = staff_has_class_teacher_assignment($staffId, $activeSession['id']);
            $isSubjectTeacher = staff_has_subject_assignments($staffId, $activeSession['id']);
            if ($isClassTeacher || $isSubjectTeacher):
        ?>
            <div class="px-6 py-2 mt-4 border-t border-school-navy/80 pt-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">My Teaching</div>
            <?php if ($isSubjectTeacher): ?>
                <a href="<?= url('teacher/results/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Score Entry</a>
            <?php endif; ?>
            <?php if ($isClassTeacher): ?>
                <a href="<?= url('teacher/attendance/index.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Attendance</a>
                <a href="<?= url('teacher/results/class_verification.php') ?>" class="block px-6 py-2 text-sm text-gray-300 hover:text-white hover:bg-school-blue transition-colors">Class Verification</a>
            <?php endif; ?>
        <?php 
            endif;
        endif; 
        ?>
    </nav>
</aside>
