@props([
    'name',
    'label',
    'options',
    'selected' => null,
    'help' => null,
])

@php
    $error = $errors->any() ? $errors->first($name) : null;
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}
    </label>

    <div class="mt-1">
        <select
            name="{{ $name }}"
            id="{{ $name }}"
            {{ $attributes->merge([
                'class' => 'block w-full rounded-lg border px-3 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 '.
                    ($error ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/30' : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500/30'),
            ]) }}
        >
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($error)
        <p class="mt-1 text-xs text-rose-600">{{ $error }}</p>
    @elseif ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
</div>
