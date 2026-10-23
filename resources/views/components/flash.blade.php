@if (session('success') || session('status'))
    <div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') ?? session('status') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-bold">Please check the following:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
