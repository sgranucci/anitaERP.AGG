@php
    use App\Support\Export\ExcelFormatoNumero;
    use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;

    $colspan = 7;
    $bloques = $bloques ?? [];
    $totalBloques = count($bloques);
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $fmtMonto = ExcelFormatoNumero::formateadorMonto($formatoNumero, 2);
    $fmtUnidades = ExcelFormatoNumero::formateadorMonto($formatoNumero, 2);
    $resumenTotales = null;
    if ($totalBloques > 0) {
        $resumenTotales = [
            'unidades' => $resultado['gran_total_unidades'] ?? 0,
            'costo_total' => $resultado['gran_total_costo'] ?? 0,
            'total_venta' => $resultado['gran_total_venta'] ?? 0,
        ];
    }
@endphp
<table>
    @include('exports.ventas.partials.gastronomia_descuento_reporte_encabezado_meta', [
        'colspan' => $colspan,
        'titulo' => $titulo ?? 'Reporte descuentos gastronomía',
        'subtitulo' => $subtitulo ?? '',
        'reservarFilaLogoExcel' => $reservarFilaLogoExcel ?? false,
        'resumen_totales' => $resumenTotales,
        'total_bloques' => $totalBloques,
        'formatoNumero' => $formatoNumero,
    ])
    @if ($totalBloques > 0)
        <tr>
            <th>Artículo</th>
            <th>Descripción</th>
            <th>Unidades</th>
            <th>Costo unit.</th>
            <th>Costo total</th>
            <th>Precio vta.</th>
            <th>Total venta</th>
        </tr>
    @endif
    @foreach ($bloques as $bloque)
        @php
            $grupos = $bloque['grupos'] ?? null;
            if ($grupos === null) {
                $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($bloque['filas'] ?? []);
                $grupos = $agrupado['grupos'];
            }
        @endphp
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 11pt; font-weight: bold; color: #17202A;">
                {{ $bloque['codigo'] ?? '' }} &mdash; {{ $bloque['nombre'] ?? '' }}
                &middot; {{ $resultado['periodo_texto'] ?? '' }}
            </td>
        </tr>
        @foreach ($grupos as $grupo)
            <tr>
                <td colspan="{{ $colspan }}" style="font-weight: bold; background-color: #D5E8F5;">
                    Tipo: {{ $grupo['tipo_nombre'] }}
                    ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})
                </td>
            </tr>
            @foreach ($grupo['filas'] as $fila)
                <tr>
                    <td>{{ $fila['sku'] ?? '' }}</td>
                    <td>{{ $fila['descripcion'] ?? '' }}</td>
                    <td>{{ $fmtUnidades($fila['unidades'] ?? 0) }}</td>
                    <td>{{ $fmtMonto($fila['costo_unitario'] ?? 0) }}</td>
                    <td>{{ $fmtMonto($fila['costo_total'] ?? 0) }}</td>
                    <td>{{ $fmtMonto($fila['precio_venta'] ?? 0) }}</td>
                    <td>{{ $fmtMonto($fila['total_venta'] ?? 0) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2"><strong>Total parcial {{ $grupo['tipo_nombre'] }}</strong></td>
                <td><strong>{{ $fmtUnidades($grupo['subtotal_unidades'] ?? 0) }}</strong></td>
                <td></td>
                <td><strong>{{ $fmtMonto($grupo['subtotal_costo_total'] ?? 0) }}</strong></td>
                <td></td>
                <td><strong>{{ $fmtMonto($grupo['subtotal_total_venta'] ?? 0) }}</strong></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2"><strong>Total final</strong></td>
            <td><strong>{{ $fmtUnidades($bloque['totales']['unidades'] ?? 0) }}</strong></td>
            <td></td>
            <td><strong>{{ $fmtMonto($bloque['totales']['costo_total'] ?? 0) }}</strong></td>
            <td></td>
            <td><strong>{{ $fmtMonto($bloque['totales']['total_venta'] ?? 0) }}</strong></td>
        </tr>
        <tr><td colspan="{{ $colspan }}"></td></tr>
    @endforeach
</table>
