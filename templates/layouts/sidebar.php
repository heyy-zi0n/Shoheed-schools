<?php
$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

if (!function_exists('portal_nav_link')) {
    function portal_nav_link(string $targetPath, string $label, array $matchPatterns = []): string {
        global $currentScript;
        $href = url($targetPath);
        $patterns = !empty($matchPatterns) ? $matchPatterns : [$targetPath];
        $isActive = false;
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_ends_with($currentScript, '/' . ltrim($pattern, '/'))) {
                $isActive = true;
                break;
            }
        }
        $activeClasses = $isActive
            ? 'bg-school-blue text-white font-semibold shadow-sm'
            : 'text-gray-300 hover:text-white hover:bg-white/10';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';
        return '<a href="' . e($href) . '"' . $ariaCurrent . ' class="block mx-3 px-3.5 py-2 text-sm rounded-md transition-colors ' . $activeClasses . '">' . e($label) . '</a>';
    }
}
?>
<div id="sidebar-backdrop" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden transition-opacity" aria-hidden="true"></div>
<aside id="portal-sidebar" aria-label="Portal Workspace" class="fixed inset-y-0 left-0 z-40 w-64 bg-school-navy text-white min-h-screen flex-shrink-0 flex flex-col shadow-lg transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-200 ease-in-out">
    <div class="p-6 border-b border-white/10 bg-school-navy flex items-center justify-between">
        <a href="<?= url() ?>" class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Logo" width="32" height="32" class="w-8 h-8 object-contain">
            </div>
            <div>
                <div class="font-bold tracking-wider text-sm leading-tight uppercase">Shoheed Schools</div>
                <div class="text-[11px] text-gray-300 tracking-wide">Management Portal</div>
            </div>
        </a>
        <button type="button" id="sidebar-close-btn" aria-label="Close menu" class="md:hidden text-gray-300 hover:text-white p-1 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-school-gold">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <nav aria-label="Portal" class="flex-1 py-5 space-y-0.5 overflow-y-auto">
        <?php $role = current_user_role(); ?>
        <?php if ($role === ROLE_ADMIN): ?>
            <div class="px-6 py-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Overview</div>
            <?= portal_nav_link('admin/dashboard.php', 'Dashboard') ?>

            <div class="px-6 pt-4 pb-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Academics</div>
            <?= portal_nav_link('admin/students/index.php', 'Students', ['admin/students/index.php', 'admin/students/view.php', 'admin/students/create.php', 'admin/students/login_slip.php']) ?>
            <?= portal_nav_link('admin/students/promotion.php', 'Promotions') ?>
            <?= portal_nav_link('admin/academics/sessions.php', 'Sessions & Terms', ['admin/academics/sessions.php', 'admin/academics/classes.php', 'admin/academics/subjects.php']) ?>
            <?= portal_nav_link('admin/reports/broadsheet.php', 'Broadsheet') ?>

            <div class="px-6 pt-4 pb-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Operations</div>
            <?= portal_nav_link('admin/staff/index.php', 'Staff Directory', ['admin/staff/index.php', 'admin/staff/view.php', 'admin/staff/create.php', 'admin/staff/edit.php']) ?>
            <?= portal_nav_link('admin/finance/fee_structures.php', 'Fee Structures', ['admin/finance/fee_structures.php', 'admin/finance/fee_structure_edit.php', 'admin/finance/generate_invoices.php', 'admin/finance/bank_accounts.php']) ?>
            <?= portal_nav_link('admin/finance/index.php', 'Transactions') ?>
            <?= portal_nav_link('admin/announcements/index.php', 'Announcements') ?>
            <?= portal_nav_link('admin/reports/audit.php', 'Audit Logs') ?>
        <?php elseif ($role === ROLE_PRINCIPAL): ?>
            <div class="px-6 py-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Academic Oversight</div>
            <?= portal_nav_link('principal/dashboard.php', 'Dashboard') ?>
            <?= portal_nav_link('principal/results/index.php', 'Result Approvals', ['principal/results/index.php', 'principal/results/review.php']) ?>
            <?= portal_nav_link('principal/students/index.php', 'Students', ['principal/students/index.php', 'principal/students/view.php']) ?>
            <?= portal_nav_link('admin/reports/broadsheet.php', 'Broadsheet') ?>

            <div class="px-6 pt-4 pb-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Allocations</div>
            <?= portal_nav_link('principal/assignments/classes.php', 'Class Teachers') ?>
            <?= portal_nav_link('principal/assignments/subjects.php', 'Subject Teachers') ?>
        <?php elseif ($role === ROLE_CLASS_TEACHER || $role === ROLE_SUBJECT_TEACHER): ?>
            <div class="px-6 py-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Overview</div>
            <?= portal_nav_link('teacher/dashboard.php', 'Dashboard') ?>
        <?php elseif ($role === ROLE_BURSAR): ?>
            <div class="px-6 py-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Finance & Accounts</div>
            <?= portal_nav_link('bursar/dashboard.php', 'Dashboard') ?>
            <?= portal_nav_link('admin/finance/fee_structures.php', 'Fee Structures', ['admin/finance/fee_structures.php', 'admin/finance/fee_structure_edit.php']) ?>
            <?= portal_nav_link('admin/finance/generate_invoices.php', 'Generate Invoices') ?>
            <?= portal_nav_link('bursar/invoices/index.php', 'Student Invoices') ?>
            <?= portal_nav_link('bursar/payments/submissions.php', 'Verify Transfers', ['bursar/payments/submissions.php', 'bursar/payments/review.php']) ?>
            <?= portal_nav_link('bursar/payments/index.php', 'Payment Ledger', ['bursar/payments/index.php', 'bursar/payments/create.php', 'bursar/receipts/view.php']) ?>
        <?php elseif ($role === ROLE_STUDENT): ?>
            <div class="px-6 py-2 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">Student Portal</div>
            <?= portal_nav_link('student/dashboard.php', 'Dashboard') ?>
            <?= portal_nav_link('student/results.php', 'My Results') ?>
            <?= portal_nav_link('student/attendance.php', 'My Attendance') ?>
            <?= portal_nav_link('student/fees.php', 'My Fees & Payments', ['student/fees.php', 'student/receipt.php']) ?>
            <?= portal_nav_link('student/profile.php', 'Profile & Security') ?>
        <?php endif; ?>

        <?php 
        $staffId = current_staff_id();
        $activeSession = get_active_academic_session();
        if ($staffId && $activeSession):
            $isClassTeacher = staff_has_class_teacher_assignment($staffId, $activeSession['id']);
            $isSubjectTeacher = staff_has_subject_assignments($staffId, $activeSession['id']);
            if ($isClassTeacher || $isSubjectTeacher):
        ?>
            <div class="px-6 pt-4 pb-2 mt-4 border-t border-white/10 text-[11px] font-bold text-gray-300 uppercase tracking-[0.14em]">My Teaching</div>
            <?php if ($isSubjectTeacher): ?>
                <?= portal_nav_link('teacher/results/index.php', 'Score Entry', ['teacher/results/index.php', 'teacher/results/enter.php']) ?>
            <?php endif; ?>
            <?php if ($isClassTeacher): ?>
                <?= portal_nav_link('teacher/attendance/index.php', 'Attendance') ?>
                <?= portal_nav_link('teacher/results/class_verification.php', 'Class Verification', ['teacher/results/class_verification.php', 'teacher/results/review_subject.php']) ?>
            <?php endif; ?>
        <?php 
            endif;
        endif; 
        ?>
    </nav>
</aside>
