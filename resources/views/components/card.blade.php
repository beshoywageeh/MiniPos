@props([
    'title' => null,
])

@php
    $hasHeader = $title !== null && $title !== ''
        || (isset($actions) && ! $actions->isEmpty());
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($hasHeader)
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
            @isset($actions)
                {{ $actions }}
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
