@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $fechaTxt = ! empty($fecha) ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '';
    $formatear = function ($v) {
        return number_format((float) $v, 2, ',', '.');
    };
    $formatearUn = function ($v) {
        return number_format((float) $v, 0, ',', '.');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Movimientos de stock {{ $fechaTxt }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.3; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th {
            border: 1px solid #cccccc; text-align: left; padding: 3px 4px; vertical-align: top; word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 8px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        h3 { font-size: 11px; margin: 12px 0 6px; }
        .total { font-weight: bold; background:#eaf2f8; }
        .corte { background:#D6EAF8; font-weight:bold; }
    </style>
</head>
<body>
    <h3>Movimientos de stock del {{ $fechaTxt }} (grupos G1–G6)</h3>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => 'Movimientos de stock del '.$fechaTxt,
                'subtitulo' => 'Reemplazo l-stkmovint · Villafranca + Bierzo · G1–G6',
                'colspan' => 5,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => is_countable($filas ?? []) ? count($filas) : 0,
            ])
            <tr class="columnas">
                <th style="width:10%;">Grupo</th>
                <th style="width:14%;">Artículo</th>
                <th style="width:46%;">Descripción</th>
                <th class="text-right" style="width:15%;">Saldo</th>
                <th class="text-right" style="width:15%;">T.Unidades</th>
            </tr>
        </thead>
        <tbody>
            @php $agrAnt = null; @endphp
            @forelse ($filas as $fila)
                @if ($agrAnt !== ($fila['agrupacion'] ?? ''))
                    @php $agrAnt = $fila['agrupacion'] ?? ''; @endphp
                    <tr class="corte">
                        <td colspan="5">Grupo {{ $agrAnt !== '' ? $agrAnt : '(sin grupo)' }}</td>
                    </tr>
                @endif
                <tr>
                    <td>{{ $fila['agrupacion'] ?? '' }}</td>
                    <td>{{ $fila['sku'] ?? '' }}</td>
                    <td>{{ $fila['descripcion'] ?? '' }}</td>
                    <td class="text-right">{{ $formatear($fila['saldo'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatearUn($fila['unidades'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin movimientos G1–G6 en la fecha.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="3">Total día</td>
                <td class="text-right">{{ $formatear($totales['saldo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatearUn($totales['unidades'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
