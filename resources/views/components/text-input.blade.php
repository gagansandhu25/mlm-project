@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-lg shadow-sm disabled:opacity-50 disabled:cursor-not-allowed']) }}>
