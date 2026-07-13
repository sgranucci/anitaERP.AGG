@php
    $filas = $filas ?? ($resultado['filas'] ?? []);
    $tot = $resultado['totales'] ?? [];
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="24" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="24"><strong style="font-size: 16px;">{{ $titulo ?? 'Reporte analítico gastronomía' }}</strong></td>
    </tr>
    <tr>
        <td colspan="24">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr><td colspan="24">{{ $subtitulo }}</td></tr>
    @endif
    <tr>
        <th>Id</th>
        <th>Fecha jornada</th>
        <th>Fecha real</th>
        <th>Sala</th>
        <th>Tipo comprobante</th>
        <th>Punto venta</th>
        <th>Nº comprobante</th>
        <th>Mozo Id</th>
        <th>Nombre mozo</th>
        <th>Legajo mozo</th>
        <th>Código artículo</th>
        <th>Descripción artículo</th>
        <th>Tipo venta</th>
        <th>Cantidad</th>
        <th>Precio unitario</th>
        <th>Total</th>
        <th>Costo</th>
        <th>Tipo descuento</th>
        <th>Categoría artículo</th>
        <th>Cliente</th>
        <th>Año</th>
        <th>Hora</th>
        <th>Mes</th>
        <th>Día</th>
    </tr>
    @foreach ($filas as $f)
        <tr>
            <td>{{ $f->id ?? '' }}</td>
            <td>{{ $f->fecha_jornada_fmt ?? '' }}</td>
            <td>{{ $f->fecha_real_fmt ?? '' }}</td>
            <td>{{ $f->sala ?? '' }}</td>
            <td>{{ $f->tipo_comprobante ?? '' }}</td>
            <td>{{ $f->punto_venta ?? '' }}</td>
            <td>{{ $f->numero_comprobante ?? '' }}</td>
            <td>{{ $f->mozo_id ?? '' }}</td>
            <td>{{ $f->nombre_mozo ?? '' }}</td>
            <td>{{ $f->legajo_mozo ?? '' }}</td>
            <td>{{ $f->codigo_articulo ?? '' }}</td>
            <td>{{ $f->descripcion_articulo ?? '' }}</td>
            <td>{{ $f->tipo_venta ?? '' }}</td>
            <td>{{ number_format((float) ($f->cantidad ?? 0), 4, '.', '') }}</td>
            <td>{{ number_format((float) ($f->precio_unitario ?? 0), 2, '.', '') }}</td>
            <td>{{ number_format((float) ($f->total ?? 0), 2, '.', '') }}</td>
            <td>{{ number_format((float) ($f->costo ?? 0), 2, '.', '') }}</td>
            <td>{{ $f->tipo_descuento ?? '' }}</td>
            <td>{{ $f->categoria_articulo ?? '' }}</td>
            <td>{{ $f->cliente ?? '' }}</td>
            <td>{{ $f->anio ?? '' }}</td>
            <td>{{ $f->hora ?? '' }}</td>
            <td>{{ $f->mes ?? '' }}</td>
            <td>{{ $f->dia ?? '' }}</td>
        </tr>
    @endforeach
    @if (! empty($tot))
        <tr>
            <td colspan="13"><strong>Totales ({{ (int) ($tot['cantidad_filas'] ?? 0) }} filas)</strong></td>
            <td>{{ number_format((float) ($tot['cantidad_total'] ?? 0), 4, '.', '') }}</td>
            <td></td>
            <td>{{ number_format((float) ($tot['total_importe'] ?? 0), 2, '.', '') }}</td>
            <td>{{ number_format((float) ($tot['costo_total'] ?? 0), 2, '.', '') }}</td>
            <td colspan="7"></td>
        </tr>
    @endif
</table>
