<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_PRINCIPAL]);

$studentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('error', 'Invalid student ID.');
    redirect('principal/students/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT s.*,
           c.name AS current_class,
           se.academic_session_id AS session_id,
           sess.name AS session_name
    FROM students s
    LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.status = 'ACTIVE'
    LEFT JOIN classes c ON c.id = se.class_id
    LEFT JOIN academic_sessions sess ON sess.id = se.academic_session_id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('error', 'Student not found.');
    redirect('principal/students/index.php');
}

// Guardian
$guardianStmt = $pdo->prepare("
    SELECT g.first_name, g.last_name, g.phone, sg.relationship
    FROM student_guardians sg
    JOIN guardians g ON g.id = sg.guardian_id
    WHERE sg.student_id = ?
");
$guardianStmt->execute([$studentId]);
$guardians = $guardianStmt->fetchAll();

$pageTitle = 'Student Profile';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>

            <div class="mb-6 flex items-center gap-4">
                <a href="<?= url('principal/students/index.php') ?>" class="text-slate-gray hover:text-school-blue text-sm">&larr; Back to Students</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile card -->
                <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 text-center">
                    <?php
                    $imgPath = $student['profile_image'] ?? null;
                    $imgUrl  = $imgPath ? url($imgPath) : null;
                    ?>
                    <div class="w-28 h-28 mx-auto rounded-full overflow-hidden border-4 border-school-blue/20 mb-4 bg-gray-100 flex items-center justify-center">
                        <?php if ($imgUrl): ?>
                            <img src="<?= e($imgUrl) ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-3xl font-bold text-slate-gray"><?= e(substr($student['first_name'],0,1) . substr($student['last_name'],0,1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-xl font-serif font-bold text-school-navy"><?= e($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']) ?></h2>
                    <p class="text-sm text-slate-gray mt-1"><?= e($student['admission_number']) ?></p>
                    <p class="text-sm font-medium text-school-blue mt-1"><?= e($student['current_class'] ?? 'Not Enrolled') ?></p>
                </div>

                <!-- Details -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-slate-gray uppercase tracking-wider mb-4">Student Information</h3>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-slate-gray">Gender</dt><dd class="font-medium text-charcoal"><?= e($student['gender']) ?></dd></div>
                            <div><dt class="text-slate-gray">Date of Birth</dt><dd class="font-medium text-charcoal"><?= e($student['date_of_birth'] ?? '—') ?></dd></div>
                            <div><dt class="text-slate-gray">Admission Date</dt><dd class="font-medium text-charcoal"><?= e($student['admission_date'] ?? '—') ?></dd></div>
                            <div><dt class="text-slate-gray">Status</dt>
                                <dd>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $student['status'] === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= e($student['status']) ?>
                                    </span>
                                </dd>
                            </div>
                            <div><dt class="text-slate-gray">Current Class</dt><dd class="font-medium text-charcoal"><?= e($student['current_class'] ?? 'Not Enrolled') ?></dd></div>
                            <div><dt class="text-slate-gray">Session</dt><dd class="font-medium text-charcoal"><?= e($student['session_name'] ?? '—') ?></dd></div>
                        </dl>
                    </div>

                    <!-- Guardians -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-slate-gray uppercase tracking-wider mb-4">Guardian(s)</h3>
                        <?php if (empty($guardians)): ?>
                            <p class="text-sm text-slate-gray">No guardian linked.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                            <?php foreach ($guardians as $g): ?>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <div>
                                        <p class="font-medium text-charcoal text-sm"><?= e($g['first_name'] . ' ' . $g['last_name']) ?></p>
                                        <p class="text-xs text-slate-gray"><?= e($g['relationship'] ?? '') ?> &bull; <?= e($g['phone']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
