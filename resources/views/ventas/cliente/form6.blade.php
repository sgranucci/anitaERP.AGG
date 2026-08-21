<div id="tab-seguimiento" class="tab-pane fade card form6" role="tabpanel">
    <div class="card-body">
		{{-- Sin este marcador el repository no sincroniza: un submit que no incluya la solapa no debe borrar el historial. --}}
		<input type="hidden" name="seguimiento_en_formulario" value="1" />
    	<table class="table table-sm table-bordered" id="seguimiento-table">
    		<thead style="background:#85C1E9;color:#17202A;">
    			<tr>
    				<th style="width: 10%;">Fecha</th>
    				<th style="width: 30%;">Observacion</th>
    				<th style="width: 45%;">Leyenda</th>
					<th style="width: 15%;">Usuario</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-seguimiento">
				@if (($data->cliente_seguimientos ?? null) && $data->cliente_seguimientos->count())
					@foreach (old('seguimientos', $data->cliente_seguimientos) as $seguimiento)
						<tr class="item-seguimiento">
							<td>
								<input type="hidden" name="seguimientos[]" class="form-control iiseguimiento" readonly value="{{ $loop->index+1 }}" />
								<input type="date" name="fechas[]" class="form-control"
									value="{{ old('fechas.' . $loop->index, data_get($seguimiento, 'fecha', '')) }}" />
							</td>
							<td>
								<input type="text" name="observaciones[]" value="{{ old('observaciones.' . $loop->index, data_get($seguimiento, 'observacion', '')) }}" class="form-control observacion" placeholder="Observación">
							</td>
							<td>
								<!-- textarea -->
								<div class="form-group">
									<textarea name="leyendas[]" class="form-control" rows="3" placeholder="Leyenda ...">{{ old('leyendas.' . $loop->index, data_get($seguimiento, 'leyenda', '')) }}</textarea>
								</div>								
							</td>		
							<td>
								<input type="hidden" name="creousuario_ids[]" class="form-control creousuario_riesgo_id" value="{{ old('creousuario_ids.' . $loop->index, data_get($seguimiento, 'creousuario_id', '')) }}"/>
								<input type="text" name="creousuarios[]" class="form-control creousuario" value="{{ data_get($seguimiento, 'creousuarios.nombre', '') }}" readonly/>
							</td>												
							<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_seguimiento tooltipsC">
									<i class="fa fa-times-circle text-danger"></i>
								</button>
							</td>
						</tr>
					@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.cliente.template6')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_seguimiento" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
