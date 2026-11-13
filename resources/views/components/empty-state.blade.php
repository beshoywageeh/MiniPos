@props([
    'message',
])

<p {{ $attributes->merge(['class' => 'px-5 py-8 text-center text-sm text-slate-500']) }}>
    {{ $message }}
</p>
