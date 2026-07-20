@extends("theme.$theme.layout")
@section('titulo')
    Agrupamientos de sueldos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/agrupamiento/dotacion.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="tabs-agrupamiento" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-datos-link" data-toggle="tab" href="#tab-datos" role="tab">
                            <i class="fa fa-fw fa-id-card"></i> Datos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-dotacion-link" data-toggle="tab" href="#tab-dotacion" role="tab">
                            <i class="fa fa-fw fa-tshirt"></i> Dotaci&oacute;n de indumentaria
                        </a>
                    </li>
                    <li class="ml-auto align-self-center pr-2">
                        <a href="{{route('consultar_agrupamiento_sueldos')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                    <form action="{{route('actualizar_agrupamiento_sueldos', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                        @csrf @method("put")
                        <div class="card-body">
                            <h3 class="card-title mb-3">Editar agrupamiento #{{ $data->codigo }}</h3>
                            @include('sueldos.agrupamiento.form')
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-lg-3"></div>
                                <div class="col-lg-6">
                                    @include('includes.boton-form-editar')
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade" id="tab-dotacion" role="tabpanel">
                    <div class="card-body">
                        <div id="host-dotacion" data-url="{{ route('panel_dotacion_agrupamiento', ['id' => $data->id]) }}">
                            <div class="text-center text-muted py-4">
                                <i class="fa fa-spinner fa-spin"></i> Cargando dotaci&oacute;n…
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
