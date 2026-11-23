@php
    use App\Support\Compras\OrdencompraListadoColumnas;
    use App\Support\Configuracion\EntornoEmpresaSupport;

    $row = $row ?? $data ?? null;
@endphp
@switch ($key)
    @case ('numeroordencompra')
        @php
            $urlConsultaFila = route('solo_consulta_ordencompra', ['id' => $row->id]);
        @endphp
        <td class="oc-col-numero">
            <a href="{{ $urlConsultaFila }}"
               class="oc-numero js-erp-workspace"
               title="Ver orden de compra"
               data-ws-modo="edit"
               data-ws-id="{{ $row->id }}"
               data-ws-titulo="Consulta OC {{ $row->numeroordencompra }}"
               data-ws-meta="{{ $row->nombreproveedor }}"
               data-ws-edit="{{ $urlConsultaFila }}">
                {{ $row->numeroordencompra }}
            </a>
            @if (!empty($row->requisicion_id))
                <span class="oc-meta">Req. {{ $row->requisicion_id }}</span>
            @endif
        </td>
        @break

    @case ('nombreempresa')
        <td>
            @if (EntornoEmpresaSupport::esCrown())
                {{ $row->codigosala ? ($row->codigosala.' — '.($row->nombresala ?? '')) : '—' }}
            @else
                {{ $row->nombreempresa }}
            @endif
        </td>
        @break

    @case ('estadoordencompra')
        <td>
            @include('compras.ordencompra.partials.estado_badge', ['estado' => $row->estadoordencompra ?? ''])
        </td>
        @break

    @case ('recepcion_numero')
        <td>
            @include('includes.listado.celda_enlace', [
                'filaEnlace' => $row,
                'metaEnlace' => ['enlace' => 'recepcion_proveedor', 'enlace_ids' => 'recepcion_ids'],
                'textoEnlace' => $row->recepcion_numero ?? '',
            ])
        </td>
        @break

    @case ('factura_numero')
        <td>
            @include('includes.listado.celda_enlace', [
                'filaEnlace' => $row,
                'metaEnlace' => ['enlace' => 'comprobante_proveedor', 'enlace_ids' => 'factura_ids'],
                'textoEnlace' => $row->factura_numero ?? '',
            ])
        </td>
        @break

    @case ('pago_numero')
        <td>
            @include('includes.listado.celda_enlace', [
                'filaEnlace' => $row,
                'metaEnlace' => ['enlace' => 'pagoproveedor', 'enlace_ids' => 'pago_ids'],
                'textoEnlace' => $row->pago_numero ?? '',
            ])
        </td>
        @break

    @case ('monto_lineas')
        <td class="oc-num text-right text-nowrap">
            {{ OrdencompraListadoColumnas::valorCelda($row, $key) }}
        </td>
        @break

    @case ('fecha')
    @case ('fechaentrega')
    @case ('fecha_alta')
    @case ('fecha_modificacion')
        <td class="text-nowrap oc-col-fecha">{{ OrdencompraListadoColumnas::valorCelda($row, $key) }}</td>
        @break

    @default
        <td>
            @include('includes.listado.celda_enlace', [
                'filaEnlace' => $row,
                'metaEnlace' => OrdencompraListadoColumnas::catalogoActivo()[$key] ?? [],
                'textoEnlace' => OrdencompraListadoColumnas::valorCelda($row, $key),
            ])
        </td>
@endswitch
