<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'EcoSight' }} | EcoSight</title>
    <x-head />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-800 antialiased">
    <div class="min-h-screen bg-gradient-to-br from-emerald-950 via-emerald-800 to-stone-100">
        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="w-full max-w-md">
                <div class="rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                    <a href="{{ route('home') }}" class="mb-6 flex justify-center" aria-label="EcoSight home">
                        <img src="{{ asset('images/logo-stacked.png') }}" alt="EcoSight logo" width="477" height="459" class="h-16 w-auto">
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
