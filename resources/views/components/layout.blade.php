<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="bg-slate-100 font-sans text-slate-900 antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen">
        @auth
            <x-layout.sidebar />
        @endauth

        <div class="flex min-h-screen flex-1 flex-col">
            @auth
                <x-layout.header />
            @endauth

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>

            @auth
                <x-layout.footer />
            @endauth
        </div>
    </div>
    </body>
</html>
