@extends("theme.$theme.layout")
@section('titulo')
    Pedidos de clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/pedido/crearferli.js")}}" type="text/javascript"></script>

<script>
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
    var PROFORMA = "{{ config('cliente.PROFORMA') }}";
    var MOROSO = "{{ config('cliente.MOROSO') }}";
    var NO_FACTURAR = "{{ config('cliente.NO_FACTURAR') }}";

	function sub()
	{
        // Validar precio en 0
        $(".precio").each(function(){
            precio = $(this).val();

			//if (precio == 0)
			//{
			  	//alert("No puede generar pedidos con precio 0");
				//return false;
			//}
        });

   		// Cuenta los articulos para validar cantidad maxima
        var cantidadArticulo = 0;
		$("#tbody-tabla .articulo").each(function(index) {
			cantidadArticulo = cantidadArticulo + 1;

			let articulo = $(this);
			let combinacion = $(this).parents("tr").find(".combinacion");

			articulo.prop('disabled', false);
			combinacion.prop('disabled', false);
		});

        if (cantidadArticulo > 42)
        {
            alert("No puede generar pedidos con mas de 42 ítems");
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
					<button type="submit" onclick="preparaPreFactura()" class="btn btn-primary">
                    	<i class="fa fa-fw fa-print"></i>
						Pre-Factura
					</button>
                    <button type="submit" onclick="preparaFactura()" class="btn btn-primary">
                    	<i class="fa fa-fw fa-print"></i>
						Factura
					</button>
                </div>
            </div>
            <form action="{{route('actualizar_pedido', ['id' => $pedido->id])}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$pedido->codigo}}" >
        			<input type="hidden" id="pedidoid" name="pedidoid" value="{{$pedido->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('ventas.pedido_ferli.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							<button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
							<button type="submit" onclick="imprimePreFactura()" style="display:none;" id="imprimePreFactura" class="btn btn-success">
                            	<i class="fa fa-print"></i>
								Imprime pre-factura
							</button>
                            <button type="submit" onclick="generaFactura()" style="display:none;" id="generaFactura" class="btn btn-success">
                            	<i class="fa fa-print"></i>
								Genera factura
							</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
