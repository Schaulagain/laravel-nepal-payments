@props(['gateway', 'size' => 'md'])

@php
    $styles = [
        'esewa' => ['bg-esewa', 'e'],
        'khalti' => ['bg-khalti', 'K'],
        'hbl' => ['bg-hbl', null],
    ][$gateway] ?? ['bg-stone-700', strtoupper($gateway[0])];
    $sizes = ['sm' => 'size-6 rounded-md text-xs', 'md' => 'size-10 rounded-xl text-base', 'lg' => 'size-12 rounded-2xl text-lg'];
@endphp

{{-- A simple coloured tile instead of the real logos, which are trademarks. --}}
<span {{ $attributes->class([$styles[0], $sizes[$size], 'inline-flex shrink-0 items-center justify-center font-semibold text-white shadow-sm']) }}>
    @if ($styles[1])
        {{ $styles[1] }}
    @else
        <x-icon name="card" @class(['size-3.5' => $size === 'sm', 'size-5' => $size !== 'sm']) />
    @endif
</span>
