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
    <table class="data" style="table-layout: auto; width: 100%;">
        <colgroup>
            <col style="width:6%;">
            <col style="width:8%;">
            <col style="width:62%;">
            <col style="width:12%;">
            <col style="width:12%;">
        </colgroup>
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => 'Movimientos de stock del '.$fechaTxt,
                'subtitulo' => 'Reemplazo l-stkmovint · Villafranca + Bierzo · G1–G6',
                'colspan' => 5,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => is_countable($filas ?? []) ? count($filas) : 0,
            ])
            <tr class="columnas">
                <th style="width:6%;">Grupo</th>
                <th style="width:8%;">Artículo</th>
                <th style="width:62%;">Descripción</th>
                <th class="text-right" style="width:12%;">Saldo</th>
                <th class="text-right" style="width:12%;">T.Unidades</th>
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

    @php
        $filasPct = $porcentajes['filas'] ?? [];
        $totPct = $porcentajes['totales'] ?? [];
    @endphp
    @if ($filasPct !== [])
        <div style="page-break-before: always;">
        <h3>Porcentajes por agrupación</h3>
        <table class="data" style="width:70%;">
            <thead>
                <tr class="columnas">
                    <th>Agrupación</th>
                    <th class="text-right">% Kg. venta</th>
                    <th class="text-right">% $ venta</th>
                    <th class="text-right">% Bonific.</th>
                    <th class="text-right">% Bonif. ABS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filasPct as $filaPct)
                    <tr>
                        <td>{{ $filaPct['etiqueta'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($filaPct['pct_kg'] ?? 0), 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($filaPct['pct_pesos'] ?? 0), 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($filaPct['pct_bonif'] ?? 0), 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($filaPct['pct_bonif_abs'] ?? 0), 1, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total final</td>
                    <td class="text-right">{{ number_format((float) ($totPct['pct_kg'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($totPct['pct_pesos'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($totPct['pct_bonif'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($totPct['pct_bonif_abs'] ?? 0), 1, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        @if (! empty($graficoDataUri))
            <p style="margin-top:10px;">
                <img src="{{ $graficoDataUri }}" alt="Porcentajes por agrupación" style="width:680px;">
            </p>
        @endif
        </div>
    @endif
</body>
</html>
