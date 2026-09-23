<?php

// Role Constants
define('ROLE_ADMIN', 'ADMIN');
define('ROLE_PRINCIPAL', 'PRINCIPAL');
define('ROLE_CLASS_TEACHER', 'CLASS_TEACHER');
define('ROLE_SUBJECT_TEACHER', 'SUBJECT_TEACHER');
define('ROLE_BURSAR', 'BURSAR');
define('ROLE_STUDENT', 'STUDENT');
define('ROLE_PARENT', 'PARENT');

// Result Workflow States
define('RESULT_DRAFT', 'DRAFT');
define('RESULT_SUBMITTED', 'SUBMITTED');
define('RESULT_REVISION_REQUIRED', 'REVISION_REQUIRED');
define('RESULT_CLASS_TEACHER_VERIFIED', 'CLASS_TEACHER_VERIFIED');
define('RESULT_PRINCIPAL_APPROVED', 'PRINCIPAL_APPROVED');
define('RESULT_PUBLISHED', 'PUBLISHED');

// Attendance Statuses
define('ATTENDANCE_PRESENT', 'Present');
define('ATTENDANCE_ABSENT', 'Absent');
define('ATTENDANCE_LATE', 'Late');
define('ATTENDANCE_EXCUSED', 'Excused');

// Session Configuration
define('SESSION_IDLE_TIMEOUT', 1800);     // 30 minutes
define('SESSION_ABSOLUTE_TIMEOUT', 28800); // 8 hours
define('SESSION_REGEN_INTERVAL', 1800);    // 30 minutes

// Invoice Statuses
define('INVOICE_PENDING', 'PENDING');
define('INVOICE_PARTIAL', 'PARTIAL');
define('INVOICE_PAID', 'PAID');
