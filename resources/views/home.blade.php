@extends('layouts.public')

@section('content')
<section class="overflow-hidden bg-gradient-to-br from-emerald-950 via-emerald-800 to-lime-700 text-white">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-28">
        <div>
            <span class="inline-flex rounded-full border border-emerald-300/30 bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">AI + environmental education</span>
            <h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl lg:text-6xl">See an animal. Learn its story. Take action.</h1>
            <p class="mt-6 max-w-2xl text-base leading-7 text-emerald-50/80 sm:text-lg">EcoSight uses a browser-based AI model to recognize supported land animals through a camera or photo, then connects the result to habitat information, climate impacts, and practical conservation actions.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                @auth
                    <a href="{{ route('scanner') }}" class="rounded-xl bg-white px-6 py-3 text-center font-bold text-emerald-900 shadow-lg hover:bg-emerald-50">Open scanner</a>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl bg-white px-6 py-3 text-center font-bold text-emerald-900 shadow-lg hover:bg-emerald-50">Create an account</a>
                    <a href="{{ route('animals.index') }}" class="rounded-xl border border-white/30 px-6 py-3 text-center font-bold text-white hover:bg-white/10">Explore the library</a>
                @endauth
            </div>
        </div>
        <div class="relative">
            <div class="rounded-3xl border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur">
                <div class="mb-5 flex justify-center">
                    <img src="{{ asset('images/logo-stacked-light.png') }}" alt="EcoSight logo" width="477" height="459" class="h-28 w-auto">
                </div>
                <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-lime-200/30 via-emerald-300/10 to-black/10 p-5">
                    <div class="flex h-full items-center justify-center rounded-xl border-2 border-dashed border-emerald-200/40">
                        <div class="text-center">
                            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white/15 text-4xl">◉</div>
                            <p class="mt-4 font-bold">Camera → AI → Recognition → Action</p>
                            <p class="mt-2 text-sm text-emerald-100/70">Designed for mobile-first scanning.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="max-w-2xl">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">How it works</p>
        <h2 class="mt-2 text-3xl font-black text-slate-900">Three simple steps</h2>
    </div>
    <div class="mt-10 grid gap-6 md:grid-cols-3">
        @foreach ([['01', 'Scan', 'Use your camera or upload a photo. EcoSight asks for camera permission only when you start scanning.'], ['02', 'Recognize', 'CLIP compares the photo with animal descriptions in the browser (with a MobileNet fallback) and filters results to the supported land animals.'], ['03', 'Act', 'Read the animal information and choose practical conservation actions that can be done at home or in the community.']] as $step)
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <span class="text-sm font-black text-emerald-700">{{ $step[0] }}</span>
                <h3 class="mt-3 text-xl font-black">{{ $step[1] }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $step[2] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="bg-stone-100">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl bg-emerald-800 p-8 text-white">
                <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold">SDG 4</span>
                <h2 class="mt-4 text-2xl font-black">Quality Education</h2>
                <p class="mt-3 text-sm leading-6 text-emerald-50/80">EcoSight turns an everyday camera interaction into a short learning experience about animals, habitats, and environmental responsibility.</p>
            </div>
            <div class="rounded-3xl bg-lime-100 p-8 text-emerald-950">
                <span class="inline-flex rounded-full bg-emerald-800 px-3 py-1 text-xs font-bold text-white">SDG 15</span>
                <h2 class="mt-4 text-2xl font-black">Life on Land</h2>
                <p class="mt-3 text-sm leading-6 text-emerald-950/75">The system connects recognition with habitat awareness, climate impacts, and simple steps that encourage land and wildlife protection.</p>
            </div>
        </div>
    </div>
</section>
@endsection
