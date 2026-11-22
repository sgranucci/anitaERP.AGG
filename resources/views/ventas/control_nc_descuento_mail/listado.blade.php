@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $fechaTxt = ! empty($fecha) ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '';
    $formatear = function ($v) {
        return number_format((float) $v, 2, ',', '.');
    };
    $formatearPorc = function ($v) {
        return number_format((float) $v, 2, ',', '.').'%';
    };
    $etiquetaComp = function ($f) {
        return sprintf(
            '%s %s-%04d-%08d',
            $f['tipo'] ?? 'NCP',
            $f['letra'] ?? ' ',
            (int) ($f['sucursal'] ?? 0),
            (int) ($f['numero'] ?? 0)
        );
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Control NC descuento {{ $fechaTxt }}</title>
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
    </style>
</head>
<body>
    @foreach ([
        ['titulo' => 'Notas de credito de descuento mayores al 4% del dia: '.$fechaTxt.' de lista 1 y 8', 'filas' => $seccionLista ?? [], 'monto' => $totales['monto_lista'] ?? 0],
        ['titulo' => 'Notas de credito de descuento mayores al 8% del dia: '.$fechaTxt, 'filas' => $seccionGeneral ?? [], 'monto' => $totales['monto_general'] ?? 0],
    ] as $bloque)
        <h3>{{ $bloque['titulo'] }}</h3>
        <table class="data">
            <thead>
                @include('includes.reportes.pdf_thead_cabecera', [
                    'titulo' => $bloque['titulo'],
                    'subtitulo' => 'Reemplazo p-controlnc',
                    'colspan' => 10,
                    'logosCabecera' => $logosCabecera,
                    'totalFilas' => is_countable($bloque['filas']) ? count($bloque['filas']) : 0,
                ])
                <tr class="columnas">
                    <th style="width:12%;">Comprobante</th>
                    <th style="width:7%;">N.Cli.</th>
                    <th style="width:18%;">Cliente</th>
                    <th class="text-right" style="width:9%;">Monto</th>
                    <th style="width:6%;">Rec.</th>
                    <th style="width:7%;">N.rec</th>
                    <th class="text-right" style="width:7%;">Porc.</th>
                    <th style="width:6%;">Lista</th>
                    <th style="width:6%;">Vend.</th>
                    <th style="width:22%;">Vendedor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bloque['filas'] as $fila)
                    <tr>
                        <td>{{ $etiquetaComp($fila) }}</td>
                        <td>{{ $fila['cliente_codigo'] ?? '' }}</td>
                        <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
                        <td class="text-right">{{ $formatear($fila['monto'] ?? 0) }}</td>
                        <td>{{ $fila['tipo_recibo'] ?? '' }}</td>
                        <td>{{ ! empty($fila['nro_recibo']) ? sprintf('%08d', $fila['nro_recibo']) : '' }}</td>
                        <td class="text-right">{{ $formatearPorc($fila['porcentaje'] ?? 0) }}</td>
                        <td>{{ $fila['lista_precio'] ?? '' }}</td>
                        <td>{{ $fila['vendedor_codigo'] ?? '' }}</td>
                        <td>{{ $fila['vendedor_nombre'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">Sin movimientos en esta sección.</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="3">Total día</td>
                    <td class="text-right">{{ $formatear($bloque['monto']) }}</td>
                    <td colspan="6"></td>
                </tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>
