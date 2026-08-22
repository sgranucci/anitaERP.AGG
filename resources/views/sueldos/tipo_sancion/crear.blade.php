@extends("theme.$theme.layout")
@section('titulo')
    Tipos de sanción
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/concepto/consulta.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear tipo de sanción</h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual')
                    <a href="{{route('consultar_tipo_sancion_sueldos')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_tipo_sancion_sueldos')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('sueldos.tipo_sancion.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-4"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaconcepto_sueldos')
@endsection
