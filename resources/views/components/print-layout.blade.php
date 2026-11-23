<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    @fonts
    @vite(['resources/css/app.css'])

    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .print-sheet {
                box-shadow: none !important;
                margin: 0 !important;
            }
        }
    </style>
</head>

<body class="bg-slate-200 font-sans">
    {{ $slot }}
</body>

</html>
