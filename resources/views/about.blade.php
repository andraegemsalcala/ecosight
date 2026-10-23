@extends('layouts.public')

@section('content')
<section class="bg-emerald-950 text-white">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-300">About EcoSight</p>
        <h1 class="mt-3 max-w-4xl text-4xl font-black sm:text-5xl">From animal recognition to environmental awareness.</h1>
        <p class="mt-5 max-w-3xl text-base leading-7 text-emerald-50/75">EcoSight is a camera-based interactive website designed to help users identify supported land animals and quickly understand why their habitats matter.</p>
        <img src="{{ asset('images/logo-stacked-light.png') }}" alt="EcoSight logo" width="477" height="459" class="mt-8 h-24 w-auto">
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
            <h2 class="text-2xl font-black">The problem</h2>
            <p class="mt-4 leading-7 text-slate-600">Urbanization and fast-paced digital content can make it harder for people to notice and understand local ecosystems. Image recognition tools can identify objects, but recognition alone does not automatically explain habitat concerns or what a person can do next.</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-7 shadow-sm">
            <h2 class="text-2xl font-black text-emerald-950">The solution</h2>
            <p class="mt-4 leading-7 text-emerald-950/75">EcoSight combines browser-based image recognition with educational animal profiles and actionable conservation suggestions. The system also records scan performance data such as confidence and response time for evaluation.</p>
        </article>
    </div>

    <div class="mt-10 grid gap-8 lg:grid-cols-3">
        <article class="rounded-2xl bg-slate-900 p-7 text-white">
            <p class="text-sm font-bold text-emerald-300">Objective 01</p>
            <h3 class="mt-2 text-xl font-black">Identify land animals</h3>
            <p class="mt-3 text-sm leading-6 text-slate-300">Use AI image recognition in the browser to identify the supported land animals with CLIP zero-shot scoring, plus a MobileNet group fallback.</p>
        </article>
        <article class="rounded-2xl bg-slate-900 p-7 text-white">
            <p class="text-sm font-bold text-emerald-300">Objective 02</p>
            <h3 class="mt-2 text-xl font-black">Display learning content</h3>
            <p class="mt-3 text-sm leading-6 text-slate-300">Show habitat, climate impact, and conservation status information after recognition.</p>
        </article>
        <article class="rounded-2xl bg-slate-900 p-7 text-white">
            <p class="text-sm font-bold text-emerald-300">Objective 03</p>
            <h3 class="mt-2 text-xl font-black">Promote action</h3>
            <p class="mt-3 text-sm leading-6 text-slate-300">Provide practical conservation actions with difficulty and category labels.</p>
        </article>
    </div>

    <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-8">
        <h2 class="text-2xl font-black">Core features</h2>
        <div class="mt-6 grid gap-5 sm:grid-cols-3">
            <div><p class="font-bold">Real-time camera scanning</p><p class="mt-1 text-sm text-slate-600">Live video with detection overlays and clear status states.</p></div>
            <div><p class="font-bold">AI-based identification</p><p class="mt-1 text-sm text-slate-600">CLIP and the MobileNet fallback run client-side with minimum confidence thresholds and a confirmation step for close calls.</p></div>
            <div><p class="font-bold">Educational content</p><p class="mt-1 text-sm text-slate-600">Recognition results lead directly to useful environmental information.</p></div>
        </div>
    </div>
</section>
@endsection
