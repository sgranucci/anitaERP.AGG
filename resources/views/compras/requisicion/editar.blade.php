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
<script>
window.requisicionLineasConfig = window.requisicionLineasConfig || {};
window.requisicionLineasConfig.urlPrecioUltimaCompra = @json(route('requisicion_precio_ultima_compra_articulo'));
</script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/lineas.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/consulta-listasprecio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/presupuestos.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/enviar-arbol.js")}}" type="text/javascript"></script>
@include('compras.requisicion.partials.banner_enviando_arbol_styles')
@if(!empty($es_provisorio))
<script src="{{ asset('assets/pages/scripts/compras/requisicion/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/confirmar.js')) ?: time() }}" type="text/javascript"></script>
@include('compras.requisicion.partials.banner_confirmando_styles')
@endif
@include('compras.requisicion.partials.comprobantes_asociados_script')
@endsection

@section('contenido')
@include('compras.requisicion.partials.comprobantes_asociados_modal')
@include('compras.requisicion.partials.modal_firmante_retome_arbol')
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
                        <button type="button" class="btn btn-outline-warning btn-sm ml-1 js-requisicion-comprobantes" title="Ver comprobantes asociados (órdenes de compra)" data-id="{{ $data->id }}" data-numero="{{ $data->numerorequisicion }}">
                            <i class="fas fa-link"></i> Ver comprobantes
                        </button>
                        @endif
                        @if (!empty($requisicion_wizard_multiples_oc_url))
                        <a href="{{ $requisicion_wizard_multiples_oc_url }}" class="btn btn-success btn-sm ml-1" title="Generar una o más órdenes de compra desde esta requisición (misma lógica que el alta de OC)">
                            <i class="fa fa-shopping-cart"></i> Generar órdenes de compra
                        </a>
                        @endif
                        <a href="{{ route('consultar_requisicion') }}" class="btn btn-outline-info btn-sm">
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
            <form action="{{ route('actualizar_requisicion', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
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
                    <div id="requisicion-aviso-arbol-grabacion" class="alert alert-secondary mb-3 d-none" role="alert">
                        <span id="requisicion-aviso-arbol-spinner" class="fa fa-spinner fa-spin mr-1" style="display:none;" aria-hidden="true"></span>
                        <strong>Aviso:</strong> <span class="texto"></span>
                    </div>
                    @endif
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
                            <button type="button" id="botonform0" class="btn btn-primary">Actualizar</button>
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
            <form action="{{ route('confirmar_requisicion', $data->id) }}" id="form-requisicion-confirmar" method="POST" class="d-none" aria-hidden="true">
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
