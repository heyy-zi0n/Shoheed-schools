<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $pageTitle = $pageTitle ?? 'Shoheed Schools'; ?>
    <title><?= e($pageTitle) ?> | Shoheed Schools</title>
    <link rel="icon" type="image/png" href="<?= url('assets/images/branding/shoheed-logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= url('assets/css/tailwind.min.css') ?>?v=<?= filemtime(__DIR__ . '/../../assets/css/tailwind.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
</head>
<body class="antialiased min-h-screen flex flex-col bg-ivory">
