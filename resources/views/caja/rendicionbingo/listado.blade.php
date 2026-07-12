<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado rendiciones bingo caja</title>
    <style>
        table { font-family: DejaVu Sans, Arial, sans-serif; border-collapse: collapse; width: 100%; font-size: 9px; }
        th, td { border: 1px solid #666; padding: 4px 6px; text-align: left; }
        th { background: #d4e6f1; }
        .num { text-align: right; }
        h2 { font-size: 14px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h2>Rendiciones bingo — caja</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Fecha rendición</th>
                <th>Empresa</th>
                <th>Jornada</th>
                <th>Turno</th>
                <th>Terminal</th>
                <th class="num">Recaudación</th>
                <th class="num">Depósito</th>
                <th>Anita sync</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rendiciones as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->codigo }}</td>
                <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $row->empresa?->nombre }}</td>
                <td>{{ $row->fecha_jornada?->format('d/m/Y') ?? $row->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                <td>{{ $row->turnoOperativo?->turno?->nombre ?? '—' }}</td>
                <td>{{ $row->turnoOperativo?->identificador_pc ?? '—' }}</td>
                <td class="num">{{ number_format((float) $row->total_cartones, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($row->deposito ?? $row->saldo_final), 2, ',', '.') }}</td>
                <td>{{ $row->anita_sincronizado_en?->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
                <td>{{ $row->creousuario?->nombre ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
