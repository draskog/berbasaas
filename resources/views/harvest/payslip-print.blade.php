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

                <div class="payslip-period payslip-header-spacing">
                    {{ __('Period') }}: {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
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
                                    <th>{{ __('Time') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th class="text-right">{{ __('Weight (kg)') }}</th>
                                    <th class="text-right">{{ __('Price/kg') }}</th>
                                    <th class="text-right">{{ __('Earnings') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($harvester['records'] as $record)
                                    <tr>
                                        <td>{{ explode(' ', $record['datetime'])[0] }}</td>
                                        <td>{{ explode(' ', $record['datetime'])[1] }}</td>
                                        <td>{{ $record['product'] }}</td>
                                        <td class="text-right">{{ number_format($record['weight'], 3, '.', '') }}</td>
                                        <td class="text-right">
                                            @if ($record['price_per_kg'])
                                                {{ number_format($record['price_per_kg'], 4, '.', '') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($record['earnings'], 2, '.', '') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td colspan="2"></td>
                                    <td class="totals-label">{{ __('Totals') }}:</td>
                                    <td class="text-right">{{ $harvester['totals']['buckets'] }}</td>
                                    <td class="text-right">{{ number_format($harvester['totals']['weight'], 3, '.', '') }}</td>
                                    <td class="text-right">{{ number_format($harvester['totals']['earnings'], 2, '.', '') }}</td>
                                </tr>
                            </tfoot>
                        </table>
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
