<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Store' }} · Cloud Store</title>
    @stack('head')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="no-print sticky top-0 z-10 border-b border-stone-200/80 bg-stone-50/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5 sm:px-6">
            <a href="{{ route('store.index') }}" class="flex items-center gap-2.5 font-semibold tracking-tight">
                <span class="inline-flex size-8 items-center justify-center rounded-lg bg-stone-900 text-white">
                    <x-icon name="cloud" class="size-4.5" />
                </span>
                Cloud Store
            </a>

            <span class="inline-flex items-center gap-2 rounded-full border border-amber-300/70 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-60"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                </span>
                Test mode, no real money
            </span>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 sm:py-14">
        @if (session('error'))
            <div role="alert" class="mb-8 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <x-icon name="alert" class="mt-0.5 size-4.5 shrink-0" />
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="no-print border-t border-stone-200">
        <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-6 text-sm text-stone-500 sm:flex-row sm:justify-between sm:px-6">
            <p>A demo store for testing Nepali payment gateways.</p>
            <a href="https://github.com/Schaulagain/laravel-nepal-payments" class="hover:text-stone-900">Source on GitHub</a>
        </div>
    </footer>
</body>
</html>
