<?php
/**
 * core/results.php
 *
 * Centralized helpers for Shoheed Schools result grading, SDBA attributes,
 * default comments, and report card data assembly.
 */

/**
 * Calculate grade and remark based on the Shoheed Schools grading scale:
 *   A  : 75 - 100 (Excellent)
 *   B2 : 71 - 74  (Very Good)
 *   B3 : 65 - 70  (Good)
 *   C4 : 61 - 64  (Credit)
 *   C5 : 55 - 60  (Credit)
 *   C6 : 50 - 54  (Credit)
 *   D7 : 45 - 49  (Pass)
 *   E8 : 40 - 44  (Pass)
 *   F9 : 0  - 39  (Fail)
 */
function calculate_shoheed_grade(?float $score): array {
    if ($score === null) {
        return ['grade' => '-', 'remark' => '-'];
    }

    if ($score >= 75) {
        return ['grade' => 'A', 'remark' => 'Excellent'];
    }
    if ($score >= 71) {
        return ['grade' => 'B2', 'remark' => 'Very Good'];
    }
    if ($score >= 65) {
        return ['grade' => 'B3', 'remark' => 'Good'];
    }
    if ($score >= 61) {
        return ['grade' => 'C4', 'remark' => 'Credit'];
    }
    if ($score >= 55) {
        return ['grade' => 'C5', 'remark' => 'Credit'];
    }
    if ($score >= 50) {
        return ['grade' => 'C6', 'remark' => 'Credit'];
    }
    if ($score >= 45) {
        return ['grade' => 'D7', 'remark' => 'Pass'];
    }
    if ($score >= 40) {
        return ['grade' => 'E8', 'remark' => 'Pass'];
    }

    return ['grade' => 'F9', 'remark' => 'Fail'];
}

/**
 * Returns the 3 SDBA categories and their fields in exact report-card order.
 */
function get_sdba_categories(): array {
    return [
        'PERSONAL DEV.' => [
            'obedience'         => 'OBEDIENCE',
            'honesty'           => 'HONESTY',
            'self_control'      => 'SELF-CONTROL',
            'self_reliance'     => 'SELF RELIANCE',
            'use_of_initiative' => 'USE OF INITIATIVE',
        ],
        'SENSE OF RESP.' => [
            'punctuality'       => 'PUNCTUALITY',
            'neatness'          => 'NEATNESS',
            'perseverance'      => 'PERSEVERANCE',
            'attendance_rating' => 'ATTENDANCE',
            'attentiveness'     => 'ATTENTIVENESS',
        ],
        'PSYCHOMOTOR' => [
            'reading_writing'   => 'READING & WRIT.',
            'verbal_comm'       => 'VERBAL COMM.',
            'sport_game'        => 'SPORT AND GAME',
            'inquisitiveness'   => 'INQUISITIVENESS',
        ],
    ];
}

/**
 * Flat map of all 14 SDBA database column names to labels.
 */
function get_sdba_fields(): array {
    $fields = [];
    foreach (get_sdba_categories() as $group) {
        foreach ($group as $col => $label) {
            $fields[$col] = $label;
        }
    }
    return $fields;
}

/**
 * Default Class Teacher comment suggestion based on overall percentage.
 */
function get_default_teacher_comment(float $percentage): string {
    if ($percentage >= 75) {
        return 'Calm, attentive, and responds well in class.';
    }
    if ($percentage >= 60) {
        return 'Good effort and active participation in class.';
    }
    if ($percentage >= 45) {
        return 'Fair performance; needs more concentration in class.';
    }
    return 'Needs closer supervision and extra effort in class.';
}

/**
 * Default Principal comment suggestion based on overall percentage.
 */
function get_default_principal_comment(float $percentage): string {
    if ($percentage >= 75) {
        return 'An excellent performance, keep it up.';
    }
    if ($percentage >= 60) {
        return 'A very good result; keep striving for excellence.';
    }
    if ($percentage >= 45) {
        return 'A fair result; put in more effort next term.';
    }
    return 'Below expectation; must work much harder next term.';
}

/**
 * Format a date string for the report card (e.g., "21 Sept., 2020").
 */
function format_report_date(?string $dateStr): string {
    if (!$dateStr) {
        return '-';
    }
    $ts = strtotime($dateStr);
    if (!$ts) {
        return '-';
    }
    return date('jS M., Y', $ts);
}

/**
 * Format a numeric score cleanly (e.g., 82.0 -> "82", 74.5 -> "74.5").
 */
function format_report_score(?float $val): string {
    if ($val === null) {
        return '-';
    }
    $rounded = round($val, 1);
    if (abs($rounded - round($rounded)) < 0.01) {
        return (string)(int)round($rounded);
    }
    return number_format($rounded, 1);
}

/**
 * Determine term ordinal (1, 2, or 3) and short label ("1st Term", "2nd Term", "3rd Term").
 */
function resolve_term_ordinal(string $termName, int $indexInSession = 1): array {
    $lower = strtolower($termName);
    if (str_contains($lower, 'second') || str_contains($lower, '2nd')) {
        return ['ordinal' => 2, 'short' => '2nd Term', 'upper' => 'SECOND TERM'];
    }
    if (str_contains($lower, 'third') || str_contains($lower, '3rd')) {
        return ['ordinal' => 3, 'short' => '3rd Term', 'upper' => 'THIRD TERM'];
    }
    if (str_contains($lower, 'first') || str_contains($lower, '1st')) {
        return ['ordinal' => 1, 'short' => '1st Term', 'upper' => 'FIRST TERM'];
    }

    return match ($indexInSession) {
        2 => ['ordinal' => 2, 'short' => '2nd Term', 'upper' => 'SECOND TERM'],
        3 => ['ordinal' => 3, 'short' => '3rd Term', 'upper' => 'THIRD TERM'],
        default => ['ordinal' => 1, 'short' => '1st Term', 'upper' => 'FIRST TERM'],
    };
}

/**
 * Assemble all data needed to render a student's report card for a given session, term, and class.
 */
function build_student_report_card_data(
    PDO $pdo,
    int $studentId,
    int $sessionId,
    int $termId,
    int $classId,
    bool $requirePublished = true
): ?array {
    // 1. Fetch student
    $stStmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch();
    if (!$student) {
        return null;
    }

    // 2. Fetch batch, session, term, class
    $statusClause = $requirePublished ? "AND rb.status = 'PUBLISHED'" : "";
    $bStmt = $pdo->prepare("
        SELECT rb.*,
               a.name AS session_name, a.start_date AS session_start, a.end_date AS session_end,
               t.name AS term_name, t.start_date AS term_start, t.end_date AS term_end,
               c.name AS class_name
        FROM result_batches rb
        JOIN academic_sessions a ON rb.academic_session_id = a.id
        JOIN terms t ON rb.term_id = t.id
        JOIN classes c ON rb.class_id = c.id
        WHERE rb.academic_session_id = :session_id
          AND rb.term_id = :term_id
          AND rb.class_id = :class_id
          {$statusClause}
        LIMIT 1
    ");
    $bStmt->execute([
        'session_id' => $sessionId,
        'term_id'    => $termId,
        'class_id'   => $classId,
    ]);
    $batch = $bStmt->fetch();
    if (!$batch) {
        return null;
    }

    // 3. Fetch all terms in this session ordered by id ASC to identify 1st, 2nd, 3rd term and next term
    $termsStmt = $pdo->prepare("SELECT * FROM terms WHERE academic_session_id = ? ORDER BY id ASC");
    $termsStmt->execute([$sessionId]);
    $sessionTerms = $termsStmt->fetchAll();

    $termIndex = 1;
    $firstTermId = null;
    $secondTermId = null;
    $nextTermStart = null;
    foreach ($sessionTerms as $idx => $st) {
        $ord = resolve_term_ordinal($st['name'], $idx + 1)['ordinal'];
        if ($ord === 1) {
            $firstTermId = (int)$st['id'];
        } elseif ($ord === 2) {
            $secondTermId = (int)$st['id'];
        }
        if ((int)$st['id'] === $termId) {
            $termIndex = $idx + 1;
            if (isset($sessionTerms[$idx + 1]) && !empty($sessionTerms[$idx + 1]['start_date'])) {
                $nextTermStart = $sessionTerms[$idx + 1]['start_date'];
            }
        }
    }

    $termMeta = resolve_term_ordinal($batch['term_name'], $termIndex);
    $termOrdinal = $termMeta['ordinal'];

    // 4. Fetch current term scores
    $scoreStmt = $pdo->prepare("
        SELECT sub.id AS subject_id, sub.name AS subject_name,
               s.ca_score, s.exam_score, s.total_score, s.grade, s.remark
        FROM scores s
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.result_batch_id = :batch_id AND s.student_id = :student_id
        ORDER BY sub.name ASC
    ");
    $scoreStmt->execute([
        'batch_id'   => $batch['id'],
        'student_id' => $studentId,
    ]);
    $rawScores = $scoreStmt->fetchAll();

    // 5. If 2nd or 3rd term, fetch previous term scores in the same session for this student
    $firstTermScores = [];
    $secondTermScores = [];

    if ($termOrdinal >= 2 && $firstTermId && $firstTermId !== $termId) {
        $ftStmt = $pdo->prepare("
            SELECT s.subject_id, s.ca_score, s.exam_score, s.total_score
            FROM scores s
            JOIN result_batches rb ON s.result_batch_id = rb.id
            WHERE s.student_id = :sid
              AND rb.academic_session_id = :sess
              AND rb.term_id = :tid
              AND rb.class_id = :cid
        ");
        $ftStmt->execute([
            'sid'  => $studentId,
            'sess' => $sessionId,
            'tid'  => $firstTermId,
            'cid'  => $classId,
        ]);
        foreach ($ftStmt->fetchAll() as $r) {
            $tot = ($r['ca_score'] !== null || $r['exam_score'] !== null)
                ? (float)($r['ca_score'] ?? 0) + (float)($r['exam_score'] ?? 0)
                : ($r['total_score'] !== null ? (float)$r['total_score'] : null);
            if ($tot !== null) {
                $firstTermScores[(int)$r['subject_id']] = $tot;
            }
        }
    }

    if ($termOrdinal === 3 && $secondTermId && $secondTermId !== $termId) {
        $st2Stmt = $pdo->prepare("
            SELECT s.subject_id, s.ca_score, s.exam_score, s.total_score
            FROM scores s
            JOIN result_batches rb ON s.result_batch_id = rb.id
            WHERE s.student_id = :sid
              AND rb.academic_session_id = :sess
              AND rb.term_id = :tid
              AND rb.class_id = :cid
        ");
        $st2Stmt->execute([
            'sid'  => $studentId,
            'sess' => $sessionId,
            'tid'  => $secondTermId,
            'cid'  => $classId,
        ]);
        foreach ($st2Stmt->fetchAll() as $r) {
            $tot = ($r['ca_score'] !== null || $r['exam_score'] !== null)
                ? (float)($r['ca_score'] ?? 0) + (float)($r['exam_score'] ?? 0)
                : ($r['total_score'] !== null ? (float)$r['total_score'] : null);
            if ($tot !== null) {
                $secondTermScores[(int)$r['subject_id']] = $tot;
            }
        }
    }

    $showFirstTermColumn = ($termOrdinal >= 2 && !empty($firstTermScores));
    $showSecondTermColumn = ($termOrdinal === 3 && !empty($secondTermScores));
    $showWeightedAverage = ($showFirstTermColumn || $showSecondTermColumn);

    $subjects = [];
    $totalObtained = 0.0;
    $subjectCount = count($rawScores);

    foreach ($rawScores as $row) {
        $subId = (int)$row['subject_id'];
        // Authoritative server-side computation: total = ca + exam
        $ca = $row['ca_score'] !== null ? (float)$row['ca_score'] : null;
        $exam = $row['exam_score'] !== null ? (float)$row['exam_score'] : null;
        $currentTotal = ($ca !== null || $exam !== null) ? (($ca ?? 0.0) + ($exam ?? 0.0)) : null;

        $t1Score = $firstTermScores[$subId] ?? null;
        $t2Score = $secondTermScores[$subId] ?? null;

        $termComponents = [];
        if ($currentTotal !== null) {
            $termComponents[] = $currentTotal;
        }
        if ($showFirstTermColumn && $t1Score !== null) {
            $termComponents[] = $t1Score;
        }
        if ($showSecondTermColumn && $t2Score !== null) {
            $termComponents[] = $t2Score;
        }

        $weightedAvg = !empty($termComponents)
            ? array_sum($termComponents) / count($termComponents)
            : null;

        $effectiveScore = $showWeightedAverage ? $weightedAvg : $currentTotal;
        $gradeInfo = calculate_shoheed_grade($effectiveScore);

        if ($effectiveScore !== null) {
            $totalObtained += $effectiveScore;
        }

        $subjects[] = [
            'subject_id'       => $subId,
            'subject_name'     => $row['subject_name'],
            'ca_score'         => $ca,
            'exam_score'       => $exam,
            'current_total'    => $currentTotal,
            'first_term_score' => $t1Score,
            'second_term_score'=> $t2Score,
            'weighted_average' => $weightedAvg,
            'effective_score'  => $effectiveScore,
            'grade'            => $gradeInfo['grade'],
            'remark'           => $gradeInfo['remark'],
        ];
    }

    $totalObtainable = $subjectCount * 100;
    $percentage = $subjectCount > 0 ? ($totalObtained / $subjectCount) : 0.0;

    // 6. Fetch student evaluation (SDBA, attendance overrides, comments)
    $evalStmt = $pdo->prepare("
        SELECT * FROM student_result_evaluations
        WHERE result_batch_id = ? AND student_id = ?
        LIMIT 1
    ");
    $evalStmt->execute([$batch['id'], $studentId]);
    $evaluation = $evalStmt->fetch() ?: [];

    // 7. Compute Attendance directly from Class Teacher's marked attendance register (attendance table)
    $openedStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT attendance_date)
        FROM attendance
        WHERE class_id = ? AND academic_session_id = ? AND term_id = ?
    ");
    $openedStmt->execute([$classId, $sessionId, $termId]);
    $classDaysOpened = (int)$openedStmt->fetchColumn();

    $attStmt = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM attendance
        WHERE student_id = ? AND academic_session_id = ? AND term_id = ?
        GROUP BY status
    ");
    $attStmt->execute([$studentId, $sessionId, $termId]);
    $attCounts = ['PRESENT' => 0, 'LATE' => 0, 'EXCUSED' => 0, 'ABSENT' => 0];
    foreach ($attStmt->fetchAll() as $ar) {
        $key = strtoupper(trim($ar['status']));
        if (isset($attCounts[$key])) {
            $attCounts[$key] += (int)$ar['cnt'];
        }
    }

    $recordedPresent = $attCounts['PRESENT'] + $attCounts['LATE'];
    $recordedAbsent = $attCounts['ABSENT'];
    $recordedTotal = $recordedPresent + $recordedAbsent + $attCounts['EXCUSED'];

    $timesOpened = max($classDaysOpened, $recordedTotal);
    $timesPresent = $recordedPresent;
    $timesAbsent = $recordedAbsent;

    // 8. Resolve SDBA ratings
    $sdbaRatings = [];
    foreach (array_keys(get_sdba_fields()) as $col) {
        $sdbaRatings[$col] = isset($evaluation[$col]) && $evaluation[$col] !== null
            ? (int)$evaluation[$col]
            : null;
    }

    // 9. Resolve Comments
    $teacherComment = !empty($evaluation['class_teacher_comment'])
        ? $evaluation['class_teacher_comment']
        : get_default_teacher_comment($percentage);

    $principalComment = !empty($evaluation['principal_comment'])
        ? $evaluation['principal_comment']
        : get_default_principal_comment($percentage);

    return [
        'student'                  => $student,
        'batch'                    => $batch,
        'term_meta'                => $termMeta,
        'duration_weeks'           => !empty($batch['duration_weeks']) ? (int)$batch['duration_weeks'] : 14,
        'term_begins'              => $batch['term_begins'] ?: ($batch['term_start'] ?: null),
        'term_ends'                => $batch['term_ends'] ?: ($batch['term_end'] ?: null),
        'next_term_begins'         => $batch['next_term_begins'] ?: $nextTermStart,
        'times_school_opened'      => $timesOpened,
        'times_present'            => $timesPresent,
        'times_absent'             => $timesAbsent,
        'show_first_term_column'   => $showFirstTermColumn,
        'show_second_term_column'  => $showSecondTermColumn,
        'show_weighted_average'    => $showWeightedAverage,
        'subjects'                 => $subjects,
        'subject_count'            => $subjectCount,
        'total_obtainable'         => $totalObtainable,
        'total_obtained'           => $totalObtained,
        'percentage'               => $percentage,
        'sdba_ratings'             => $sdbaRatings,
        'class_teacher_comment'    => $teacherComment,
        'principal_comment'        => $principalComment,
        'raw_evaluation'           => $evaluation,
    ];
}
