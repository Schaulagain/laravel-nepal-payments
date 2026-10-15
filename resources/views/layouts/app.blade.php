<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cloud Store' }} · {{ config('app.name') }}</title>
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #1d2330; --muted: #5d6677; --border: #dfe3ea; --accent: #2457d6; --ok: #1d7a46; --bad: #b4232a; --warn: #9a6200; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
        main { max-width: 860px; margin: 0 auto; padding: 32px 16px 64px; }
        header { border-bottom: 1px solid var(--border); background: var(--card); }
        header div { max-width: 860px; margin: 0 auto; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: var(--text); font-weight: 700; text-decoration: none; }
        .sandbox { font-size: 12px; color: var(--warn); border: 1px solid currentColor; border-radius: 999px; padding: 2px 10px; }
        h1 { margin: 0 0 4px; font-size: 1.6rem; }
        .muted { color: var(--muted); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 24px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; }
        .tag { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        .price { font-size: 1.4rem; font-weight: 700; margin: 8px 0 16px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; border: 0; background: var(--accent); color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .btn.secondary { background: transparent; color: var(--accent); border: 1px solid var(--border); }
        label { display: block; font-weight: 600; margin: 16px 0 6px; }
        input[type=text], input[type=email] { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font: inherit; }
        .gateways { display: flex; gap: 12px; flex-wrap: wrap; }
        .gateways label { font-weight: 500; margin: 0; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; background: #fdecec; color: var(--bad); }
        .error { color: var(--bad); font-size: 14px; margin-top: 4px; }
        .status { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 14px; font-weight: 600; }
        .status-completed { background: #e3f4ea; color: var(--ok); }
        .status-failed, .status-refunded { background: #fdecec; color: var(--bad); }
        .status-pending { background: #fdf3e1; color: var(--warn); }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 8px 24px; margin: 16px 0; }
        dt { color: var(--muted); }
        dd { margin: 0; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 10px 0; border-bottom: 1px solid var(--border); }
        td:last-child, th:last-child { text-align: right; }
    </style>
</head>
<body>
    <header>
        <div>
            <a href="{{ route('store.index') }}">☁️ Cloud Store</a>
            @if (config('payments.gateways.esewa.sandbox') || config('payments.gateways.khalti.sandbox'))
                <span class="sandbox">sandbox mode · no real money</span>
            @endif
        </div>
    </header>
    <main>
        @if (session('error'))
            <div class="alert" role="alert">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
