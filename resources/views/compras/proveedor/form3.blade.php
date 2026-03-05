<div class="card form3" style="display: none">
    <div class="card-body">
		<h3>Formas de pago</h3>
    	<table class="table" id="formapago-table">
    		<thead>
    			<tr>
    				<th style="width: 5%;">Cod.</th>
    				<th style="width: 10%;">Nombre</th>
    				<th style="width: 10%;">Forma de pago</th>
    				<th style="width: 10%;">CBU</th>
    				<th style="width: 5%;">TC</th>
    				<th>Moneda</th>
    				<th style="width: 10%;">Número de cuenta</th>
					<th style="width: 10%;">C.U.I.T.</th>
					<th style="width: 10%;">Banco</th>
					<th>Medio de pago</thstyle=>
					<th>Email confirmación pago</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-formapago-table">
		 		@if ($data->proveedor_formapagos ?? '') 
					@foreach (old('formapagos', $data->proveedor_formapagos->count() ? $data->proveedor_formapagos : ['']) as $formapago)
            			<tr class="item-formapago">
                			<td>
                				<input type="text" name="formapagos[]" class="form-control iiformapago" readonly value="{{ $loop->index+1 }}" />
                			</td>
                			<td>
                				<input type="text" name="nombres[]" class="form-control"
                					value="{{ (old('nombres.' . $loop->index) ?? optional($formapago)->nombre) ?? '' }}" />
                			</td>
                			<td>
								<select name="formapago_ids[]" id="formapago_ids" data-placeholder="Forma de pago" class="form-control formapago" data-fouc>
        							<option value=""></option>
        							@foreach($formapago_query as $key => $value)
        								@if( (int) $value->id == (int) old('formapago_ids', $formapago->formapago_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
                			</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="cbus[]" value="{{old('cbus.' . $loop->index, $formapago->cbu ?? '')}}" class="form-control cbus" placeholder="CBU">
        						</div>
                			</td>							
							<td>
        						<select name="tipocuentacaja_ids[]" id="tipocuentacaja_ids" data-placeholder="Tipo de cuenta de caja" class="form-control tipocuentacaja" data-fouc>
        							<option value=""></option>
        							@foreach($tipocuentacaja_query as $key => $value)
        								@if( (int) $value->id == (int) old('tipocuentacaja_ids', $formapago->tipocuentacaja_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
        								@endif
        							@endforeach
        						</select>
        					</td>
							<td>
								<select name="moneda_ids[]" id="moneda_ids" data-placeholder="Moneda" class="form-control moneda" data-fouc>
        							<option value=""></option>
        							@foreach($moneda_query as $key => $value)
        								@if( (int) $value->id == (int) old('moneda_ids', $formapago->moneda_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
        								@endif
        							@endforeach
        						</select>
							</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="numerocuentas[]" value="{{old('numerocuentas.' . $loop->index, $formapago->numerocuenta ?? '')}}" class="form-control numerocuentas" placeholder="Nro.cuenta">
        						</div>
                			</td>
							<td>
        						<div class="form-group">
        							<input type="text" name="nroinscripciones[]" value="{{old('nroinscripciones.' . $loop->index, $formapago->nroinscripcion ?? '')}}" class="form-control nroinscripciones" placeholder="C.U.I.T.">
        						</div>
                			</td>
							<td>
								<select name="banco_ids[]" id="banco_ids" data-placeholder="Banco" class="form-control banco" data-fouc>
        							<option value=""></option>
        							@foreach($banco_query as $key => $value)
        								@if( (int) $value->id == (int) old('banco_ids', $formapago->banco_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
							</td>
							<td>
								<select name="mediopago_ids[]" id="mediopago_ids" data-placeholder="Medio de pago" class="form-control mediopago" data-fouc>
        							<option value=""></option>
        							@foreach($mediopago_query as $key => $value)
        								@if( (int) $value->id == (int) old('mediopago_ids', $formapago->mediopago_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
							</td>
							<td>
        						<div class="form-group">
        							<input type="text" name="emails[]" value="{{old('emails.' . $loop->index, $formapago->email ?? '')}}" class="form-control emails" placeholder="Email">
        						</div>
                			</td>
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_formapago tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('compras.proveedor.template3')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_formapago" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
