@isset($edit)
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Sku</label>
    				<div class="col-lg-5">
    					<input type="text" name="sku" id="sku" class="form-control" value="{{old('sku', $producto->sku ?? '')}}" required/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="descripcion" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control" value="{{old('descripcion', $producto->descripcion ?? '')}}" required/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="tipcorteforro_id" class="col-lg-4 col-form-label">Tipo de corte forro</label>
					<select id="tipocorteforro_id" name="tipocorteforro_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($tipoCorte as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('tipocorteforro_id', $producto->tipocorteforro_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
				<div class="form-group row">
    				<label for="usoarticulo_id" class="col-lg-4 col-form-label requerido">Uso de art&iacute;culo</label>
					<select id="usoarticulo_id" name="usoarticulo_id" class="col-lg-8 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($usosArticulos as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('usoarticulo_id', $producto->usoarticulo_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
				<div class="form-group row">
    				<label for="tipcorte_id" class="col-lg-4 col-form-label">Tipo de corte</label>
					<select id="tipocorte_id" name="tipocorte_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($tipoCorte as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('tipocorte_id', $producto->tipocorte_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
				<div class="form-group row">
    				<label for="puntera_id" class="col-lg-4 col-form-label">Puntera</label>
					<select id="puntera_id" name="puntera_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($punteras as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('puntera_id', $producto->puntera_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
                <div class="form-group row">
    				<label for="horma_id" class="col-lg-4 col-form-label requerido">Horma</label>
					<select id="horma_id" name="horma_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($horma as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->horma_id )
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
        	</div>
            <div class="col-sm-6">
				<div class="form-group row">
    				<label for="contrafuerte_id" class="col-lg-4 col-form-label">Contrafuerte</label>
					<select id="contrafuerte_id" name="contrafuerte_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($contrafuertes as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('contrafuerte_id', $producto->contrafuerte_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>
				<div class="form-group row">
    				<label for="mventa_id" class="col-lg-4 col-form-label requerido">Marca</label>
					<select id="mventa_id" name="mventa_id" class="col-lg-8 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($marca as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('mventa_id', $producto->mventa_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="forro_id" class="col-lg-4 col-form-label">Forro</label>
					<select id="forro_id" name="forro_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($forro as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('forro_id', $producto->forro_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="compfondo_id" class="col-lg-4 col-form-label">Componente del fondo</label>
					<select id="compfondo_id" name="compfondo_id" class="col-lg-8 form-control">
                            <option value=""> -- Seleccionar -- </option>
                            @foreach( $compfondo as $key => $value)
                            	@if( isset($producto) && (int) $value->id == (int) old('compfondo_id', $producto->compfondo_id ?? ''))
                                    <option value="{{ $value->id }}" selected="select"> {{ $value->nombre }} </option>
                                @else
                                    <option value="{{ $value->id}}"> {{ $value->nombre }} </option>
                                @endif
                            @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="unidadmedida_id" class="col-lg-4 col-form-label requerido">Unidad de medida</label>
					<select id="unidadmedida_id" name="unidadmedida_id" class="col-lg-8 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($unidadmedida as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('unidadmedida_id', $producto->unidadmedida_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                            	@if( !isset($producto) && (int) $value->abreviatura == "PAR" )
                                	<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
                                	<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            	@endif
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-group row">
    				<label for="fondo_id" class="col-lg-4 col-form-label requerido">Fondo</label>
					<select id="fondo_id" name="fondo_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($fondo as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->fondo_id )
                                <option value="{{ $value->id }}" selected="select">{{$value->nombre}}-{{ $value->codigo }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{$value->nombre}}-{{ $value->codigo }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-group row">
    				<label for="serigrafia_id" class="col-lg-4 col-form-label requerido">Serigraf&iacute;a</label>
					<select id="serigrafia_id" name="serigrafia_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($serigrafia as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->serigrafia_id )
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
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
    	<table class="table" id="cajas_table">
    		<thead>
    			<tr>
    				<th>Caja</th>
    				<th>Nombre</th>
                    <th>Desde N&uacute;mero</th>
                    <th>Hasta N&uacute;mero</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
				@foreach (old('items', $producto->articulos_caja->count() > 0 ? $producto->articulos_caja : ['']) as $articulocaja)
    			<tr class="item-caja">
                    <td>
						<select name="cajas_id[]" class="form-control">
							<option value="">-- Elija caja --</option>
								@foreach ($caja_query as $caja)
									<option value="{{ $caja->id }}"
									@if (old('cajas.' . $loop->parent->index, optional($articulocaja)->caja_id) == $caja->id) selected @endif
									>{{ $caja->descripcion }} {{ $caja->nombre }}</option>
								@endforeach
						</select>
					</td>
					<td>{{ $articulocaja->cajas->articulos->descripcion??'' }}
					</td>
                    <td>
                	    <input type="text" id="desdenro" name="desdenros[]" class="form-control desdenro" value="{{number_format(old('desdenros.'.$loop->index, optional($articulocaja)->desdenro),0)}}" />
                	</td>
                    <td>
                	    <input type="text" id="hastanro" name="hastanros[]" class="form-control hastanro" value="{{number_format(old('hastanros.'.$loop->index, optional($articulocaja)->hastanro),0)}}" />
                	</td>
					<td>
						<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarCaja tooltipsC">
              				<i class="fa fa-trash text-danger"></i>
						</button>
					</td>
				</tr>
				@endforeach
    		</tbody>
   		</table>
		@include('stock.product.tecnica.partials.template-caja')
    	<div class="row">
    		<div class="col-md-12">
    			<button id="agrega_renglon_caja" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
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
