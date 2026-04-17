@extends("theme.$theme.layout")
@section('titulo')
    Pedidos de clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/pedido/crear.js")}}" type="text/javascript"></script>

<script>
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
    var PROFORMA = "{{ config('cliente.PROFORMA') }}";
    var MOROSO = "{{ config('cliente.MOROSO') }}";
    var NO_FACTURAR = "{{ config('cliente.NO_FACTURAR') }}";

	function sub()
	{
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
        completarCliente_Entrega(cliente_id);
        asignaDatosCliente(cliente_id, false);

        setTimeout(() => {
            muestraTipoSuspension();
        }, 1000);

        if ($("#cliente_entrega_id_previa").val() != "") {
            setTimeout(() => {
                    $("#cliente_entrega_id").val($("#cliente_entrega_id_previa").val());
            }, 1000);
        }
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
                    <a href="{{route('pedido')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
					<button type="submit" onclick="pesada()" class="btn btn-success">
                    	<i class="fa fa-fw fa-check"></i>
						Pesada
					</button>
                    @if ($pedido->estadopedido != "Facturado" && $pedido->estadopedido != "Suspendido")
                        <button type="submit" onclick="generaFactura()" class="btn btn-primary">
                            <i class="fa fa-fw fa-print"></i>
                            Factura
                        </button>
                    @endif
                    @if ($pedido->estadopedido != "Facturado")
                        <button type="submit" onclick="suspendePedido()" id="suspendepedido" class="btn btn-warning">
                            <i class="fa fa-fw fa-cross"></i>
                            Suspender el Pedido
                        </button>
                    @else
                        <input type="hidden" id="suspendepedido" value="">
                    @endif
                    @if ($pedido->estadopedido == "Facturado" && isset($pedido->ventas[0]->id))
                        @if (can('listar-factura', false))
                            <a href="{{route('lista_una_factura', ['id' => $pedido->ventas[0]->id])}}" class="btn btn-primary" title="Listar la factura">
                                <i class="fas fa-file-pdf"> Listar Factura</i>
                            </a>                    
                        @endif  
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
            <form action="{{route('actualizar_pedido', ['id' => $pedido->id])}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$pedido->codigo}}" >
        			<input type="hidden" id="pedido_id" name="pedido_id" value="{{$pedido->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('ventas.pedido.form', $datos)
                </div>
                <div class="card-footer">
                    @if ($pedido->estadopedido != "Facturado")
                        <div class="row">
                            <div class="col-lg-6">
                                <button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
                            </div>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
