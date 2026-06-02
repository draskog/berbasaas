@php use Carbon\Carbon; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - {{ $company->name }}</title>
    @vite(['resources/css/payslip-print.css', 'resources/js/paged.polyfill.js'])
</head>
<body>

@foreach ($harvesters as $harvester)
    <section class="payslip-section">
        <header class="payslip-header">
            <h1>{{ __('Harvester #') }} {{ $harvester['number'] }}
                @if ($harvester['prefix'])
                    <span class="prefix">{{ $harvester['prefix'] }}</span>
                @endif
                {{ $harvester['name'] }}
            </h1>
            <div class="company">{{ $company->name }}</div>
        </header>

        <p class="payslip-period">
            {{ __('Period') }}: {{ Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon::parse($dateTo)->format('d.m.Y') }}
        </p>

        <div class="payslip-summary">
            <div class="summary-card">
                <span class="summary-card-label">{{ __('Total buckets') }}</span>
                <span class="summary-card-value">{{ $harvester['totals']['buckets'] }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card-label">{{ __('Total weight') }}</span>
                <span class="summary-card-value">{{ number_format($harvester['totals']['weight'], 2, ',', '.') }}</span>
                <span class="summary-card-unit">{{ __('kg') }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card-label">{{ __('Price per kg') }}</span>
                <span class="summary-card-value">
                    @if ($harvester['totals']['price_per_kg'])
                        {{ number_format($harvester['totals']['price_per_kg'], 0, ',', '.') }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="summary-card">
                <span class="summary-card-label">{{ __('Total earnings') }}</span>
                <span class="summary-card-value">{{ number_format($harvester['totals']['earnings'], 0, ',', '.') }}</span>
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

            <div class="table-wrapper cols-{{ $columnCount }}">
                @foreach ($chunkedRecords as $chunk)
                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th class="text-right">{{ __('Weight (kg)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chunk as $record)
                                <tr>
                                    <td>{{ explode(' ', $record['datetime'])[0] }}</td>
                                    <td class="text-right">{{ number_format($record['weight'], 3, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        @else
            <p class="no-data">{{ __('No records found for the selected period.') }}</p>
        @endif
    </section>
@endforeach

<script>
    window.PagedConfig = {
        auto: true,
        allowHyphenation: false,
    };
</script>
</body>
</html>
