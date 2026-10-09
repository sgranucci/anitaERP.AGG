@php
    use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
    use App\Support\Compras\ComprobanteProveedorArchivoTipos;
    use App\Support\Compras\ComprobanteProveedorEstados;
    use App\Support\Compras\ComprobanteProveedorListadoColumnas;
    use App\Support\Compras\ComprobanteProveedorModoCarga;
    use App\Support\Compras\ComprobanteProveedorOrigenEntrada;

    $puedeVerComprobante = $puedeVerComprobante ?? (
        can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false)
    );
    $retorno = $retornoListadoQuery ?? [];
@endphp
@switch ($key)
    @case ('id')
        <td class="text-nowrap">
            @if ($puedeVerComprobante)
                <a href="{{ route('editar_comprobante_proveedor', ['id' => $data->id] + $retorno) }}"
                   class="text-primary" title="Ver comprobante">{{ $data->id }}</a>
            @else
                {{ $data->id }}
            @endif
        </td>
        @break
    @case ('numero')
        @php
            $tienePdfFactura = ($data->comprobante_proveedor_archivos ?? collect())->contains(
                fn ($a) => in_array($a->tipo ?? '', [
                    ComprobanteProveedorArchivoTipos::ORIGEN_IA,
                    ComprobanteProveedorArchivoTipos::FACTURA,
                ], true)
            );
        @endphp
        <td class="text-nowrap cp-col-numero">
            {{ ComprobanteProveedorListadoColumnas::numeroVisible($data) }}
            @if ($puedeVerComprobante && $tienePdfFactura)
                <a href="{{ route('comprobante_proveedor_factura_pdf', ['id' => $data->id, 'inline' => 1]) }}"
                   class="btn-accion-tabla text-danger ml-1" target="_blank" rel="noopener"
                   title="Ver PDF de la factura">
                    <i class="fa fa-file-pdf-o"></i>
                </a>
            @endif
        </td>
        @break
    @case ('oc')
        @php
            $numeroOc = $data->numero_oc ?? ($data->ordencompras->numeroordencompra ?? null);
            $ordencompraId = (int) ($data->ordencompra_id ?? 0);
        @endphp
        <td>
            @if ($numeroOc !== null && $numeroOc !== '')
                @if ($ordencompraId > 0 && can('editar-ordencompra', false))
                    <a href="{{ route('editar_ordencompra', ['id' => $ordencompraId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       class="text-primary" target="_blank" rel="noopener" title="Consultar orden de compra">
                        {{ $numeroOc }}
                    </a>
                @else
                    {{ $numeroOc }}
                @endif
            @endif
        </td>
        @break
    @case ('fuera_pago')
        <td class="text-center">
            @if ((int) ($data->bloqueado_pago ?? 0) === 1)
                <i class="fa fa-check text-muted" title="{{ $data->bloqueado_pago_motivo ?: 'Fuera del circuito de pago y de la proyección' }}"></i>
            @endif
        </td>
        @break
    @case ('estado')
        @php
            $errorAnita = ($data->anita_sync_estado ?? '') === ComprobanteProveedorAnitaSyncEstado::ERROR;
            $badgeEstado = ComprobanteProveedorEstados::badge($data->estado ?? '');
            $badgeError = $errorAnita ? ComprobanteProveedorEstados::badge(null, true) : null;
        @endphp
        <td>
            <span class="{{ $badgeEstado['class'] }}">{{ $badgeEstado['label'] }}</span>
            @if ((int) ($data->bloqueado_pago ?? 0) === 1)
                <span class="badge badge-light border ml-1" title="{{ $data->bloqueado_pago_motivo ?: 'Fuera del circuito de pago y de la proyección' }}">Fuera de pago</span>
            @endif
            @if ($badgeError)
                <span class="{{ $badgeError['class'] }}" title="{{ $data->anita_sync_error }}">{{ $badgeError['label'] }}</span>
            @endif
        </td>
        @break
    @case ('origen')
        <td><span class="cp-celda">{{ ComprobanteProveedorOrigenEntrada::etiqueta($data->origen_entrada ?? '') }}</span></td>
        @break
    @case ('modo_carga')
        <td><span class="cp-celda">{{ ComprobanteProveedorModoCarga::etiqueta($data->modo_carga ?? '') }}</span></td>
        @break
    @case ('fechacomprobante')
        <td class="text-nowrap cp-col-fecha">{{ ComprobanteProveedorListadoColumnas::valorCelda($data, $key) }}</td>
        @break
    @case ('fechaiva')
        <td class="text-nowrap cp-col-fechaiva">
            @if ($data->fechaiva)
                <span title="Fecha de contabilización e IVA compras">{{ $data->fechaiva->format('d/m/Y') }}</span>
            @endif
        </td>
        @break
    @case ('total')
    @case ('cotizacion')
        <td class="text-right text-nowrap">{{ ComprobanteProveedorListadoColumnas::valorCelda($data, $key) }}</td>
        @break
    @default
        <td>{{ ComprobanteProveedorListadoColumnas::valorCelda($data, $key) }}</td>
@endswitch
