@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $logoDefault = EmpresaLogoArchivo::dataUriDesdeNombre(config('app.empresa'));
    $totalFilas = is_countable($filas) ? count($filas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planificación de indumentaria</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 4px; vertical-align: top; word-wrap: break-word; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background-color: #eaf2f8; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @if (! empty($logoDefault['uri']))
                    <img src="{{ $logoDefault['uri'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; vertical-align: middle;">
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Planificación de indumentaria</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }} &middot; compra sugerida</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0) Prendas: {{ $totalFilas }} @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 7%;">Código</th>
                <th style="width: 27%;">Prenda</th>
                <th style="width: 6%;" class="text-center">EPP</th>
                <th style="width: 9%;" class="text-right">Empleados</th>
                <th style="width: 9%;" class="text-right">Cupo</th>
                <th style="width: 9%;" class="text-right">Entregado</th>
                <th style="width: 9%;" class="text-right">Pendiente</th>
                <th style="width: 8%;" class="text-right">Stock</th>
                <th style="width: 7%;" class="text-right">% Ped.</th>
                <th style="width: 9%;" class="text-right">Sugerido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td>{{ $f['codigo'] }}</td>
                    <td>{{ $f['descripcion'] }}@if(($f['modo'] ?? 'anual') === 'vencimiento') <span style="color:#888">(vida útil {{ $f['vida_util_meses'] }}m)</span>@endif</td>
                    <td class="text-center">{{ ! empty($f['es_seguridad']) ? 'Sí' : '' }}</td>
                    <td class="text-right">{{ $f['empleados'] }}</td>
                    <td class="text-right">{{ $fmt($f['cupo']) }}</td>
                    <td class="text-right">{{ $fmt($f['entregado']) }}</td>
                    <td class="text-right">{{ $fmt($f['pendiente']) }}</td>
                    <td class="text-right">{{ $fmt($f['stock']) }}</td>
                    <td class="text-right">{{ $fmt($f['porcentaje_pedido']) }}</td>
                    <td class="text-right">{{ $f['sugerido'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">Totales ({{ $totales['prendas'] ?? $totalFilas }} prendas)</td>
                <td class="text-right">{{ $fmt($totales['cupo'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['entregado'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['pendiente'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['stock'] ?? 0) }}</td>
                <td></td>
                <td class="text-right">{{ $totales['sugerido'] ?? 0 }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
