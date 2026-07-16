@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-3 w-full px-4 py-2.5 rounded-lg text-start text-base font-medium bg-amber-50 text-amber-700 focus:outline-none transition duration-150 ease-in-out'
            : 'flex items-center gap-3 w-full px-4 py-2.5 rounded-lg text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
