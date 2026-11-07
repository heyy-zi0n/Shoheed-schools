<?php
/**
 * @var string $title
 * @var string $url
 * @var string $icon (1-2 characters, e.g. 'S', 'T')
 * @var string $color (e.g. 'school-blue', 'school-gold', 'school-red', 'sage')
 */
$color = $color ?? 'school-blue';
$bgColorMap = [
    'school-blue' => 'bg-school-blue',
    'school-navy' => 'bg-school-navy',
    'school-red'  => 'bg-school-red',
    'school-gold' => 'bg-school-gold',
    'sage'        => 'bg-sage',
    'amber'       => 'bg-amber',
    'dusty-red'   => 'bg-dusty-red',
    'charcoal'    => 'bg-charcoal',
];
$bgClass = $bgColorMap[$color] ?? 'bg-school-blue';
?>
<a href="<?= e($url) ?>" class="flex items-center p-4 bg-paper border border-gray-200 rounded-lg shadow-sm hover:bg-blue-50 hover:border-school-blue transition-colors group">
    <div class="w-10 h-10 rounded <?= $bgClass ?> text-white flex items-center justify-center mr-4 flex-shrink-0 group-hover:scale-105 transition-transform">
        <span class="font-bold"><?= e($icon) ?></span>
    </div>
    <div>
        <p class="font-bold text-sm text-school-navy"><?= e($title) ?></p>
    </div>
</a>
