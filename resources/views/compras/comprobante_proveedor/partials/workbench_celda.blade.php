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
        <td class="text-nowrap">
            <small>{{ ComprobanteProveedorListadoColumnas::numeroVisible($data) }}</small>
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
                        <small>{{ $numeroOc }}</small>
                    </a>
                @else
                    <small>{{ $numeroOc }}</small>
                @endif
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
            @if ($badgeError)
                <span class="{{ $badgeError['class'] }}" title="{{ $data->anita_sync_error }}">{{ $badgeError['label'] }}</span>
            @endif
        </td>
        @break
    @case ('origen')
        <td><small>{{ ComprobanteProveedorOrigenEntrada::etiqueta($data->origen_entrada ?? '') }}</small></td>
        @break
    @case ('modo_carga')
        <td><small>{{ ComprobanteProveedorModoCarga::etiqueta($data->modo_carga ?? '') }}</small></td>
        @break
    @case ('fechaiva')
        <td>
            @if ($data->fechaiva)
                <span class="badge badge-info" title="Fecha de contabilización e IVA compras">{{ $data->fechaiva->format('d/m/Y') }}</span>
            @endif
        </td>
        @break
    @case ('total')
    @case ('cotizacion')
        <td class="text-right text-nowrap"><small>{{ ComprobanteProveedorListadoColumnas::valorCelda($data, $key) }}</small></td>
        @break
    @default
        <td><small>{{ ComprobanteProveedorListadoColumnas::valorCelda($data, $key) }}</small></td>
@endswitch
