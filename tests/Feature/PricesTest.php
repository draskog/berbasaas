<?php

test('updater handles array format from date picker', function () {
    $componentPath = resource_path('views/livewire/harvest/prices.blade.php');
    $code = file_get_contents($componentPath);

    expect($code)
        ->toContain('if (is_array($value))')
        ->toContain('$this->editEffectiveFrom = $value[\'start\'] ?? null;')
        ->toContain('$this->editEffectiveTo = $value[\'end\'] ?? null;');
});

test('updater handles string format with slash separator', function () {
    $componentPath = resource_path('views/livewire/harvest/prices.blade.php');
    $code = file_get_contents($componentPath);

    expect($code)
        ->toContain('elseif ($value && str_contains($value, \'/\'))')
        ->toContain('[$from, $to] = explode(\'/\', $value, 2);');
});
