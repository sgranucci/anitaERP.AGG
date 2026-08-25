@extends("theme.$theme.layout")
@section('titulo')
    Pedidos de clientes
@endsection

@section("scripts")
@php
    $requiereValidacionApocOperacion = filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN)
        && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<script>window.VALIDACION_PADRON_POST_CARGA = true;</script>
<script>window.REQUIERE_VALIDACION_APOC_OPERACION = @json($requiereValidacionApocOperacion);</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/pedido/proceso-overlay.js")}}" type="text/javascript"></script>
@include('includes.ventas.preferencias_facturacion_scripts')
@include('ventas.partials.aviso_deposito_facturacion')
@include('includes.ventas.cliente_despacho_js')
<script src="{{asset("assets/pages/scripts/ventas/pedido/crear.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/crear.js')) ?: time() }}" type="text/javascript"></script>

<script>
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
    var PROFORMA = "{{ config('cliente.PROFORMA') }}";
    var MOROSO = "{{ config('cliente.MOROSO') }}";
    var NO_FACTURAR = "{{ config('cliente.NO_FACTURAR') }}";

	function sub()
	{
        if (window.AnitaGrabacion && window.AnitaGrabacion.enCurso()) {
            return false;
        }

        if ($('#formgeneral').hasClass('pedido-bloqueado-padron')) {
            if (typeof window.notificarBloqueoPadronCliente === 'function') {
                window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede guardar el pedido con este cliente.');
            } else {
                alert('Problemas en ARCA: no puede guardar el pedido con este cliente.');
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

        var form = document.getElementById('formgeneral');
        $('.pedido-carga-bloqueable').filter('button, input[type="submit"]').prop('disabled', true);
        if (window.AnitaGrabacion && typeof window.AnitaGrabacion.enviar === 'function') {
            window.AnitaGrabacion.enviar(form);
        } else if (form) {
            form.submit();
        }
        return false;
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

        var cliente_id = $("#cliente_id").val();
        if (cliente_id > 0) {
            completarCliente_Entrega(cliente_id);
            asignaDatosCliente(cliente_id, false);
        }

        setTimeout(() => {
            muestraTipoSuspension();
        }, 1000);
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
                <h3 class="card-title">Editar Pedidos de clientes</h3>
				&nbsp;- ID: {{ $pedido->id }} - Pedido: {{$pedido->codigo}}
                <div class="card-tools">
                    @if (empty($ocultarVolver))
                    <a href="{{ route('pedido', $filtrosQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                    @if (empty($pedidoTransferido) && $pedido->estadopedido != "Facturado")
					<button type="submit" onclick="pesada()" class="btn btn-success">
                    	<i class="fa fa-fw fa-check"></i>
						Pesada
					</button>
                    @endif
                    @if (!empty($mostrarTransferirDespacho))
                        <button type="button" onclick="transferirPedidoDespacho()" class="btn btn-success">
                            <i class="fa fa-fw fa-exchange-alt"></i>
                            Transferir al despacho
                        </button>
                    @endif
                    @if (!empty($pedidoTransferido) && !empty($pedido->transferencia_mercaderia_id) && can('crear-transferencia-mercaderia', false))
                        <a href="{{ route('transferencia_mercaderia') }}" class="btn btn-outline-success btn-sm" title="Ver transferencias de mercadería" target="_blank" rel="noopener">
                            <i class="fa fa-fw fa-exchange-alt"></i> TM #{{ $pedido->transferencia_mercaderia_id }}
                        </a>
                    @endif
                    @if (!empty($mostrarFacturarPedido) && $pedido->estadopedido != "Facturado" && $pedido->estadopedido != "Suspendido")
                        <button type="button" onclick="generaFactura()" class="btn btn-primary" data-padron-accion-factura="1">
                            <i class="fa fa-fw fa-print"></i>
                            Factura
                        </button>
                        @if (can('crear-remitos', false))
                        <button type="button" onclick="generaRemito()" class="btn btn-info" data-padron-accion-factura="1">
                            <i class="fa fa-fw fa-truck"></i>
                            Remito
                        </button>
                        @endif
                    @endif
                    @if ($pedido->estadopedido != "Facturado" && empty($pedidoTransferido))
                        <button type="submit" onclick="suspendePedido()" id="suspendepedido" class="btn btn-warning">
                            <i class="fa fa-fw fa-cross"></i>
                            Suspender el Pedido
                        </button>
                    @else
                        <input type="hidden" id="suspendepedido" value="">
                    @endif
                    @php
                        $ventasPedido = \App\Support\Ventas\PedidoFacturaAnitaArchivosSupport::ventasVisiblesEnPedido($pedido->ventas ?? []);
                    @endphp
                    @if ($ventasPedido->isNotEmpty() && can('listar-factura', false))
                        @foreach ($ventasPedido as $ventaPedido)
                            <a href="{{ route('lista_una_factura', ['id' => $ventaPedido->id]) }}" class="btn btn-primary" title="Listar {{ $ventaPedido->codigo }}" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf"></i> {{ $ventaPedido->codigo }}
                            </a>
                        @endforeach
                    @endif
                    <a href="{{route('listar_pedido_pdf', ['id' => $pedido['id']])}}" class="btn btn-primary" title="Listar el pedido en PDF">
                        <i class="fas fa-file-pdf"> Listar Pedido</i>
                    </a>      
                    @if (can('listar-cuentacorriente-cliente', false))
                        <a href="{{route('listar_cuentacorriente_cliente', ['id' => $pedido->cliente_id])}}" target="_blank" class="btn btn-primary" title="Cuenta Corriente">
                        <i class="fa fa-folder-open"> Listar Cuenta Corriente</i>
                        </a>
                    @endif                                  
                </div>
            </div>
            <form action="{{ route('actualizar_pedido', ['id' => $pedido->id] + ($filtrosQuery ?? [])) }}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-articulo-solo-facturable="1" data-mensaje-grabacion="Grabando pedido…" onsubmit="return typeof validarLugarEntregaAntesGuardar !== 'function' || validarLugarEntregaAntesGuardar();">
                @csrf @method("put")
                @if (!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$pedido->codigo}}" >
        			<input type="hidden" id="pedido_id" name="pedido_id" value="{{$pedido->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('ventas.pedido.form', $datos)
                </div>
                <div class="card-footer">
                    @if ($pedido->estadopedido != "Facturado" && empty($pedidoTransferido))
                        <div class="row">
                            <div class="col-lg-6">
                                @if (!empty($puedeActualizarPedido))
                                    <button type="submit" onclick="return sub()" class="btn btn-success pedido-carga-bloqueable">Guardar</button>
                                @endif
                                @if (!empty($soloConsulta))
                                    <button type="button" class="btn btn-secondary @if(!empty($puedeActualizarPedido)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
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
            @if (!empty($mostrarTransferirDespacho))
            <form id="form-transferir-despacho" action="{{ route('transferir_pedido_despacho', $pedido->id) }}" method="POST" class="d-none">
                @csrf
            </form>
            @endif
        </div>
    </div>
</div>
@include('includes.compras.arca_apoc_validacion_modal')
@endsection
