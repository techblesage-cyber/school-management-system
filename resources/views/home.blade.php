<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $school?->name ?? config('app.name') }}</title>
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --staff: #1d4ed8; --parents: #047857; --bg: #f8fafc; --card: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #e2e8f0; --muted: #94a3b8; --line: #334155; --bg: #0f172a; --card: #1e293b; --staff: #3b82f6; --parents: #10b981; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 16px; }
        main { width: 100%; max-width: 460px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 32px 24px; text-align: center; }
        img { width: 88px; height: 88px; object-fit: contain; margin-bottom: 8px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        p.motto { margin: 0 0 24px; color: var(--muted); font-style: italic; }
        p.lead { margin: 0 0 24px; color: var(--muted); }
        a.btn { display: block; padding: 14px 16px; border-radius: 10px; color: #fff; text-decoration: none; font-weight: 600; margin-bottom: 12px; }
        a.btn span { display: block; font-weight: 400; font-size: 13px; opacity: .9; }
        a.staff { background: var(--staff); }
        a.parents { background: var(--parents); }
        footer { margin-top: 16px; font-size: 12px; color: var(--muted); }
        footer a { color: var(--muted); }
    </style>
</head>
<body>
    <main>
        @if ($school?->logo)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) }}" alt="">
        @endif
        <h1>{{ $school?->name ?? 'School Management System' }}</h1>
        @if ($school?->motto)
            <p class="motto">{{ $school->motto }}</p>
        @else
            <p class="lead">Sign in to continue.</p>
        @endif

        <a class="btn parents" href="{{ url('/parent') }}">Parents' portal<span>Results, report cards, fees and circulars</span></a>
        <a class="btn staff" href="{{ $school ? url('/school') : url('/school/login') }}">Staff sign in<span>Teachers, bursar and administration</span></a>

        <footer>
            @unless ($school)
                <a href="{{ url('/admin') }}">Sharebility admin</a>
            @endunless
        </footer>
    </main>
</body>
</html>
