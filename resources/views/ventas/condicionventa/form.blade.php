<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $condicionventa->nombre ?? '')}}" required/>
    </div>
</div>
<div class="card">
    <div class="card-header">
    	Cuotas
    </div>

    <div class="card-body">
    	<table class="table" id="cuotas-table">
    		<thead>
    			<tr>
    				<th>Cuota</th>
    				<th>Tipo de plazo</th>
    				<th>Plazo</th>
    				<th>Fecha de vto.</th>
    				<th>Porcentaje</th>
    				<th>Interes</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($condicionventa->condicionventacuotas ?? '') 
					@foreach (old('cuotas', $condicionventa->condicionventacuotas->count() ? $condicionventa->condicionventacuotas : ['']) as $condicionventacuota)
            			<tr class="item-cuota">
                			<td>
                				<input type="number" name="cuotas[]" class="form-control iicuota" readonly value="{{ $loop->index+1 }}" />
                			</td>
                			<td>
                				<select name="tiposplazo[]" class="form-control tipoplazo">
                					<option value="">-- Elija tipo de plazo --</option>
                					@foreach ($colTipoPlazo as $tipoplazo)
                						<option value="{{ $tipoplazo['valor'] }}"
                						@if (old('tiposplazos.' . $loop->parent->index, optional($condicionventacuota)->tipoplazo) == $tipoplazo['valor']) selected @endif
                						>{{ $tipoplazo['nombre'] }}</option>
                					@endforeach
                				</select>
                			</td>
                			<td>
                				<input type="number" name="plazos[]" class="form-control plazo"
                					value="{{ (old('plazos.' . $loop->index) ?? optional($condicionventacuota)->plazo) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="text" name="fechasvencimiento[]" class="form-control fechavencimiento"
                					value="{{ (old(('fechasvencimiento.' . $loop->index), \Carbon\Carbon::parse(strtotime($condicionventacuota->fechavencimiento ?? date('d-m-Y')))->formatLocalized('%d-%m-%Y') ?? date('d-m-Y'))) }}" readonly/>
                			</td>
                			<td>
                				<input type="number" name="porcentajes[]" class="form-control"
                					value="{{ (old('porcentajes.' . $loop->index) ?? optional($condicionventacuota)->porcentaje) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="number" name="intereses[]" class="form-control"
                					value="{{ (old('intereses.' . $loop->index) ?? optional($condicionventacuota)->interes) ?? '0' }}" />
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
                			<select id="tipoplazo" name="tiposplazo[]" class="form-control tipoplazo">
                				<option value="">-- Elija tipo de plazo --</option>
                				@foreach ($colTipoPlazo as $tipoplazo)
                					<option value="{{ $tipoplazo['valor'] }}">{{ $tipoplazo['nombre'] }}</option>
                				@endforeach
                			</select>
                		</td>
                		<td>
                			<input type="number" id="plazo" name="plazos[]" class="form-control plazo" value="0"/>
                		</td>
                		<td>
                			<input type="text" id="fechavencimiento" name="fechasvencimiento[]" class="form-control fechavencimiento" value="{{date('d-m-Y')}}" readonly/>
                		</td>
                		<td>
                			<input type="number" name="porcentajes[]" class="form-control" value="0" />
                		</td>
                		<td>
                			<input type="number" name="intereses[]" class="form-control" value="0" />
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
		<template id="template-renglon">
            	<tr class="item-cuota">
                	<td>
                		<input type="number" name="cuotas[]" class="form-control iicuota" readonly value="1" />
                	</td>
                	<td>
                		<select name="tiposplazo[]" class="form-control tipoplazo">
                			<option value="">-- Elija tipo de plazo --</option>
                			@foreach ($colTipoPlazo as $tipoplazo)
                				<option value="{{ $tipoplazo['valor'] }}">{{ $tipoplazo['nombre'] }}</option>
                			@endforeach
                		</select>
                	</td>
                	<td>
                		<input type="number" name="plazos[]" class="form-control plazo" value="0"/>
                	</td>
                	<td>
                		<input type="text" name="fechasvencimiento[]" class="form-control fechavencimiento" value="{{date('d-m-Y')}}" readonly/>
                	</td>
                	<td>
                		<input type="number" name="porcentajes[]" class="form-control" value="0" />
                	</td>
                	<td>
                		<input type="number" name="intereses[]" class="form-control" value="0" />
                	</td>
                	<td>
						<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
						</button>
                	</td>
           		</tr>
		</template>
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
