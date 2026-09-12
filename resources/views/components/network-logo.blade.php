@props(['network', 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-7 w-7',
        'md' => 'h-9 w-9 sm:h-10 sm:w-10',
        'lg' => 'h-12 w-12',
    ];
    $imgSizes = [
        'sm' => 'h-7 w-7',
        'md' => 'h-9 w-9 sm:h-10 sm:w-10',
        'lg' => 'h-12 w-12',
    ];
    $cls = $sizes[$size] ?? $sizes['md'];
    $colors = ['MTN' => '#ffcc08', 'Telecel' => '#e4032e', 'AirtelTigo' => '#1b4f9c'];
    $slugs = ['MTN' => 'mtn', 'Telecel' => 'telecel', 'AirtelTigo' => 'airteltigo'];
    $slug = $slugs[$network] ?? strtolower($network);
    $color = $colors[$network] ?? '#9aa2ad';
    $letter = substr($network, 0, 1);
@endphp

<img
    src="/images/{{ $slug }}.png"
    alt="{{ $network }}"
    class="{{ $cls }} rounded-full object-contain"
    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
>
<span
    class="{{ $cls }} items-center justify-center rounded-full text-white font-display font-bold text-xs"
    style="display:none; background:{{ $color }}"
>{{ $letter }}</span>
