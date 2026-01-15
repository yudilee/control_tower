@props(['value' => null, 'showIcon' => true, 'showLabel' => true, 'size' => 'normal'])

@php
    // Use Job model's WORK_STATUS_META for consistent colors across app
    $meta = \App\Models\Job::getWorkStatusMeta($value ?? '');
    $color = $meta['color'] ?? 'secondary';
    $icon = $meta['icon'] ?? null;
    $label = $meta['label'] ?? $value ?? '-';
    
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

