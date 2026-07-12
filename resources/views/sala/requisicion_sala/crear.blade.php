@extends("theme.$theme.layout")
@section('titulo')
Requisición de sala
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/depmae/consulta.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/deposito.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/grabando.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sala/requisicion_sala/grabando.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/crear.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sala/requisicion_sala/crear.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Nueva requisición de sala</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_requisicion_sala') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_requisicion_sala') }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off"
                data-url-npu="{{ route('requisicion_sala_consulta_npu') }}">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-paperclip"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('sala.requisicion_sala.form')
                    <div class="form4" id="requisicion-sala-solapa-archivos" style="display:none;">
                        <p class="text-muted small mb-2">Archivos actuales</p>
                        @include('sala.requisicion_sala.partials.archivos_adjuntos', [
                            'data' => null,
                            'ocultarInputsConservar' => false,
                        ])
                        @include('sala.requisicion_sala.partials.solapa_agregar_archivos', ['data' => null])
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
@include('sala.requisicion_sala.partials.banner_grabando_styles')
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@endsection
