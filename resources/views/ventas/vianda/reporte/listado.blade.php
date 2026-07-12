<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18px 20px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #17202A; font-size: 8px; }
        .cabecera { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .cabecera td { vertical-align: middle; }
        .logo { height: 40px; }
        .titulo { font-size: 15px; font-weight: bold; }
        .meta { font-size: 8px; color: #555; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th { background-color: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; text-align: left; font-size: 8px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 4px; font-size: 7.5px; }
        table.data tbody tr:nth-child(even) td { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .subtitulo-seccion { font-size: 11px; font-weight: bold; margin: 12px 0 2px; }
        .anulado td { color: #999999; }
        tfoot td { font-weight: bold; background-color: #eaf2f8; border: 1px solid #cccccc; padding: 3px 4px; }
    </style>
</head>
<body>
    @php
        $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    @endphp
    <table class="cabecera">
        <tr>
            <td style="width: 25%;">
                @foreach ($logos as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="logo">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <div class="titulo">Reporte de viandas</div>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right;">
                <div class="meta">{{ $filas->count() }} consumo(s)</div>
            </td>
        </tr>
    </table>

    <div class="meta">{{ $subtitulo ?? '' }}</div>

    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Login</th>
                <th>Empleado</th>
                <th>Centro de costo</th>
                <th>Empresa</th>
                <th class="text-right">Ítems</th>
                <th class="text-right">Costo</th>
                <th class="text-right">Venta</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr @class(['anulado' => $fila->estado === 'N'])>
                    <td>{{ $fila->codigo_retiro }}</td>
                    <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $fila->hora }}</td>
                    <td>{{ $fila->login_usuario }}</td>
                    <td>{{ $fila->nombre_usuario }}</td>
                    <td>{{ optional($fila->centrocosto)->nombre }}</td>
                    <td>{{ optional($fila->empresa)->nombre }}</td>
                    <td class="text-right">{{ (int) $fila->cantidad_items }}</td>
                    <td class="text-right">{{ number_format((float) $fila->total_costo, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila->total_venta, 2, ',', '.') }}</td>
                    <td>{{ $fila->etiquetaEstado() }}</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center;">Sin consumos para el filtro seleccionado.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-right">Totales</td>
                <td class="text-right">{{ (int) ($totales['items'] ?? 0) }}</td>
                <td class="text-right">{{ number_format((float) ($totales['costo'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($totales['venta'] ?? 0), 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    @if (count($resumen_centrocosto) > 0)
        <div class="subtitulo-seccion">Resumen por centro de costo</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Centro de costo</th>
                    <th class="text-right">Consumos</th>
                    <th class="text-right">Ítems</th>
                    <th class="text-right">Costo</th>
                    <th class="text-right">Venta</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resumen_centrocosto as $r)
                    <tr>
                        <td>{{ $r['centrocosto'] }}</td>
                        <td class="text-right">{{ (int) $r['consumos'] }}</td>
                        <td class="text-right">{{ (int) $r['items'] }}</td>
                        <td class="text-right">{{ number_format((float) $r['costo'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $r['venta'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-right">Totales</td>
                    <td class="text-right">{{ (int) ($totales['consumos'] ?? 0) }}</td>
                    <td class="text-right">{{ (int) ($totales['items'] ?? 0) }}</td>
                    <td class="text-right">{{ number_format((float) ($totales['costo'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($totales['venta'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>
