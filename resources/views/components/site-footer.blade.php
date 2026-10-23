<footer class="mt-16 border-t border-emerald-900/10 bg-emerald-950 text-emerald-50">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8">
        <div>
            <img src="{{ asset('images/logo-horizontal-light.png') }}" alt="EcoSight logo" width="891" height="307" class="h-10 w-auto">
            <p class="mt-4 text-sm leading-6 text-emerald-100/75">An AI-powered lens for identifying land animals and turning recognition into simple conservation action.</p>
        </div>
        <div>
            <p class="font-bold">Built around</p>
            <p class="mt-2 text-sm text-emerald-100/75">SDG 4: Quality Education</p>
            <p class="text-sm text-emerald-100/75">SDG 15: Life on Land</p>
        </div>
        <div>
            <p class="font-bold">Project</p>
            <p class="mt-2 text-sm text-emerald-100/75">José Rizal University · BSIT · ITC C301-303I</p>
            <a href="{{ route('credits') }}" class="mt-2 block text-sm text-emerald-200 hover:text-white">Photo credits</a>
            <a href="{{ route('reflection') }}" class="mt-2 inline-block text-sm text-emerald-200 hover:text-white">Narrative reaction</a>
        </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-emerald-100/60">EcoSight practical exam project</div>
</footer>
