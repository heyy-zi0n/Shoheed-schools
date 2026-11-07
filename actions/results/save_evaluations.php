<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$returnUrl = sanitize_internal_redirect_path(
    (string)($_POST['return_url'] ?? ''),
    'teacher/results/class_verification.php'
);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect($returnUrl);
}

$batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
$postClassId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);

if ((!$batchId || $batchId <= 0) && !$postClassId) {
    set_flash('error', 'Invalid result batch.');
    redirect($returnUrl);
}

try {
    $pdo = get_db_connection();
    $userId = current_user_id();
    $userRole = current_user_role();
    $staffId = current_staff_id();

    $pdo->beginTransaction();

    if (!$batchId || $batchId <= 0) {
        $activeSession = get_active_academic_session();
        $activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;
        if (!$activeSession || !$activeTerm) {
            throw new Exception('No active academic session or term is configured.');
        }
        $findStmt = $pdo->prepare("SELECT * FROM result_batches WHERE class_id = ? AND academic_session_id = ? AND term_id = ? FOR UPDATE");
        $findStmt->execute([$postClassId, $activeSession['id'], $activeTerm['id']]);
        $batch = $findStmt->fetch();
        if (!$batch) {
            $insBatch = $pdo->prepare("INSERT INTO result_batches (class_id, academic_session_id, term_id, status) VALUES (?, ?, ?, 'DRAFT')");
            $insBatch->execute([$postClassId, $activeSession['id'], $activeTerm['id']]);
            $batchId = (int)$pdo->lastInsertId();
            $findStmt->execute([$postClassId, $activeSession['id'], $activeTerm['id']]);
            $batch = $findStmt->fetch();
        } else {
            $batchId = (int)$batch['id'];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM result_batches WHERE id = ? FOR UPDATE");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch();
    }

    if (!$batch) {
        throw new Exception('Result batch not found.');
    }

    $classId = (int)$batch['class_id'];
    $sessionId = (int)$batch['academic_session_id'];
    $batchStatus = $batch['status'];

    $isPrincipal = ($userRole === ROLE_PRINCIPAL || $userRole === ROLE_ADMIN);
    $isClassTeacher = ($staffId && staff_can_access_class_teacher_function($staffId, $classId, $sessionId));

    if (!$isPrincipal && !$isClassTeacher) {
        throw new Exception('You are not authorized to update evaluations for this class.');
    }

    if ($batchStatus === 'PUBLISHED') {
        throw new Exception('This result batch has already been published and is locked.');
    }

    if (!$isPrincipal && in_array($batchStatus, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED'], true)) {
        throw new Exception('This result batch has been verified and locked for class teacher edits.');
    }

    // 1. Validate & update batch terminal duration & attendance metadata
    $parseDate = static function (?string $val): ?string {
        $val = trim($val ?? '');
        if ($val === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $val);
        if (!$d || $d->format('Y-m-d') !== $val) {
            throw new Exception("Invalid date format: {$val}");
        }
        return $val;
    };

    $termBegins = $parseDate($_POST['term_begins'] ?? null);
    $termEnds = $parseDate($_POST['term_ends'] ?? null);
    $nextTermBegins = $parseDate($_POST['next_term_begins'] ?? null);

    $durationWeeksRaw = trim($_POST['duration_weeks'] ?? '');
    $durationWeeks = $durationWeeksRaw !== '' ? filter_var($durationWeeksRaw, FILTER_VALIDATE_INT) : 14;
    if ($durationWeeks === false || $durationWeeks < 1 || $durationWeeks > 52) {
        throw new Exception('Duration weeks must be between 1 and 52.');
    }

    $timesOpenedRaw = trim($_POST['times_school_opened'] ?? '');
    $timesOpened = null;
    if ($timesOpenedRaw !== '') {
        $timesOpened = filter_var($timesOpenedRaw, FILTER_VALIDATE_INT);
        if ($timesOpened === false || $timesOpened < 0 || $timesOpened > 366) {
            throw new Exception('Times school opened must be between 0 and 366.');
        }
    }

    $updBatch = $pdo->prepare("
        UPDATE result_batches
        SET term_begins = :tb,
            term_ends = :te,
            next_term_begins = :ntb,
            duration_weeks = :dw,
            times_school_opened = :tso
        WHERE id = :id
    ");
    $updBatch->execute([
        'tb'  => $termBegins,
        'te'  => $termEnds,
        'ntb' => $nextTermBegins,
        'dw'  => $durationWeeks,
        'tso' => $timesOpened,
        'id'  => $batchId,
    ]);

    // 2. Validate & upsert student evaluations (only for students enrolled in this class/session)
    $enrolledStmt = $pdo->prepare("SELECT student_id FROM student_enrollments WHERE class_id = ? AND academic_session_id = ?");
    $enrolledStmt->execute([$classId, $sessionId]);
    $enrolledMap = array_flip($enrolledStmt->fetchAll(PDO::FETCH_COLUMN));

    $evaluationsInput = $_POST['evaluations'] ?? [];
    $sdbaCols = array_keys(get_sdba_fields());

    $existingStmt = $pdo->prepare("SELECT * FROM student_result_evaluations WHERE result_batch_id = ? AND student_id = ?");

    $upsertSql = "
        INSERT INTO student_result_evaluations (
            result_batch_id, student_id, times_present, times_absent,
            obedience, honesty, self_control, self_reliance, use_of_initiative,
            punctuality, neatness, perseverance, attendance_rating, attentiveness,
            reading_writing, verbal_comm, sport_game, inquisitiveness,
            class_teacher_comment, principal_comment, updated_by
        ) VALUES (
            :result_batch_id, :student_id, :times_present, :times_absent,
            :obedience, :honesty, :self_control, :self_reliance, :use_of_initiative,
            :punctuality, :neatness, :perseverance, :attendance_rating, :attentiveness,
            :reading_writing, :verbal_comm, :sport_game, :inquisitiveness,
            :class_teacher_comment, :principal_comment, :updated_by
        )
        ON DUPLICATE KEY UPDATE
            times_present = VALUES(times_present),
            times_absent = VALUES(times_absent),
            obedience = VALUES(obedience),
            honesty = VALUES(honesty),
            self_control = VALUES(self_control),
            self_reliance = VALUES(self_reliance),
            use_of_initiative = VALUES(use_of_initiative),
            punctuality = VALUES(punctuality),
            neatness = VALUES(neatness),
            perseverance = VALUES(perseverance),
            attendance_rating = VALUES(attendance_rating),
            attentiveness = VALUES(attentiveness),
            reading_writing = VALUES(reading_writing),
            verbal_comm = VALUES(verbal_comm),
            sport_game = VALUES(sport_game),
            inquisitiveness = VALUES(inquisitiveness),
            class_teacher_comment = VALUES(class_teacher_comment),
            principal_comment = VALUES(principal_comment),
            updated_by = VALUES(updated_by)
    ";
    $upsertStmt = $pdo->prepare($upsertSql);

    foreach ($evaluationsInput as $studentIdRaw => $data) {
        $studentId = filter_var($studentIdRaw, FILTER_VALIDATE_INT);
        if (!$studentId || !is_array($data)) {
            continue;
        }
        if (!isset($enrolledMap[$studentId])) {
            throw new Exception('Cannot save evaluations for a student not enrolled in this class.');
        }

        $existingStmt->execute([$batchId, $studentId]);
        $existing = $existingStmt->fetch() ?: [];

        $tpRaw = trim($data['times_present'] ?? '');
        $timesPresent = $existing['times_present'] ?? null;
        if (array_key_exists('times_present', $data)) {
            if ($tpRaw === '') {
                $timesPresent = null;
            } else {
                $timesPresent = filter_var($tpRaw, FILTER_VALIDATE_INT);
                if ($timesPresent === false || $timesPresent < 0 || $timesPresent > 366) {
                    throw new Exception('Times present must be between 0 and 366.');
                }
            }
        }

        $taRaw = trim($data['times_absent'] ?? '');
        $timesAbsent = $existing['times_absent'] ?? null;
        if (array_key_exists('times_absent', $data)) {
            if ($taRaw === '') {
                $timesAbsent = null;
            } else {
                $timesAbsent = filter_var($taRaw, FILTER_VALIDATE_INT);
                if ($timesAbsent === false || $timesAbsent < 0 || $timesAbsent > 366) {
                    throw new Exception('Times absent must be between 0 and 366.');
                }
            }
        }

        $params = [
            'result_batch_id' => $batchId,
            'student_id'      => $studentId,
            'times_present'   => $timesPresent,
            'times_absent'    => $timesAbsent,
            'updated_by'      => $userId,
        ];

        foreach ($sdbaCols as $col) {
            $val = $existing[$col] ?? null;
            if (array_key_exists($col, $data)) {
                $raw = trim((string)$data[$col]);
                if ($raw === '') {
                    $val = null;
                } else {
                    $intVal = filter_var($raw, FILTER_VALIDATE_INT);
                    if ($intVal === false || $intVal < 1 || $intVal > 5) {
                        throw new Exception("SDBA rating for {$col} must be between 1 and 5.");
                    }
                    $val = $intVal;
                }
            }
            $params[$col] = $val;
        }

        $teacherComment = $existing['class_teacher_comment'] ?? null;
        if ($isClassTeacher || $isPrincipal) {
            if (array_key_exists('class_teacher_comment', $data)) {
                $tc = trim((string)$data['class_teacher_comment']);
                $teacherComment = $tc !== '' ? mb_substr($tc, 0, 500) : null;
            }
        }

        $principalComment = $existing['principal_comment'] ?? null;
        if ($isPrincipal && array_key_exists('principal_comment', $data)) {
            $pc = trim((string)$data['principal_comment']);
            $principalComment = $pc !== '' ? mb_substr($pc, 0, 500) : null;
        }

        $params['class_teacher_comment'] = $teacherComment;
        $params['principal_comment'] = $principalComment;

        $upsertStmt->execute($params);
    }

    // 3. Audit Log
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description)
        VALUES (?, 'SAVE_RESULT_EVALUATIONS', 'result_batch', ?, ?)
    ");
    $auditStmt->execute([
        $userId,
        $batchId,
        "Saved report card evaluations (SDBA, attendance, and comments) for result batch {$batchId}.",
    ]);

    $pdo->commit();
    set_flash('success', 'Report card settings, SDBA ratings, and comments saved successfully.');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database error in save_evaluations: ' . $e->getMessage());
    set_flash('error', 'Unable to save evaluations due to a database error.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect($returnUrl);
