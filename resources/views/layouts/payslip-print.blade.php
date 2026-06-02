<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Harvesters Payslips') }} - {{ $company->name }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preload" as="font" href="/build/assets/instrument-sans-400-normal-DRC__1Mx.woff2" type="font/woff2" crossorigin="anonymous"/>
    @vite(['resources/css/payslip-interface.css', 'resources/css/payslip-print.css', 'resources/js/paged.polyfill.js'])
    <script>
        window.PagedConfig = {
            auto: false // disable auto, we'll trigger manually
        };
    </script>
</head>
<body>
<div id="content">
    @foreach ($harvesters as $harvester)
        <section class="px-2 payslip-page">
            <table class="border-collapse m-0 w-full">
                <tr>
                    <td colspan="2" class="text-lg">
                        {{ __('Harvester #') }} {{ $harvester['number'] }} <span class="ml-2 font-bold">{{ $harvester['name'] }}</span>
                    </td>
                    <td class="text-right">{{ $company->name }}</td>
                </tr>
                <tr class="mt-2">
                    <td colspan="3" class="text-sm">{{ __('Period') }}: <strong>{{ Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}</strong>
                    </td>
                </tr>
            </table>
            <table class="mt-8 border-collapse m-0 w-full">
                <tr>
                    <td>{{ __('Total buckets') }}</td>
                    <td>{{ __('Total weight') }}</td>
                    <td>{{ __('Price per kg') }}</td>
                    <td class="text-right font-semibold">{{ __('Total earnings') }}</td>
                </tr>
                <tr class="mt-2">
                    <td>{{ $harvester['totals']['buckets'] }} kom</td>
                    <td>{{ number_format($harvester['totals']['weight'], 0, '', '') }} kg</td>
                    <td>
                        @if ($harvester['totals']['price_per_kg'])
                            {{ number_format($harvester['totals']['price_per_kg'], 0, '', '') }} <span class="text-sm">RSD</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right font-bold">{{ number_format($harvester['totals']['earnings'], 0, '', '') }} <span class="text-sm">RSD</span></td>
                </tr>
            </table>
            @if (count($harvester['records']) > 0)
                @php
                    $records = $harvester['records'];
                    $recordsPerPage = 40*4;
                    $pageChunks = array_chunk($records, $recordsPerPage);
                @endphp
                @foreach ($pageChunks as $pageIndex => $pageRecords)
                    @if ($pageIndex > 0)
                        <div>
                            <table class="border-collapse m-0 w-full">
                                <tr>
                                    <td colspan="2" class="text-lg">
                                        {{ __('Harvester #') }} {{ $harvester['number'] }} <span class="ml-2 font-bold">{{ $harvester['name'] }}</span>
                                    </td>
                                    <td class="text-right">{{ $company->name }}</td>
                                </tr>
                                <tr class="mt-2">
                                    <td colspan="3" class="text-sm">{{ __('Period') }}:
                                        <strong>{{ Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}</strong>
                                    </td>
                                </tr>
                            </table>
                            <p class="mt-4 mb-2 text-sm">{{ __('Harvest Records') }} {{ __('for') }} {{ $harvester['name'] }} {{ __('nastavak') }}</p>
                            @else
                                <div class="mt-4">
                                    <p class="mb-2 text-sm">{{ __('Harvest Records') }} {{ __('for') }} {{ $harvester['name'] }}</p>
                                    @endif
                                    <div class="text-sm grid grid-cols-4 gap-2">
                                        @php
                                            $recordsPerColumn = 40;
                                            $columns = array_chunk($pageRecords, $recordsPerColumn);
                                        @endphp
                                        @foreach ($columns as $column)
                                            <div class="w-full text-center">
                                                <div class="grid grid-cols-2 gap-2 mb-2">
                                                    <span>{{ __('Date') }}</span>
                                                    <span>{{ __('Weight (kg)') }}</span>
                                                </div>
                                                @foreach ($column as $record)
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <span>{{ explode(' ', $record['datetime'])[0] }}</span>
                                                        <span>{{ number_format($record['weight'], 2, ',', '') }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                                @else
                                    <p class="text-center">{{ __('No records found.') }}</p>
                    @endif
        </section>
    @endforeach
</div>

<script>
    document.addEventListener('DOMContentLoaded', async function () {
        console.log('DOM loaded, waiting for Paged...');

        // Wait for Paged to be available
        let attempts = 0;
        while (!window.Paged && attempts < 50) {
            await new Promise(r => setTimeout(r, 100));
            attempts++;
        }

        if (window.Paged) {
            console.log('Paged available, creating previewer...');
            try {
                const previewer = new window.Paged.Previewer();
                const contentElement = document.querySelector('#content');

                // Create a container for the rendered pages
                const renderContainer = document.createElement('div');
                renderContainer.id = 'paged-render';
                document.body.appendChild(renderContainer);

                // Render the pages to the container
                const result = await previewer.preview(contentElement.innerHTML, [], renderContainer);
                console.log('Preview complete, pages rendered:', result.total);

                // Hide original content
                contentElement.style.display = 'none';
            } catch (err) {
                console.error('Preview error:', err);
            }
        } else {
            console.error('Paged never loaded!');
        }
    });
</script>
</body>
</html>
