@php
    $art = $contexto['articulo'] ?? [];
    $sufijoUm = \App\Support\Stock\MovimientosArticuloDepositoSupport::sufijoColumnaCantidad($art['unidad_medida'] ?? '');
    $titulo = 'Recepciones de proveedor — '.($art['sku'] ?? '');
    if (! empty($art['descripcion'])) {
        $titulo .= ' — '.$art['descripcion'];
    }
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
    <tr>
        <td colspan="12" style="height:52px;"></td>
    </tr>
    @endif
    <tr>
        <td colspan="12"><strong style="font-size:16px;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="12">Generado {{ date('d/m/Y H:i') }} · {{ $filas->count() }} l&iacute;nea(s)</td>
    </tr>
    <thead>
        <tr>
            <th>N&ordm; recep.</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>OC</th>
            <th>SKU l&iacute;nea</th>
            <th>Cant.{!! $sufijoUm !!}</th>
            <th>Cant. stock</th>
            <th>Precio</th>
            <th>Proveedor</th>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Diff.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila->numerorecepcion }}</td>
            <td>{{ $fila->fecha_fmt ?? '' }}</td>
            <td>{{ $fila->tipo ?? '' }}</td>
            <td>{{ $fila->numeroordencompra ?? '' }}</td>
            <td>{{ $fila->sku_linea ?? '' }}</td>
            <td>{{ $fila->cantidad_fmt ?? '' }}</td>
            <td>{{ $fila->cantidad_stock_fmt ?? '' }}</td>
            <td>{{ $fila->precio_fmt ?? '' }}</td>
            <td>{{ $fila->nombreproveedor ?? '' }}</td>
            <td>{{ $fila->nombreempresa ?? '' }}</td>
            <td>{{ $fila->estado_recepcion ?? '' }}</td>
            <td>
                @if($fila->tiene_diff ?? false) Sí @else — @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
