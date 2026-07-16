@props(['label', 'value', 'href' => null, 'linkText' => null])

<x-card>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="text-sm font-medium text-gray-500">{{ $label }}</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900 tracking-tight truncate">{{ $value }}</div>
        </div>
        @isset($icon)
            <div class="shrink-0 w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                {{ $icon }}
            </div>
        @endisset
    </div>

    @if ($href)
        <a href="{{ $href }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-sm text-amber-600 hover:text-amber-700 font-medium">
            {{ $linkText }} <span aria-hidden="true">&rarr;</span>
        </a>
    @endif
</x-card>
