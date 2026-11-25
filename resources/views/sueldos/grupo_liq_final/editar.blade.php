@extends("theme.$theme.layout")
@section('titulo')
    Grupos liquidación final
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/maestro/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/maestro/consulta.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar regla — grupo liquidación final</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_grupo_liq_final_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_grupo_liq_final_sueldos', $data->id) }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('sueldos.grupo_liq_final.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsulta_maestro_sueldos')
@endsection
