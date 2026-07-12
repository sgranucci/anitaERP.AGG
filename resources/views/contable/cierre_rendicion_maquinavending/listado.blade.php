<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre rendiciones vending</title>
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
    <h2>Cierre rendiciones vending — contabilidad</h2>
    <p>Generado {{ date('d/m/Y H:i') }} — {{ $rendiciones->count() }} registros</p>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ticket</th>
                <th>Fecha rend.</th>
                <th>Empresa</th>
                <th>PV</th>
                <th>M&aacute;quina</th>
                <th>Jornada</th>
                <th>Estado cierre</th>
                <th>Asiento</th>
                <th class="num">Ventas</th>
                <th class="num">Invitaciones</th>
                <th class="num">Cobrado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rendiciones as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->codigo }}</td>
                <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $row->empresa?->nombre }}</td>
                <td>{{ $row->puntoventaCae?->codigo ?? $row->puntoventaCaea?->codigo ?? '—' }}</td>
                <td>{{ $row->maquinavending?->nombre ?? '—' }}</td>
                <td>{{ $row->maquinavendingRendicion?->fecha_jornada?->format('d/m/Y') ?? $row->fecharendicion?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $row->tieneCierreContable() ? ($row->esCierreContableLegacy() ? 'Cerrada (hist.)' : 'Cerrada') : 'Pendiente' }}</td>
                <td>{{ $row->asiento?->numeroasiento ?? '—' }}</td>
                <td class="num">{{ number_format((float) $row->totalfactura, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->totalinvitacion, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
