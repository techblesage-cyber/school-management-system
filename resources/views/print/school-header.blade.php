        <header class="school">
            @if ($school->logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) }}" alt="">
            @endif
            <div>
                <h1>{{ $school->name }}</h1>
                @if ($school->motto)<p><em>{{ $school->motto }}</em></p>@endif
                <p>{{ collect([$school->address, $school->phone, $school->email])->filter()->implode(' · ') }}</p>
            </div>
        </header>
