@extends(!empty($acceso_visualizacion_por_hash) ? 'layouts.requisicion-visualizar-hash' : "theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/partidagasto/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/capex/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/articulo_proveedor/operativo.js")}}" type="text/javascript"></script>
@php
    $estadoPendienteNombre = \App\Models\Compras\Requisicion_Estado::$enumEstado[array_search('P', array_column(\App\Models\Compras\Requisicion_Estado::$enumEstado, 'valor'))]['nombre'] ?? 'PENDIENTE';
@endphp
<script>
window.requisicionLineasConfig = window.requisicionLineasConfig || {};
window.requisicionLineasConfig.urlPrecioUltimaCompra = @json(route('requisicion_precio_ultima_compra_articulo'));
window.requisicionLineasConfig.urlCalcularTotales = @json(route('requisicion_calcular_totales'));
window.requisicionModoProvisorio = @json(!empty($es_provisorio));
window.requisicionPideCcArbolAlGrabar = @json(empty($visualizar) && empty($es_provisorio) && (($data->estado ?? '') === $estadoPendienteNombre));
window.requisicionUsaCcOrigenArbol = @json(\App\Support\Compras\RequisicionCentrocostoArbolOrigenSupport::usuarioPuedeCargar());
window.msColoresOpciones = @json(($color_query ?? collect())->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values());
window.msTallesOpciones = @json(($talle_query ?? collect())->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre])->values());
</script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/lineas.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/form-color-talle.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/centrocosto-arbol-grabacion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/centrocosto-arbol-grabacion.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/arbolaprobacion/panel_ia.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/consulta-listasprecio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/presupuestos.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/enviar-arbol.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/volver-compras.js') }}" type="text/javascript"></script>
@include('compras.requisicion.partials.banner_enviando_arbol_styles')
@if(!empty($es_provisorio))
<script src="{{ asset('assets/pages/scripts/compras/requisicion/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/confirmar.js')) ?: time() }}" type="text/javascript"></script>
@include('compras.requisicion.partials.banner_confirmando_styles')
@endif
@include('compras.requisicion.partials.comprobantes_asociados_script')
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_requisicion', $filtrosQuery ?? []);
@endphp
@include('compras.requisicion.partials.comprobantes_asociados_modal')
@include('compras.requisicion.partials.modal_firmante_retome_arbol')
@include('compras.requisicion.partials.modal_centrocosto_retome_arbol')
@if(!empty($es_provisorio))
@include('compras.requisicion.partials.modal_confirmar_envio_arbol')
@endif
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    Requisición &nbsp;ID:&nbsp;{{ $data->id }}&nbsp;Número {{ $data->numerorequisicion }}
                    @if(!empty($es_provisorio))
                        <span class="badge badge-secondary ml-2">PROVISORIO</span>
                    @endif
                </h3>
                <div class="card-tools">
                    @if(empty($acceso_visualizacion_por_hash))
                        @if (can('listar-requisicion', false) || can('editar-requisicion', false))
                        <a href="{{ route('imprimir_pdf_requisicion', ['id' => $data->id]) }}" class="btn btn-primary" title="Listar la requisición en PDF" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-file-pdf"> Listar Requisición</i>
                        </a>
                        @endif
                        @if (!empty($tiene_ordencompra_asociada) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
                        <button type="button" class="btn btn-outline-warning btn-sm ml-1 js-requisicion-comprobantes" title="Ver órdenes de compra y comprobantes vinculados" data-id="{{ $data->id }}" data-numero="{{ $data->numerorequisicion }}">
                            <i class="fas fa-shopping-cart"></i> Ver órdenes de compra
                        </button>
                        @endif
                        @if (!empty($requisicion_wizard_multiples_oc_url))
                        <a href="{{ $requisicion_wizard_multiples_oc_url }}" class="btn btn-success btn-sm ml-1" title="{{ !empty($tiene_ordencompra_asociada) ? 'Generar más órdenes de compra para ítems pendientes' : 'Generar órdenes de compra desde esta requisición' }}">
                            <i class="fa fa-shopping-cart"></i>
                            {{ !empty($tiene_ordencompra_asociada) ? 'Generar más órdenes de compra' : 'Generar órdenes de compra' }}
                            @if (!empty($requisicion_lineas_pendientes_oc))
                            <span class="badge badge-light text-dark ml-1">{{ $requisicion_lineas_pendientes_oc }}</span>
                            @endif
                        </a>
                        @endif
                        @if (can('cumplir-requisicion-compra', false) && ($data->estado ?? '') === ($estado_aprobada_requisicion ?? 'APROBADA'))
                        <a href="{{ route('crear_cumplir_requisicion_compra', ['requisicion_id' => $data->id]) }}" class="btn btn-info btn-sm ml-1" title="Cumplir requisición (genera transferencia de mercadería)">
                            <i class="fa fa-truck-loading"></i> Cumplir requisición
                        </a>
                        @endif
                        @include('compras.requisicion.partials.boton_volver_compras', [
                            'data' => $data,
                            'filtrosQuery' => $filtrosQuery ?? [],
                        ])
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                    @if(empty($visualizar))
                        @if (can('editar-requisicion', false) && ($data->estado ?? '') === ($estado_en_compras ?? 'EN COMPRAS') && empty($es_provisorio))
                        <button type="button"
                                class="btn btn-success btn-sm ml-1 js-enviar-arbol-requisicion"
                                data-requisicion-id="{{ $data->id }}"
                                data-preview-url="{{ route('firmantes_retome_arbol_requisicion', ['id' => $data->id]) }}"
                                data-post-url="{{ route('enviar_arbol_requisicion', ['id' => $data->id]) }}"
                                data-redirect-url="{{ route('editar_requisicion', ['id' => $data->id]) }}">
                            <i class="fa fa-sitemap"></i> Envía al árbol de aprobación
                        </button>
                        @endif
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_requisicion', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method('put')
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos principales</button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">Historia</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">Archivos asociados</button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">Árbol aprobación</button>
                    <button type="button" id="boton-solapa-presupuesto-requisicion" class="btn btn-info btn-sm" @if(!empty($es_provisorio)) style="display:none;" @endif>Presupuestos</button>
                </div>
                <div class="card-body">
                    @if(empty($visualizar))
                    @if(!empty($edicionLimitadaAprobada))
                    <div class="alert alert-warning mb-3">
                        <strong>Requisición aprobada:</strong> solo puede modificar el <em>proveedor sugerido</em> y guardar con <strong>Actualizar</strong>. El resto de los datos y líneas quedan en solo lectura.
                    </div>
                    @endif
                    @include('compras.requisicion.partials.aviso_arbol_grabacion')
                    @endif
                    @include('compras.requisicion.partials.ordenes_compra_vinculadas_texto')
                    @include('compras.requisicion.partials.cambios_articulo_historia', ['cambios_articulo' => $cambios_articulo ?? collect()])
                    @include('compras.requisicion.form')
                    <div class="form3" style="display:none;">
                        <h5>Historia de estados</h5>
                        <table class="table table-bordered">
                            <thead><tr><th>Fecha y hora</th><th>Estado</th><th>Usuario</th><th>Observación</th></tr></thead>
                            <tbody class="container-historia"></tbody>
                        </table>
                    </div>
                    <div class="form4" id="requisicion-solapa-archivos-adjuntos" style="display:none;">
                        <p class="text-muted small mb-2">Archivos actuales</p>
                        @include('compras.requisicion.partials.archivos_adjuntos', [
                            'data' => $data,
                            'ocultarInputsConservar' => !empty($visualizar),
                        ])
                        @include('compras.requisicion.partials.solapa_agregar_archivos', [
                            'visualizar' => $visualizar ?? null,
                            'data' => $data,
                        ])
                    </div>
                    <div class="form5" style="display:none;">
                        <div id="requisicion-panel-ia-arbol" class="d-none mb-3"></div>
                        <h5>Movimientos árbol de aprobación</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Envío</th><th>Envió</th><th>Nivel</th><th>Estado</th><th>Proceso</th><th>Destinatario</th><th>Obs.</th>
                                </tr>
                            </thead>
                            <tbody class="container-arbol"></tbody>
                        </table>
                    </div>
                    @include('compras.requisicion.presupuestos-tab')
                </div>
                <div class="card-footer">
                    @if(empty($visualizar))
                    <div id="requisicion-footer-bar" class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        @if(!empty($es_provisorio))
                            @include('compras.requisicion.partials.acciones_provisorio')
                        @else
                        <div class="flex-shrink-0">
                            <button type="button" id="botonform0" class="btn btn-primary">{{ !empty($edicionLimitadaAprobada) ? 'Actualizar proveedor' : 'Actualizar' }}</button>
                        </div>
                        @endif
                        <div id="requisicion-footer-presupuesto-actions" class="d-none">
                            <div class="d-flex flex-wrap align-items-center" style="gap: 0.5rem;">
                                <button type="button" class="btn btn-primary btn-sm" id="btn-footer-nuevo-presupuesto-requisicion">
                                    <i class="fa fa-plus"></i> Nuevo presupuesto
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-footer-volver-datos-requisicion" title="Volver a datos principales">
                                    <i class="fa fa-arrow-left"></i> Datos principales
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </form>
            @if(!empty($es_provisorio) && empty($visualizar))
            <form action="{{ route('confirmar_requisicion', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-requisicion-confirmar" method="POST" class="d-none" aria-hidden="true"
                  data-preview-cc-url="{{ route('centros_costo_arbol_requisicion', ['id' => $data->id]) }}">
                @csrf
            </form>
            @if(empty($tiene_ordencompra_asociada))
            <form action="{{ route('eliminar_requisicion_provisorio', $data->id) }}" id="form-requisicion-eliminar-provisorio" method="POST" class="d-none" aria-hidden="true">
                @csrf
                @method('DELETE')
            </form>
            @endif
            @endif
        </div>
    </div>
</div>
@endsection
