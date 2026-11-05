<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$pageTitle = 'My Attendance';
$pdo = get_db_connection();
$userId = current_user_id();

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    die("Student profile not found.");
}

$activeSession = get_active_academic_session();
$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

$attendance = [];
$stats = ['PRESENT' => 0, 'ABSENT' => 0];

if ($activeSession && $activeTerm) {
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE student_id = :sid AND academic_session_id = :sess AND term_id = :term
        ORDER BY attendance_date DESC
    ");
    $stmt->execute(['sid' => $studentId, 'sess' => $activeSession['id'], 'term' => $activeTerm['id']]);
    $attendance = $stmt->fetchAll();
    
    foreach ($attendance as $record) {
        $stKey = strtoupper($record['status'] ?? '');
        if ($stKey === 'ABSENT') {
            $stats['ABSENT']++;
        } else {
            $stats['PRESENT']++;
        }
    }
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">My Attendance</h1>
                <p class="text-sm text-slate-gray">Current Term: <?= $activeTerm ? e($activeTerm['name']) : 'None' ?></p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 text-center">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Present</p>
                    <p class="text-2xl font-bold text-school-blue"><?= $stats['PRESENT'] ?></p>
                </div>
                <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 text-center">
                    <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Absent</p>
                    <p class="text-2xl font-bold text-red-600"><?= $stats['ABSENT'] ?></p>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal font-medium">
                                    <?= e(date('l, F j, Y', strtotime($record['attendance_date']))) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                        $stUpper = strtoupper($record['status'] ?? '') === 'ABSENT' ? 'ABSENT' : 'PRESENT';
                                        $color = $stUpper === 'ABSENT' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $color ?>"><?= e($stUpper) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attendance)): ?>
                            <tr><td colspan="2" class="px-6 py-4 text-center text-slate-gray text-sm">No attendance records found for this term.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
