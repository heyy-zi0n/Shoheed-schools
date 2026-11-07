<?php
/**
 * templates/components/report_card.php
 *
 * Reusable Shoheed Continuous Assessment Report sheet component.
 * Expects $report (returned by build_student_report_card_data()).
 */
if (empty($report)) {
    return;
}

$student = $report['student'];
$batch = $report['batch'];
$termMeta = $report['term_meta'];
$sdbaCategories = get_sdba_categories();
$sdbaRatings = $report['sdba_ratings'] ?? [];

$personalKeys = array_keys($sdbaCategories['PERSONAL DEV.']);
$senseKeys    = array_keys($sdbaCategories['SENSE OF RESP.']);
$psychoKeys   = array_keys($sdbaCategories['PSYCHOMOTOR']);

$fullName = strtoupper(trim(
    ($student['last_name'] ?? '') . ' ' .
    ($student['first_name'] ?? '') . ' ' .
    ($student['middle_name'] ?? '')
));
?>
<div class="shoheed-report-sheet bg-white text-black p-6 md:p-8 rounded shadow-sm border border-gray-300 max-w-4xl mx-auto">
    <!-- Report Sheet Header -->
    <div class="flex items-start gap-4 mb-4">
        <div class="w-28 flex-shrink-0 flex flex-col items-center text-center">
            <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Crest" class="h-16 w-16 object-contain mb-1">
            <div class="text-[11px] font-bold uppercase leading-tight text-amber">
                <div><?= e($batch['class_name']) ?></div>
                <div><?= e($termMeta['upper']) ?></div>
            </div>
        </div>
        <div class="flex-1 text-center pr-4 md:pr-12">
            <h2 class="text-xl md:text-2xl font-bold uppercase tracking-wide text-school-red leading-tight">
                SHOHEED SECONDARY SCHOOL
            </h2>
            <p class="text-xs md:text-sm font-medium text-charcoal leading-snug mt-0.5">
                <span class="font-bold">School 1 :</span> 5, Olufunmilayo Close, Alakuko, Lagos
            </p>
            <p class="text-xs md:text-sm font-medium text-charcoal leading-snug">
                <span class="font-bold">School 2 :</span> Andiri Bus Stop, Ibaragun, Ogun State
            </p>
            <h3 class="text-sm md:text-base font-bold text-school-blue mt-1.5">
                Continuous Assessment Report <?= e($batch['session_name']) ?>
            </h3>
        </div>
    </div>

    <!-- Top Metadata Grid: Personal Data / Terminal Duration (Left) & Attendance / Grade Key (Right) -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4 items-start report-top-grid">
        <!-- Left Column (7 cols) -->
        <div class="md:col-span-7 space-y-3">
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-amber mb-0.5">
                    STUDENT'S PERSONAL DATA
                </div>
                <table class="w-full report-grid-table text-xs">
                    <tbody>
                        <tr>
                            <td class="w-32 font-semibold uppercase">NAME</td>
                            <td class="font-bold uppercase"><?= e($fullName) ?></td>
                            <td class="w-20"></td>
                        </tr>
                        <tr>
                            <td class="font-semibold uppercase">ADMISSION NO</td>
                            <td class="font-bold"><?= e($student['admission_number']) ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-semibold uppercase">SEX</td>
                            <td class="font-bold uppercase"><?= e($student['gender'] ?? '-') ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-amber mb-0.5">
                    TERMINAL DURATION (<?= (int)$report['duration_weeks'] ?> WEEKS)
                </div>
                <table class="w-full report-grid-table text-xs">
                    <tbody>
                        <tr>
                            <td class="w-32 font-semibold">Term Begins</td>
                            <td class="font-medium"><?= e(format_report_date($report['term_begins'])) ?></td>
                            <td class="w-20"></td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Term Ends</td>
                            <td class="font-medium"><?= e(format_report_date($report['term_ends'])) ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Next Term Begins</td>
                            <td class="font-medium"><?= e(format_report_date($report['next_term_begins'])) ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column (5 cols) -->
        <div class="md:col-span-5 space-y-3">
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-amber mb-0.5 text-center md:text-left">
                    ATTENDANCE
                </div>
                <table class="w-full report-grid-table text-xs">
                    <tbody>
                        <tr>
                            <td class="font-semibold">Times sch opened</td>
                            <td class="w-20 text-center font-bold">
                                <?= $report['times_school_opened'] !== null ? (int)$report['times_school_opened'] : '-' ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Times Present</td>
                            <td class="text-center font-bold">
                                <?= $report['times_present'] !== null ? (int)$report['times_present'] : '-' ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="font-semibold">Times Absent</td>
                            <td class="text-center font-bold">
                                <?php
                                if ($report['times_absent'] === null) {
                                    echo '-';
                                } elseif ((int)$report['times_absent'] === 0) {
                                    echo 'NIL';
                                } else {
                                    echo (int)$report['times_absent'];
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-amber mb-0.5">
                    GRADE
                </div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-[11px] leading-snug text-charcoal font-medium">
                    <div>A - 75 - 100 (Excellent)</div>
                    <div>B2 - 71 - 74 (Very Good)</div>
                    <div>B3 - 65 - 70 (Good)</div>
                    <div>C4 - 61 - 64 (Credit)</div>
                    <div>C5 - 55 - 60 (Credit)</div>
                    <div>C6 - 50 - 54 (Credit)</div>
                    <div>D7 - 45 - 49 (Pass)</div>
                    <div>E8 - 40 - 44 (Pass)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Performance Section -->
    <div class="mb-3">
        <div class="text-center text-sm font-bold uppercase tracking-wider text-school-blue mb-1">
            ACADEMIC PERFORMANCE
        </div>
        <div class="overflow-x-auto">
            <table class="w-full report-grid-table text-xs">
                <thead>
                    <tr class="font-bold text-center">
                        <th scope="col" class="w-2/5 text-left">Subject</th>
                        <th scope="col" class="w-16"><?= e($termMeta['short']) ?><br>C.A</th>
                        <th class="w-16"><?= e($termMeta['short']) ?><br>Exam</th>
                        <?php if ($report['show_first_term_column'] && $report['show_second_term_column']): ?>
                            <th class="w-16"><?= e($termMeta['short']) ?><br>Scores</th>
                            <th class="w-16">1st term<br>scores</th>
                            <th class="w-16">2nd term<br>scores</th>
                            <th class="w-20">Weighted<br>Average</th>
                        <?php elseif ($report['show_first_term_column']): ?>
                            <th class="w-16">1st term<br>scores</th>
                            <th class="w-20">Weighted<br>Average</th>
                        <?php else: ?>
                            <th class="w-20"><?= e($termMeta['short']) ?><br>scores</th>
                        <?php endif; ?>
                        <th class="w-16">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Obtainable Mark Row -->
                    <tr class="font-bold text-center bg-gray-50/60">
                        <td class="text-left font-bold">Obtainable mark</td>
                        <td>40</td>
                        <td>60</td>
                        <?php if ($report['show_first_term_column'] && $report['show_second_term_column']): ?>
                            <td>100</td>
                            <td>100</td>
                            <td>100</td>
                            <td>100</td>
                        <?php elseif ($report['show_first_term_column']): ?>
                            <td>100</td>
                            <td>100</td>
                        <?php else: ?>
                            <td>100</td>
                        <?php endif; ?>
                        <td></td>
                    </tr>

                    <?php if (empty($report['subjects'])): ?>
                        <tr>
                            <td colspan="8" class="text-center py-6 text-slate-gray">No scores recorded for this term.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['subjects'] as $sub): ?>
                            <tr class="text-center">
                                <td class="text-left font-medium"><?= e($sub['subject_name']) ?></td>
                                <td><?= e(format_report_score($sub['ca_score'])) ?></td>
                                <td><?= e(format_report_score($sub['exam_score'])) ?></td>
                                <?php if ($report['show_first_term_column'] && $report['show_second_term_column']): ?>
                                    <td><?= e(format_report_score($sub['current_total'])) ?></td>
                                    <td><?= e(format_report_score($sub['first_term_score'])) ?></td>
                                    <td><?= e(format_report_score($sub['second_term_score'])) ?></td>
                                    <td class="font-semibold"><?= e(format_report_score($sub['weighted_average'])) ?></td>
                                <?php elseif ($report['show_first_term_column']): ?>
                                    <td><?= e(format_report_score($sub['first_term_score'])) ?></td>
                                    <td class="font-semibold"><?= e(format_report_score($sub['weighted_average'])) ?></td>
                                <?php else: ?>
                                    <td class="font-semibold"><?= e(format_report_score($sub['current_total'])) ?></td>
                                <?php endif; ?>
                                <td class="font-bold"><?= e($sub['grade']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary Totals & SDBA Key Row -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-3 items-start report-summary-grid">
        <div class="md:col-span-6">
            <table class="w-full report-grid-table text-xs">
                <tbody>
                    <tr>
                        <td class="font-semibold">Total mark obtainable</td>
                        <td class="w-20 text-center font-bold"><?= (int)$report['total_obtainable'] ?></td>
                        <td class="w-16"></td>
                    </tr>
                    <tr>
                        <td class="font-semibold">Total mark obtained</td>
                        <td class="text-center font-bold"><?= e(format_report_score($report['total_obtained'])) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="font-semibold">Percentage</td>
                        <td class="text-center font-bold"><?= number_format((float)$report['percentage'], 1) ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="md:col-span-6">
            <div class="text-xs font-bold uppercase tracking-wider text-amber mb-0.5">
                KEYS TO RATINGS ON SDBA
            </div>
            <p class="text-[11px] leading-snug text-charcoal font-medium">
                Excellent (5) &nbsp; High level traits (4)<br>
                Acceptable level traits (3) &nbsp; Minimal<br>
                level traits (2) &nbsp; Show no regard (1)
            </p>
        </div>
    </div>

    <!-- Skill Development and Behavioural Attributes (SDBA) -->
    <div class="mb-4">
        <div class="text-xs font-bold uppercase tracking-wider text-school-blue mb-1">
            SKILL DEVELOPMENT AND BEHAVIOURAL ATTRIBUTES (SDBA)
        </div>
        <table class="w-full report-grid-table text-[11px]">
            <thead>
                <tr class="text-left font-bold uppercase text-amber">
                    <th colspan="2" class="w-1/3">PERSONAL DEV.</th>
                    <th colspan="2" class="w-1/3">SENSE OF RESP.</th>
                    <th colspan="2" class="w-1/3">PSYCHOMOTOR</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < 5; $i++):
                    $pKey = $personalKeys[$i] ?? null;
                    $sKey = $senseKeys[$i] ?? null;
                    $mKey = $psychoKeys[$i] ?? null;
                ?>
                    <tr>
                        <td class="uppercase font-medium"><?= $pKey ? e($sdbaCategories['PERSONAL DEV.'][$pKey]) : '' ?></td>
                        <td class="w-10 text-center font-bold"><?= ($pKey && isset($sdbaRatings[$pKey]) && $sdbaRatings[$pKey] !== null) ? (int)$sdbaRatings[$pKey] : '' ?></td>

                        <td class="uppercase font-medium"><?= $sKey ? e($sdbaCategories['SENSE OF RESP.'][$sKey]) : '' ?></td>
                        <td class="w-10 text-center font-bold"><?= ($sKey && isset($sdbaRatings[$sKey]) && $sdbaRatings[$sKey] !== null) ? (int)$sdbaRatings[$sKey] : '' ?></td>

                        <td class="uppercase font-medium"><?= $mKey ? e($sdbaCategories['PSYCHOMOTOR'][$mKey]) : '' ?></td>
                        <td class="w-10 text-center font-bold"><?= ($mKey && isset($sdbaRatings[$mKey]) && $sdbaRatings[$mKey] !== null) ? (int)$sdbaRatings[$mKey] : '' ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Comments & Signature Footer -->
    <div class="pt-1 text-xs space-y-2">
        <div>
            <span class="font-bold">Class teacher's comments :</span>
            <span class="font-medium ml-1"><?= e($report['class_teacher_comment']) ?></span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 pt-1">
            <div>
                <span class="font-bold">Principal's comment :</span>
                <span class="font-medium ml-1"><?= e($report['principal_comment']) ?></span>
            </div>
            <div class="text-center sm:w-56 flex-shrink-0 pt-4 sm:pt-0">
                <div class="border-b border-black mb-1"></div>
                <div class="text-xs font-medium text-charcoal">Stamp and Signature</div>
            </div>
        </div>
    </div>
</div>

<style>
.shoheed-report-sheet .report-grid-table {
    border-collapse: collapse;
    border: 1.5px solid #1f2937;
}
.shoheed-report-sheet .report-grid-table th,
.shoheed-report-sheet .report-grid-table td {
    border: 1px solid #374151;
    padding: 3px 6px;
}
@media print {
    @page {
        size: A4 portrait;
        margin: 0.8cm;
    }
    .shoheed-report-sheet {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        font-size: 11px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .shoheed-report-sheet .report-top-grid {
        display: grid !important;
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
    }
    .shoheed-report-sheet .report-top-grid > :first-child {
        grid-column: span 7 / span 7 !important;
    }
    .shoheed-report-sheet .report-top-grid > :last-child {
        grid-column: span 5 / span 5 !important;
    }
    .shoheed-report-sheet .report-summary-grid {
        display: grid !important;
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
    }
    .shoheed-report-sheet .report-summary-grid > * {
        grid-column: span 6 / span 6 !important;
    }
    .shoheed-report-sheet .report-grid-table th,
    .shoheed-report-sheet .report-grid-table td {
        padding: 2.5px 5px !important;
    }
}
</style>
