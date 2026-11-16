@extends("theme.$theme.layout")
@section('titulo') Nuevo tipo de gasto importación @endsection
@section('contenido')
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title">Nuevo tipo de gasto</h3>
        <div class="card-tools">
            <a href="{{ route('importacion_gasto_tipo') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
        </div>
    </div>
    <div class="card-body">@include('compras.importacion_gasto_tipo.form')</div>
</div>
@endsection
