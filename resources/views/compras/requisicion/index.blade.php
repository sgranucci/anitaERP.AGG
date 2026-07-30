@extends("theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/enviar-arbol.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/volver-compras.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/confirmar.js')) ?: time() }}" type="text/javascript"></script>
@include('compras.requisicion.partials.banner_confirmando_styles')
@include('compras.requisicion.partials.banner_enviando_arbol_styles')
@include('compras.requisicion.partials.comprobantes_asociados_script')
@endsection

<?php use App\Support\Compras\RequisicionListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
@include('compras.requisicion.partials.comprobantes_asociados_modal')
@include('compras.requisicion.partials.modal_firmante_retome_arbol')
@include('compras.requisicion.partials.modal_centrocosto_retome_arbol')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Requisiciones</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-requisicion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RequisicionListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => route('consultar_requisicion', RequisicionListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-requisicion',
                        'toggleId' => 'btn-toggle-filtros-requisicion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_requisicion', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-requisicion',
                        'nuevoRegistroLabel' => 'Nuevo registro',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_requisicion') }}" id="form-filtros-requisicion" class="mb-0">
                @include('compras.requisicion.partials.filtros_listado')
            </form>
            @include('compras.requisicion.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_requisicion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">Número</th>
                            <th>Solicitante</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Centro costo</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th class="text-right">Total</th>
                            <th>Items</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requisicion as $data)
                        @php
                            $esProvisorioFila = ($data->estado ?? '') === ($estado_provisorio ?? 'PROVISORIO');
                        @endphp
                        <tr @if($esProvisorioFila) class="table-secondary" @endif>
                            <td>
                                @if($esProvisorioFila)
                                    <strong>{{ $data->numerorequisicion }}</strong>
                                @else
                                    {{ $data->numerorequisicion }}
                                @endif
                            </td>
                            <td><small>{{ $data->nombreusuario ?? '' }}</small></td>
                            <td>{{ date('d/m/Y', strtotime($data->fecha)) }}</td>
                            <td>{{ $data->nombreempresa }}</td>
                            <td><small>{{ $data->nombrecentrocosto }}</small></td>
                            <td><small>{{ $data->nombreproveedor }}</small></td>
                            <td>
                                @include('compras.requisicion.partials.estado_badge', ['estado' => $data->estado ?? ''])
                            </td>
                            <td class="text-right text-nowrap">
                                <small>{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }} {{ $data->monedacabecera_abreviatura ?? '' }}</small>
                            </td>
                            <td>
                                @foreach ($data->requisicion_articulos as $item)
                                    <small>{{ $item->articulos->sku ?? '' }}-{{ $item->articulos->descripcion ?? '' }}-Cant.:{{ $item->cantidad }}-Precio:{{ $item->precio }}</small><br>
                                @endforeach
                            </td>
                            <td>
                                @if (can('editar-requisicion', false))
                                <a href="{{ route('editar_requisicion', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="{{ $esProvisorioFila ? 'Editar provisorio' : 'Editar' }}">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if ($esProvisorioFila && can('confirmar-requisicion', false))
                                <form action="{{ route('confirmar_requisicion', $data->id) }}" class="d-inline form-confirmar-requisicion" method="POST"
                                      data-confirm-msg="¿Confirmar requisición {{ $data->numerorequisicion }}? Enviará al árbol de aprobación y sincronizará con Anita."
                                      data-preview-cc-url="{{ route('centros_costo_arbol_requisicion', ['id' => $data->id]) }}">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-success" title="Confirmar requisición">
                                        <i class="fa fa-check"></i>
                                    </button>
                                </form>
                                @endif
                                @if (($data->estado ?? '') === ($estado_en_compras ?? 'EN COMPRAS'))
                                <button type="button"
                                        class="btn-accion-tabla tooltipsC text-success js-enviar-arbol-requisicion"
                                        title="Envía al árbol de aprobación"
                                        data-requisicion-id="{{ $data->id }}"
                                        data-preview-url="{{ route('firmantes_retome_arbol_requisicion', ['id' => $data->id]) }}"
                                        data-post-url="{{ route('enviar_arbol_requisicion', ['id' => $data->id]) }}"
                                        data-redirect-url="{{ route('consultar_requisicion') }}">
                                    <i class="fas fa-sitemap"></i>
                                </button>
                                @endif
                                @endif
                                @include('compras.requisicion.partials.boton_volver_compras', [
                                    'data' => $data,
                                    'filtrosQuery' => $retornoListadoQuery,
                                    'claseBoton' => 'btn-accion-tabla tooltipsC text-warning',
                                ])
                                @if (can('listar-requisicion', false) || can('editar-requisicion', false))
                                <a href="{{ route('imprimir_pdf_requisicion', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Listar la requisición (PDF)" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-print"></i>
                                </a>
                                @endif
                                @php
                                    $estadoReq = $data->estado ?? '';
                                    $puedeWizardOcListado = can('crear-ordencompra', false)
                                        && (
                                            $estadoReq === ($estado_aprobada_requisicion ?? '')
                                            || $estadoReq === ($estado_genero_oc_requisicion ?? 'GENERO ORDEN COMPRA')
                                            || $estadoReq === 'GENERO OC'
                                        );
                                @endphp
                                @if ($puedeWizardOcListado)
                                <a href="{{ route('requisicion_wizard_multiples_oc', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC text-success" title="Generar órdenes de compra (ítems pendientes; permisos al abrir)">
                                    <i class="fa fa-shopping-cart"></i>
                                </a>
                                @endif
                                @php
                                    $puedeCumplirListado = can('cumplir-requisicion-compra', false)
                                        && ($data->estado ?? '') === ($estado_aprobada_requisicion ?? 'APROBADA');
                                @endphp
                                @if ($puedeCumplirListado)
                                <a href="{{ route('crear_cumplir_requisicion_compra', ['requisicion_id' => $data->id]) }}" class="btn-accion-tabla tooltipsC text-info" title="Cumplir requisición (genera transferencia)">
                                    <i class="fa fa-truck-loading"></i>
                                </a>
                                @endif
                                @if ((int) ($data->ordencompra_vinculadas_count ?? 0) > 0 && (can('editar-requisicion', false) || can('listar-requisicion', false)))
                                <button type="button" class="btn-accion-tabla tooltipsC text-warning js-requisicion-comprobantes" title="Ver órdenes de compra vinculadas" data-id="{{ $data->id }}" data-numero="{{ $data->numerorequisicion }}">
                                    <i class="fa fa-shopping-cart"></i>
                                </button>
                                @endif
                                @if (can('borrar-requisicion', false)
                                    && ($data->estado ?? '') !== ($estado_provisorio ?? 'PROVISORIO')
                                    && (int) ($data->ordencompra_vinculadas_count ?? 0) === 0)
                                <form action="{{ route('eliminar_requisicion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                        <i class="fas fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                                @if (can('actualizar-requisicion', false)
                                    && ($data->estado ?? '') === ($estado_provisorio ?? 'PROVISORIO')
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
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($requisicion, 'links'))
            <div class="card-footer">
                {{ $requisicion->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
