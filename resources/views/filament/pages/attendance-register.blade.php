@php
    $dots = ['present' => '#34c759', 'absent' => '#ff3b30', 'sick' => '#af52de', 'late' => '#ff9500', 'excused' => '#8e8e93'];
@endphp

@if (! $ready)
    <x-filament::section>
        <div style="text-align: center; padding: 1.5rem 0; color: var(--ink-soft);">
            Choose a day and a class above to see the register.
        </div>
    </x-filament::section>
@else
    <div style="display: grid; gap: 1rem;">
        <div class="register-counts" aria-live="polite">
            @foreach ($statuses as $status)
                <span class="count-pill" style="--dot: {{ $dots[$status->value] }}"><i></i>{{ $status->buttonLabel() }} {{ $counts[$status->value] }}</span>
            @endforeach
            <span class="count-pill" style="--dot: transparent; border-style: dashed;">
                @if ($notMarked > 0)
                    {{ $notMarked }} not marked yet
                @else
                    ✓ Register complete
                @endif
            </span>
        </div>

        <div class="glass-card" style="overflow: hidden;">
            <div style="padding: 0.9rem 1rem; border-bottom: 1px solid var(--glass-edge);">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="Find a student" />
                </x-filament::input.wrapper>
            </div>

            <div class="register">
                @forelse ($rows as $row)
                    <div class="register-row" wire:key="register-{{ $row['id'] }}" data-student="{{ $row['id'] }}">
                        <div class="register-who">
                            <img class="register-photo" src="{{ $row['photo'] }}" alt="">
                            <div style="min-width: 0;">
                                <div class="register-name">{{ $row['name'] }}</div>
                                <div class="register-sub">{{ $row['sub'] }}</div>
                            </div>
                        </div>
                        <div class="register-choices" role="radiogroup" aria-label="Attendance for {{ $row['name'] }}">
                            @foreach ($statuses as $status)
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $row['status'] === $status->value ? 'true' : 'false' }}"
                                    data-status="{{ $status->value }}"
                                    @class(['register-choice', 'is-chosen' => $row['status'] === $status->value])
                                    wire:click="mark({{ $row['id'] }}, '{{ $status->value }}')"
                                    wire:loading.attr="disabled"
                                >
                                    <x-filament::icon :icon="$status->icon()" />
                                    <span>{{ $status->buttonLabel() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div style="padding: 2rem 1rem; text-align: center; color: var(--ink-soft);">
                        {{ $searching ? 'No student matches that name.' : 'No students in this class yet.' }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endif
