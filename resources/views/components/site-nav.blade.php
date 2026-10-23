<nav class="border-b border-emerald-900/10 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center">
            <img src="{{ asset('images/logo-horizontal.png') }}" alt="EcoSight logo" width="720" height="248" class="h-10 w-auto">
        </a>
        <div class="hidden items-center gap-5 text-sm font-medium md:flex">
            <a href="{{ route('home') }}" class="hover:text-emerald-700">Home</a>
            <a href="{{ route('about') }}" class="hover:text-emerald-700">About</a>
            <a href="{{ route('animals.index') }}" class="hover:text-emerald-700">Animal Library</a>
            <a href="{{ route('contact.create') }}" class="hover:text-emerald-700">Contact</a>
            @auth
                <a href="{{ route('dashboard') }}" class="hover:text-emerald-700">Dashboard</a>
                <a href="{{ route('scanner') }}" class="rounded-lg bg-brand-dark px-4 py-2 text-white hover:bg-emerald-900">Scan</a>
                <a href="{{ route('scans.index') }}" class="hover:text-emerald-700">My Scans</a>
                <a href="{{ route('animals.images.review') }}" class="hover:text-emerald-700">Image review</a>
                <a href="{{ route('performance') }}" class="hover:text-emerald-700">Performance</a>
                <a href="{{ route('profile.edit') }}" class="hover:text-emerald-700">Profile</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="hover:text-emerald-700">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hover:text-emerald-700">Login</a>
                <a href="{{ route('register') }}" class="rounded-lg border border-emerald-700 px-4 py-2 text-emerald-800 hover:bg-emerald-50">Sign up</a>
            @endauth
        </div>
        <div class="md:hidden flex items-center gap-2">
            @auth
                <a href="{{ route('scanner') }}" class="rounded-lg bg-brand-dark px-3 py-2 text-sm font-semibold text-white">Scan</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-emerald-800">Login</a>
            @endauth
            <button type="button" id="mobile-nav-toggle" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 px-3 py-2 text-emerald-800" aria-expanded="false" aria-controls="mobile-nav-menu">
                <span class="sr-only">Open menu</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 100 2h12a1 1 0 100-2H4z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
    <div id="mobile-nav-menu" class="hidden border-t border-emerald-900/10 bg-white md:hidden">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 text-sm font-medium">
            <a href="{{ route('home') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Home</a>
            <a href="{{ route('about') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">About</a>
            <a href="{{ route('animals.index') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Animal Library</a>
            <a href="{{ route('contact.create') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Contact</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Dashboard</a>
                <a href="{{ route('scans.index') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">My Scans</a>
                <a href="{{ route('animals.images.review') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Image review</a>
                <a href="{{ route('performance') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Performance</a>
                <a href="{{ route('profile.edit') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Profile</a>
                <form method="POST" action="{{ route('logout') }}" class="flex">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-2 py-2 text-left hover:bg-emerald-50 hover:text-emerald-700">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Login</a>
                <a href="{{ route('register') }}" class="rounded-lg px-2 py-2 hover:bg-emerald-50 hover:text-emerald-700">Sign up</a>
            @endauth
        </div>
    </div>
</nav>

