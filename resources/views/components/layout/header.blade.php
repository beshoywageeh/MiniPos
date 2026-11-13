<header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6">
    <div class="flex items-center gap-3">
        <button
            @click="sidebarOpen = true"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 lg:hidden"
            aria-label="{{ __('dashboard.open_menu') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        <h1 class="text-base font-semibold text-slate-900">{{ __('dashboard.title') }}</h1>
    </div>

    <div class="flex items-center gap-3">
        @auth
            <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()->name }}</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600">
                {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
            </span>

            <form action="/logout" method="POST">
                @csrf
                <button
                    type="submit"
                    class="inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring active:bg-indigo-500"
                >
                    {{ __('dashboard.logout') }}
                </button>
            </form>
        @endauth
    </div>
</header>
