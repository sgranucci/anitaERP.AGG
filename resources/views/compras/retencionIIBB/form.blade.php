<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $retencionIIBB->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label class="col-lg-3 col-form-label requerido">Provincia</label>
	<select name="provincia_id" id="provincia_id" data-placeholder="Provincia" class="col-lg-3 form-control required" data-fouc>
		<option value="">-- Seleccionar --</option>
		@foreach($provincia_query as $key => $value)
			@if( (int) $value->id == (int) old('provincia_id', $retencionIIBB->provincia_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }} Jur: {{ $value->jurisdiccion }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }} Jur: {{ $value->jurisdiccion }}</option>    
			@endif
		@endforeach
	</select>
	<input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia', $retencionIIBB->desc_provincia ?? '')}}" >
</div>
<div class="form-group row">
	<label for="cuentacontable" class="col-lg-3 col-form-label">Cuenta contable</label>
	<select name="cuentacontable_id" id="cuentacontable_id" data-placeholder="Cuenta contable para imputaciones" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar Cta. Contable --</option>
		@foreach($cuentacontable_query as $key => $value)
			@if( (int) $value->id == (int) old('cuentacontable_id', $retencionIIBB->cuentacontable_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="card">
    <div class="card-header">
    	Condiciones de IIBB
    </div>

    <div class="card-body">
    	<table class="table" id="cuotas-table">
    		<thead>
    			<tr>
					<th>Renglón</th>
    				<th>Condición IIBB</th>
    				<th>Mínimo retención</th>
    				<th>Mínimo imponible</th>
    				<th>Porcentaje retención</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($retencionIIBB->retencionIIBB_condiciones ?? '') 
					@foreach (old('cuotas', $retencionIIBB->retencionIIBB_condiciones->count() ? $retencionIIBB->retencionIIBB_condiciones : ['']) as $retencionIIBB_condicion)
            			<tr class="item-cuota">
							<td>
                				<input type="number" name="cuotas[]" class="form-control iicuota" readonly value="{{ $loop->index+1 }}" />
                			</td>
                			<td>
        						<select name="condicionIIBB_ids[]" data-placeholder="Condiciones de IIBB" class="form-control condicionIIBB" data-fouc>
									<option value="">-- Seleccionar Condición de IIBB --</option>
									@foreach ($condicionIIBB_query as $condicionIIBB)
										<option value="{{ $condicionIIBB->id }}"
										@if (old('condicionIIBB_ids.' . $loop->parent->index, optional($retencionIIBB_condicion)->condicionIIBB_id) == $condicionIIBB->id) selected @endif
										>{{ $condicionIIBB->nombre }}</option>
									@endforeach
								</select>
                			</td>
							<td>
                				<input type="number" name="minimoretenciones[]" class="form-control minimoretencion"
                					value="{{ (old('minimoretenciones.' . $loop->index) ?? optional($retencionIIBB_condicion)->minimoretencion) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="number" name="minimoimponibles[]" class="form-control minimoimponible"
                					value="{{ (old('minimoimponibles.' . $loop->index) ?? optional($retencionIIBB_condicion)->minimoimponible) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="number" name="porcentajeretenciones[]" class="form-control porcentajeretencion"
                					value="{{ (old('porcentajeretenciones.' . $loop->index) ?? optional($retencionIIBB_condicion)->porcentajeretencion) ?? '0' }}" />
                			</td>							
                			<td>
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@else
            		<tr class="item-cuota">
						<td>
                			<input type="number" id="icuota" name="cuotas[]" class="form-control iicuota" readonly value="1" />
                		</td>
						<td>
							<select name="condicionIIBB_ids[]" data-placeholder="Condiciones de IIBB" class="form-control condicionIIBB" data-fouc>
									<option value="">-- Seleccionar Condición de IIBB --</option>
									@foreach ($condicionIIBB_query as $condicionIIBB)
										<option value="{{ $condicionIIBB->id }}"
										@if (old('condicionIIBB_ids.' . $loop->parent->index, optional($retencionIIBB_condicion)->condicionIIBB_id) == $condicionIIBB->id) selected @endif
										>{{ $condicionIIBB->nombre }}</option>
									@endforeach
							</select>
						</td>
                		<td>
                			<input type="number" id="minimoretencion" name="minimoretenciones[]" class="form-control minimoretencion" value="0"/>
                		</td>
						<td>
                			<input type="number" id="minimoimponibl" name="minimoimponibles[]" class="form-control minimoimponible" value="0" />
                		</td>
						<td>
                			<input type="number" id="porcentajeretencion" name="porcentajeretenciones[]" class="form-control porcentajeretencion" value="0" />
                		</td>
						<td>
							<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                            	<i class="fa fa-times-circle text-danger"></i>
							</button>
                		</td>
           			</tr>
				@endif
       		</tbody>
       	</table>
		@include('compras.retencionIIBB.template')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
