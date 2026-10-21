<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $school->name }}</title>
    @include('print.styles')
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <main class="page">
        @include('print.school-header')

        @yield('content')

        <footer class="printed">Printed {{ now()->format('j M Y, H:i') }}</footer>
    </main>
</body>
</html>
