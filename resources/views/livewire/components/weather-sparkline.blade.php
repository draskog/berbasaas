<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public array $data = [];

    #[Computed]
    public function chartData(): array
    {
        return [
            'labels' => $this->getHours(),
            'datasets' => [
                [
                    'label' => 'Padavine (mm)',
                    'data' => $this->data,
                    'fill' => true,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ],
            ],
        ];
    }

    public function getHours(): array
    {
        $hours = [];
        for ($i = 9; $i <= 20; $i++) {
            $hours[] = str_pad($i, 2, '0', STR_PAD_LEFT).':00';
        }

        return $hours;
    }
}; ?>

<div class="w-full">
    <flux:chart type="line" :data="$this->chartData" />
</div>
