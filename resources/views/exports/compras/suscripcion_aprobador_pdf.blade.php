<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202a; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .sub { color: #667; font-size: 9px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85c1e9; text-align: left; padding: 4px; border: 1px solid #b6c6d1; }
        td { padding: 3px 4px; border: 1px solid #d5dbdb; }
        tr:nth-child(even) td { background: #f7f9f9; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Aprobadores de suscripciones</h1>
    <div class="sub">
        Generado el {{ now()->format('d/m/Y H:i') }}
        @if (! empty($filtros['empresa_id']))
            · Empresa ID {{ $filtros['empresa_id'] }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Centro de costo</th>
                <th>Gerente</th>
                <th class="num">Suscripciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    <td>{{ $fila['id'] }}</td>
                    <td>{{ $fila['empresa'] }}</td>
                    <td><strong>{{ $fila['codigo'] }}</strong> {{ $fila['nombre'] }}</td>
                    <td>
                        {{ $fila['usuario_nombre'] }}
                        @if (! empty($fila['usuario_codigo']))
                            ({{ $fila['usuario_codigo'] }})
                        @endif
                    </td>
                    <td class="num">{{ $fila['suscripciones'] ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:#667;">Sin aprobadores</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
