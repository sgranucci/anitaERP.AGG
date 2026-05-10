@extends("theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/partidagasto/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/capex/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/requisicion/consulta-listasprecio.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Requisición {{ $data->numerorequisicion }}</h3>
                <div class="card-tools">
                    @if(!empty($visualizar))
                        @if(empty($acceso_visualizacion_por_hash))
                        <a href="{{ route('consultar_requisicion') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                        @endif
                    @else
                        @if (can('listar-requisicion', false) || can('editar-requisicion', false))
                        <a href="{{ route('imprimir_pdf_requisicion', ['id' => $data->id]) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener noreferrer" title="Descargar PDF con todos los datos">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        @endif
                        <a href="{{ route('consultar_requisicion') }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                        @if (can('editar-requisicion', false) && ($data->estado ?? '') === ($estado_en_compras ?? 'EN_COMPRAS'))
                        <form action="{{ route('enviar_arbol_requisicion', ['id' => $data->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Enviar esta requisición al árbol de aprobación para continuar el circuito?');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm ml-1">
                                <i class="fa fa-sitemap"></i> Envía al árbol de aprobación
                            </button>
                        </form>
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
                            <thead><tr><th>Fecha</th><th>Estado</th><th>Usuario</th><th>Observación</th></tr></thead>
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
                </div>
                <div class="card-footer">
                    @if(empty($visualizar))
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="button" id="botonform0" class="btn btn-primary">Actualizar</button>
                        </div>                    
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
