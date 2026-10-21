<x-filament-widgets::widget>
    <div style="display: grid; gap: 1rem;">
        <div class="glass-card">
            <div class="home-hello" style="padding-bottom: 1.25rem;">
                <span class="home-hello-avatar" aria-hidden="true" style="background: linear-gradient(160deg, #34d399, #059669);">
                    <x-filament::icon icon="heroicon-o-home-modern" style="width: 1.5rem; height: 1.5rem;" />
                </span>
                <div class="home-hello-text">
                    <h2>{{ $greeting }}</h2>
                    <p>{{ collect([$school, $date])->filter()->implode(' · ') }}</p>
                </div>
            </div>
        </div>

        @if (count($children) === 0)
            <div class="glass-card" style="padding: 1.5rem; text-align: center; color: var(--ink-soft);">
                No children are linked to your account yet. Ask the school to link them to your phone number.
            </div>
        @else
            <div class="child-cards">
                @foreach ($children as $child)
                    <a href="{{ $child['url'] }}" class="glass-card child-card" data-child="{{ $child['id'] }}">
                        <img class="child-card-photo" src="{{ $child['photo'] }}" alt="">
                        <div style="min-width: 0;">
                            <div class="child-card-name">{{ $child['name'] }}</div>
                            <div class="child-card-meta">{{ $child['class'] }}</div>
                            <div class="child-card-facts">
                                @if ($child['result'])
                                    <span class="fact">{{ $child['result'] }}</span>
                                @endif
                                @if ($child['attendance'])
                                    <span class="fact">{{ $child['attendance'] }}</span>
                                @endif
                                <span @class(['fact', 'is-bad' => $child['owes'], 'is-good' => ! $child['owes']])>Fees: {{ $child['fees'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
