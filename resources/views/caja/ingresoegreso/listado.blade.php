<!DOCTYPE html>
<html>
<title>Ingresos y Egresos de Caja</title>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px 6px;
        }
        table.data thead th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        h2 { font-size: 14px; margin: 0 0 8px; }
        .meta { font-size: 8px; margin-bottom: 8px; color: #444; }
    </style>
</head>
<body>
    <h2>Ingresos y Egresos de Caja</h2>
    <div class="meta">Generado {{ date('d/m/Y H:i') }} — {{ $caja_movimiento->count() }} registro(s)</div>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Número</th>
                <th>Fecha</th>
                <th>Tipo de transacción</th>
                <th>Concepto</th>
                <th>Detalle</th>
                @if (config('app.empresa') == 'Iguassu Travel')
                    <th>Orden de servicio</th>
                @endif
                <th>Monto en $</th>
                <th>Movimientos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($caja_movimiento as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombreempresa }}</td>
                <td>{{ $data->numerotransaccion }}</td>
                <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
                <td>{{ $data->nombretipotransaccion_caja }}</td>
                <td>{{ $data->nombreconceptogasto ?? '' }}</td>
                <td>{{ $data->detalle ?? '' }}</td>
                @if (config('app.empresa') == 'Iguassu Travel')
                    <td>{{ $data->ordenservicio_id }}</td>
                @endif
                <td>
                    @php
                        $ieMonto = \App\Support\Caja\IngresoEgresoListadoMontoSupport::resumen($data);
                    @endphp
                    {{ number_format($ieMonto['monto'], 2, ',', '.') }}
                </td>
                <td>
                    @foreach ($ieMonto['lineas'] as $lineaMov)
                        {{ $lineaMov }}
                        @if (! $loop->last)
                            <br>
                        @endif
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
