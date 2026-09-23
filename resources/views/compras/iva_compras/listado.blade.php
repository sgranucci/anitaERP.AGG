@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Compras\IvaComprasListadoFiltros;
    $coleccionLogos = collect($filas ?? [])->map(fn ($f) => ['nombreempresa' => $f['nombreempresa'] ?? '']);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalFilas = (int) ($resultado['stats']['comprobantes'] ?? (is_countable($filas) ? count($filas) : 0));
    $tituloReporte = 'IVA COMPRAS';
    $subtitulo = 'Período: '.IvaComprasListadoFiltros::formatearPeriodoTexto($filtros)
        .' · Orden: '.IvaComprasListadoFiltros::formatearOrdenTexto($filtros)
        .' · '.IvaComprasListadoFiltros::formatearSubdiarioTexto($filtros);
    $columnas = $resultado['columnas'] ?? [];
    $cantMontos = max(1, count($columnas));
    $colSpan = 7 + count($columnas);
    // Anchos fijos (~40%) + resto repartido en montos (legal landscape).
    $anchoMonto = round(60 / $cantMontos, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.25; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 2px;
            vertical-align: top;
            font-size: 6.5px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 6.5px; font-weight: bold; color: #17202A; }
        table.data tr { page-break-inside: avoid; }
        /* Especificidad mayor que table.data td (si no, DomPDF deja los importes a la izquierda). */
        table.data th.text-right,
        table.data td.text-right {
            text-align: right;
            white-space: nowrap;
            word-wrap: normal;
            overflow-wrap: normal;
        }
        table.data th.col-nowrap,
        table.data td.col-nowrap {
            white-space: nowrap;
            word-wrap: normal;
            overflow-wrap: normal;
        }
        table.data td.col-proveedor {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        h3.seccion { font-size: 10px; margin: 8px 0 4px; }
    </style>
</head>
<body>
    <table class="data">
        <colgroup>
            <col style="width: 3%;">
            <col style="width: 11%;">
            <col style="width: 7%;">
            <col style="width: 4.5%;">
            <col style="width: 4.5%;">
            <col style="width: 2.5%;">
            <col style="width: 7.5%;">
            @foreach ($columnas as $col)
                <col style="width: {{ $anchoMonto }}%;">
            @endforeach
        </colgroup>
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $tituloReporte,
                'subtitulo' => $subtitulo,
                'colspan' => $colSpan,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr>
                <th class="col-nowrap">N.Pro.</th>
                <th>Proveedor</th>
                <th class="col-nowrap">CUIT</th>
                <th class="col-nowrap">Fec.Mov.</th>
                <th class="col-nowrap">Fec.Iva</th>
                <th class="col-nowrap">Tip</th>
                <th class="col-nowrap">Nro.Comp.</th>
                @foreach ($columnas as $col)
                    <th class="text-right">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td class="col-nowrap">{{ $fila['proveedor_codigo'] ?? '' }}</td>
                    <td class="col-proveedor">{{ $fila['proveedor_nombre'] ?? '' }}</td>
                    <td class="col-nowrap">{{ $fila['cuit'] ?? '' }}</td>
                    <td class="col-nowrap">{{ $fila['fecha_mov'] ?? '' }}</td>
                    <td class="col-nowrap">{{ $fila['fecha_iva'] ?? '' }}</td>
                    <td class="col-nowrap">{{ $fila['tipo'] ?? '' }}</td>
                    <td class="col-nowrap">{{ $fila['comprobante'] ?? '' }}</td>
                    @foreach ($columnas as $col)
                        <td class="text-right">{{ number_format((float) ($fila['columnas'][$col['key']] ?? 0), 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            @endforeach
            @if (! empty($resultado['totales_general']))
                <tr style="font-weight: bold; background-color: #D6EAF8;">
                    <td colspan="7">TOTAL GENERAL</td>
                    @foreach ($columnas as $col)
                        <td class="text-right">{{ number_format((float) ($resultado['totales_general'][$col['key']] ?? 0), 2, ',', '.') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if (! empty($resultado['conciliacion_contable']['habilitada']))
        @php $conc = $resultado['conciliacion_contable']; $res = $conc['resumen_empresa'] ?? []; @endphp
        <h3 class="seccion">Conciliación contable</h3>
        <table class="data" style="margin-top: 6px; table-layout: auto;">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right">IVA compras</th>
                    <th class="text-right">Mayor</th>
                    <th class="text-right">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($res['lineas'] ?? [] as $linea)
                    <tr>
                        <td>{{ $linea['concepto'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($linea['erp'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($linea['contable'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($linea['diferencia'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
