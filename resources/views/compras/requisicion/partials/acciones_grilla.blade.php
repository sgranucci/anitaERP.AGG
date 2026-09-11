@php
    $esProvisorioFila = ($data->estado ?? '') === ($estado_provisorio ?? 'PROVISORIO');
    $urlEditar = route('editar_requisicion', ['id' => $data->id] + $retornoListadoQuery);
    $urlConsulta = route('solo_consulta_requisicion', ['id' => $data->id] + $retornoListadoQuery);
    $estadoReq = $data->estado ?? '';
    $puedeWizardOcListado = can('crear-ordencompra', false)
        && (
            $estadoReq === ($estado_aprobada_requisicion ?? '')
            || $estadoReq === ($estado_genero_oc_requisicion ?? 'GENERO ORDEN COMPRA')
            || $estadoReq === 'GENERO OC'
        );
    $puedeCumplirListado = can('cumplir-requisicion-compra', false)
        && $estadoReq === ($estado_aprobada_requisicion ?? 'APROBADA');
@endphp
<div class="oc-acciones">
    @if (can('editar-requisicion', false))
        <a href="{{ $urlEditar }}" class="btn-accion-tabla tooltipsC" title="{{ $esProvisorioFila ? 'Editar provisorio' : 'Editar' }}">
            <i class="fa fa-edit"></i>
        </a>
    @endif
    @if (can('listar-requisicion', false) || can('editar-requisicion', false))
        <a href="{{ $urlConsulta }}"
           class="btn-accion-tabla tooltipsC js-erp-workspace"
           title="Solo consulta"
           data-ws-modo="edit"
           data-ws-id="{{ $data->id }}"
           data-ws-titulo="Consulta RQ {{ $data->numerorequisicion }}"
           data-ws-meta="{{ $data->nombreproveedor ?? '' }}"
           data-ws-edit="{{ $urlConsulta }}">
            <i class="fa fa-eye"></i>
        </a>
        <a href="{{ route('imprimir_pdf_requisicion', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Listar la requisición (PDF)" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-print"></i>
        </a>
    @endif
    @if (can('editar-requisicion', false) && $esProvisorioFila && can('confirmar-requisicion', false))
        <form action="{{ route('confirmar_requisicion', $data->id) }}" class="d-inline form-confirmar-requisicion" method="POST"
              data-confirm-msg="¿Confirmar requisición {{ $data->numerorequisicion }}? Enviará al árbol de aprobación y sincronizará con Anita."
              data-preview-cc-url="{{ route('centros_costo_arbol_requisicion', ['id' => $data->id]) }}">
            @csrf
            <button type="submit" class="btn-accion-tabla tooltipsC text-success" title="Confirmar requisición">
                <i class="fa fa-check"></i>
            </button>
        </form>
    @endif
    @if (can('editar-requisicion', false) && $estadoReq === ($estado_en_compras ?? 'EN COMPRAS'))
        <button type="button"
                class="btn-accion-tabla tooltipsC text-success js-enviar-arbol-requisicion"
                title="Envía al árbol de aprobación"
                data-requisicion-id="{{ $data->id }}"
                data-preview-url="{{ route('firmantes_retome_arbol_requisicion', ['id' => $data->id]) }}"
                data-post-url="{{ route('enviar_arbol_requisicion', ['id' => $data->id]) }}"
                data-redirect-url="{{ route('consultar_requisicion', $retornoListadoQuery) }}">
            <i class="fas fa-sitemap"></i>
        </button>
    @endif
    @include('compras.requisicion.partials.boton_volver_compras', [
        'data' => $data,
        'filtrosQuery' => $retornoListadoQuery,
        'claseBoton' => 'btn-accion-tabla tooltipsC text-warning',
    ])
    @if ($puedeWizardOcListado)
        <a href="{{ route('requisicion_wizard_multiples_oc', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC text-success" title="Generar órdenes de compra (ítems pendientes; permisos al abrir)">
            <i class="fa fa-shopping-cart"></i>
        </a>
    @endif
    @if ($puedeCumplirListado)
        <a href="{{ route('crear_cumplir_requisicion_compra', ['requisicion_id' => $data->id]) }}" class="btn-accion-tabla tooltipsC text-info" title="Cumplir requisición (genera transferencia)">
            <i class="fa fa-truck-loading"></i>
        </a>
    @endif
    @include('compras.requisicion.partials.boton_marcar_cumplida', [
        'data' => $data,
        'filtrosQuery' => $retornoListadoQuery,
        'claseBoton' => 'btn-accion-tabla tooltipsC text-secondary',
        'soloIcono' => true,
    ])
    @if ((int) ($data->ordencompra_vinculadas_count ?? 0) > 0 && (can('editar-requisicion', false) || can('listar-requisicion', false)))
        <button type="button" class="btn-accion-tabla tooltipsC text-warning js-requisicion-comprobantes" title="Ver órdenes de compra vinculadas" data-id="{{ $data->id }}" data-numero="{{ $data->numerorequisicion }}">
            <i class="fa fa-link"></i>
        </button>
    @endif
    @if (can('borrar-requisicion', false)
        && $estadoReq !== ($estado_provisorio ?? 'PROVISORIO')
        && (int) ($data->ordencompra_vinculadas_count ?? 0) === 0)
        <form action="{{ route('eliminar_requisicion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
            @csrf @method("delete")
            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                <i class="fas fa-times-circle text-danger"></i>
            </button>
        </form>
    @endif
    @if (can('actualizar-requisicion', false)
        && $estadoReq === ($estado_provisorio ?? 'PROVISORIO')
        && (int) ($data->ordencompra_vinculadas_count ?? 0) === 0)
        <form action="{{ route('eliminar_requisicion_provisorio', $data->id) }}" class="d-inline form-eliminar-provisorio" method="POST"
              onsubmit="return confirm('¿Eliminar este provisorio? Esta acción no se puede deshacer.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar provisorio">
                <i class="fas fa-times-circle text-danger"></i>
            </button>
        </form>
    @endif
</div>
