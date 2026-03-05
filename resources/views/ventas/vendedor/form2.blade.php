<div class="card form2" style="display: none">
	<div class="col-sm-6">
		<div class="card-body">
			<table class="table table-sm" id="vendedor-asociado-table">
				<thead>
					<tr>
						<th style="width: 30%;">Vendedor Asociado</th>
						<th style="width: 70%;">Nombre Vendedor</th>
					</tr>
				</thead>
				<tbody id="tbody-vendedor-asociado-table">
					@if ($data->vendedorasociados ?? '') 
					@if (count($data->vendedorasociados) > 0)
						@foreach (old('tasa', $data->vendedorasociados->count() ? $data->vendedorasociados : ['']) as $vendedorasociado)
							<tr class="item-vendedor-asociado">
								<td>
									<div class="form-group row" id="vendedorasociado">
										<input type="hidden" name="vendedorasociado[]" class="form-control iivendedorasociado" readonly value="{{ $loop->index+1 }}" />
										<input type="hidden" class="vendedor_id" name="vendedor_ids[]" value="{{$vendedorasociado->vendedorasociado_id ?? ''}}" >
										<input type="hidden" class="vendedor_id_previo" name="vendedor_id_previo[]" value="{{$vendedorasociado->vendedorasociado_id ?? ''}}" >
										<button type="button" title="Consulta Vendedores" style="padding:1;" class="btn-accion-tabla consultavendedor tooltipsC">
												<i class="fa fa-search text-primary"></i>
										</button>
										<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigovendedor form-control" name="codigovendedores[]" value="{{$vendedorasociado->vendedorasociados->codigo ?? ''}}" >
									</div>
								</td>			
								<td>
									<input type="text" style="WIDTH: 400px; HEIGHT: 38px" class="nombrevendedor form-control" name="nombrevendedores[]" value="{{$vendedorasociado->vendedorasociados->nombre ?? ''}}" readonly>
								</td>														
								<td>
									<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_vendedorasociado tooltipsC">
										<i class="fa fa-times-circle text-danger"></i>
									</button>
								</td>
							</tr>
						@endforeach
					@endif
					@endif
				</tbody>
			</table>
			@include('ventas.vendedor.template2')
			<div class="row">
				<div class="col-md-12">
					<button id="agrega_renglon_vendedorasociado" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
				</div>
			</div>
		</div>
	</div>
</div>

