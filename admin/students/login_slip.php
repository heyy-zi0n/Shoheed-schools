<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

// Check if we have slip data in session
if (!isset($_SESSION['student_login_slip']) || !isset($_SESSION['student_login_slip']['pin'])) {
    set_flash('error', 'Login slip data is expired or unavailable. You can generate a new slip from the student profile.');
    redirect('admin/students/index.php');
}

$slipData = $_SESSION['student_login_slip'];

// Clear the raw PIN from the session immediately so it is not persisted
unset($_SESSION['student_login_slip']['pin']);

$page_title = 'Student Login Slip';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Shoheed Schools</title>
    <link rel="icon" type="image/png" href="<?= url('assets/images/branding/shoheed-logo.png') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'school-blue': '#263B8F',
                        'school-navy': '#111B3F',
                        'school-red': '#C62828',
                        'school-gold': '#E5B926',
                        'ivory': '#F5F1E8',
                        'paper': '#FFFDFC',
                        'slate-gray': '#697386',
                        'charcoal': '#20242C',
                        'sage': '#5D7A68',
                        'amber': '#B7813B',
                        'dusty-red': '#B95656'
                    },
                    fontFamily: {
                        'sans': ['Manrope', 'sans-serif'],
                        'serif': ['DM Serif Display', 'serif'],
                        'mono': ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', '"Liberation Mono"', '"Courier New"', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700&display=swap');

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-border {
                border: 2px dashed #000 !important;
                padding: 2rem !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans text-charcoal p-8">

    <!-- Controls (No Print) -->
    <div class="max-w-2xl mx-auto mb-8 no-print flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-xl font-serif font-bold text-school-navy">Student Login Slip</h2>
        <div class="flex gap-4">
            <button onclick="window.print()" class="bg-school-blue text-white px-4 py-2 rounded font-medium hover:bg-school-navy transition-colors">
                Print Login Slip
            </button>
            <a href="<?= url("admin/students/view.php?id=" . urlencode($slipData['student_id'])) ?>" class="border border-gray-300 text-charcoal px-4 py-2 rounded font-medium hover:bg-gray-50 transition-colors">
                Continue to Student Profile
            </a>
        </div>
    </div>

    <!-- Printable Slip -->
    <div class="max-w-2xl mx-auto bg-white p-10 shadow-sm border border-gray-200 print-container print-border">

        <div class="text-center mb-8 pb-8 border-b border-gray-200">
            <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Logo" class="h-20 mx-auto mb-4">
            <h1 class="text-3xl font-serif font-bold text-school-navy uppercase tracking-wider">Shoheed Schools</h1>
            <p class="text-sm font-medium tracking-[0.2em] text-school-blue uppercase mt-1">God Is Sufficient</p>
            <h2 class="mt-6 text-xl font-bold bg-gray-100 py-2 inline-block px-8 rounded">STUDENT PORTAL LOGIN DETAILS</h2>
        </div>

        <div class="grid grid-cols-2 gap-y-6 mb-8">
            <div>
                <p class="text-xs text-slate-gray uppercase font-bold tracking-wider mb-1">Student Name</p>
                <p class="text-lg font-bold text-charcoal"><?= e($slipData['student_name']) ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-gray uppercase font-bold tracking-wider mb-1">Admission Number</p>
                <p class="text-lg font-bold text-charcoal"><?= e($slipData['admission_number']) ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-gray uppercase font-bold tracking-wider mb-1">Class</p>
                <p class="text-lg font-medium text-charcoal"><?= e($slipData['class_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-gray uppercase font-bold tracking-wider mb-1">Academic Session</p>
                <p class="text-lg font-medium text-charcoal"><?= e($slipData['session_name'] ?? 'N/A') ?></p>
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 p-6 rounded-lg mb-8 text-center">
            <h3 class="text-sm uppercase font-bold text-slate-gray tracking-wider mb-6">PORTAL LOGIN</h3>

            <div class="flex flex-col items-center gap-4 mb-6">
                <div>
                    <p class="text-xs text-slate-gray mb-1 uppercase font-bold">Username / Admission Number</p>
                    <p class="text-2xl font-mono font-bold text-school-navy tracking-wider bg-white border border-gray-200 px-6 py-2 rounded"><?= e($slipData['admission_number']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-slate-gray mb-1 uppercase font-bold">Password / PIN</p>
                    <p class="text-3xl font-mono font-bold text-school-blue tracking-[0.2em] bg-white border border-gray-200 px-8 py-3 rounded"><?= e($slipData['pin']) ?></p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <h3 class="font-bold text-school-navy mb-2">HOW TO LOGIN</h3>
            <ol class="list-decimal list-inside text-sm text-charcoal space-y-2 mb-6">
                <li>Open the Shoheed Schools Portal.</li>
                <li>Enter your Admission Number as your username.</li>
                <li>Enter the six-digit PIN shown above.</li>
                <li>Click Sign In.</li>
            </ol>

            <div class="bg-red-50 text-school-red p-4 rounded text-sm font-medium">
                <p>Keep this slip private. Anyone with these login details may access the Student Portal.</p>
                <p class="mt-2">Keep this slip safe. If it is lost, contact the school office for a new login slip.</p>
            </div>
        </div>

    </div>

</body>
</html>
