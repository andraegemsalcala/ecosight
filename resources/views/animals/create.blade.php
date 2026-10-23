@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Animal Library</p>
    <h1 class="mt-2 text-4xl font-black">Add an animal</h1>
    <p class="mt-3 text-slate-600">Create an animal record and its conservation actions together.</p>
    <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @include('animals.form', ['animal' => $animal, 'actions' => $actions, 'formAction' => route('animals.store'), 'formMethod' => 'POST'])
    </div>
</div>
@endsection
