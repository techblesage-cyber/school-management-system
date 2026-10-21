@php
    use App\Support\Money;
@endphp

<div class="class-fees" style="display: grid; gap: 1.25rem;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
        <label for="class-fees-term" style="font-weight: 700;">Term</label>
        <x-filament::input.wrapper style="min-width: 14rem;">
            <x-filament::input.select id="class-fees-term" wire:model.live="termId">
                @foreach ($terms as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        @if ($feeSummary)
            <span class="count-pill">{{ $feeSummary }}</span>
        @endif
    </div>

    @forelse ($tiles as $class)
        <section>
            <h3 style="font-size: 1.15rem; font-weight: 800; letter-spacing: -0.01em; margin: 0 0 0.6rem;">{{ $class['name'] }}</h3>
            <div class="class-tiles">
                @foreach ($class['tiles'] as $tile)
                    <button
                        type="button"
                        wire:click="choose('{{ $tile['key'] }}')"
                        @class(['class-tile', 'is-active' => $tile['active']])
                        data-tile="{{ $tile['key'] }}"
                    >
                        <div class="class-tile-name">{{ $tile['name'] }}</div>
                        <div class="class-tile-meta">
                            {{ $tile['students'] }} {{ str('student')->plural($tile['students']) }}
                            @if ($tile['percent'] !== null) · {{ $tile['percent'] }}% paid @endif
                        </div>
                        <div @class(['progress', 'is-low' => ($tile['percent'] ?? 0) < 60, 'is-very-low' => ($tile['percent'] ?? 0) < 30])>
                            <span style="width: {{ $tile['percent'] ?? 0 }}%"></span>
                        </div>
                        <div class="class-tile-meta" style="margin-top: 0.45rem;">
                            @if ($tile['owed'] > 0)
                                <span style="color: #dc2626; font-weight: 700;">{{ Money::format($tile['owed']) }} owed</span>
                            @elseif ($tile['billed'] > 0)
                                <span style="color: #16a34a; font-weight: 700;">All cleared</span>
                            @else
                                Not billed yet
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </section>
    @empty
        <x-filament::section>
            No classes yet. Add them under Setup → Classes &amp; streams.
        </x-filament::section>
    @endforelse

    @if ($unbilled > 0)
        <div class="fi-callout glass-card" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 1rem 1.25rem;">
            <div>
                <strong>{{ $unbilled }} {{ str('student')->plural($unbilled) }} here {{ $unbilled === 1 ? 'has' : 'have' }} no bill for {{ $termName }} yet.</strong>
                <div style="color: var(--ink-soft); font-size: 0.9rem;">Their fees come from the fee structure for their class once they are billed.</div>
            </div>
            @if ($canBill)
                <x-filament::button wire:click="billUnbilled" icon="heroicon-o-banknotes">
                    Bill them now
                </x-filament::button>
            @endif
        </div>
    @endif
</div>
