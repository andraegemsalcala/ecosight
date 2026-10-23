@extends('layouts.public')

@section('content')
<section class="mx-auto flex max-w-7xl flex-col items-center px-4 py-24 text-center sm:px-6 lg:px-8">
    <img src="{{ asset('images/logo-mark.png') }}" alt="EcoSight logo" width="96" height="96" class="h-24 w-auto opacity-90">
    <h1 class="mt-6 text-6xl font-black text-brand-dark">404</h1>
    <p class="mt-3 text-lg font-bold text-slate-800">Page not found</p>
    <p class="mt-2 max-w-md text-sm leading-6 text-slate-600">The page you were looking for may have been moved, renamed, or does not exist.</p>
    <a href="{{ route('home') }}" class="mt-8 rounded-xl bg-brand-dark px-6 py-3 font-bold text-white shadow-lg hover:bg-emerald-900">Back to home</a>
</section>
@endsection