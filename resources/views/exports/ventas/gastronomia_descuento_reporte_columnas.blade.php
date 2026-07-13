@php
    use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;

    $vista = $resultado['vista_columnas'] ?? [];
    $columnas = $vista['columnas'] ?? [];
    $filas = $vista['filas'] ?? [];
    $grupos = $vista['grupos'] ?? null;
    if ($grupos === null && $filas !== []) {
        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($filas);
        $grupos = $agrupado['grupos'];
    }
    $grupos = $grupos ?? [];
    $totalesPorColumna = $vista['totales_por_columna'] ?? [];
    $subCols = 3;
    $colsFijas = 4;
    $colCount = max($colsFijas + count($columnas) * $subCols, 7);
    $resumenTotales = [
        'unidades' => $resultado['gran_total_unidades'] ?? 0,
        'costo_total' => $resultado['gran_total_costo'] ?? 0,
        'total_venta' => $resultado['gran_total_venta'] ?? 0,
    ];
@endphp
<table>
    @include('exports.ventas.partials.gastronomia_descuento_reporte_encabezado_meta', [
        'colspan' => $colCount,
        'titulo' => $titulo ?? 'Reporte descuentos gastronomía',
        'subtitulo' => $subtitulo ?? '',
        'reservarFilaLogoExcel' => $reservarFilaLogoExcel ?? false,
        'resumen_totales' => $resumenTotales,
        'total_bloques' => count($columnas),
    ])
    <tr>
        <th rowspan="2">Artículo</th>
        <th rowspan="2">Descripción</th>
        <th rowspan="2">Costo unit.</th>
        <th rowspan="2">Precio vta.</th>
        @foreach ($columnas as $col)
            <th colspan="{{ $subCols }}" style="text-align: center;">{{ $col['titulo'] ?? '' }}</th>
        @endforeach
    </tr>
    <tr>
        @foreach ($columnas as $col)
            <th>Unidades</th>
            <th>Costo total</th>
            <th>Total venta</th>
        @endforeach
    </tr>
    @foreach ($grupos as $grupo)
        <tr>
            <td colspan="{{ $colCount }}" style="font-weight: bold; background-color: #D5E8F5;">
                Tipo: {{ $grupo['tipo_nombre'] }}
                ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})
            </td>
        </tr>
        @foreach ($grupo['filas'] as $fila)
            <tr>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['costo_unitario'] ?? 0 }}</td>
                <td>{{ $fila['precio_venta'] ?? 0 }}</td>
                @foreach ($columnas as $col)
                    @php $celda = ($fila['celdas'] ?? [])[$col['clave'] ?? ''] ?? null; @endphp
                    @if ($celda)
                        <td>{{ $celda['unidades'] ?? 0 }}</td>
                        <td>{{ $celda['costo_total'] ?? 0 }}</td>
                        <td>{{ $celda['total_venta'] ?? 0 }}</td>
                    @else
                        <td colspan="{{ $subCols }}"></td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        <tr>
            <td colspan="{{ $colsFijas }}"><strong>Total parcial {{ $grupo['tipo_nombre'] }}</strong></td>
            @foreach ($columnas as $col)
                @php
                    $sumaUnid = 0.0;
                    $sumaCosto = 0.0;
                    $sumaVenta = 0.0;
                    foreach ($grupo['filas'] as $filaGrupo) {
                        $celda = ($filaGrupo['celdas'] ?? [])[$col['clave'] ?? ''] ?? null;
                        if (! $celda) {
                            continue;
                        }
                        $sumaUnid += (float) ($celda['unidades'] ?? 0);
                        $sumaCosto += (float) ($celda['costo_total'] ?? 0);
                        $sumaVenta += (float) ($celda['total_venta'] ?? 0);
                    }
                @endphp
                <td><strong>{{ $sumaUnid }}</strong></td>
                <td><strong>{{ $sumaCosto }}</strong></td>
                <td><strong>{{ $sumaVenta }}</strong></td>
            @endforeach
        </tr>
    @endforeach
    @if ($totalesPorColumna !== [])
        <tr>
            <td colspan="{{ $colsFijas }}"><strong>Total final</strong></td>
            @foreach ($totalesPorColumna as $totCol)
                @php $tot = $totCol['totales'] ?? []; @endphp
                <td><strong>{{ $tot['unidades'] ?? 0 }}</strong></td>
                <td><strong>{{ $tot['costo_total'] ?? 0 }}</strong></td>
                <td><strong>{{ $tot['total_venta'] ?? 0 }}</strong></td>
            @endforeach
        </tr>
    @endif
</table>
