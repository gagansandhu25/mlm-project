@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => "bg-white rounded-xl border border-gray-200 shadow-sm {$padding}"]) }}>
    {{ $slot }}
</div>
