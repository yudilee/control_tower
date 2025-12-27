@props(['value' => null, 'showIcon' => true, 'showLabel' => true, 'size' => 'normal'])

@php
    $option = \App\Models\DropdownOption::getOption('work_status', $value);
    $color = $option?->color ?? 'secondary';
    $icon = $option?->icon ?? null;
    $label = $option?->label ?? $value ?? '-';
    
    $sizeClass = match($size) {
        'sm' => 'badge-sm',
        'lg' => 'fs-6',
        default => '',
    };
@endphp

@if($value)
<span {{ $attributes->merge(['class' => "badge bg-{$color} {$sizeClass}"]) }}>
    @if($showIcon && $icon)
        <i class="bi bi-{{ $icon }} {{ $showLabel ? 'me-1' : '' }}"></i>
    @endif
    @if($showLabel)
        {{ $label }}
    @endif
</span>
@else
<span class="text-muted">-</span>
@endif
