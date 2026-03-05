@extends("theme.$theme.layout")
@section('titulo')
    Cheques
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Cheques</h3>
                <div class="card-tools">
                    <a href="{{route('cheque')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_cheque', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                                <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{$data->caja_id ?? ''}}" >
                <input type="hidden" class="origen" id="origen" name="origen" value="{{$origen ?? ''}}" >
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Asiento Contable
                    </button>
                </div>
                <div class="card-body">
                    @include('caja.cheque.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                            @include('includes.contable.formasientoexterno')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
