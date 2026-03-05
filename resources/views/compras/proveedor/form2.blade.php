<div class="form2" style="display: none">
	<h3>Datos de impuestos</h3>
	<div class="row">
		<div class="col-sm-6">
			<div class="form-group row">
				<label for="nroinscripcion" class="col-lg-4 col-form-label requerido">C.U.I.T.</label>
				<div class="col-lg-3">
					<input type="text" name="nroinscripcion" id="nroinscripcion" class="form-control" value="{{old('nroinscripcion', $data->nroinscripcion ?? '')}}" required/>
				</div>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="nroIIBB" class="col-lg-4 col-form-label requerido">Nro.IIBB</label>
				@else
					<label for="nroIIBB" class="col-lg-4 col-form-label">Nro.IIBB</label>
				@endif
				<div class="col-lg-3">
					<input type="text" name="nroIIBB" id="nroIIBB" class="form-control" value="{{old('nroIIBB', $data->nroIIBB ?? '')}}" @if ($tipoalta != 'P') required @endif/>
				</div>
			</div>
			<div class="form-group row">
				<label for="condicioniva_id" class="col-lg-4 col-form-label requerido">Condicion de iva.</label>
				<select name="condicioniva_id" id="condicioniva_id" data-placeholder="Condicion de iva" class="col-lg-5 form-control" required data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($condicioniva_query as $key => $value)
						@if( (int) $value->id == (int) old('condicioniva_id', $data->condicioniva_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
				<input type="hidden" id="condicioniva_query" value="{{$condicioniva_query}}">
			</div>
			<div class="form-group row">
				<label for="letra" class="col-lg-4 col-form-label">Letra </label>
				<div class="col-lg-2">
					<input type="text" name="letra" id="letra" class="form-control" value="" readonly>
				</div>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="retieneiva" class="col-lg-4 col-form-label requerido">Retiene iva</label>
				@else
					<label for="retieneiva" class="col-lg-4 col-form-label">Retiene iva</label>
				@endif
				<select name="retieneiva" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija retiene iva --</option>
					@foreach ($retieneiva_enum as $value => $retieneiva)
						<option value="{{ $value }}"
							@if (old('retieneiva', $data->retieneiva ?? '') == $value) selected @endif
							>{{ $retieneiva }}</option>
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="retencioniva" class="col-lg-4 col-form-label">Codigo de retención de iva</label>
				<select name="retencioniva_id" id="retencioniva_id" data-placeholder="Codigo de retención de iva" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar Código --</option>
					@foreach($retencioniva_query as $key => $value)
						@if( (int) $value->id == (int) old('retencioniva_id', $data->retencioniva_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="agentepercepcioniva" class="col-lg-4 col-form-label requerido">Agente de perc. iva</label>
				@else
					<label for="agentepercepcioniva" class="col-lg-4 col-form-label">Agente de perc. iva</label>
				@endif
				<select name="agentepercepcioniva" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija Agente de perc. iva --</option>
					@foreach ($agentepercepcioniva_enum as $value => $agentepercepcioniva)
						<option value="{{ $value }}"
							@if (old('agentepercepcioniva', $data->agentepercepcioniva ?? '') == $value) selected @endif
							>{{ $agentepercepcioniva }}</option>
					@endforeach
				</select>
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="retieneganancia" class="col-lg-4 col-form-label requerido">Retiene Ganancias</label>
				@else
					<label for="retieneganancia" class="col-lg-4 col-form-label">Retiene Ganancias</label>
				@endif
				<select name="retieneganancia" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija retiene iva --</option>
					@foreach ($retieneganancia_enum as $value => $retieneganancia)
						<option value="{{ $value }}"
							@if (old('retieneganancia', $data->retieneganancia ?? '') == $value) selected @endif
							>{{ $retieneganancia }}</option>
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="condicionganancia" class="col-lg-4 col-form-label requerido">Condición de Ganancias</label>
				@else
					<label for="condicionganancia" class="col-lg-4 col-form-label">Condición de Ganancias</label>
				@endif
				<select name="condicionganancia" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija condicion de ganancias --</option>
					@foreach ($condicionganancia_enum as $value => $condicionganancia)
						<option value="{{ $value }}"
							@if (old('condicionganancia', $data->condicionganancia ?? '') == $value) selected @endif
							>{{ $condicionganancia }}</option>
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="retencionganancia" class="col-lg-4 col-form-label">Codigo de retención de ganancias</label>
				<select name="retencionganancia_id" id="retencionganancia_id" data-placeholder="Codigo de retención de ganancias" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar Código --</option>
					@foreach($retencionganancia_query as $key => $value)
						@if( (int) $value->id == (int) old('retencionganancia_id', $data->retencionganancia_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="retienesuss" class="col-lg-4 col-form-label requerido">Retiene SUSS</label>
				@else
					<label for="retienesuss" class="col-lg-4 col-form-label">Retiene SUSS</label>
				@endif
				<select name="retienesuss" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija retiene iva --</option>
					@foreach ($retienesuss_enum as $value => $retienesuss)
						<option value="{{ $value }}"
							@if (old('retienesuss', $data->retienesuss ?? '') == $value) selected @endif
							>{{ $retienesuss }}</option>
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="retencionsuss" class="col-lg-4 col-form-label">Codigo de retención de suss</label>
				<select name="retencionsuss_id" id="retencionsuss_id" data-placeholder="Codigo de retención de suss" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar Código --</option>
					@foreach($retencionsuss_query as $key => $value)
						@if( (int) $value->id == (int) old('retencionsuss_id', $data->retencionsuss_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="condicionIIBB" class="col-lg-4 col-form-label">Condición de IIBB</label>
				<select name="condicionIIBB_id" id="condicionIIBB_id" data-placeholder="Condicion de retencion de ingresos brutos" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar Código --</option>
					@foreach($condicionIIBB_query as $key => $value)
						@if( (int) $value->id == (int) old('condicionIIBB_id', $data->condicionIIBB_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="agentepercepcionIIBB" class="col-lg-4 col-form-label requerido">Agente de perc. IIBB</label>
				@else
					<label for="agentepercepcionIIBB" class="col-lg-4 col-form-label">Agente de perc. IIBB</label>
				@endif
				<select name="agentepercepcionIIBB" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija Agente de perc. IIBB --</option>
					@foreach ($agentepercepcionIIBB_enum as $value => $agentepercepcionIIBB)
						<option value="{{ $value }}"
							@if (old('agentepercepcionIIBB', $data->agentepercepcionIIBB ?? '') == $value) selected @endif
							>{{ $agentepercepcionIIBB }}</option>
					@endforeach
				</select>
			</div>
		</div>
	</div>
	<h3>Exclusiones</h3>
	<div class="card-body">
    	<table class="table" id="exclusion-table">
    		<thead>
    			<tr>
					<th style="width: 4%;"></th>
    				<th style="width: 12%;">Desde fecha</th>
    				<th style="width: 12%;">Hasta fecha</th>
					<th>Tipo de retención</th>
    				<th style="width: 10%;">Porc.Excl.</th>
    				<th style="width: 30%;">Comentario</th>
    				<th></th>
				</tr>
    		</thead>
    		<tbody id="tbody-exclusion-table">
			@if ($data->proveedor_exclusiones ?? '') 
				@foreach (old('exclusiones', $data->proveedor_exclusiones->count() ? $data->proveedor_exclusiones : ['']) as $exclusion)
					<tr class="item-exclusion">
						<td>
							<input type="text" name="exclusiones[]" class="form-control iiexclusion" readonly value="{{ $loop->index+1 }}" />
						</td>
						<td>
							<input type="date" name="desdefechas[]" class="form-control"
								value="{{ (old('desdefechas.' . $loop->index) ?? optional($exclusion)->desdefecha) ?? '' }}" />
						</td>
						<td>
							<input type="date" name="hastafechas[]" class="form-control"
								value="{{ (old('hastafechas.' . $loop->index) ?? optional($exclusion)->hastafecha) ?? '' }}" />
						</td>
						<td>
							<select name="tiporetenciones[]" id="formpago_ids" data-placeholder="Forma de pago" class="form-control formapago" data-fouc>
								<option value="">-- Elija tipo de retención --</option>
								@foreach ($tiporetencion_enum as $value => $tiporetencion)
									<option value="{{ $value }}"
										@if (old('tiporetenciones', $exclusion->tiporetencion ?? '') == $value) selected @endif
										>{{ $tiporetencion }}</option>
								@endforeach
							</select>
						</td>
						<td>
							<input type="number" name="porcentajeexclusiones[]" class="form-control"
								value="{{ (old('porcentajeexclusiones.' . $loop->index) ?? optional($exclusion)->porcentajeexclusion) ?? '' }}" />
						</td>
						<td>
							<input type="text" name="comentarios[]" class="form-control"
								value="{{ (old('comentarios.' . $loop->index) ?? optional($exclusion)->comentario) ?? '' }}" />
						</td>
						<td>
							<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_exclusion tooltipsC">
								<i class="fa fa-times-circle text-danger"></i>
							</button>
						</td>
					</tr>
				@endforeach
			@endif
			</tbody>
		</table>
		@include('compras.proveedor.template2')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_exclusion" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
	</div>
</div>
