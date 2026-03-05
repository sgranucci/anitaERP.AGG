<div class="card form4" style="display: none">
    <div class="card-body">
    	<table class="table" id="riesgo-table">
    		<thead>
    			<tr>
					<th style="width: 25%;">Período</th>
    				<th style="width: 25%;">Inusualidad</th>
    				<th style="width: 25%;">Riesgo</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-riesgo">
		 		@if ($data->cliente_riesgos_uif ?? '') 
					@foreach (old('riesgo', $data->cliente_riesgos_uif->count() ? $data->cliente_riesgos_uif : ['']) as $riesgo)
            			<tr class="item-riesgo">
                			<td>
                				<input type="hidden" name="iiriesgos[]" class="form-control iiriesgo" readonly value="{{ $loop->index+1 }}" />
								<input type="hidden" name="riesgo_ids[]" class="form-control riesgo_id" readonly value="{{ $riesgo->id ?? ''}}" />
								<input type="hidden" name="creousuario_riesgo_ids[]" class="form-control creousuario_riesgo_id" value="{{ $riesgo->creousuario_id ?? ''}}" />
        						<div class="form-group">
        							<input type="month" name="periodos[]" value="{{old('periodos.' . $loop->index, $riesgo->periodo ?? '')}}" class="form-control periodo" placeholder="Periodo">
        						</div>
                			</td>
							<td>
        						<select name="inusualidad_uif_ids[]" data-placeholder="Inusualidad" class="form-control inusualidad_uif" data-fouc>
        							<option value="">-- Seleccionar --</option>
        							@foreach($inusualidad_uif_query as $key => $value)
        								@if( (int) $value->id == (int) old('inusualidad_uif_ids', $riesgo->inusualidad_uif_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
        					</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="riesgos[]" value="{{old('riesgos.' . $loop->index, $riesgo->riesgo ?? '')}}" class="form-control riesgo" placeholder="Riesgo asociado">
        						</div>
                			</td>
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_riesgo tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('uif.cliente_uif.template4')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_riesgo" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
