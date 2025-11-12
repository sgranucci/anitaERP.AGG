@extends("theme.$theme.layout")
@section('titulo')
    Pedidos de clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/pedido/crear.js")}}" type="text/javascript"></script>
<script>
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
	function sub()
	{

        // Cuenta los articulos para validar cantidad maxima
        var cantidadArticulo = 0;

        $("#tbody-tabla .articulo").each(function(index) {
            cantidadArticulo = cantidadArticulo + 1;
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
                <h3 class="card-title">Crear Pedidos de clientes</h3>
                <div class="card-tools">
                    <a href="{{route('pedido')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_pedido')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @php $datos = ["funcion" => "crear"]; @endphp
                    @include('ventas.pedido.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							<button type="submit" onclick="sub()" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
