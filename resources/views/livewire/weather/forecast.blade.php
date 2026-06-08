<?php

use App\Models\ReligiousHoliday;
use App\Models\WeatherRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    private const WMO_EMOJIS = [
        0 => '☀️',
        1 => '🌤️',
        2 => '⛅',
        3 => '☁️',
        45 => '🌫️',
        48 => '🌫️',
        51 => '🌧️',
        53 => '🌧️',
        55 => '🌧️',
        61 => '🌧️',
        63 => '🌧️',
        65 => '🌧️',
        71 => '🌨️',
        73 => '🌨️',
        75 => '🌨️',
        77 => '🌨️',
        80 => '🌦️',
        81 => '🌦️',
        82 => '🌦️',
        85 => '🌨️',
        86 => '🌨️',
        95 => '⛈️',
        96 => '⛈️',
        99 => '⛈️',
    ];

    #[Computed]
    public function isLocationConfigured (): bool
    {
        $company = Auth::user()->company;

        return $company && $company->latitude && $company->longitude;
    }

    #[Computed]
    public function weatherDays (): Collection
    {
        return WeatherRecord::where('company_id', auth()->user()->company_id)
            ->whereBetween('date', [now()->toDateString(), now()->addDays(5)->toDateString()])
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function religiousHolidays (): array
    {
        return ReligiousHoliday::whereBetween('date', [now()->toDateString(), now()->addDays(5)->toDateString()])
            ->get()
            ->mapWithKeys(fn($holiday) => [$holiday->date->toDateString() => $holiday->description])
            ->toArray();
    }

    public function getWeatherDescription (int $code): array
    {
        $labels = [
            0 => __('Clear'),
            1 => __('Mostly clear'),
            2 => __('Partly cloudy'),
            3 => __('Cloudy'),
            45 => __('Fog'),
            48 => __('Depositing fog'),
            51 => __('Light drizzle'),
            53 => __('Moderate drizzle'),
            55 => __('Heavy drizzle'),
            61 => __('Slight rain'),
            63 => __('Moderate rain'),
            65 => __('Heavy rain'),
            71 => __('Slight snow'),
            73 => __('Moderate snow'),
            75 => __('Heavy snow'),
            77 => __('Snow grains'),
            80 => __('Slight rain showers'),
            81 => __('Moderate rain showers'),
            82 => __('Heavy rain showers'),
            85 => __('Slight snow showers'),
            86 => __('Heavy snow showers'),
            95 => __('Thunderstorm'),
            96 => __('Thunderstorm with hail'),
            99 => __('Thunderstorm with hail'),
        ];

        return [
            'emoji' => self::WMO_EMOJIS[$code] ?? '❓',
            'label' => $labels[$code] ?? __('Unknown'),
        ];
    }

    public function getDayName ($date): string
    {
        $days = ['Ned', 'Pon', 'Uto', 'Sre', 'Čet', 'Pet', 'Sub'];
        if (is_string($date)) {
            $date = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('Y-m-d H:i:s', $date);
        }

        $dayIndex = (int) ($date?->format('w') ?? 0);

        return $days[$dayIndex];
    }

    public function getTotalPrecipitation (?array $hourly): float
    {
        if (! $hourly) {
            return 0;
        }

        return array_sum($hourly);
    }

    public function buildHourlyChartData (array $hourlyPrecipitation): array
    {
        $hours = [];
        for ($i = 9; $i <= 20; $i++) {
            $hours[] = str_pad($i, 2, '0', STR_PAD_LEFT).':00';
        }

        return collect($hourlyPrecipitation)
            ->take(12)
            ->map(fn($mm, $index) => [
                'hour' => $hours[$index] ?? ($index + 9).':00',
                'mm' => round((float) $mm, 1),
            ])
            ->values()
            ->toArray();
    }
}; ?>

<div class="space-y-6 mt-6">
    <div>
        <h3 class="text-zinc-900 dark:text-white mb-4">
            {{ __('Vremenska prognoza - narednih 6 dana') }}
        </h3>

        @if (! $this->isLocationConfigured)
            <flux:card class="text-center py-8">
                <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                    {{ __('Lokacija nije konfigurisana. Postavi je u podešavanjima kompanije.') }}
                </p>
                <a href="{{ route('company.edit') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
                    {{ __('Postavi lokaciju kompanije') }}
                </a>
            </flux:card>
        @elseif ($this->weatherDays->isEmpty())
            <flux:card class="text-center py-8">
                <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                    {{ __('Meteorološki podaci još nisu dostupni') }}
                </p>
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

                    <flux:card class="relative p-4 flex flex-col">
                        <!-- Date header (left) and weather (right) in same row -->
                        <div class="flex items-start justify-between mb-4 w-full">
                            <!-- Left: Day name and date -->
                            <div>
                                @if ($isHoliday)
                                    <div class="text-sm font-semibold text-red-400 dark:text-red-400">
                                        {{ $dayName }}
                                    </div>
                                    <flux:tooltip size="sm" class="inline-flex items-center gap-1">
                                        <span class="text-lg font-bold text-red-400 dark:text-red-400">{{ $dayOfMonth }}</span>
                                        <flux:icon.bell class="size-5 cursor-help text-red-400 dark:text-red-600"/>
                                        <flux:tooltip.content class="max-w-[20rem] space-y-2">
                                            <p>{{ $holidayName }}</p>
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                @else
                                    <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $dayName }}
                                    </div>
                                    <span class="text-lg font-bold text-zinc-900 dark:text-white">{{ $dayOfMonth }}</span>
                                @endif
                            </div>

                            <!-- Right: Weather icon and conditions inline -->
                            <div class="flex items-start gap-2">
                                <div class="text-2xl">
                                    {{ $weatherDesc['emoji'] }}
                                </div>
                                <div class="flex flex-col gap-0.5 text-xs">
                                    <div class="text-zinc-600 dark:text-zinc-400 max-w-[80px] line-clamp-2">
                                        {{ __($weatherDesc['label']) }}
                                    </div>
                                    <div class="flex items-center gap-1 font-semibold">
                                        <span class="text-zinc-900 dark:text-white">{{ number_format($weather->temperature_max, 0) }}°</span>
                                        <span class="text-zinc-600 dark:text-zinc-400">{{ number_format($weather->temperature_min, 0) }}°</span>
                                        <span class="text-zinc-500">💨 {{ number_format($weather->wind_speed_max, 0) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hourly precipitation chart -->
                        @if ($weather->hourly_precipitation)
                            @php $chartData = $this->buildHourlyChartData($weather->hourly_precipitation); @endphp
                            <div class="w-full mb-3 h-40">
                                <flux:chart :value="$chartData" class="w-full h-full">
                                    <flux:chart.svg>
                                        <flux:chart.area field="mm" class="text-blue-400" curve="smooth"/>
                                        <flux:chart.line field="mm" class="text-blue-500" curve="smooth" stroke-width="2"/>
                                        <flux:chart.axis axis="x" field="hour">
                                            <flux:chart.axis.tick/>
                                        </flux:chart.axis>
                                        <flux:chart.axis axis="y">
                                            <flux:chart.axis.grid/>
                                        </flux:chart.axis>
                                        <flux:chart.cursor type="line"/>
                                    </flux:chart.svg>
                                    <flux:chart.tooltip>
                                        <flux:chart.tooltip.heading field="hour"/>
                                        <flux:chart.tooltip.value field="mm" label="{{ __('Padavine') }}" suffix=" mm"/>
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
