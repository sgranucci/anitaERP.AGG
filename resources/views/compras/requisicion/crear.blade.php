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
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Nueva requisición</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_requisicion') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_requisicion') }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Historia
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-paperclip"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    <div id="requisicion-aviso-arbol-grabacion" class="alert alert-secondary mb-3 d-none" role="alert">
                        <span id="requisicion-aviso-arbol-spinner" class="fa fa-spinner fa-spin mr-1" style="display:none;" aria-hidden="true"></span>
                        <strong>Aviso:</strong> <span class="texto"></span>
                    </div>
                    @include('compras.requisicion.form')
                    <div class="form3" style="display:none;">
                        <h5>Historia de estados</h5>
                        <table class="table table-bordered">
                            <thead><tr><th>Fecha</th><th>Estado</th><th>Usuario</th><th>Observación</th></tr></thead>
                            <tbody class="container-historia"></tbody>
                        </table>
                    </div>
                    <div class="form4" id="requisicion-solapa-archivos-adjuntos" style="display:none;">
                        @include('compras.requisicion.partials.solapa_agregar_archivos', ['data' => $data ?? null])
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="button" id="botonform0" class="btn btn-success">
    						   	<i class="fa fa-save"></i> Guardar
							</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
