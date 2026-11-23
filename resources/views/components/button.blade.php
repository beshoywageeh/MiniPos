@props([
    'variant' => 'primary',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-700 active:bg-indigo-500',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 active:bg-rose-500',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    ];

    $tag = $href !== null ? 'a' : 'button';
@endphp

<{{ $tag }} {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-500/30 '.$variants[$variant],
]) }} @if ($href !== null) href="{{ $href }}" @elseif (! $attributes->has('type')) type="button" @endif>
    {{ $slot }}
</{{ $tag }}>
