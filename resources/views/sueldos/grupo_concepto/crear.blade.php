@extends("theme.$theme.layout")
@section('titulo') Grupos de conceptos @endsection
@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@endsection
@section('contenido')
<div class="row"><div class="col-lg-12">
    @include('includes.form-error') @include('includes.mensaje')
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Crear grupo de conceptos</h3>
            <div class="card-tools">
                <a href="{{ route('consultar_grupo_concepto_sueldos') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
            </div>
        </div>
        <form action="{{ route('guardar_grupo_concepto_sueldos') }}" method="POST" class="form-horizontal" id="form-general">
            @csrf
            <div class="card-body">@include('sueldos.grupo_concepto.form')</div>
            <div class="card-footer">@include('includes.boton-form-crear')</div>
        </form>
    </div>
</div></div>
@endsection
