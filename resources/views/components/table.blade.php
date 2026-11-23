<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="w-full text-left text-sm">
        @isset($head)
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                {{ $head }}
            </thead>
        @endisset

        <tbody class="divide-y divide-slate-100">
            {{ $slot }}
        </tbody>
    </table>
</div>
