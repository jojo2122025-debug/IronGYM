<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ef4444">
    <title>{{ config('app.name', 'IRONGYM') }} - لوحة إدارة الصالة الرياضية</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ @filemtime(public_path('css/style.css')) }}&t={{ time() }}">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" defer></script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}&t={{ time() }}" defer></script>
</head>
<body class="font-sans bg-slate-50 text-slate-900">
    @unless (request()->routeIs('dashboard'))
        <header class="app-layout-header border-b border-slate-200 bg-white/95 backdrop-blur-sm" style="color: #0f172a;">
            <div class="mx-auto flex max-w-[1200px] flex-col gap-3 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 text-lg font-semibold text-slate-900" style="color: #0f172a;">
                    <img src="{{ asset('images/irongym-logo.png') }}" alt="IRONGYM logo" class="h-10 w-10 rounded-3xl brand-badge object-cover">
                        <span class="leading-tight irongym-wordmark"><span class="brand-iron">IRON</span><span class="brand-gym">GYM</span></span>
                </a>
                    <nav class="flex flex-wrap items-center gap-3 text-sm text-slate-900">
                        <a href="{{ url('/') }}" class="rounded-xl px-4 py-2 hover:bg-slate-100" style="color: #0f172a;">الرئيسية</a>
                        <a href="{{ url('/dashboard') }}" class="rounded-xl px-4 py-2 hover:bg-slate-100" style="color: #0f172a;">لوحة الإدارة</a>
                        @if (request()->routeIs('home'))
                            <a href="#features" class="rounded-xl px-4 py-2 hover:bg-slate-100" style="color: #0f172a;">المزايا</a>
                        @endif
                    </nav>
            </div>
        </header>
    @endunless
        @yield('content')
    </main>
</body>
</html>
