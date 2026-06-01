<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - {{ $company->name }}</title>
    @vite(['resources/css/payslip-print.css', 'resources/js/paged.polyfill.js'])
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay">
        Preparing for print...
    </div>

    <div id="content">
        @foreach ($harvesters as $harvester)
            <section class="payslip-section break-after-page">
                <div class="payslip-header">
                    <div class="header-left">
                        <span class="harvester-number">#{{ $harvester['number'] }}</span>
                        @if ($harvester['prefix'])
                            <span class="harvester-prefix">{{ $harvester['prefix'] }}</span>
                        @endif
                        <span class="harvester-name">{{ $harvester['name'] }}</span>
                    </div>
                    <div class="header-right">
                        {{ $company->name }}
                    </div>
                </div>

                <div class="payslip-period">
                    {{ __('Period') }}: {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
                </div>

                <!-- Summary block -->
                <div class="payslip-summary">
                    <div>
                        <div class="payslip-summary-label">{{ __('Total weight harvested') }}</div>
                        <div class="payslip-summary-value">{{ number_format($harvester['totals']['weight'], 2, '.', '') }} kg</div>
                    </div>
                    <div>
                        <div class="payslip-summary-label">{{ __('Price per kg') }}</div>
                        <div class="payslip-summary-value">
                            @if ($harvester['totals']['price_per_kg'])
                                {{ number_format($harvester['totals']['price_per_kg'], 0, '.', '') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="payslip-summary-label">{{ __('Total earnings') }}</div>
                        <div class="payslip-summary-value earnings">{{ number_format($harvester['totals']['earnings'], 0, '.', '') }}</div>
                    </div>
                </div>

                @if (count($harvester['records']) > 0)
                    @php
                        $recordCount = count($harvester['records']);
                        // Dynamic column count: 1 col for ≤20, 2 cols for 21-40, 3 cols for 40+
                        $columnCount = $recordCount <= 20 ? 1 : ($recordCount <= 40 ? 2 : 3);
                    @endphp
                    <div class="table-wrapper" data-columns="{{ $columnCount }}">
                        <table class="payslip-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th class="text-right">{{ __('Weight (kg)') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($harvester['records'] as $record)
                                    <tr>
                                        <td>{{ explode(' ', $record['datetime'])[0] }}</td>
                                        <td class="text-right">{{ number_format($record['weight'], 3, '.', '') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals summary with spacing -->
                    <div class="payslip-totals-summary">
                        <div class="payslip-totals-item">
                            <div class="payslip-totals-label">{{ __('Total buckets') }}:</div>
                            <div class="payslip-totals-value">{{ $harvester['totals']['buckets'] }}</div>
                        </div>
                        <div class="payslip-totals-item">
                            <div class="payslip-totals-label">{{ __('Total weight') }}:</div>
                            <div class="payslip-totals-value">{{ number_format($harvester['totals']['weight'], 2, '.', '') }}</div>
                        </div>
                    </div>
                @else
                    <div class="no-data">
                        {{ __('No records found for the selected period.') }}
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
