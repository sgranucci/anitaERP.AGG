@php
    $esSuspendidaFila = ($row->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA;
    $urlEditar = route('editar_ordencompra', ['id' => $row->id] + $retornoListadoQuery);
    $urlConsulta = route('solo_consulta_ordencompra', ['id' => $row->id]);
    $urlPdf = route('imprimir_pdf_ordencompra', ['id' => $row->id]);
@endphp
<div class="oc-acciones">
    @if (can('editar-ordencompra', false))
        <a href="{{ $urlEditar }}" class="btn-accion-tabla tooltipsC" title="Editar">
            <i class="fa fa-edit"></i>
        </a>
    @endif
    @if (can('listar-ordencompra', false))
        <a href="{{ $urlConsulta }}"
           class="btn-accion-tabla tooltipsC js-erp-workspace"
           title="Solo consulta"
           data-ws-modo="edit"
           data-ws-id="{{ $row->id }}"
           data-ws-titulo="Consulta OC {{ $row->numeroordencompra }}"
           data-ws-meta="{{ $row->nombreproveedor }}"
           data-ws-edit="{{ $urlConsulta }}">
            <i class="fa fa-eye"></i>
        </a>
    @endif
    @if (can('listar-ordencompra', false) || can('editar-ordencompra', false))
        <a href="{{ $urlPdf }}" class="btn-accion-tabla tooltipsC" title="Imprimir orden (PDF vertical)" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-print"></i>
        </a>
        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $row->id, 'formato' => 'apaisado']) }}" class="btn-accion-tabla tooltipsC" title="PDF Legal apaisado" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-arrows-alt-h"></i>
        </a>
    @endif
    @if (can('editar-ordencompra', false) && !empty($row->proveedor_id))
        <button type="button" class="btn-accion-tabla tooltipsC js-oc-enviar-proveedor text-success" title="Enviar OC al proveedor por email" data-ordencompra-id="{{ $row->id }}">
            <i class="fa fa-envelope"></i>
        </button>
    @endif
    @if (!empty($row->requisicion_id) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
        <a href="{{ route('editar_requisicion', ['id' => $row->requisicion_id]) }}" class="btn-accion-tabla tooltipsC text-warning" title="Ver requisición" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-link"></i>
        </a>
    @endif
    @if (can('actualizar-ordencompra', false) && !empty($row->proveedor_id))
        <button type="button" class="btn-accion-tabla tooltipsC js-oc-asignar-factura btn-oc-asignar-factura"
                title="Asignar PDF de factura al legajo"
                data-url="{{ route('ordencompra_asignar_factura_pdf', ['id' => $row->id]) }}"
                data-numero="{{ $row->numeroordencompra }}"
                data-proveedor="{{ $row->nombreproveedor }}">
            <i class="fa fa-file-pdf-o"></i>
        </button>
    @endif
    @if (can('actualizar-ordencompra', false))
        <button type="button" class="btn-accion-tabla tooltipsC js-oc-index-abrir-estado text-dark" title="Cambiar estado"
            data-url="{{ route('ordencompra_cambiar_estado', ['id' => $row->id]) }}"
            data-estado-actual="{{ $row->estadoordencompra }}">
            <i class="fa fa-random"></i>
        </button>
        <button type="button" class="btn-accion-tabla tooltipsC js-oc-index-abrir-sector text-dark" title="Cambiar sector"
            data-url="{{ route('ordencompra_cambiar_sector', ['id' => $row->id]) }}"
            data-sector-id="{{ $row->sector_legajocompra_id }}"
            data-ordencompra-id="{{ $row->id }}">
            <i class="fa fa-folder-open"></i>
        </button>
    @endif
    @if ($esSuspendidaFila && can('actualizar-ordencompra', false))
        <form action="{{ route('ordencompra_reactivar', ['id' => $row->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Reactivar a PENDIENTE?');">
            @csrf
            <button type="submit" class="btn-accion-tabla tooltipsC text-warning" title="Reactivar">
                <i class="fa fa-undo"></i>
            </button>
        </form>
    @endif
    @if (can('borrar-ordencompra', false))
        <form action="{{ route('eliminar_ordencompra', ['id' => $row->id]) }}" class="d-inline form-eliminar" method="POST">
            @csrf
            @method('delete')
            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </form>
    @endif
</div>
