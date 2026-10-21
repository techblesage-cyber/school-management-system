<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#eaf1fb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>{{ $school?->name ?? config('app.name') }}</title>
    <style>
        :root {
            --ink: #0f172a; --muted: #64748b;
            --glass: rgba(255, 255, 255, .7); --edge: rgba(255, 255, 255, .85);
            --base-1: #eaf1fb; --base-2: #f5f0fb;
        }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #f1f5f9; --muted: #94a3b8; --glass: rgba(28, 30, 38, .62); --edge: rgba(255, 255, 255, .08); --base-1: #0b1020; --base-2: #120d1f; }
        }
        * { box-sizing: border-box; }
        html { background: var(--base-1); }
        body {
            margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 16px;
            color: var(--ink);
            font: 16px/1.5 -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Arial, sans-serif;
            background:
                radial-gradient(900px 650px at 10% -10%, rgba(0, 122, 255, .30), transparent 62%),
                radial-gradient(800px 600px at 100% 0%, rgba(175, 82, 222, .22), transparent 58%),
                radial-gradient(900px 700px at 40% 112%, rgba(52, 199, 89, .20), transparent 60%),
                linear-gradient(170deg, var(--base-1), var(--base-2));
            background-attachment: fixed;
        }
        main {
            width: 100%; max-width: 440px; padding: 32px 22px 26px; text-align: center;
            background: var(--glass); border: 1px solid var(--edge); border-radius: 28px;
            -webkit-backdrop-filter: saturate(180%) blur(22px); backdrop-filter: saturate(180%) blur(22px);
            box-shadow: 0 30px 80px -30px rgba(30, 41, 90, .35);
        }
        .logo { width: 84px; height: 84px; object-fit: contain; margin: 0 auto 10px; display: block; border-radius: 20px; }
        .badge { width: 72px; height: 72px; margin: 0 auto 12px; border-radius: 22px; display: grid; place-items: center; color: #fff;
            background: linear-gradient(160deg, #5ac8fa, #007aff); box-shadow: 0 10px 24px -10px #007aff; }
        .badge svg { width: 38px; height: 38px; }
        h1 { margin: 0 0 4px; font-size: 24px; font-weight: 800; letter-spacing: -.02em; }
        p.lead { margin: 0 0 24px; color: var(--muted); }
        p.motto { margin: 0 0 24px; color: var(--muted); font-style: italic; }
        a.btn { display: flex; align-items: center; gap: 14px; text-align: start; padding: 14px 16px; border-radius: 18px; text-decoration: none;
            color: var(--ink); background: rgba(255, 255, 255, .6); border: 1px solid var(--edge); margin-bottom: 12px; transition: transform .12s ease; }
        @media (prefers-color-scheme: dark) { a.btn { background: rgba(255, 255, 255, .05); } }
        a.btn:active { transform: scale(.98); }
        a.btn b { display: block; font-size: 17px; }
        a.btn span.sub { display: block; font-size: 13px; color: var(--muted); }
        .icon { flex: none; width: 52px; height: 52px; border-radius: 15px; display: grid; place-items: center; color: #fff; }
        .icon svg { width: 26px; height: 26px; }
        .icon.parents { background: linear-gradient(160deg, #34d399, #059669); box-shadow: 0 8px 18px -8px #059669; }
        .icon.staff { background: linear-gradient(160deg, #5ac8fa, #007aff); box-shadow: 0 8px 18px -8px #007aff; }
        .chev { margin-inline-start: auto; color: var(--muted); }
    </style>
</head>
<body>
    <main>
        @if ($school?->logo)
            <img class="logo" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) }}" alt="">
        @else
            <div class="badge" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/></svg>
            </div>
        @endif

        <h1>{{ $school?->name ?? 'School Management System' }}</h1>
        @if ($school?->motto)
            <p class="motto">{{ $school->motto }}</p>
        @else
            <p class="lead">Who are you signing in as?</p>
        @endif

        <a class="btn" href="{{ url('/parent') }}">
            <span class="icon parents" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
            </span>
            <span><b>A parent</b><span class="sub">Report cards, marks, fees and circulars</span></span>
            <span class="chev" aria-hidden="true">›</span>
        </a>
        <a class="btn" href="{{ $school ? url('/school') : url('/school/login') }}">
            <span class="icon staff" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
            </span>
            <span><b>Staff</b><span class="sub">Teachers, bursar and administration</span></span>
            <span class="chev" aria-hidden="true">›</span>
        </a>
    </main>
</body>
</html>
