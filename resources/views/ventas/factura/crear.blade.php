@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de stock
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/factura/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>

<script>
    function sub()
	{
        var tipotransaccion_id = $("#tipotransaccion_id").val();

        if (tipotransaccion_id == '')
        {
            alert('No puede grabar sin un tipo de transacción');
            return;
        }

        // Controla datos correctos
		$("#tbody-tabla .articulo").each(function(index) {
			var articulo = $(this);
			var combinacion = $(this).parents("tr").find(".combinacion").val();
			var combinacion_id = $(this).parents("tr").find(".combinacion_id_previa").val();
			var modulo_id = $(this).parents("tr").find(".modulo_id_previa").val();

		});

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
                <h3 class="card-title">Crear Comprobante de Venta</h3>
                <div class="card-tools">
                    <a href="{{route('factura')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_factura')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @php $datos = ["funcion" => "crear"]; @endphp
                    @include('ventas.factura.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							<button type="submit" onclick="subm()" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
