@extends("theme.$theme.layout")
@section('titulo')
    Comprobante de Ventas
@endsection

@section("scripts")
<script>window.VALIDACION_PADRON_POST_CARGA = true;</script>
<script>window.REQUIERE_VALIDACION_PADRON_OPERACION = true;</script>
@php
    $requiereValidacionApocOperacion = filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN)
        && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<script>window.REQUIERE_VALIDACION_APOC_OPERACION = @json($requiereValidacionApocOperacion);</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@include('includes.ventas.preferencias_facturacion_scripts')
<script src="{{asset("assets/pages/scripts/ventas/factura/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
@if (config('app.empresa') == 'EL BIERZO')
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
@endif

<script>
	function sub()
	{
        if (typeof window.formularioVentasBloqueadoPorPadron === 'function' && window.formularioVentasBloqueadoPorPadron()) {
            if (typeof window.notificarBloqueoPadronCliente === 'function') {
                window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede operar con este cliente.');
            } else {
                alert('Problemas en ARCA: no puede operar con este cliente.');
            }
            return false;
        }

        $('#formgeneral').submit();
    }
    $(function () {
        $("#cliente_id").change(function(){
            var cliente_id = $(this).val();
            completarCliente_Entrega(cliente_id);
            asignaDatosCliente(cliente_id, true);
            setTimeout(() => {
                muestraTipoSuspension();			
            }, 1500);
        });

		$("#divlugar").show();
		$("#divcodigoentrega").hide();

        var cliente_id = $("#cliente_id").val();
		if (cliente_id > 0)
        	completarCliente_Entrega(cliente_id);
	  });

</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                @if (isset($flGeneraNotaDeCredito))
                    <h3 class="card-title">Genera Nota de Crédito</h3>
                @else
                    <h3 class="card-title">Editar Comprobante de Ventas</h3>
                @endif
				&nbsp;- ID: {{ $data->id }} - {{$data->codigo}}
                <div class="card-tools">
                    @if (isset($urlOrigen))
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                        </a>
                    @endif
                    @if (can('listar-factura', false))
                        <a href="{{route('lista_una_factura', ['id' => $data->id])}}" class="btn btn-primary btn-sm" title="Listar el Comprobante">
                            <i class="fa fa-print"> Listar comprobante</i>
                        </a>                    
                    @endif                    
                </div>
            </div>
            <form action="{{route('grabar_comprobante')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-articulo-solo-facturable="1" onsubmit="return typeof validarPadronOperacionAntesSubmitForm === 'function' ? validarPadronOperacionAntesSubmitForm(event) : true;">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Asiento Contable
                    </button>
                </div>
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$data->codigo}}" >
        			<input type="hidden" id="venta_id" name="venta_id" value="{{$data->id??''}}" >
                    @php $datos = ['funcion' => 'editar', 'consultaFacturasDia' => $consultaFacturasDia ?? false]; @endphp
                    @include('ventas.factura.form', $datos)
                    @include('includes.contable.formasientoexterno')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
                            @if (isset($flGeneraNotaDeCredito))
							    <button type="submit" onclick="sub()" class="btn btn-success factura-carga-bloqueable" data-padron-accion-factura="1">Genera Nota de Crédito</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.arca_apoc_validacion_modal')
@endsection
