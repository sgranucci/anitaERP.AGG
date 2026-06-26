@php
    $colspan = 7;
    $bloques = $bloques ?? [];
    $totalBloques = count($bloques);
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
    ])
    @foreach ($bloques as $bloque)
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 11pt; font-weight: bold; color: #17202A;">
                {{ $bloque['codigo'] ?? '' }} &mdash; {{ $bloque['nombre'] ?? '' }}
                &middot; {{ $resultado['periodo_texto'] ?? '' }}
            </td>
        </tr>
        <tr>
            <th>Artículo</th>
            <th>Descripción</th>
            <th>Unidades</th>
            <th>Costo unit.</th>
            <th>Costo total</th>
            <th>Precio vta.</th>
            <th>Total venta</th>
        </tr>
        @foreach ($bloque['filas'] ?? [] as $fila)
            <tr>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['unidades'] ?? 0 }}</td>
                <td>{{ $fila['costo_unitario'] ?? 0 }}</td>
                <td>{{ $fila['costo_total'] ?? 0 }}</td>
                <td>{{ $fila['precio_venta'] ?? 0 }}</td>
                <td>{{ $fila['total_venta'] ?? 0 }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2"><strong>Total final</strong></td>
            <td><strong>{{ $bloque['totales']['unidades'] ?? 0 }}</strong></td>
            <td></td>
            <td><strong>{{ $bloque['totales']['costo_total'] ?? 0 }}</strong></td>
            <td></td>
            <td><strong>{{ $bloque['totales']['total_venta'] ?? 0 }}</strong></td>
        </tr>
        <tr><td colspan="{{ $colspan }}"></td></tr>
    @endforeach
</table>
