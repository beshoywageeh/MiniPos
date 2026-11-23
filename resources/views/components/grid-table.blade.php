@props([
    'columns',
    'data',
    'dateColumnIndex' => null,
    'show' => 'true',
])

@php
    $config = array_filter([
        'columns' => $columns,
        'data' => $data,
        'dateColumnIndex' => $dateColumnIndex,
    ], fn ($value) => $value !== null);
@endphp

<div {{ $attributes->merge(['data-gridjs' => json_encode($config)]) }} x-effect="{{ $show }} && $dispatch('gridjs-init')">
</div>
