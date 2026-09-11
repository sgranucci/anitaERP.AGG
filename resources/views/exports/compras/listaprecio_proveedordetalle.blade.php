@php
    $lineas = $lista->listaprecio_proveedor_articulos ?? collect();
    $letterhead = $letterhead ?? ['nombre' => '', 'cuit' => '', 'domicilio' => ''];
    $proveedor = $lista->proveedores;
    $codigoProv = trim((string) ($proveedor->codigo ?? ''));
    $nombreProv = trim((string) ($proveedor->nombre ?? ''));
    $proveedorTxt = trim($codigoProv.($codigoProv !== '' && $nombreProv !== '' ? ' — ' : '').$nombreProv);
    $moneda = trim((string) (optional($lista->monedas)->abreviatura ?: optional($lista->monedas)->nombre ?: ''));
    $fecha = $lista->fecha ? date('d/m/Y', strtotime((string) $lista->fecha)) : '';
    $lineaEmpresa = implode(' · ', array_filter([
        $letterhead['nombre'] ?? '',
        ! empty($letterhead['cuit']) ? 'CUIT '.$letterhead['cuit'] : '',
        $letterhead['domicilio'] ?? '',
        'Impreso '.date('d/m/Y H:i'),
    ]));
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="6" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Lista de precios de proveedor</h2></td>
        </tr>
        <tr>
            <td colspan="6">{{ $lineaEmpresa }}</td>
        </tr>
        <tr>
            <td>Proveedor</td>
            <td>{{ $proveedorTxt !== '' ? $proveedorTxt : '—' }}</td>
            <td>Fecha</td>
            <td>{{ $fecha !== '' ? $fecha : '—' }}</td>
            <td>Moneda</td>
            <td>{{ $moneda !== '' ? $moneda : '—' }}</td>
        </tr>
        <tr>
            <td>Lista</td>
            <td>{{ $lista->nombre ?: ('ID '.$lista->id) }}</td>
            <td>Estado</td>
            <td>{{ $lista->estado ?: '—' }}</td>
            <td>&Iacute;tems</td>
            <td>{{ $lineas->count() }}</td>
        </tr>
        <tr>
            <td>Cond. pago</td>
            <td>{{ optional($lista->condicionpagos)->nombre ?: '—' }}</td>
            <td>Cond. entrega</td>
            <td>{{ optional($lista->condicionentregas)->nombre ?: '—' }}</td>
            <td>Cond. compra</td>
            <td>{{ optional($lista->condicioncompras)->nombre ?: '—' }}</td>
        </tr>
        @if (trim((string) ($lista->observaciones ?? '')) !== '')
            <tr>
                <td>Observaciones</td>
                <td colspan="5">{{ $lista->observaciones }}</td>
            </tr>
        @endif
    </tbody>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripci&oacute;n</th>
            <th>Precio</th>
            <th>% Desc.</th>
            <th>C&oacute;d. art. proveedor</th>
            <th>Fecha vigencia</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineas as $linea)
            <tr>
                <td>{{ optional($linea->articulos)->sku ?? '' }}</td>
                <td>{{ optional($linea->articulos)->descripcion ?? '' }}</td>
                <td>{{ $linea->precio }}</td>
                <td>{{ $linea->descuento }}</td>
                <td>{{ $linea->codigo_articulo_proveedor ?? '' }}</td>
                <td>{{ $linea->fechavigencia ? date('d/m/Y', strtotime($linea->fechavigencia)) : '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">Sin renglones de precio.</td>
            </tr>
        @endforelse
    </tbody>
</table>
