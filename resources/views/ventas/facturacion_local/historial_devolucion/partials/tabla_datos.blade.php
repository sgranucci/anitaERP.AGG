@php
    use App\Support\Stock\ArticuloConsultaDesdeModal;
    use App\Support\Ventas\FacturacionLocal\DevolucionHistorialOrigenSupport;
    $presentacion = $presentacion ?? 'pantalla';
    $filas = $datas ?? [];
    $enlazar = $presentacion === 'pantalla';
    $importeCelda = static function ($valor) use ($presentacion): string {
        $n = (float) $valor;
        if ($presentacion === 'excel') {
            return number_format($n, 2, '.', '');
        }

        return number_format($n, 2, ',', '.');
    };
@endphp
@if ($presentacion === 'pdf')
<thead>
    @include('includes.reportes.pdf_thead_cabecera', [
        'titulo' => 'Historial de devoluciones',
        'subtitulo' => $subtitulo ?? '',
        'colspan' => 12,
        'logosCabecera' => $logosCabecera ?? [],
        'totalFilas' => $totalFilas ?? (is_countable($filas) ? count($filas) : 0),
    ])
@else
<thead>
@endif
    <tr @class(['columnas' => $presentacion === 'pdf']) @if ($presentacion === 'pantalla') style="background:#85C1E9;color:#17202A;" @endif>
        <th>Fecha</th>
        <th>Origen</th>
        <th>Motivo</th>
        <th>Stock</th>
        <th>SKU</th>
        <th>Descripción</th>
        <th>Cant.</th>
        <th>Importe</th>
        <th>Local</th>
        <th>Comprobante</th>
        <th>Usuario</th>
        <th>Legajo</th>
    </tr>
</thead>
<tbody>
    @foreach ($filas as $fila)
        @php
            $fechaTxt = $fila->fecha ? \Illuminate\Support\Carbon::parse($fila->fecha)->format('d/m/Y') : '';
            $localTxt = trim((string) (($fila->local_codigo ?? '').' '.($fila->local_nombre ?? '')));
        @endphp
        <tr>
            <td>{{ $fechaTxt }}</td>
            <td>{{ DevolucionHistorialOrigenSupport::etiqueta((string) $fila->origen) }}</td>
            <td>{{ $fila->motivo_nombre }}</td>
            <td>{{ $fila->vuelve_stock ? 'Vuelve al stock' : 'No entra al stock' }}</td>
            <td>
                @if ($enlazar && $fila->articulo_id && ArticuloConsultaDesdeModal::puedeConsultar())
                    <a class="text-primary" target="_blank" rel="noopener" href="{{ ArticuloConsultaDesdeModal::urlEditar((int) $fila->articulo_id) }}">{{ $fila->sku }}</a>
                @else
                    {{ $fila->sku }}
                @endif
            </td>
            <td>{{ $fila->descripcion }}</td>
            <td style="text-align:right;">{{ $importeCelda($fila->cantidad) }}</td>
            <td style="text-align:right;">{{ $importeCelda($fila->importe) }}</td>
            <td>{{ $localTxt }}</td>
            <td>
                @if ($enlazar && $fila->venta_id && can('ver-factura-facturacion-local', false))
                    <a class="text-primary" target="_blank" rel="noopener" href="{{ route('facturacion_local_facturas_ver', ['ventaId' => $fila->venta_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila->venta_codigo }}</a>
                @else
                    {{ $fila->venta_codigo }}
                @endif
            </td>
            <td>{{ $fila->usuario_nombre }}</td>
            <td>
                @if ($enlazar && $fila->cambio_devolucion_id && can('ver-cambio-devolucion-marketplace-facturacion-local', false))
                    <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_cambio_devolucion_marketplace', ['id' => $fila->cambio_devolucion_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila->cambio_numero }}</a>
                @else
                    {{ $fila->cambio_numero }}
                @endif
            </td>
        </tr>
    @endforeach
    @if ($presentacion !== 'pantalla')
        <tr>
            <td>Total general</td>
            <td></td>
            <td>{{ (int) ($totales['cantidad'] ?? 0) }} devoluciones</td>
            <td>{{ (int) ($totales['sin_stock'] ?? 0) }} sin stock</td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align:right;">{{ $importeCelda($totales['importe'] ?? 0) }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    @endif
</tbody>
