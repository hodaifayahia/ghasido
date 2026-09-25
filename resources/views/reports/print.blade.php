<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <style>
        /* Guesvia tokens, mirrored from the @theme block in resources/css/app.css.
           This page lives outside the Vite bundle so the variables are inlined
           here; never use a value that is not a token there. */
        :root {
            --color-brand-100: #dbe8ff;
            --color-brand-600: #0b5cff;
            --color-brand-700: #1249c9;
            --color-brand-900: #0c2e7a;
            --color-ink: #12233d;
            --color-ink-muted: #64748b;
            --color-ink-royal: #110da8;
            --color-ink-slate: #737ba9;
            --color-surface: #ffffff;
            --color-line: #e4edf8;
            --color-tint-header: #f6f9fd;
            --color-app: #f4f9fe;
            --color-success-text: #1b7f4f;
            --color-danger-text: #b42318;
            --font-sans: Inter, ui-sans-serif, system-ui, sans-serif;
            --font-heading: Poppins, ui-sans-serif, system-ui, sans-serif;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            background: var(--color-surface);
            color: var(--color-ink);
            font-family: var(--font-sans);
            font-size: 11px;
            line-height: 1.5;
        }

        .page { padding: 28px 32px; }

        header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }

        h1 {
            margin: 0;
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--color-ink-royal);
        }

        .subtitle { margin: 4px 0 0; color: var(--color-ink-slate); font-size: 12px; }

        .brand { text-align: right; font-family: var(--font-heading); font-weight: 700; font-size: 16px; color: var(--color-brand-700); }
        .brand small { display: block; font-family: var(--font-sans); font-weight: 400; font-size: 10px; color: var(--color-ink-muted); }

        .filters { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 16px; margin: 18px 0 0; padding: 12px 14px; border: 1px solid var(--color-line); border-radius: 10px; background: var(--color-app); }
        .filters dt { font-size: 9.5px; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; color: var(--color-ink-slate); }
        .filters dd { margin: 1px 0 0; font-weight: 500; color: var(--color-brand-900); }

        .stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; margin: 14px 0 0; }
        .stat { border: 1px solid var(--color-line); border-radius: 10px; padding: 10px 12px; }
        .stat strong { display: block; font-family: var(--font-heading); font-size: 18px; font-weight: 700; color: var(--color-brand-700); line-height: 1.1; }
        .stat span { display: block; margin-top: 3px; font-weight: 500; color: var(--color-brand-900); }
        .stat small { display: block; color: var(--color-ink-slate); }

        h2 { margin: 20px 0 8px; font-family: var(--font-heading); font-size: 14px; font-weight: 600; color: var(--color-brand-900); }
        .count { color: var(--color-ink-muted); font-weight: 400; }

        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        thead th { text-align: left; padding: 6px 8px; background: var(--color-tint-header); color: var(--color-brand-900); font-size: 10px; font-weight: 600; border-bottom: 1px solid var(--color-line); }
        tbody td { padding: 5px 8px; border-bottom: 1px solid var(--color-line); vertical-align: top; word-break: break-word; max-width: 260px; }
        tbody tr:nth-child(even) td { background: var(--color-app); }
        .empty { padding: 24px; text-align: center; color: var(--color-ink-slate); }

        footer { margin-top: 18px; display: flex; justify-content: space-between; color: var(--color-ink-muted); font-size: 10px; }

        .toolbar { position: fixed; top: 12px; right: 16px; }
        .toolbar button { font: 600 12px var(--font-heading); color: #fff; background: var(--color-brand-600); border: 0; border-radius: 10px; padding: 9px 14px; cursor: pointer; }

        @page { size: A4 landscape; margin: 12mm; }

        @media print {
            .toolbar { display: none; }
            .page { padding: 0; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button></div>

<div class="page">
    <header>
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ __('Reports & Export') }} · {{ $rangeLabel }}</p>
        </div>
        <div class="brand">
            {{ config('app.name') }}
            <small>{{ __('Generated :when by :who', ['when' => $generatedAt->format('d M Y H:i'), 'who' => $generatedBy]) }}</small>
        </div>
    </header>

    <dl class="filters">
        @foreach ($filterSummary as $entry)
            <div>
                <dt>{{ $entry['label'] }}</dt>
                <dd>{{ $entry['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="stats">
        @foreach ($stats as $stat)
            <div class="stat">
                <strong>{{ $stat['value'] }}</strong>
                <span>{{ $stat['label'] }}</span>
                <small>{{ $stat['detail'] }}</small>
            </div>
        @endforeach
    </div>

    <h2>{{ $title }} <span class="count">· {{ trans_choice(':count row|:count rows', $rowCount, ['count' => $rowCount]) }}</span></h2>

    @if ($rows === [])
        <p class="empty">{{ __('No rows match these filters.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer>
        <span>{{ config('app.name') }} · {{ __('Reports & Export') }}</span>
        <span>{{ $rangeLabel }}</span>
    </footer>
</div>

<script>
    // Opened in a new tab by the Export PDF button: offer the print dialog
    // straight away; the button above repeats it.
    window.addEventListener('load', function () {
        if (window.matchMedia && !window.matchMedia('print').matches) {
            setTimeout(function () { window.print(); }, 300);
        }
    });
</script>
</body>
</html>
