@extends("theme.$theme.layout")
@section('titulo')
    Empleados
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/form.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/domicilio.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/domicilio.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/arca-padron.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/arca-padron.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary" id="tab2" data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}">
            <div class="card-header">
                <h3 class="card-title">Crear empleado (alta provisoria)</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_empleado_sueldos')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab">
                                <i class="fa fa-info-circle"></i> Datos personales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-laborales" role="tab">
                                <i class="fa fa-briefcase"></i> Laborales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-leyendas" role="tab">
                                <i class="fa fa-comment"></i> Leyendas
                            </a>
                        </li>
                    </ul>
                </div>
                <form action="{{route('guardar_empleado_sueldos')}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('sueldos.empleado.partials.form_datos')
                        </div>
                        <div class="tab-pane fade" id="tab-laborales" role="tabpanel">
                            @include('sueldos.empleado.partials.form_laborales')
                        </div>
                        <div class="tab-pane fade" id="tab-leyendas" role="tabpanel">
                            @include('sueldos.empleado.partials.form_leyendas')
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@include('compras.proveedor.arca-cuit-entry-modal')
@include('compras.proveedor.arca-padron-modals')
@endsection
