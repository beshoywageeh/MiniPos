@props([
    'name',
    'label',
    'value' => '1',
    'checked' => false,
])

<label class="flex items-start gap-3">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $id ?? $name }}"
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes->merge(['class' => 'mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 accent-indigo-600 focus:ring-2 focus:ring-indigo-500/30 focus:ring-offset-0']) }}
    />

    <span class="text-sm text-slate-700">
        {{ $label }}
    </span>
</label>
