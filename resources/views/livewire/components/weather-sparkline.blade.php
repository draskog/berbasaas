<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $data = [];
}; ?>

<div class="w-full h-20 flex items-end justify-between gap-0.5 px-1">
    @forelse ($data as $value)
        @php
            $maxValue = max($data) ?: 1;
            $height = ($value / $maxValue) * 100;
        @endphp
        <div
            class="flex-1 bg-blue-400 dark:bg-blue-500 rounded-sm opacity-75 hover:opacity-100 transition-opacity"
            style="height: {{ $height }}%"
            title="{{ number_format($value, 1) }} mm"
        ></div>
    @empty
        <div class="text-xs text-zinc-500 text-center w-full">—</div>
    @endforelse
</div>
