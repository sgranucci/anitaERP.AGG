@extends("theme.$theme.layout")
@section('titulo')
    Editar combinac&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Combinaci&oacute;n - Datos Diseño</h3>
                <div class="card-tools">
                    <a href="{{  URL::previous() }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('combinacion.update', ['id' => $combinacion->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <input type="hidden" name="id" class="form-control" value="{{ $id }}" />
                <input type="hidden" name="codigo" class="form-control" value="{{ $combinacion->codigo }}" />
                @include('stock.combinacion.diseno.partials.form', ['edit' => true])
            </form>
        </div>
    </div>
</div>
@endsection
