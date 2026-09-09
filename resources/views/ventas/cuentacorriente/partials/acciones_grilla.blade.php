@php
    use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;

    $destinoImpresion = ClienteCuentacorrienteGrillaSupport::destinoImpresion($data);
    $urlImpresion = ClienteCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ClienteCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
    $destinoEdicion = ClienteCuentacorrienteGrillaSupport::destinoEdicion($data);
    $urlEdicion = ClienteCuentacorrienteGrillaSupport::urlEdicion($data);
    $puedeEditar = ClienteCuentacorrienteGrillaSupport::puedeEditarComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       class="btn-accion-tabla tooltipsC js-erp-workspace"
       data-ws-modo="pdf"
       data-ws-id="cc-{{ $data->id }}"
       data-ws-titulo="{{ $destinoImpresion['titulo'] ?? 'Imprimir comprobante' }}"
       data-ws-meta="{{ $data->empresas->nombre ?? '' }}"
       data-ws-pdf="{{ $urlImpresion }}"
       @if ($puedeEditar && $urlEdicion)
           data-ws-edit="{{ $urlEdicion }}"
       @endif
       title="{{ $destinoImpresion['titulo'] ?? 'Imprimir comprobante' }}">
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
