@php use Carbon\Carbon; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - {{ $company->name }}</title>
    @vite(['resources/css/app.css', 'resources/css/payslip-print.css', 'resources/js/paged.polyfill.js'])
</head>
<body class="bg-white p-4">
<div id="loadingOverlay" class="fixed inset-0 bg-white flex items-center justify-center z-50">
    <div class="text-center">
        <div class="mb-4">
            <svg class="w-10 h-10 text-blue-500 animate-spin mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <p class="text-sm text-gray-500">Preparing for print...</p>
    </div>
</div>

<div id="content">
    @foreach ($harvesters as $harvester)
        <section class="payslip-section bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-4">
            <!-- Header: Harvester left, Company right -->
            <div class="border-b-2 border-green-400 pb-4 flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('Harvester #') }} {{ $harvester['number'] }}
                        @if ($harvester['prefix'])
                            <span class="font-normal italic text-gray-500">{{ $harvester['prefix'] }}</span>
                        @endif
                        {{ $harvester['name'] }}
                    </h2>
                </div>
                <div class="text-right">
                    <p class="text-lg text-gray-500">{{ $company->name }}</p>
                </div>
            </div>

            <!-- Period -->
            <p class="text-sm text-gray-600 mb-4">
                {{ __('Period') }}: {{ Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon::parse($dateTo)->format('d.m.Y') }}
            </p>

            <!-- Summary cards -->
            <div class="grid gap-3 grid-cols-4 mb-6">
                <div class="border border-gray-200 rounded p-3">
                    <p class="text-xs font-semibold text-gray-700">{{ __('Total buckets') }}</p>
                    <p class="text-lg font-bold mt-1">{{ $harvester['totals']['buckets'] }}</p>
                </div>
                <div class="border border-gray-200 rounded p-3">
                    <p class="text-xs font-semibold text-gray-700">{{ __('Total weight') }}</p>
                    <p class="text-lg font-bold mt-1">{{ number_format($harvester['totals']['weight'], 2, ',', '.') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('kg') }}</p>
                </div>
                <div class="border border-gray-200 rounded p-3">
                    <p class="text-xs font-semibold text-gray-700">{{ __('Price per kg') }}</p>
                    <p class="text-lg font-bold mt-1">
                        @if ($harvester['totals']['price_per_kg'])
                            {{ number_format($harvester['totals']['price_per_kg'], 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="border border-gray-200 rounded p-3">
                    <p class="text-xs font-semibold text-gray-700">{{ __('Total earnings') }}</p>
                    <p class="text-lg font-bold mt-1">{{ number_format($harvester['totals']['earnings'], 0, ',', '.') }}</p>
                </div>
            </div>

            @if (count($harvester['records']) > 0)
                @php
                    $recordCount = count($harvester['records']);
                    if ($recordCount <= 25) {
                        $columnCount = 1;
                    } elseif ($recordCount <= 50) {
                        $columnCount = 2;
                    } else {
                        $columnCount = 3;
                    }
                    $chunkSize = (int) ceil($recordCount / $columnCount);
                    $chunkedRecords = array_chunk($harvester['records'], $chunkSize);
                @endphp

                <!-- Multi-column detail table -->
                <div class="mb-12 grid gap-4 @if ($columnCount === 1) grid-cols-1 @elseif ($columnCount === 2) grid-cols-2 @else grid-cols-3 @endif">
                    @foreach ($chunkedRecords as $chunk)
                        <table class="w-full text-sm border-collapse">
                            <thead>
                            <tr class="border-b border-gray-300">
                                <th class="text-left py-2 px-2 text-xs font-semibold text-gray-700">{{ __('Date') }}</th>
                                <th class="text-right py-2 px-2 text-xs font-semibold text-gray-700">{{ __('Weight (kg)') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($chunk as $record)
                                <tr class="border-b border-gray-100">
                                    <td class="py-1 px-2 text-xs">{{ explode(' ', $record['datetime'])[0] }}</td>
                                    <td class="py-1 px-2 text-xs text-right">{{ number_format($record['weight'], 3, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
            @else
                <div class="text-center text-gray-500 py-8">
                    <p class="text-sm">{{ __('No records found for the selected period.') }}</p>
                </div>
            @endif
        </section>
    @endforeach
</div>

<script>
    window.PagedConfig = {
        auto: true,
        allowHyphenation: false,
    };

    document.addEventListener('renderedPagedJs', function () {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    });
</script>
</body>
</html>
