<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_CLASS_TEACHER]);

$pageTitle = 'Class Attendance';
$pdo = get_db_connection();
$userId = current_user_id();

$activeSession = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1")->fetch();
$activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();

if (!$activeSession || !$activeTerm) {
    die("No active session or term.");
}

$classId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);
$dateStr = filter_input(INPUT_GET, 'date');
$date = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');

$stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$staffId = $stmt->fetchColumn();

// Get assigned classes
$stmt = $pdo->prepare("
    SELECT c.id, c.name
    FROM class_teacher_assignments cta
    JOIN classes c ON cta.class_id = c.id
    WHERE cta.staff_id = :staff_id AND cta.academic_session_id = :session_id
");
$stmt->execute(['staff_id' => $staffId, 'session_id' => $activeSession['id']]);
$assignedClasses = $stmt->fetchAll();

if (empty($assignedClasses)) {
    die("You are not assigned as a Class Teacher for the current session.");
}

if (!$classId) {
    redirect("teacher/attendance/index.php?class={$assignedClasses[0]['id']}&date={$date}");
}

// Check authorization
$isAssigned = false;
$className = '';
foreach ($assignedClasses as $ac) {
    if ($ac['id'] == $classId) {
        $isAssigned = true;
        $className = $ac['name'];
        break;
    }
}
if (!$isAssigned) {
    die("You are not authorized to manage attendance for this class.");
}

// Get enrolled students
$stmt = $pdo->prepare("
    SELECT s.id, s.admission_number, s.first_name, s.last_name, s.middle_name,
           a.status as attendance_status, a.id as attendance_id
    FROM students s
    JOIN student_enrollments se ON s.id = se.student_id
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = :att_date AND a.academic_session_id = :sess_id
    WHERE se.class_id = :class_id AND se.academic_session_id = :sess_id AND se.status = 'ACTIVE'
    ORDER BY s.last_name ASC
");
$stmt->execute(['class_id' => $classId, 'sess_id' => $activeSession['id'], 'att_date' => $date]);
$students = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Class Attendance</h1>
                    <p class="text-sm text-slate-gray">
                        <?= e($className) ?> &bull; <?= e(date('F j, Y', strtotime($date))) ?>
                    </p>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Selectors -->
                <div class="md:w-1/4">
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 sticky top-6 mb-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Filter</h3>
                        
                        <form method="GET" action="<?= url('teacher/attendance/index.php') ?>" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-gray mb-1">Class</label>
                                <select name="class" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-school-blue sm:text-sm">
                                    <?php foreach ($assignedClasses as $ac): ?>
                                        <option value="<?= $ac['id'] ?>" <?= $ac['id'] == $classId ? 'selected' : '' ?>><?= e($ac['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-gray mb-1">Date</label>
                                <input type="date" name="date" value="<?= e($date) ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-school-blue sm:text-sm">
                            </div>
                            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-charcoal py-2 rounded text-sm font-medium transition-colors">
                                View Attendance
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Attendance Form -->
                <div class="md:w-3/4">
                    <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 mb-6">
                        <form action="<?= url('actions/attendance/save.php') ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="class_id" value="<?= $classId ?>">
                            <input type="hidden" name="attendance_date" value="<?= e($date) ?>">
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Student</th>
                                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Present</th>
                                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Absent</th>
                                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Late</th>
                                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Excused</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($students as $student): 
                                            $sid = $student['id'];
                                            $status = $student['attendance_status'] ?? 'PRESENT'; // default present
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-charcoal"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></div>
                                                <div class="text-xs text-slate-gray"><?= e($student['admission_number']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <input type="radio" name="attendance[<?= $sid ?>]" value="PRESENT" <?= $status === 'PRESENT' ? 'checked' : '' ?> class="w-4 h-4 text-school-blue focus:ring-school-blue border-gray-300">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <input type="radio" name="attendance[<?= $sid ?>]" value="ABSENT" <?= $status === 'ABSENT' ? 'checked' : '' ?> class="w-4 h-4 text-red-600 focus:ring-red-500 border-gray-300">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <input type="radio" name="attendance[<?= $sid ?>]" value="LATE" <?= $status === 'LATE' ? 'checked' : '' ?> class="w-4 h-4 text-yellow-600 focus:ring-yellow-500 border-gray-300">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <input type="radio" name="attendance[<?= $sid ?>]" value="EXCUSED" <?= $status === 'EXCUSED' ? 'checked' : '' ?> class="w-4 h-4 text-green-600 focus:ring-green-500 border-gray-300">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($students)): ?>
                                            <tr><td colspan="5" class="text-center py-4 text-slate-gray">No students enrolled in this class.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (!empty($students)): ?>
                            <div class="mt-6 flex justify-end">
                                <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">
                                    Save Attendance
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
