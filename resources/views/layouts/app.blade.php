<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ef4444">
    <title>{{ config('app.name', 'IRONGYM') }} - لوحة إدارة الصالة الرياضية</title>
    <script>
        try {
            const savedTheme = localStorage.getItem('irongym_theme_v1');
            if (['ocean', 'royal', 'sand', 'frost'].includes(savedTheme)) document.documentElement.dataset.theme = savedTheme;
        } catch (_) { /* Keep the default theme when storage is unavailable. */ }
    </script>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/css/style.css?v={{ @filemtime(public_path('css/style.css')) }}&t={{ time() }}">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" defer></script>
    <script src="/js/app.js?v={{ @filemtime(public_path('js/app.js')) }}&t={{ time() }}" defer></script>
</head>
<body class="font-sans bg-slate-50 text-slate-900">
    @yield('content')
</body>
</html>
