@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;

    $destinoImpresion = ProveedorCuentacorrienteGrillaSupport::destinoImpresion($data);
    $urlImpresion = ProveedorCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ProveedorCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
    $destinoEdicion = ProveedorCuentacorrienteGrillaSupport::destinoEdicion($data);
    $urlEdicion = ProveedorCuentacorrienteGrillaSupport::urlEdicion($data);
    $puedeEditar = ProveedorCuentacorrienteGrillaSupport::puedeEditarComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       class="btn-accion-tabla tooltipsC js-erp-workspace"
       data-ws-modo="pdf"
       data-ws-id="cc-{{ $data->id }}"
       data-ws-titulo="{{ $destinoImpresion['titulo'] }}"
       data-ws-meta="{{ $data->empresas->nombre ?? '' }}"
       data-ws-pdf="{{ $urlImpresion }}"
       @if ($puedeEditar && $urlEdicion)
           data-ws-edit="{{ $urlEdicion }}"
       @endif
       title="{{ $destinoImpresion['titulo'] }}">
        <i class="fa fa-print"></i>
    </a>
@endif
@if ($puedeEditar && $urlEdicion)
    <a href="{{ $urlEdicion }}"
       class="btn-accion-tabla tooltipsC js-erp-workspace"
       data-ws-modo="edit"
       data-ws-id="cc-{{ $data->id }}"
       data-ws-titulo="{{ $destinoEdicion['titulo'] }}"
       data-ws-meta="{{ $data->empresas->nombre ?? '' }}"
       data-ws-edit="{{ $urlEdicion }}"
       @if ($puedeImprimir && $urlImpresion)
           data-ws-pdf="{{ $urlImpresion }}"
       @endif
       title="Editar en solapa (sin menú)">
        <i class="fa fa-edit"></i>
    </a>
@endif
<a href="#" class="btn-accion-tabla tooltipsC veraplicaciones" title="Ver aplicaciones">
    <i class="fa fa-clone"></i>
</a>
