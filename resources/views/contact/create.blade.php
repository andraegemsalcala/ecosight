@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid gap-10 lg:grid-cols-2">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Contact</p>
            <h1 class="mt-2 text-4xl font-black">Send a message</h1>
            <p class="mt-4 leading-7 text-slate-600">Have feedback about EcoSight, the animal library, or the scanner experience? Send a message using the form.</p>
            <div class="mt-8 rounded-2xl bg-emerald-950 p-6 text-white">
                <p class="font-bold">Why this form exists</p>
                <p class="mt-2 text-sm leading-6 text-emerald-100/75">Messages are stored in the contact_messages table for the practical exam database requirement.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('contact.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <div>
                <label class="form-label" for="name">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="form-input">
                @error('name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="mt-5">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="form-input">
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="mt-5">
                <label class="form-label" for="message">Message</label>
                <textarea id="message" name="message" rows="7" required class="form-input">{{ old('message') }}</textarea>
                @error('message')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button class="mt-6 w-full rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white hover:bg-emerald-800">Send message</button>
        </form>
    </div>
</div>
@endsection
