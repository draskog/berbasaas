<?php

use App\Models\ReligiousHoliday;
use App\Models\WeatherRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    private const WMO_DESCRIPTIONS = [
        0 => ['emoji' => '☀️', 'label' => 'Sunčano'],
        1 => ['emoji' => '🌤️', 'label' => 'Većinski sunčano'],
        2 => ['emoji' => '⛅', 'label' => 'Delimično oblačno'],
        3 => ['emoji' => '☁️', 'label' => 'Oblačno'],
        45 => ['emoji' => '🌫️', 'label' => 'Magla'],
        48 => ['emoji' => '🌫️', 'label' => 'Magla sa rosulom'],
        51 => ['emoji' => '🌧️', 'label' => 'Laka kiša'],
        53 => ['emoji' => '🌧️', 'label' => 'Umeren kiša'],
        55 => ['emoji' => '🌧️', 'label' => 'Jaka kiša'],
        61 => ['emoji' => '🌧️', 'label' => 'Malo kiše'],
        63 => ['emoji' => '🌧️', 'label' => 'Umeren kiša'],
        65 => ['emoji' => '🌧️', 'label' => 'Jaka kiša'],
        71 => ['emoji' => '🌨️', 'label' => 'Mali sneg'],
        73 => ['emoji' => '🌨️', 'label' => 'Umeren sneg'],
        75 => ['emoji' => '🌨️', 'label' => 'Jak sneg'],
        77 => ['emoji' => '🌨️', 'label' => 'Sneg krupe'],
        80 => ['emoji' => '🌦️', 'label' => 'Laka kiša i sunce'],
        81 => ['emoji' => '🌦️', 'label' => 'Umeren kiša i sunce'],
        82 => ['emoji' => '🌦️', 'label' => 'Jaka kiša i sunce'],
        85 => ['emoji' => '🌨️', 'label' => 'Mali sneg i sunce'],
        86 => ['emoji' => '🌨️', 'label' => 'Jak sneg i sunce'],
        95 => ['emoji' => '⛈️', 'label' => 'Grmljavina'],
        96 => ['emoji' => '⛈️', 'label' => 'Grmljavina sa ledom'],
        99 => ['emoji' => '⛈️', 'label' => 'Grmljavina sa ledom'],
    ];

    #[Computed]
    public function weatherDays(): Collection
    {
        return WeatherRecord::where('company_id', auth()->user()->company_id)
            ->whereBetween('date', [now()->toDateString(), now()->addDays(6)->toDateString()])
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function religiousHolidays(): array
    {
        return ReligiousHoliday::whereBetween('date', [now()->toDateString(), now()->addDays(6)->toDateString()])
            ->get()
            ->mapWithKeys(fn ($holiday) => [$holiday->date->toDateString() => $holiday->description])
            ->toArray();
    }

    public function getWeatherDescription(int $code): array
    {
        return self::WMO_DESCRIPTIONS[$code] ?? ['emoji' => '❓', 'label' => 'Nepoznato'];
    }

    public function getDayName($date): string
    {
        $days = ['Ned', 'Pon', 'Uto', 'Sre', 'Čet', 'Pet', 'Sub'];
        if (is_string($date)) {
            $date = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('Y-m-d H:i:s', $date);
        }

        $dayIndex = (int) ($date?->format('w') ?? 0);

        return $days[$dayIndex];
    }

    public function getTotalPrecipitation(?array $hourly): float
    {
        if (! $hourly) {
            return 0;
        }

        return array_sum($hourly);
    }

    public function buildHourlyChartData(array $hourlyPrecipitation): array
    {
        $hours = [];
        for ($i = 9; $i <= 20; $i++) {
            $hours[] = str_pad($i, 2, '0', STR_PAD_LEFT).':00';
        }

        return collect($hourlyPrecipitation)
            ->take(12)
            ->map(fn ($mm, $index) => [
                'hour' => $hours[$index] ?? ($index + 9).':00',
                'mm' => round((float) $mm, 1),
            ])
            ->values()
            ->toArray();
    }
}; ?>

<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">
            {{ __('Vremenska prognoza - narednih 7 dana') }}
        </h3>

        @if ($this->weatherDays->isEmpty())
            <flux:card class="text-center py-8">
                <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                    {{ __('Meteorološki podaci još nisu dostupni') }}
                </p>
                <a href="{{ route('company.edit') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
                    {{ __('Postavi lokaciju kompanije') }}
                </a>
            </flux:card>
        @else
            <div class="grid grid-cols-3 gap-4">
                @foreach ($this->weatherDays as $weather)
                    @php
                        $date = $weather->date;
                        $dayName = $this->getDayName($date);
                        $dayOfMonth = $date->format('j');
                        $weatherDesc = $this->getWeatherDescription($weather->weather_code);
                        $totalPrecip = $this->getTotalPrecipitation($weather->hourly_precipitation);
                        $isHoliday = isset($this->religiousHolidays[$date->toDateString()]);
                        $holidayName = $this->religiousHolidays[$date->toDateString()] ?? null;
                    @endphp

                    <flux:card class="relative p-4 flex flex-col items-center">
                        <!-- Date header -->
                        <div class="text-center mb-3 w-full">
                            <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $dayName }}
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-lg font-bold text-zinc-900 dark:text-white">
                                    {{ $dayOfMonth }}
                                </span>
                                @if ($isHoliday)
                                    <flux:tooltip text="{{ $holidayName }}">
                                        <flux:badge color="red" class="cursor-help">
                                            ●
                                        </flux:badge>
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>

                        <!-- Weather icon and temp -->
                        <div class="text-center mb-3">
                            <div class="text-2xl mb-1">
                                {{ $weatherDesc['emoji'] }}
                            </div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">
                                {{ $weatherDesc['label'] }}
                            </div>
                            <div class="flex items-center justify-center gap-2 text-sm">
                                <span class="font-semibold text-zinc-900 dark:text-white">
                                    {{ number_format($weather->temperature_max, 0) }}°
                                </span>
                                <span class="text-zinc-600 dark:text-zinc-400">
                                    {{ number_format($weather->temperature_min, 0) }}°
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-500">
                                    💨 {{ number_format($weather->wind_speed_max, 0) }} km/h
                                </span>
                            </div>
                        </div>

                        <!-- Hourly precipitation chart -->
                        @if ($weather->hourly_precipitation)
                            @php $chartData = $this->buildHourlyChartData($weather->hourly_precipitation); @endphp
                            <div class="w-full mb-3 h-40">
                                <flux:chart :value="$chartData" class="w-full h-full">
                                    <flux:chart.svg>
                                        <flux:chart.area field="mm" class="text-blue-400" curve="smooth" />
                                        <flux:chart.line field="mm" class="text-blue-500" curve="smooth" stroke-width="2" />
                                        <flux:chart.axis axis="x" field="hour">
                                            <flux:chart.axis.tick />
                                        </flux:chart.axis>
                                        <flux:chart.axis axis="y">
                                            <flux:chart.axis.grid />
                                        </flux:chart.axis>
                                        <flux:chart.cursor type="line" />
                                    </flux:chart.svg>
                                    <flux:chart.tooltip>
                                        <flux:chart.tooltip.heading field="hour" />
                                        <flux:chart.tooltip.value field="mm" label="{{ __('Padavine') }}" suffix=" mm" />
                                    </flux:chart.tooltip>
                                </flux:chart>
                            </div>
                        @endif

                        <!-- Precipitation total -->
                        <div class="text-center border-t pt-2 w-full">
                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                {{ number_format($totalPrecip, 1) }} mm
                            </div>
                            <div class="text-xs text-zinc-500">
                                {{ __('Padavine') }}
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>
</div>
