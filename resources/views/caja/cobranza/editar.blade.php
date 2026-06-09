@extends("theme.$theme.layout")
@section('titulo')
    Cobranzas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cobranza/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cobranza/descuento_comprobante.js")}}" type="text/javascript"></script>
@if (!empty($cobranza_descuentos_json))
<script type="text/javascript">
    $(function () {
        setTimeout(function () {
            sincronizaFilasNcPendientes();
            sumaMontoComprobante();
        }, 400);
    });
</script>
@endif
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/banco/consulta.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Cobranza - {{$data->tipotransaccioncajas->nombre}}- Numero {{$data->numerotransaccion}}</h3>
                @if (isset($caja_id))
                    <h3 class="card-title">&nbsp&nbsp&nbsp&nbsp&nbsp Caja: {{$caja_id}} - {{$nombreCaja}}</h3>
                @endif
                <div class="card-tools">
                    <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                    </a> 
                    @if (can('emitir-cobranza', false))
                        <a href="{{route('listar_una_cobranza', ['id' => $data->id])}}" class="btn btn-primary btn-sm" title="Listar la Cobranza">
                            <i class="fa fa-print"> Listar cobranza</i>
                        </a>                    
                    @endif
                    @if (can('revertir-cobranza', false))
                        <button type="button" id="botonrevertir" class="btn btn-info btn-sm">
                            <span class="fa fa-history"></span> Revierte cobranza
                        </button>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_cobranza', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{$data->caja_id ?? ''}}" >
                <input type="hidden" class="cobranza_id" id="cobranza_id" name="cobranza_id" value="{{$data->id ?? ''}}" >
                <input type="hidden" class="origen" id="origen" name="origen" value="{{$origen ?? ''}}" >
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Cuentas
                    </button>                    
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Cheques
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Retenciones
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Historia
                    </button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Asiento Contable
                    </button>
                    <button type="button" id="botonform7" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('caja.cobranza.form')
                    @include('caja.cobranza.form2')
                    @include('caja.cobranza.form3')
                    @include('caja.cobranza.form4')
                    @include('caja.cobranza.form5')
                    @include('includes.contable.formasientoexterno')
                    @include('caja.cobranza.form7')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-4">
                            @if (can('actualizar-cobranza', false))
                        	    <button type="button" id="botonform0" class="btn btn-success">Actualizar</button>
                            @endif
                    	</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
