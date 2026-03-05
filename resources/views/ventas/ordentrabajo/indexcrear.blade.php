@extends("theme.$theme.layout")
@section('titulo')
	Crear Ordenes de trabajo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script>
	var checksId=[];
	var leyendas=[];

    $(function () {
		$(document).on('click', '.checkImpresion', sumaPares);

    	// Cierra modal 
    	$('#cierraOrdenTrabajoModal').on('click', function () {
    	});
	
    	// Acepta modal
    	$('#aceptaOrdenTrabajoModal').on('click', function () {
			var leyenda = JSON.stringify($("#leyendaot").val());
			var checkotstock = $("input:checkbox[class=checkboxotstock]:checked").val();
			var ids = JSON.stringify(checksId);
			var ordentrabajo_stock_codigo = $("#ordentrabajo_stock_codigo").val();
			var articulo_id = $("#articulo_id").val();
			var combinacion_id = $("#combinacion_id").val();

			if (ordentrabajo_stock_codigo == '')
				ordentrabajo_stock_codigo = 0;

			if (checkotstock == 'on' && ordentrabajo_stock_codigo == 0)
			{
				akert("Lote inexistente");
				return;
			}

			if (checkotstock == 'on')
			{
				var listarUri = "/anitaERP/public/ventas/controlaordentrabajostock/"+ordentrabajo_stock_codigo+"/"+articulo_id+"/"+combinacion_id;

				$.get(listarUri, function(data){
					if (data.estado != -1)
					{
						alert("Saldo lote "+ordentrabajo_stock_codigo+" "+data.saldo+" Deposito "+data.deposito_id);

						$('#crearOrdenTrabajoModal').modal('hide');

						var pattern = /[\^*@!"#$%&/,()=?¡!¿'\\]/gi;
						leyenda = leyenda.replace(pattern, '');

						if (checkotstock == 'on')
							var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/ordentrabajo/"
											+ids+"/on/"+ordentrabajo_stock_codigo+'/'+data.deposito_id+'/'+leyenda;
						else
							var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/ordentrabajo/"
											+ids+"/off/"+ordentrabajo_stock_codigo+'/'+data.deposito_id+'/'+leyenda;
				
						window.location.href = listarUri;
					}
					else	
					{
						alert("Lote inexistente");
						return;
					}
				});
			}
			else
			{
				$('#crearOrdenTrabajoModal').modal('hide');
				
				var pattern = /[\^*@!"#$%&/,()=?¡!¿'\\]/gi;
				leyenda = leyenda.replace(pattern, '');
						
				var listarUri = "/anitaERP/public/ventas/guardaordenestrabajo/ordentrabajo/"
								+ids+"/off/"+ordentrabajo_stock_codigo+'/1/'+leyenda;

				window.location.href = listarUri;
			}
		});
	});

	function sumaPares()
	{
		let pares = 0;

		$('input[type=checkbox]:checked').each(function() {
			var cantidad = $(this).parents('tr').find('.pares').html();

			pares += parseFloat(cantidad);
		});

		$("#totalpares").val(pares.toFixed(0));
	}
	function generaOt()
	{
		checksId=[];
		leyendas='';
		$('input[type=checkbox]:checked').each(function() {
			var itemId = $(this).parents('tr').find('.ids').html();
			var leyenda = $(this).parents('tr').find('.observaciones').html();
			checksId.push(itemId);
			if (leyenda !== "" && leyenda !== " " && leyenda !== null)
				leyendas = leyendas + leyenda + " ";
		});
		
		// Elimina caracteres especiales de la leyenda
		var pattern = /[\^*@!"#$%&/,()=?¡!¿'\\]/gi;
		leyendas = leyendas.replace(pattern, ' ');

		$("#leyendaot").val(leyendas);
		$("#crearOrdenTrabajoModal").modal('show');
	}

</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
			<input type="hidden" id="articulo_id" class="form-control" value="{{$articulo_id}}" />
			<input type="hidden" id="combinacion_id" class="form-control" value="{{$combinacion_id}}" />
            <div class="card-header">
                <h3 class="card-title">Crear Ordenes de trabajo</h3>
				@if ($datas[0] ?? '')
					<h3 class="card-title">&nbsp; Art&iacute;culo: {{$datas[0]->pedido_combinaciones[0]->articulo_id}}-{{$datas[0]->pedido_combinaciones[0]->articulos->descripcion}}</h2>
					<h3 class="card-title">&nbsp; Combinaci&oacute;n: {{$datas[0]->pedido_combinaciones[0]->combinaciones->nombre}}</h2>
				@endif
				<h3 class="card-title">&nbsp;</h3>
				<input type="text" class="col-lg-1" style="color:black;" value="Total pares:" readonly/>
				<input type="text" id="totalpares" class="col-lg-1" style="color:black;" value="" readonly/>
				<div class="card-tools">
                    @if (can('crear-ordenes-de-trabajo', false))
						<button type="submit" onclick="generaOt()" class="btn btn-primary">
                    		<i class="fa fa-fw fa-industry"></i>Genera OT
						</button>
					@endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha Pedido</th>
                            <th>Entrega</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Pares</th>
                            <th>Observacion</th>
                            <th>OT</th>
							<th class="width80" data-orderable="false">Edita Pedido</th>
                            <th class="width80" data-orderable="false">Genera OT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $pedido)
                        	@foreach ($pedido->pedido_combinaciones as $data)
                        	<tr>
								@php $estadoItem = " "; @endphp
								@foreach ($data->pedido_combinacion_estados as $estado)
									@php $estadoItem = $estado->estado; @endphp
								@endforeach

								@if ($estadoItem != "A")
									<td class="ids">{{$data->id}}</td>
									<td>{{date("d/m/Y", strtotime($pedido->fecha ?? ''))}}</td>
									<td>{{date("d/m/Y", strtotime($pedido->fechaentrega ?? ''))}}</td>
									<td>{{$pedido->codigo}}</td>
									<td>{{$pedido->clientes->nombre}}</td>
									<td class="pares">{{round($data->cantidad,0)}}</td>
									<td class="observaciones">{{$data->observacion}}</td>
									<td>{{$data->ot_id}}</td>
									<td>
									@if (can('editar-pedidos', false))
										<a href="{{route('editar_pedido', ['id' => $pedido->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
										<i class="fa fa-edit"></i>
										</a>
									@endif
									</td>
									<td>
										<input name="checks[]" class="checkImpresion" type="checkbox" autocomplete="off">
									</td>
								@endif
                        	</tr>
                        	@endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('ventas.ordentrabajo.modalcrearordentrabajo')

@endsection
