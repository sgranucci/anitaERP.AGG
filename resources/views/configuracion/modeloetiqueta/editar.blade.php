@extends("theme.$theme.layout")
@section('titulo')
    Modelos de Etiquetas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/modeloetiqueta/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Modelo de Etiqueta</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_modeloetiqueta')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @if (can('borrar-modeloetiqueta', false))
                        @if ($data->estado != "ANULADA")
                            <button type="submit" onclick="anulaModeloetiqueta()" id="anulamodeloetiqueta" class="btn btn-warning">
                                <i class="fa fa-fw fa-cross"></i>
                                Anular Modelo de Etiqueta
                            </button>
                        @else
                            <input type="hidden" id="anulamodeloetiqueta" value="">
                        @endif
                    @else
                      <input type="hidden" id="anulamodeloetiqueta" value="">
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_modeloetiqueta', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
                    @include('configuracion.modeloetiqueta.form')
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
    </div>
</div>
@endsection
