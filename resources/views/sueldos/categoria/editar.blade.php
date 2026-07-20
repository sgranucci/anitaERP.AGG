@extends("theme.$theme.layout")
@section('titulo')
    Categorías de sueldos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/categoria/bases.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/categoria/bases.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar categoría #{{ $data->codigo }}</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_categoria_sueldos')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas">
                    <ul class="nav nav-tabs" id="tabs-categoria" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-datos-link" data-toggle="tab" href="#tab-datos" role="tab">
                                <i class="fa fa-info-circle"></i> Datos principales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-bases-link" data-toggle="tab" href="#tab-bases" role="tab">
                                <i class="fa fa-list-ol"></i> Bases de c&aacute;lculo
                                <span class="badge badge-info" id="badge-cant-bases">{{ count($basesGrilla) }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                        <form action="{{route('actualizar_categoria_sueldos', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                            @csrf @method("put")
                            @include('sueldos.categoria.form_datos')
                            <div class="row mt-3">
                                <div class="col-lg-3"></div>
                                <div class="col-lg-6">
                                    @include('includes.boton-form-editar')
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-bases" role="tabpanel">
                        @include('sueldos.categoria.form_bases')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($usaTabla)
    @include('sueldos.categoria.modal_vigencias_base')
@endif
@endsection
