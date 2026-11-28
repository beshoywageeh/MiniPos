@props(['align' => 'left'])

@php
    $alignments = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ];
@endphp

<th {{ $attributes->merge(['scope' => 'col', 'class' => 'px-4 py-3 '.$alignments[$align]]) }}>
    {{ $slot }}
</th>
