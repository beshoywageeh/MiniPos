@props(['align' => 'left'])

@php
    $alignments = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ];
@endphp

<td {{ $attributes->merge(['class' => 'px-4 py-3 '.$alignments[$align]]) }}>
    {{ $slot }}
</td>
