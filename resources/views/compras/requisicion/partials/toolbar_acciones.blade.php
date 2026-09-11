@php
    $estadoReq = (isset($data) && $data) ? ($data->estado ?? '') : '';
    $hayCircuito = isset($data) && $data && empty($visualizar) && empty($acceso_visualizacion_por_hash) && (
        (can('volver-compras-requisicion', false) && $estadoReq === ($estado_en_arbol_aprobacion ?? 'EN ARBOL APROBACION'))
        || (can('cumplir-requisicion-compra', false) && $estadoReq === ($estado_aprobada_requisicion ?? 'APROBADA'))
        || (can('actualizar-requisicion', false) && (
            $estadoReq === ($estado_aprobada_requisicion ?? 'APROBADA')
            || $estadoReq === ($estado_genero_oc_requisicion ?? 'GENERO ORDEN COMPRA')
            || $estadoReq === 'GENERO OC'
        ))
    );
@endphp
<div class="card-tools oc-form-toolbar d-flex flex-wrap align-items-center justify-content-end">
    @if (empty($acceso_visualizacion_por_hash))
        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm mr-1">
            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
        </a>
    @endif

    @if (isset($data) && $data && (can('listar-requisicion', false) || can('editar-requisicion', false)))
        <a href="{{ route('imprimir_pdf_requisicion', ['id' => $data->id]) }}" class="btn btn-primary btn-sm mr-1" title="Descargar PDF de la requisición" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    @endif

    @if (isset($data) && $data && !empty($tiene_ordencompra_asociada) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
        <button type="button" class="btn btn-outline-light btn-sm mr-1 js-requisicion-comprobantes" title="Ver órdenes de compra y comprobantes vinculados" data-id="{{ $data->id }}" data-numero="{{ $data->numerorequisicion }}">
            <i class="fas fa-shopping-cart"></i> Órdenes de compra
        </button>
    @endif

    @if (isset($data) && $data && !empty($requisicion_wizard_multiples_oc_url))
        <a href="{{ $requisicion_wizard_multiples_oc_url }}" class="btn btn-success btn-sm mr-1" title="{{ !empty($tiene_ordencompra_asociada) ? 'Generar más órdenes de compra para ítems pendientes' : 'Generar órdenes de compra desde esta requisición' }}">
            <i class="fa fa-shopping-cart"></i>
            {{ !empty($tiene_ordencompra_asociada) ? 'Más OC' : 'Generar OC' }}
            @if (!empty($requisicion_lineas_pendientes_oc))
                <span class="badge badge-light text-dark ml-1">{{ $requisicion_lineas_pendientes_oc }}</span>
            @endif
        </a>
    @endif

    @if (isset($data) && $data && empty($visualizar) && can('editar-requisicion', false) && $estadoReq === ($estado_en_compras ?? 'EN COMPRAS') && empty($es_provisorio))
        <button type="button"
                class="btn btn-success btn-sm mr-1 js-enviar-arbol-requisicion"
                data-requisicion-id="{{ $data->id }}"
                data-preview-url="{{ route('firmantes_retome_arbol_requisicion', ['id' => $data->id]) }}"
                data-post-url="{{ route('enviar_arbol_requisicion', ['id' => $data->id]) }}"
                data-redirect-url="{{ route('editar_requisicion', ['id' => $data->id] + ($filtrosQuery ?? [])) }}">
            <i class="fa fa-sitemap"></i> Enviar al árbol
        </button>
    @endif

    @if ($hayCircuito)
        <div class="btn-group mr-1">
            <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-random"></i> Circuito
            </button>
            <div class="dropdown-menu dropdown-menu-right">
                @include('compras.requisicion.partials.boton_volver_compras', [
                    'data' => $data,
                    'filtrosQuery' => $filtrosQuery ?? [],
                    'claseBoton' => 'dropdown-item',
                ])
                @if (can('cumplir-requisicion-compra', false) && $estadoReq === ($estado_aprobada_requisicion ?? 'APROBADA'))
                    <a href="{{ route('crear_cumplir_requisicion_compra', ['requisicion_id' => $data->id]) }}" class="dropdown-item" title="Cumplir requisición (genera transferencia de mercadería)">
                        <i class="fa fa-truck-loading"></i> Cumplir requisición
                    </a>
                @endif
                @include('compras.requisicion.partials.boton_marcar_cumplida', [
                    'data' => $data,
                    'filtrosQuery' => $filtrosQuery ?? [],
                    'claseBoton' => 'dropdown-item',
                ])
            </div>
        </div>
    @endif
</div>
