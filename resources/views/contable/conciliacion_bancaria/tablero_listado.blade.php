<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { margin-bottom: 8px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; text-align: left; }
        table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Tablero conciliación bancaria</h1>
    <div class="meta">
        Generado {{ date('d/m/Y H:i') }} ·
        Período {{ sprintf('%02d/%d', (int) ($meta['mes'] ?? 0), (int) ($meta['anio'] ?? 0)) }} ·
        Cuentas {{ (int) ($resumen['cuentas'] ?? 0) }} ·
        Cerradas {{ (int) ($resumen['cerradas'] ?? 0) }} ·
        Aprobadas {{ (int) ($resumen['aprobadas'] ?? 0) }} ·
        Pares {{ (int) ($resumen['pares'] ?? 0) }} ·
        Grupos {{ (int) ($resumen['grupos'] ?? 0) }}
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Cuenta</th>
                <th>Extracto</th>
                <th class="num">Pares</th>
                <th class="num">Grupos</th>
                <th class="num">Hashes C/B</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ !empty($fila['tiene_interbanking']) ? 'IB/EXT' : 'Sin extracto' }}</td>
                    <td class="num">{{ $fila['pares_mes'] }}</td>
                    <td class="num">{{ $fila['grupos_mes'] }}</td>
                    <td class="num">{{ $fila['hashes_contables'] }} / {{ $fila['hashes_banco'] }}</td>
                    <td>{{ $fila['estado'] ?? 'sin_ejecucion' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
