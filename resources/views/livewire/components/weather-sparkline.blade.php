<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $data = [];

    public function getChartData(): array
    {
        return [
            'labels' => $this->getHours(),
            'datasets' => [
                [
                    'label' => 'mm',
                    'data' => $this->data,
                    'fill' => true,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'borderWidth' => 1.5,
                ],
            ],
        ];
    }

    private function getHours(): array
    {
        $hours = [];
        for ($i = 9; $i <= 20; $i++) {
            $hours[] = $i.':00';
        }

        return $hours;
    }
}; ?>

<div class="w-full h-24">
    <flux:chart type="line" :data="$this->getChartData()" />
</div>
