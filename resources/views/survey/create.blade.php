@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Usability survey</p>
        <h1 class="mt-2 text-4xl font-black">How was the EcoSight experience?</h1>
        <p class="mt-3 text-slate-600">Four short questions about the scanner. Your answers help shape future improvements.</p>
    </div>

    <form method="POST" action="{{ route('survey.store') }}" class="mt-10 space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @csrf

        @foreach ([
            ['ease_rating', 'Ease of use', 'How easy was it to scan an animal?', 'Very difficult', 'Very easy'],
            ['speed_rating', 'Speed', 'How fast did recognition results appear?', 'Very slow', 'Very fast'],
            ['clarity_rating', 'Clarity', 'How clear were the animal details and conservation action suggestions?', 'Very unclear', 'Very clear'],
            ['usefulness_rating', 'Usefulness', 'How useful did you find the educational information?', 'Not useful', 'Very useful'],
        ] as [$name, $title, $question, $lowLabel, $highLabel])
            <fieldset>
                <legend class="font-black">{{ $title }}</legend>
                <p class="mt-1 text-sm text-slate-600">{{ $question }}</p>
                <div class="mt-4 grid grid-cols-5 gap-2">
                    @for ($stars = 1; $stars <= 5; $stars++)
                        <label for="{{ $name }}_{{ $stars }}" class="cursor-pointer rounded-xl border border-slate-200 py-3 text-center text-sm font-bold text-slate-600 transition hover:border-emerald-600 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-800">
                            {{ $stars }}
                            <input id="{{ $name }}_{{ $stars }}" type="radio" name="{{ $name }}" value="{{ $stars }}" class="sr-only" @checked(old($name) == $stars)>
                        </label>
                    @endfor
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ $lowLabel }}</span><span>{{ $highLabel }}</span>
                </div>
                @error($name)
                    <p class="form-error mt-2">{{ $message }}</p>
                @enderror
            </fieldset>
        @endforeach

        <div>
            <label for="comment" class="font-black">Anything else? <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea id="comment" name="comment" rows="4" class="form-input mt-3 w-full rounded-xl border-slate-300" placeholder="What worked well or what should improve?">{{ old('comment') }}</textarea>
            @error('comment')
                <p class="form-error mt-2">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-xl bg-brand-dark px-6 py-3 font-black text-white shadow-lg hover:bg-emerald-900">Submit survey</button>
    </form>
</div>
@endsection