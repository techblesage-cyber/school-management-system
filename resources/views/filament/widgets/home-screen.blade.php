<x-filament-widgets::widget>
    <div class="glass-card">
        <div class="home-hello">
            @if ($avatar)
                <img class="home-hello-avatar" src="{{ $avatar }}" alt="">
            @else
                <span class="home-hello-avatar" aria-hidden="true">{{ $initials }}</span>
            @endif
            <div class="home-hello-text">
                <h2>{{ $greeting }}</h2>
                <p>{{ $subtitle }} · {{ $date }}</p>
            </div>
        </div>

        @if (count($apps) > 0)
            <nav class="app-grid" aria-label="Apps">
                @foreach ($apps as $app)
                    <a href="{{ $app['url'] }}" class="app-tile" data-app="{{ $app['key'] }}">
                        <span class="app-icon" style="--app: {{ $app['color'] }}">
                            <x-filament::icon :icon="$app['icon']" />
                        </span>
                        <span class="app-label">{{ $app['label'] }}</span>
                        @if ($app['badge'])
                            <span class="app-badge" aria-label="{{ $app['badge'] }} waiting">{{ $app['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif
    </div>
</x-filament-widgets::widget>
