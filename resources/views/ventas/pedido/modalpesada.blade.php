<div class="modal fade" id="pesadaModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
<div class="modal-dialog modal-xl" role="document">
	<div class="modal-content">
		<div class="modal-header">
			<h5 class="modal-title" id="exampleModalLabel">Pesada del Pedido</h5>
			<button type="button" class="close" data-dismiss="modal" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="modal-body">
			<form action="" method="post">
				<div class="form-group row">
					<label for="lectura_qr_pesada" class="col-form-label">Lectura QR:</label>
					<input type="text" id="lecturaqrpesada" autofocus>
				</div>
			</form>
			<div class="form-group">
				<table class="table table-hover" id="pesadapedido-table">
				<thead>
					<tr>
						<th style="width: 10%;">Id Caja</th>
						<th style="width: 15%;">Art&iacute;culo</th>
						<th style="width: 20%;">Descripción Artículo</th>
						<th style="width: 8%;">UMD</th>
						<th style="width: 15%;">Lote</th>
						<th style="width: 10%;">Vencimiento</th>
						<th style="width: 10%;">Piezas</th>
						<th style="width: 10%;">Kilos</th>
					</tr>
				</thead>
				<tbody id="tbody-tabla-pesada">     
					@if (isset($pedido))
			 		@if (count($pedido->pedido_articulo_cajas) > 0) 
						@foreach (old('items', $pedido->pedido_articulo_cajas->count() ? $pedido->pedido_articulo_cajas : ['']) as $pesada)
							<tr class="item-pesada">
								<td>
									<input type="text" class="form-control numerocajapesada" name="numerocajapesadas[]" value="{{$pesada->numerocaja}}" readonly>
									<input type="hidden" class="form-control pedido_articulo_id" name="pedido_articulo_ids[]" value="{{$pesada->pedido_articulo_id}}">
								</td>
								<td>
									<input type="hidden" class="articulopesada_id" name="articulopesada_ids[]" value="{{$pesada->pedido_articulos->articulo_id}}" >
									<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulopesada form-control" name="codigoarticulopesadas[]" value="{{$pesada->pedido_articulos->articulos->sku}}" readonly>
								</td>		
								<td>
									<input type="text" style="WIDTH: 220px; HEIGHT: 38px" class="descripcionarticulopesada form-control" name="descripcionarticulopesadas[]" value="{{$pesada->pedido_articulos->articulos->descripcion}}" readonly>
								</td>	
								<td>
									<input type="text" name="unidadmedidapesadas[]" class="form-control unidadmedidapesada" value="{{$pesada->pedido_articulos->articulos->unidadesdemedidas->abreviatura}}" />								
								</td>		
								<td>
									<input type="text" name="lotepesadas[]" class="form-control lotepesada" value="{{$pesada->lote}}" />
								</td>		
								<td>
									<input type="date" name="fechavencimientopesadas[]" class="form-control fechavencimientopesada" value="{{$pesada->fechavencimiento}}" />
								</td>				
								<td>
									<input type="text" name="piezapesadas[]" class="form-control piezapesada" value="{{number_format($pesada->pieza,0)}}" />
								</td>	
								<td>
									<input type="text" name="kilopesadas[]" class="form-control kilopesada" value="{{number_format($pesada->kilo,2)}}" />
								</td>	
								<td>
									<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarpesada tooltipsC">
										<i class="fa fa-trash text-danger"></i>
									</button>
									<input type="hidden" name="creousuariopesada_ids[]" class="form-control creousuariopesada_id" value="{{ auth()->id() }}"/>
								</td>
							</tr>
						@endforeach
					@endif
				@endif
				</tbody>       
				</table>
			</div>
			@include('ventas.pedido.templatepesada')
			<div class="row col-md-12">
				<div class="col-md-2">
					<button id="agrega_renglon_pesada" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" id="cierraPesadaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
			<button type="button" id="aceptaPesadaModal" class="btn btn-primary">Acepta Pesadas</button>
		</div>
	</div>
</div>
</div>
