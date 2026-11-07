<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$studentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('error', 'Invalid student ID.');
    redirect('admin/students/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT s.*,
           c.name AS current_class,
           se.academic_session_id AS session_id,
           sess.name AS session_name,
           u.username as portal_username,
           u.status as portal_status
    FROM students s
    LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.status = 'ACTIVE'
    LEFT JOIN classes c ON c.id = se.class_id
    LEFT JOIN academic_sessions sess ON sess.id = se.academic_session_id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('error', 'Student not found.');
    redirect('admin/students/index.php');
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
                <a href="<?= url('admin/students/index.php') ?>" class="text-slate-gray hover:text-school-blue text-sm">&larr; Back to Students</a>
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

                    <!-- Upload form -->
                    <form action="<?= url('actions/students/upload_image.php') ?>" method="POST" enctype="multipart/form-data" class="mt-6 text-left">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
                        <label for="student_profile_image" class="block text-xs font-semibold text-slate-gray mb-2 uppercase tracking-wider">Update Profile Photo</label>
                        <input type="file" id="student_profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" class="block w-full text-xs text-slate-gray border border-gray-200 rounded mb-2 p-2">
                        <p class="text-xs text-gray-400 mb-3">JPG, PNG, GIF, WebP. Max 2 MB.</p>
                        <button type="submit" class="w-full bg-school-blue text-white px-4 py-2 rounded text-sm hover:bg-school-navy transition-colors">Upload Photo</button>
                    </form>
                </div>

                <!-- Details -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-slate-gray uppercase tracking-wider">Student Information</h3>
                            <button type="button" onclick="document.getElementById('edit-student-panel').classList.toggle('hidden')" class="text-xs font-semibold text-school-blue hover:text-school-navy border border-school-blue/30 px-3 py-1 rounded">
                                Edit Profile / Status
                            </button>
                        </div>
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

                        <!-- Collapsible Edit Student Form -->
                        <form id="edit-student-panel" action="<?= url('actions/students/update.php') ?>" method="POST" class="hidden mt-6 pt-6 border-t border-gray-100 space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="stu_first_name" class="block text-xs font-bold text-slate-gray mb-1">First Name *</label>
                                    <input type="text" id="stu_first_name" name="first_name" value="<?= e($student['first_name']) ?>" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label for="stu_middle_name" class="block text-xs font-bold text-slate-gray mb-1">Middle Name</label>
                                    <input type="text" id="stu_middle_name" name="middle_name" value="<?= e($student['middle_name'] ?? '') ?>" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label for="stu_last_name" class="block text-xs font-bold text-slate-gray mb-1">Last Name *</label>
                                    <input type="text" id="stu_last_name" name="last_name" value="<?= e($student['last_name']) ?>" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="stu_gender" class="block text-xs font-bold text-slate-gray mb-1">Gender *</label>
                                    <select id="stu_gender" name="gender" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                        <option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="stu_dob" class="block text-xs font-bold text-slate-gray mb-1">Date of Birth</label>
                                    <input type="date" id="stu_dob" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= e($student['date_of_birth'] ?? '') ?>" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label for="stu_status" class="block text-xs font-bold text-slate-gray mb-1">Record Status *</label>
                                    <select id="stu_status" name="status" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                        <?php foreach (['ACTIVE', 'INACTIVE', 'GRADUATED', 'WITHDRAWN'] as $stOpt): ?>
                                            <option value="<?= $stOpt ?>" <?= $student['status'] === $stOpt ? 'selected' : '' ?>><?= $stOpt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('edit-student-panel').classList.add('hidden')" class="px-4 py-1.5 border border-gray-300 rounded text-xs font-medium text-charcoal hover:bg-gray-50">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 bg-school-blue text-white rounded text-xs font-semibold hover:bg-school-navy">Save Student Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Portal Access -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-slate-gray uppercase tracking-wider mb-4">Portal Access</h3>
                        <?php if ($student['user_id'] === null): ?>
                            <dl class="grid grid-cols-2 gap-4 text-sm mb-4">
                                <div><dt class="text-slate-gray">Username</dt><dd class="font-medium text-charcoal"><?= e($student['admission_number']) ?></dd></div>
                                <div><dt class="text-slate-gray">Account Status</dt><dd class="font-medium text-amber-700">NO PORTAL ACCOUNT</dd></div>
                            </dl>
                            <form action="<?= url('actions/students/generate_login_slip.php') ?>" method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                                <button type="submit" class="bg-school-blue text-white px-4 py-2 rounded text-sm font-medium hover:bg-school-navy transition-colors">
                                    Create Portal Account & Generate Login Slip
                                </button>
                            </form>
                        <?php else: ?>
                            <dl class="grid grid-cols-2 gap-4 text-sm mb-4">
                                <div><dt class="text-slate-gray">Username</dt><dd class="font-medium text-charcoal"><?= e($student['portal_username']) ?></dd></div>
                                <div><dt class="text-slate-gray">Account Status</dt><dd class="font-medium text-charcoal"><?= e($student['portal_status']) ?></dd></div>
                            </dl>
                            <form action="<?= url('actions/students/generate_login_slip.php') ?>" method="POST" class="inline" onsubmit="return confirm('Generate a new Student login slip?\n\nThis will create a new six-digit PIN.\nThe Student\'s previous PIN will stop working immediately.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                                <button type="submit" class="border border-school-blue text-school-blue px-4 py-2 rounded text-sm font-medium hover:bg-blue-50 transition-colors">
                                    Generate New Login Slip
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Guardians -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-slate-gray uppercase tracking-wider">Guardian(s)</h3>
                            <button type="button" onclick="document.getElementById('add-guardian-panel').classList.toggle('hidden')" class="text-xs font-semibold text-school-blue hover:text-school-navy border border-school-blue/30 px-3 py-1 rounded">
                                + Link Guardian
                            </button>
                        </div>
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

                        <!-- Collapsible Add/Link Guardian Form -->
                        <form id="add-guardian-panel" action="<?= url('actions/students/add_guardian.php') ?>" method="POST" class="hidden mt-6 pt-6 border-t border-gray-100 space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="g_first_name" class="block text-xs font-bold text-slate-gray mb-1">Guardian First Name *</label>
                                    <input type="text" id="g_first_name" name="first_name" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label for="g_last_name" class="block text-xs font-bold text-slate-gray mb-1">Guardian Last Name *</label>
                                    <input type="text" id="g_last_name" name="last_name" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label for="g_phone" class="block text-xs font-bold text-slate-gray mb-1">Phone Number *</label>
                                    <input type="tel" id="g_phone" name="phone" required class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" placeholder="e.g. +234 803 000 0000">
                                </div>
                                <div>
                                    <label for="g_relationship" class="block text-xs font-bold text-slate-gray mb-1">Relationship</label>
                                    <input type="text" id="g_relationship" name="relationship" value="Parent/Guardian" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" placeholder="Father, Mother, Guardian...">
                                </div>
                            </div>
                            <div>
                                <label for="g_address" class="block text-xs font-bold text-slate-gray mb-1">Residential Address</label>
                                <input type="text" id="g_address" name="address" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('add-guardian-panel').classList.add('hidden')" class="px-4 py-1.5 border border-gray-300 rounded text-xs font-medium text-charcoal hover:bg-gray-50">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 bg-school-blue text-white rounded text-xs font-semibold hover:bg-school-navy">Save Guardian</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
