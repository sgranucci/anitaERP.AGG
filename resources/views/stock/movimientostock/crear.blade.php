@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de stock
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/movimientostock/crear.js")}}" type="text/javascript"></script>
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
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Movimientos de Stock</h3>
                <div class="card-tools">
                    <a href="{{route('movimientostock')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_movimientostock')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @php $datos = ["funcion" => "crear"]; @endphp
                    @include('stock.movimientostock.form', $datos)
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
