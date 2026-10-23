@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Bonus · Part 4</p>
    <h1 class="mt-2 text-4xl font-black">Narrative Reaction</h1>
    <p class="mt-3 text-slate-600">ReactJS vs. Laravel · 296 words</p>
    <article class="mt-8 rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
        <p class="leading-8 text-slate-700">ReactJS and Laravel are both popular tools for building web applications, but they focus on different parts of development. ReactJS is a JavaScript library mainly used for building interactive user interfaces, while Laravel is a PHP framework that provides structure for backend development and full-stack applications.</p>
        <p class="mt-5 leading-8 text-slate-700">From my experience, ReactJS is strong when an application needs a lot of dynamic interface updates. Components can be reused, and state management can make complex interfaces easier to organize. However, ReactJS by itself does not provide the same complete backend structure as Laravel, so developers usually need additional tools for routing, databases, authentication, and server-side logic. Laravel, on the other hand, already provides features such as routing, controllers, Eloquent ORM, migrations, validation, authentication, and Blade views. Its weakness is that very interactive frontends can require more JavaScript or another frontend library.</p>
        <p class="mt-5 leading-8 text-slate-700">ReactJS is commonly used for frontend applications and can also be used with APIs in a full-stack setup. Laravel is useful for backend systems and can also handle the frontend through Blade, making it a full-stack option. While building EcoSight, I noticed how quickly Laravel Breeze provided login, registration, and profile features. Eloquent, migrations, and seeders also made the database setup easier to organize. For the scanner, I used client-side JavaScript because the camera and TensorFlow.js model need to respond directly in the browser. ReactJS could have helped organize the scanner's changing states, such as loading, detecting, low confidence, and saving.</p>
        <p class="mt-5 leading-8 text-slate-700">Because of this experience, I do not think it is completely fair to say that one is always better. They solve different problems. For EcoSight, Laravel worked well as the main application framework, while JavaScript handled the real-time AI interaction. The better choice depends on the application's needs and which part of the system is being developed.</p>
        <p class="mt-6 text-sm font-bold text-emerald-700">Word count: 296</p>
    </article>
</div>
@endsection
