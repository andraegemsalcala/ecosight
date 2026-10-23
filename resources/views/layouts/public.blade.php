<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'EcoSight' }} | EcoSight</title>
    <x-head />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-800 antialiased">
    @include('components.site-nav')
    <main>
        @include('components.flash')
        @yield('content')
    </main>
    @stack('scripts')
    @include('components.site-footer')
</body>
</html>
