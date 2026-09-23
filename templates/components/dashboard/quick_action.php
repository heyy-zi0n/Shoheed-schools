<?php
/**
 * @var string $title
 * @var string $url
 * @var string $icon (1-2 characters, e.g. 'S', 'T')
 * @var string $color (e.g. 'school-blue', 'school-gold', 'school-red', 'sage')
 */
$color = $color ?? 'school-blue';
?>
<a href="<?= e($url) ?>" class="flex items-center p-4 bg-paper border border-gray-200 rounded-lg shadow-sm hover:bg-blue-50 hover:border-school-blue transition-colors group">
    <div class="w-10 h-10 rounded bg-<?= e($color) ?> text-white flex items-center justify-center mr-4 flex-shrink-0 group-hover:scale-105 transition-transform">
        <span class="font-bold"><?= e($icon) ?></span>
    </div>
    <div>
        <p class="font-bold text-sm text-school-navy"><?= e($title) ?></p>
    </div>
</a>
