<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $pageTitle = $pageTitle ?? 'Shoheed Schools'; ?>
    <title><?= e($pageTitle) ?> | Shoheed Schools</title>
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
                        'serif': ['DM Serif Display', 'serif']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700&display=swap');
    </style>
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
</head>
<body class="antialiased min-h-screen flex flex-col bg-ivory">
