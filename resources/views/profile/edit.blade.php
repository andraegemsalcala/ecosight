<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900">Extended species list</h2>
                    <p class="mt-1 text-sm text-gray-600">The extended list lets the scanner check a much larger species vocabulary. It is downloaded only when you ask for it and stored in this browser.</p>
                    <p id="extended-vocab-status" class="mt-3 text-sm font-medium text-gray-700">Checking this browser...</p>
                    <button id="extended-vocab-remove" type="button" class="mt-3 hidden rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Remove download</button>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        import { getCachedVocabulary, clearCachedVocabulary } from '/js/open-vocab-store.js';

        const status = document.getElementById('extended-vocab-status');
        const remove = document.getElementById('extended-vocab-remove');

        async function refresh() {
            const cached = await getCachedVocabulary('tier2');
            if (! cached) {
                status.textContent = 'Not downloaded in this browser.';
                remove.classList.add('hidden');
                return;
            }

            const megabytes = (cached.buffer.byteLength / 1024 / 1024).toFixed(1);
            status.textContent = `Downloaded in this browser (${megabytes} MB).`;
            remove.classList.remove('hidden');
        }

        remove.addEventListener('click', async () => {
            remove.disabled = true;
            await clearCachedVocabulary('tier2');
            remove.disabled = false;
            await refresh();
        });

        refresh();
    </script>
</x-app-layout>
