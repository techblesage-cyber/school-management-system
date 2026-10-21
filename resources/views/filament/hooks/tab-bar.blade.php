@php
    $path = fn (string $url): string => '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');
    $homePath = $path($home);
@endphp
<nav class="tab-bar" aria-label="Main">
    <a href="{{ $home }}" @class(['is-active' => $current === $homePath])>
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedHome" />
        <span>Home</span>
    </a>
    @foreach ($items as $item)
        <a href="{{ $item['url'] }}" @class(['is-active' => $current === $path($item['url']) || str_starts_with($current, $path($item['url']).'/')])>
            <x-filament::icon :icon="$item['icon']" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
    <button type="button" x-data x-on:click="$store.sidebar.open()">
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedSquares2x2" />
        <span>More</span>
    </button>
</nav>
