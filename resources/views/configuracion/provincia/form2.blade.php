<div class="card form2" style="display: none">
    <div class="card-body">
    	<table class="table" id="tasaiibb-table">
    		<thead>
    			<tr>
					<th>Condición IIBB</th=>
    				<th>Tasa</th=>
                    <th>Mínimo Neto</th=>
                    <th>Mínimo Percepción</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tasaiibb-table">
		 		@if ($data->provincia_tasaiibbs ?? '') 
				@if (count($data->provincia_tasaiibbs) > 0)
					@foreach (old('tasa', $data->provincia_tasaiibbs->count() ? $data->provincia_tasaiibbs : ['']) as $tasa)
            			<tr class="item-tasaiibb">
							<td>
								<select name="condicioniibb_ids[]" data-placeholder="Condicion IIBB" class="condicioniibb_id form-control" data-fouc>
									<option value="">-- Seleccionar --</option>
									@foreach($condicioniibb_query as $value)
										@if( (int) $value->id == (int) old('condicioniibb_ids[]', $tasa->condicioniibb_id ?? ''))
											<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
										@else
											<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
										@endif
									@endforeach
								</select>
							</td>
                            <td>
                                <input type="number" name="tasas[]" min="0" max="100" value="{{old('tasas.' . $loop->index, $tasa->tasa ?? '')}}" class="form-control tasa" placeholder="Tasa de percepción por defecto">
                            </td>
                            <td>
                                <input type="number" name="minimonetos[]" value="{{old('minimonetos.' . $loop->index, $tasa->minimoneto ?? '')}}" class="form-control minimoneto" placeholder="Mínimo Neto sujeto a Percepión">
                            </td>   
                            <td>
                                <input type="number" name="minimopercepciones[]" value="{{old('minimopercepciones.' . $loop->index, $tasa->minimopercepcion ?? '')}}" class="form-control minimopercepcion" placeholder="Monto Mínimo de Percepión">
                            </td>                                                        
                			<td>
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tasaiibb tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
								<input type="hidden" name="creousuario_tasa_ids[]" class="form-control creousuario_tasa_id" value="{{ $tasa->creousuario_id ?? ''}}"/>
                			</td>
                		</tr>
           			@endforeach
				@endif
				@endif
       		</tbody>
       	</table>
		@include('configuracion.provincia.template2')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_tasaiibb" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
