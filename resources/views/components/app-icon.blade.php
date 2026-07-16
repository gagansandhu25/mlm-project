@props(['name'])

@switch($name)
    @case('home')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l7-6 7 6" />
            <path d="M5 8v8a1 1 0 001 1h8a1 1 0 001-1V8" />
        </svg>
        @break

    @case('network')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="10" cy="4" r="2" />
            <circle cx="4" cy="16" r="2" />
            <circle cx="16" cy="16" r="2" />
            <path d="M10 6v4M10 10L4 14M10 10l6 4" />
        </svg>
        @break

    @case('drilldown')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h6M4 10h4M4 16h8" />
            <path d="M14 8l3 3-3 3" />
        </svg>
        @break

    @case('wallet')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2.5" y="5" width="15" height="11" rx="2" />
            <path d="M2.5 8.5h15" />
            <circle cx="14" cy="12" r="1" fill="currentColor" stroke="none" />
        </svg>
        @break

    @case('trending-up')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 13l5-5 3 3 6-6" />
            <path d="M13 5h4v4" />
        </svg>
        @break

    @case('user')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="10" cy="6" r="3" />
            <path d="M4 17c0-3 3-5 6-5s6 2 6 5" />
        </svg>
        @break
@endswitch
