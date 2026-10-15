@props(['name'])

{{-- Small stroke icons (24px grid). --}}
<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('cloud')
            <path d="M17.5 19H8a5 5 0 1 1 1.1-9.88A6 6 0 0 1 20.6 11.5 3.75 3.75 0 0 1 17.5 19Z" />
            @break
        @case('vps')
            <rect x="3" y="4" width="18" height="7" rx="2" /><rect x="3" y="13" width="18" height="7" rx="2" /><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6" />
            @break
        @case('domain')
            <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c2.5 2.6 3.75 5.6 3.75 9S14.5 18.4 12 21c-2.5-2.6-3.75-5.6-3.75-9S9.5 5.6 12 3Z" />
            @break
        @case('email')
            <rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3.5 6.5 8.5 6.5 8.5-6.5" />
            @break
        @case('card')
            <rect x="2.5" y="5" width="19" height="14" rx="2" /><path d="M2.5 10h19M6.5 15h3" />
            @break
        @case('wallet')
            <path d="M19 7V5.5A1.5 1.5 0 0 0 17.5 4h-12A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15" /><path d="M21 9h-5a3 3 0 0 0 0 6h5V9Z" /><path d="M16 12h.01" />
            @break
        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7.5" />
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('undo')
            <path d="M9 14 4 9l5-5" /><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11" />
            @break
        @case('arrow-left')
            <path d="M19 12H5M11 18l-6-6 6-6" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('lock')
            <rect x="4.5" y="10.5" width="15" height="10" rx="2" /><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
            @break
        @case('shield')
            <path d="M12 21s7-3.2 7-9.5V5.8L12 3 5 5.8v5.7C5 17.8 12 21 12 21Z" /><path d="m9 11.5 2 2 4-4" />
            @break
        @case('receipt')
            <path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2V3Z" /><path d="M9 8h6M9 12h6" />
            @break
        @case('printer')
            <path d="M7 9V3h10v6" /><rect x="3" y="9" width="18" height="8" rx="2" /><path d="M7 14h10v7H7z" />
            @break
        @case('alert')
            <circle cx="12" cy="12" r="9" /><path d="M12 7.5v5M12 16.5h.01" />
            @break
        @case('refresh')
            <path d="M20 11a8 8 0 0 0-14.6-4.5L4 8" /><path d="M4 3v5h5" /><path d="M4 13a8 8 0 0 0 14.6 4.5L20 16" /><path d="M20 21v-5h-5" />
            @break
    @endswitch
</svg>
