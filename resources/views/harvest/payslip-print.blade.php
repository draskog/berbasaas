<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Payslips') }} - {{ $company->name }}</title>
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
        <table class="payslip-header">
            <tr>
                <td colspan="2">
                    {{ __('Harvester') }} #{{ $harvester['number'] }} {{ $harvester['name'] }}
                    @if ($harvester['prefix'])
                        ({{ $harvester['prefix'] }})
                    @endif
                </td>
                <td>{{ $company->name }}</td>
            </tr>
            <tr>
                <td colspan="3">{{ __('Period') }}: {{ Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}</td>
            </tr>
        </table>
    @endforeach
</div>

<script>
    document.addEventListener('DOMContentLoaded', async function() {
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

                // Get all stylesheets
                const stylesheets = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(link => link.href);

                // Render the pages to the container
                const result = await previewer.preview(contentElement.innerHTML, stylesheets, renderContainer);
                console.log('Preview complete, pages rendered:', result.total);
                console.log('Stylesheets passed:', stylesheets);

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
