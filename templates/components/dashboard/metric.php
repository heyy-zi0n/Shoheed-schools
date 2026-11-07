<?php
/**
 * @var string $title
 * @var mixed $value
 * @var string|null $subtitle
 * @var string|null $color (e.g. 'school-blue', 'school-gold', 'school-red', 'sage', 'school-navy')
 * @var string|null $link
 */
$color = $color ?? 'school-blue';
$subtitle = $subtitle ?? null;
$link = $link ?? null;
$borderColorMap = [
    'school-blue' => 'border-l-school-blue',
    'school-navy' => 'border-l-school-navy',
    'school-red'  => 'border-l-school-red',
    'school-gold' => 'border-l-school-gold',
    'sage'        => 'border-l-sage',
    'amber'       => 'border-l-amber',
    'dusty-red'   => 'border-l-dusty-red',
    'slate-gray'  => 'border-l-slate-gray',
];
$borderClass = $borderColorMap[$color] ?? 'border-l-school-blue';
?>
<div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 border-l-4 <?= $borderClass ?> h-full flex flex-col justify-between">
    <div>
        <p class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1"><?= e($title) ?></p>
        <p class="text-3xl font-serif font-bold text-school-navy mt-1 truncate"><?= is_numeric($value) && floor($value) == $value ? number_format((float)$value) : e($value) ?></p>
        <?php if (!empty($subtitle)): ?>
            <p class="text-xs text-slate-gray mt-2"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($link)): ?>
        <div class="mt-4 pt-4 border-t border-gray-50">
            <a href="<?= e($link) ?>" class="text-sm text-school-blue hover:text-school-navy font-medium">View details &rarr;</a>
        </div>
    <?php endif; ?>
</div>
