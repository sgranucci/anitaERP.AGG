<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre rendiciones bingo</title>
    <style>
        table { font-family: DejaVu Sans, Arial, sans-serif; border-collapse: collapse; width: 100%; font-size: 8px; }
        th, td { border: 1px solid #cccccc; padding: 4px 6px; text-align: left; }
        th { background: #85C1E9; color: #17202A; }
        tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        h2 { font-size: 14px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h2>Cierre rendiciones bingo — contabilidad</h2>
    <p>Generado {{ date('d/m/Y H:i') }} — {{ $rendiciones->count() }} registros</p>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ticket</th>
                <th>Fecha rend.</th>
                <th>Empresa</th>
                <th>Jornada</th>
                <th>Estado cierre</th>
                <th>Asiento</th>
                <th>FBI</th>
                <th class="num">Recaudaci&oacute;n</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rendiciones as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->codigo }}</td>
                <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $row->empresa?->nombre }}</td>
                <td>{{ $row->fecha_jornada?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $row->tieneCierreContable() ? 'Cerrada' : 'Pendiente' }}</td>
                <td>{{ $row->asiento?->numeroasiento ?? '—' }}</td>
                <td>
                    @if ((int) ($row->factura_nro ?? 0) > 0)
                        {{ $row->factura_letra }}{{ str_pad((string) $row->factura_sucursal, 4, '0', STR_PAD_LEFT) }}-{{ str_pad((string) $row->factura_nro, 8, '0', STR_PAD_LEFT) }}
                    @else
                        —
                    @endif
                </td>
                <td class="num">{{ number_format((float) $row->total_cartones, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
