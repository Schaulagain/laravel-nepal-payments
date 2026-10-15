@props(['paisa'])
<span {{ $attributes->class('tabular-nums') }}>Rs {{ number_format($paisa / 100, 2) }}</span>
