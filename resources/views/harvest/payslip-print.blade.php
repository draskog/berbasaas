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
    <div class="payslip">
        <div class="header">
            <div class="title">Harvester #{{ $harvester['number'] }} {{ $harvester['name'] }}
                @if ($harvester['prefix'])
                    ({{ $harvester['prefix'] }})
                @endif
            </div>
            <div class="company">{{ $company->name }}</div>
        </div>

        <p class="info">Company: {{ $company->name }}</p>
        <p class="info">Period: {{ Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon::parse($dateTo)->format('d.m.Y') }}</p>

        <div class="summary-section">
            <div class="summary-card">
                <span class="label">Total buckets</span>
                <span class="value">{{ $harvester['totals']['buckets'] }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Total weight</span>
                <span class="value">{{ number_format($harvester['totals']['weight'], 2, '.', '') }}</span>
                <span class="unit">kg</span>
            </div>
            <div class="summary-card">
                <span class="label">Price per kg</span>
                <span class="value">{{ $harvester['totals']['price_per_kg'] ?? '—' }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Total earnings</span>
                <span class="value">{{ number_format($harvester['totals']['earnings'], 2, '.', '') }}</span>
            </div>
        </div>

        @if (count($harvester['records']) > 0)
            <div class="records-section">
                <p class="section-title">Records</p>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($harvester['records'] as $record)
                            <tr>
                                <td>{{ explode(' ', $record['datetime'])[0] }}</td>
                                <td>{{ number_format($record['weight'], 3, '.', '') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="no-data">No records found.</p>
        @endif
    </div>
@endforeach

<script>
    window.PagedConfig = {
        auto: true,
        allowHyphenation: false,
    };
</script>

</body>
</html>
