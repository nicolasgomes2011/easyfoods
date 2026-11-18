<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Codes das mesas</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #18181b; background: #fff; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
        .toolbar h1 { margin: 0; font-size: 20px; }
        .toolbar a, .toolbar button { font: inherit; font-size: 14px; padding: 6px 12px; border-radius: 8px; border: 1px solid #d4d4d8; background: #fff; color: #18181b; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #f97316; border-color: #f97316; color: #fff; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .card { border: 1px dashed #a1a1aa; border-radius: 12px; padding: 16px; text-align: center; break-inside: avoid; page-break-inside: avoid; }
        .card h2 { margin: 0 0 8px; font-size: 22px; }
        .card svg { width: 100%; max-width: 200px; height: auto; }
        .card p { margin: 8px 0 0; font-size: 13px; }
        .card .link { font-size: 10px; color: #71717a; word-break: break-all; }
        .empty { color: #71717a; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>QR Codes das mesas</h1>
        <div>
            <a href="{{ route('admin.dining.tables') }}">Voltar para mesas</a>
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    @if($codes->isEmpty())
        <p class="empty">Nenhuma mesa cadastrada ainda.</p>
    @else
        <div class="grid">
            @foreach($codes as $code)
                <div class="card">
                    <h2>Mesa {{ $code['table']->number }}</h2>
                    {!! $code['svg'] !!}
                    <p>Aponte a câmera para ver o cardápio e pedir</p>
                    <p class="link">{{ $code['url'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
