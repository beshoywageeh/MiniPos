<x-layout>
    <form action="/logout" method="POST">
        @csrf
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">
            {{ __('dashboard.welcome') }}
        </h1>

        <p class="mt-4 text-sm text-slate-500">
            {{ __('dashboard.msg') }}
        </p>

        <div class="mt-6 flex gap-4">
            <button type="submit"
                class="inline-block rounded-lg bg-indigo-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring active:bg-indigo-500">
                {{ __('dashboard.logout') }}
            </button>
        </div>



</x-layout>
