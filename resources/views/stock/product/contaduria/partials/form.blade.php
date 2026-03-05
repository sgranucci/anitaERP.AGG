@isset($edit)
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Sku</label>
    				<div class="col-lg-5">
    					<input type="text" name="sku" id="sku" class="form-control" value="{{old('sku', $producto->sku ?? '')}}" required readonly/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control" value="{{old('descripcion', $producto->descripcion ?? '')}}" required disabled/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="cuentacontableventa_id" class="col-lg-4 col-form-label requerido">Cuenta contable venta</label>
					<select id="cuentacontableventa_id" name="cuentacontableventa_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($ctamae as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('cuentacontableventa_id', $producto->cuentacontableventa_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{$value->nombre}}{{-$value->codigo}}</option>    
                            @else
                                <option value="{{ $value->id }}">{{$value->nombre}}{{-$value->codigo}}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
            </div>
            <div class="col-sm-6">
				<div class="form-group row">
    				<label for="impuesto_id" class="col-lg-4 col-form-label requerido">Impuesto aplicado</label>
					<select id="impuesto_id" name="impuesto_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($codimp as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('impuesto_id', $producto->impuesto_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
                <div class="form-group row">
    				<label for="nomenclador" class="col-lg-4 col-form-label requerido">Nomenclador</label>
    				<div class="col-lg-8">
    					<input type="text" name="nomenclador" id="nomenclador" class="form-control" value="{{old('nomenclador', $producto->nomenclador ?? '')}}" required/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="nofactura" class="col-lg-4 col-form-label requerido">Facturable</label>
					<select id="nofactura" name="nofactura" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($nofactura_enum as $key => $value)
                            @if( isset($producto) && (int) $value['id'] == (int) old('nofactura', $producto->nofactura ?? ''))
                                <option value="{{ $value['id'] }}" selected="select">{{ $value['nombre'] }}</option>    
                            @else
                                <option value="{{ $value['id'] }}">{{ $value['nombre'] }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
            </div>
        </div>
		<div class="card-footer">
        	<div class="row">
            	@isset($edit)
        			@include('includes.boton-form-editar')
            	@else
        			@include('includes.boton-form-crear')
            	@endisset
        	</div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body col-sm-6">
    	<table class="table" id="costos_table">
    		<thead>
    			<tr>
    				<th>Tarea</th>
    				<th>Costo</th>
                    <th>Fecha actualizaci&oacute;n</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
				@foreach (old('items', $producto->articulos_costo->count() > 0 ? $producto->articulos_costo : ['']) as $articulocosto)
    			<tr class="item-costo">
                    <td>
						<select name="tareas_id[]" class="form-control tarea">
							<option value="">-- Elija tarea --</option>
								@foreach ($tarea_query as $tarea)
									<option value="{{ $tarea->id }}"
									@if (old('tareas.' . $loop->parent->index, optional($articulocosto)->tarea_id) == $tarea->id) selected @endif
									>{{ $tarea->nombre }}</option>
								@endforeach
						</select>
					</td>
                    <td>
                        <input type="number" name="costos[]" class="form-control costo" value="{{old('costos', $articulocosto->costo ?? '')}}"/>
                	</td>
                    <td>
                        <input type="date" name="fechasvigencia[]" class="form-control fecha" value="{{substr(optional($articulocosto)->fechavigencia ?? date('Y-m-d'),0,10)}}"/>
                    </td>
                    <td>
						<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarCosto tooltipsC">
              				<i class="fa fa-trash text-danger"></i>
						</button>
					</td>
				</tr>
				@endforeach
    		</tbody>
   		</table>
		@include('stock.product.contaduria.partials.template-costo')
    	<div class="row">
    		<div class="col-md-12">
    			<button id="agrega_renglon_costo" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            	@isset($edit)
        			@include('includes.boton-form-editar')
            	@else
        			@include('includes.boton-form-crear')
            	@endisset
        	</div>
		</div>
   	</div>
</div>
@endisset
