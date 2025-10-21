<div class="form3" style="display: none">
    <div class="card-body">
    	<table class="table" id="premio-table">
    		<thead>
    			<tr>
    				<th>Fecha de Entrega</th>
					<th>Sala</th>
					<th>Juego</th>
					<th>Nro. de Tito</th>
					<th>Monto Premio</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-premio">
		 		@if (isset($data->cliente_premios_uif) ? count($data->cliente_premios_uif) > 0 : false)
					@foreach (old('premio', $data->cliente_premios_uif->count() ? $data->cliente_premios_uif : ['']) as $premio)
            			<tr class="item-premio">
                			<td>
                				<input type="hidden" name="premios[]" class="form-control iipremio" readonly value="{{ $loop->index+1 }}" />
								<input type="hidden" name="premio_ids[]" class="form-control premio_id" value="{{ $premio->id ?? '' }}" />
								<input type="datetime" name="fechaentregas[]" class="form-control fechaentrega" value="{{ $premio->fechaentrega->format('d-m-Y H:i:s') }}" />
                			</td>
							<td>
                				<input type="text" name="salas[]" class="form-control sala" readonly value="{{ $premio->salas->nombre }}" />
                			</td>
							<td>
                				<input type="text" name="detalles[]" class="form-control detalle" value="{{ $premio->juegos_uif->nombre }}" />
                			</td>
							<td>
                				<input type="text" name="numerotitos[]" class="form-control numerotito" value="{{ $premio->numerotito }}" />
                			</td>
							<td>
                				<input type="text" name="montopremios[]" class="form-control montopremio" value="{{ $premio->monto }}" />
                			</td>
                			<td>
								@if (can('editar-cliente-premio-uif', false))
                                	<a href="{{route('edita_cliente_premio_uif', ['id' => $premio->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
								@if (can('editar-cliente-premio-uif', false))
                                	<a href="{{route('muestra_foto_cliente_premio_uif', ['id' => $premio->id])}}" class="btn-accion-tabla tooltipsC" title="Muestra foto jugador">
                                    <i class="fa fa-address-card"></i>
                                	</a>
								@endif
								@if (can('editar-cliente-premio-uif', false))
                                	<a href="{{route('lista_un_cliente_premio_uif', ['id' => $premio->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el premio">
                                    <i class="fa fa-print"></i>
                                	</a>
								@endif
								@if (can('borrar-cliente-premio-uif', false))
									<button style="width: 7%;" type="button" title="Elimina el premio" class="btn-accion-tabla eliminar_premio tooltipsC">
										<i class="fa fa-times-circle text-danger"></i>
									</button>								
								@endif		
														
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('uif.cliente_uif.template2')
        <div class="row">
			@if (isset($data))
				<div class="col-md-12">
					<a href="{{route('crea_cliente_premio_uif', ['id' => $data->id])}}" class="btn btn-info pull-right">+ Nuevo Premio</a>
				</div>
			@endif
        </div>
    </div>	
</div>
