{{-- Phones: open full screen from the home screen, with a matching status bar --}}
<meta name="theme-color" content="#eaf1fb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1020" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="stylesheet" href="{{ asset('css/school-theme.css') }}?v={{ @filemtime(public_path('css/school-theme.css')) }}">
