<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Document') - {{ config('app.name', 'TMS') }}</title>
    <link href="{{ asset('assets/css/theme.min.css') }}" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #1f2937;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
        }
        .print-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
        }
        .table-print th {
            background-color: #f3f4f6 !important;
            color: #111827;
            font-weight: 600;
        }
        .table-print td, .table-print th {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
        }
        .stamp-box {
            border: 1px dashed #9ca3af;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 11px;
            border-radius: 4px;
        }
        @media print {
            body {
                background: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4;
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print text-center my-3">
        <button onclick="window.print()" class="btn btn-primary btn-sm px-4 shadow">
            <i class="fas fa-print me-1"></i> Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm px-3 ms-2">
            Close Window
        </button>
    </div>

    <div class="print-container">
        @yield('content')
    </div>
</body>
</html>
