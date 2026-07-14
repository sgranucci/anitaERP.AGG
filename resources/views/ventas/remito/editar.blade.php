@extends("theme.$theme.layout")
@section('titulo')
    Remitos de clientes
@endsection

@section("scripts")
<script>window.VALIDACION_PADRON_POST_CARGA = true;</script>
@php
    $requiereValidacionApocOperacion = filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN)
        && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<script>window.REQUIERE_VALIDACION_APOC_OPERACION = @json($requiereValidacionApocOperacion);</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/remito/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/remito/crear.js')) ?: time() }}" type="text/javascript"></script>

<script>
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
    var PROFORMA = "{{ config('cliente.PROFORMA') }}";
    var MOROSO = "{{ config('cliente.MOROSO') }}";
    var NO_FACTURAR = "{{ config('cliente.NO_FACTURAR') }}";

	function sub()
	{
        if ($('#formgeneral').hasClass('pedido-bloqueado-padron')) {
            if (typeof window.notificarBloqueoPadronCliente === 'function') {
                window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede guardar el remito con este cliente.');
            } else {
                alert('Problemas en ARCA: no puede guardar el remito con este cliente.');
            }
            return false;
        }

        if (typeof validarLugarEntregaAntesGuardar === 'function' && !validarLugarEntregaAntesGuardar()) {
            return false;
        }

        if (typeof validarListaprecioLineasFormularioVentas === 'function'
            && !validarListaprecioLineasFormularioVentas('#tbody-tabla tr')) {
            return false;
        }

        $('#formgeneral').submit();
    }

    $(function () {
        $("#cliente_id").change(function(){
            var cliente_id = $(this).val();
            completarCliente_Entrega(cliente_id);
            asignaDatosCliente(cliente_id, true);
            setTimeout(() => { muestraTipoSuspension(); }, 1500);
    	});
		$("#divlugar").show();
        var cliente_id = $("#cliente_id").val();
        if (cliente_id > 0) {
            completarCliente_Entrega(cliente_id);
            asignaDatosCliente(cliente_id, false);
        }
        setTimeout(() => { muestraTipoSuspension(); }, 1000);
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
                <h3 class="card-title">Editar Remito</h3>
				&nbsp;- ID: {{ $remito->id }} - Remito: {{ $remito->codigo }}
                <div class="card-tools">
                    @if (empty($ocultarVolver))
                    <a href="{{route('remito')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                    @php
                        $puedeFacturarRemito = \App\Support\Ventas\RemitoEstadosSupport::puedeFacturarCabecera($remito);
                    @endphp
                    @if ($puedeFacturarRemito)
                        <button type="button" onclick="generaFactura()" class="btn btn-primary" data-padron-accion-factura="1">
                            <i class="fa fa-fw fa-print"></i>
                            Factura
                        </button>
                    @endif
                    @if ($remito->estadoremito == "Facturado" && $remito->venta_id)
                        @if (can('listar-factura', false))
                            <a href="{{route('lista_una_factura', ['id' => $remito->venta_id])}}" class="btn btn-primary" title="Listar la factura">
                                <i class="fas fa-file-pdf"> Listar Factura</i>
                            </a>
                        @endif
                    @endif
                    <a href="{{route('listar_remito_pdf', ['id' => $remito->id])}}" class="btn btn-primary" title="Listar el remito en PDF">
                        <i class="fas fa-file-pdf"> Listar Remito</i>
                    </a>
                    @if (can('listar-cuentacorriente-cliente', false))
                        <a href="{{route('listar_cuentacorriente_cliente', ['id' => $remito->cliente_id])}}" target="_blank" class="btn btn-primary" title="Cuenta Corriente">
                        <i class="fa fa-folder-open"> Listar Cuenta Corriente</i>
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_remito', ['id' => $remito->id])}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-articulo-solo-facturable="1" onsubmit="return typeof validarLugarEntregaAntesGuardar !== 'function' || validarLugarEntregaAntesGuardar();">
                @csrf @method("put")
                @if (!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$remito->codigo}}" >
        			<input type="hidden" id="remito_id" name="remito_id" value="{{$remito->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('ventas.remito.form', $datos)
                </div>
                <div class="card-footer">
                    @if ($remito->estadoremito != "Facturado")
                        <div class="row">
                            <div class="col-lg-6">
                                @if (!empty($puedeActualizarRemito))
                                    <button type="submit" onclick="sub()" class="btn btn-success remito-carga-bloqueable">Guardar</button>
                                @endif
                                @if (!empty($soloConsulta))
                                    <button type="button" class="btn btn-secondary @if(!empty($puedeActualizarRemito)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                                @endif
                            </div>
                        </div>
                    @elseif (!empty($soloConsulta))
                        <div class="text-center">
                            <button type="button" class="btn btn-secondary" onclick="window.close()">Cerrar solapa</button>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.arca_apoc_validacion_modal')
@endsection
