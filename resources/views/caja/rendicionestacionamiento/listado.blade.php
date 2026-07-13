<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{-- PhpSpreadsheet usa <title> como nombre de hoja Excel (máx. 31 chars) --}}
    <title>Rend. estacionamiento caja</title>
    <style>
        table { font-family: DejaVu Sans, Arial, sans-serif; border-collapse: collapse; width: 100%; font-size: 9px; }
        th, td { border: 1px solid #666; padding: 4px 6px; text-align: left; }
        th { background: #d4e6f1; }
        .num { text-align: right; }
        h2 { font-size: 14px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h2>Rendiciones estacionamiento — caja</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Ticket</th>
                <th>Fecha rendición</th>
                <th>Empresa</th>
                <th>Caja</th>
                <th>Turno op.</th>
                <th>Jornada</th>
                <th class="num">Inicio fondo</th>
                <th class="num">Facturado</th>
                <th class="num">Invitaciones</th>
                <th class="num">Cobrado</th>
                <th class="num">Sobr./falt.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rendiciones as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->codigo }}</td>
                <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $row->empresa?->nombre }}</td>
                <td>{{ $row->caja?->nombre }}</td>
                <td>#{{ $row->turno_operativo_estacionamiento_id }}</td>
                <td>{{ $row->turnoOperativo?->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                <td class="num">{{ number_format((float) $row->iniciodelfondo, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->totalfactura, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->totalinvitacion, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->sobrantefaltante, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
