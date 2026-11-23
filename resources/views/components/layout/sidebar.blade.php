<div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-20 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false"></div>

<aside
    class="fixed inset-y-0 left-0 z-30 w-64 transform bg-slate-900 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <div class="flex h-full flex-col">
        <div class="flex h-16 items-center gap-3 border-b border-slate-800 px-6">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
                {{ str(config('app.name', 'MiniPOS'))->substr(0, 1) }}
            </span>
            <span class="text-base font-semibold text-white">{{ config('app.name', 'MiniPOS') }}</span>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ([
        ['label' => __('dashboard.nav_dashboard'), 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'navigate' => true, 'path' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5'],
        ['label' => __('dashboard.nav_users'), 'href' => route('users.index'), 'active' => request()->routeIs('users.index'), 'navigate' => true, 'path' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.76M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
        ['label' => __('dashboard.nav_categories'), 'href' => route('categories.index'), 'active' => request()->routeIs('categories.*'), 'navigate' => true, 'path' => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z'],
        ['label' => __('dashboard.nav_brands'), 'href' => route('brands.index'), 'active' => request()->routeIs('brands.*'), 'navigate' => true, 'path' => 'M17.593 3.75H6.841a.375.375 0 0 1-.313-.68 70.5 70.5 0 0 1 9.406 0 .375.375 0 0 1-.313.68ZM6.841 5.25h10.752a.75.75 0 0 1 .746.68 70.825 70.825 0 0 1-.847 6.036 71.502 71.502 0 0 1-4.215 16.244.75.75 0 0 1-1.114 0 71.502 71.502 0 0 1-4.215-16.244 70.825 70.825 0 0 1-.847-6.036.75.75 0 0 1 .746-.68Z'],
        ['label' => __('dashboard.nav_units'), 'href' => route('units.index'), 'active' => request()->routeIs('units.*'), 'navigate' => true, 'path' => 'M17.593 3.75H6.841a.375.375 0 0 1-.313-.68 70.5 70.5 0 0 1 9.406 0 .375.375 0 0 1-.313.68ZM6.841 5.25h10.752a.75.75 0 0 1 .746.68 70.825 70.825 0 0 1-.847 6.036 71.502 71.502 0 0 1-4.215 16.244.75.75 0 0 1-1.114 0 71.502 71.502 0 0 1-4.215-16.244 70.825 70.825 0 0 1-.847-6.036.75.75 0 0 1 .746-.68Z'],
        ['label' => __('dashboard.nav_parties'), 'href' => route('parties.index'), 'active' => request()->routeIs('parties.*'), 'navigate' => true, 'path' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
        ['label' => __('dashboard.nav_products'), 'href' => route('products.index'), 'active' => request()->routeIs('products.*'), 'navigate' => true, 'path' => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
        ['label' => __('dashboard.nav_sales'), 'href' => route('sales.create'), 'active' => request()->routeIs('sales.*'), 'navigate' => true, 'path' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z'],
        ['label' => __('dashboard.nav_purchases'), 'href' => '#', 'active' => false, 'navigate' => false, 'path' => 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m8.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h4.5M20.25 18.75a1.5 1.5 0 0 0 1.5-1.5V13.5L18 9m2.25 9.75H21a.75.75 0 0 0 .75-.75V14.25a2.25 2.25 0 0 0-2.25-2.25h-1.5M3.75 14.25h4.5m-4.5 0V8.25A1.125 1.125 0 0 1 4.875 7.5H6.75V6m0 0h6V4.5h-6V6m6 0v9.75'],

        ['label' => __('dashboard.nav_expenses'), 'href' => '#', 'active' => false, 'navigate' => false, 'path' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.443 12.9 12.998 12.75 12 12.75c-.532 0-1.057-.112-1.55-.335a3.378 3.378 0 0 1-.936-.635 3.106 3.106 0 0 1-.664-.936 3.323 3.323 0 0 1-.35-1.52V9.75c0-.531.116-1.057.35-1.55a3.106 3.106 0 0 1 .664-.936c.27-.27.587-.484.936-.635.493-.223 1.018-.335 1.55-.335.998 0 2.057.15 2.878.879l.879.659M12 6v12m8.25-6a8.25 8.25 0 1 1-16.5 0 8.25 8.25 0 0 1 16.5 0Z'],
        ['label' => __('dashboard.nav_reports'), 'href' => route('reports.index'), 'active' => request()->routeIs('reports.*'), 'navigate' => true, 'path' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
        ['label' => __('dashboard.nav_settings'), 'href' => route('settings.index'), 'active' => request()->routeIs('settings.*'), 'navigate' => true, 'path' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.297 2.247a1.125 1.125 0 0 1-1.37.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
    ] as $item)
                <a href="{{ $item['href'] }}" @if ($item['navigate']) wire:navigate @endif
                    @class([
                        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-indigo-600 text-white' => $item['active'],
                        'text-slate-300 hover:bg-slate-800 hover:text-white' => !$item['active'],
                    ])>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-5 w-5 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['path'] }}" />
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</aside>
