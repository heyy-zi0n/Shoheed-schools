<?php
/**
 * @var string $title
 * @var mixed $value
 * @var string|null $subtitle
 * @var string|null $color (e.g. 'school-blue', 'school-gold', 'school-red', 'sage', 'school-navy')
 * @var string|null $link
 */
$color = $color ?? 'school-navy';
$subtitle = $subtitle ?? null;
$link = $link ?? null;

$indicatorDotMap = [
    'school-red'  => 'bg-school-red',
    'dusty-red'   => 'bg-dusty-red',
    'school-gold' => 'bg-school-gold',
    'amber'       => 'bg-amber',
    'sage'        => 'bg-sage',
    'school-blue' => 'bg-school-blue',
    'school-navy' => 'bg-school-navy',
    'slate-gray'  => 'bg-slate-gray',
];
$dotClass = $indicatorDotMap[$color] ?? 'bg-school-navy';
$isAlertTone = in_array($color, ['school-red', 'dusty-red'], true);
$valueColorClass = $isAlertTone ? 'text-school-red' : 'text-school-navy';
?>
<div class="bg-paper p-5 rounded-lg shadow-sm border border-gray-200/80 h-full flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-[11px] font-bold text-slate-gray uppercase tracking-[0.12em]"><?= e($title) ?></p>
            <span class="w-2 h-2 rounded-full flex-shrink-0 <?= $dotClass ?>" aria-hidden="true"></span>
        </div>
        <p class="text-2xl sm:text-3xl font-serif font-bold <?= $valueColorClass ?> tabular-nums tracking-tight truncate">
            <?= is_numeric($value) && floor((float)$value) == (float)$value ? number_format((float)$value) : e($value) ?>
        </p>
        <?php if (!empty($subtitle)): ?>
            <p class="text-xs text-slate-gray mt-1.5"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($link)): ?>
        <div class="mt-4 pt-3 border-t border-gray-100">
            <a href="<?= e($link) ?>" class="text-xs text-school-blue hover:text-school-navy font-semibold inline-flex items-center gap-1 transition-colors">
                <span>Open view</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    <?php endif; ?>
</div>
