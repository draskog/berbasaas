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
    <section>
        <h1>Harvester #{{ $harvester['number'] }} {{ $harvester['name'] }}
            @if ($harvester['prefix'])
                ({{ $harvester['prefix'] }})
            @endif
        </h1>

        <p><strong>Company:</strong> {{ $company->name }}</p>
        <p><strong>Period:</strong> {{ Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon::parse($dateTo)->format('d.m.Y') }}</p>

        <h2>Summary</h2>
        <p>
            <strong>Total buckets:</strong> {{ $harvester['totals']['buckets'] }}<br>
            <strong>Total weight:</strong> {{ number_format($harvester['totals']['weight'], 2, '.', '') }} kg<br>
            <strong>Price per kg:</strong> {{ $harvester['totals']['price_per_kg'] ?? '—' }}<br>
            <strong>Total earnings:</strong> {{ number_format($harvester['totals']['earnings'], 2, '.', '') }}
        </p>

        @if (count($harvester['records']) > 0)
            <h2>Records</h2>
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
        @else
            <p>No records found for the selected period.</p>
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
