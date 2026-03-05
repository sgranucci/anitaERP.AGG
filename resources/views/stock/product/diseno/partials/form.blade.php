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
    				<label for="sku" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control" value="{{old('descripcion', $producto->descripcion ?? '')}}" required/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="usoarticulo_id" class="col-lg-4 col-form-label requerido">Tipo de art&iacute;culo</label>
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
    				<label for="unidadmedida_id" class="col-lg-4 col-form-label requerido">Unidad de medida</label>
					<select id="unidadmedida_id" name="unidadmedida_id" class="col-lg-8 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($unidadmedida as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->unidadmedida_id )
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
    				<label for="categoria_id" class="col-lg-4 col-form-label requerido">Categor&iacute;a</label>
					<select id="categoria_id" name="categoria_id" class="col-lg-8 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($categoria as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->categoria_id )
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="subcategoria_id" class="col-lg-4 col-form-label">Subcategor&iacute;a</label>
					<select id="subcategoria_id" name="subcategoria_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($subcategoria as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->subcategoria_id )
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
    				<label for="mventa_id" class="col-lg-4 col-form-label requerido">Marca</label>
					<select id="mventa_id" name="mventa_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($marca as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->mventa_id )
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="linea_id" class="col-lg-4 col-form-label requerido">Linea</label>
					<select id="linea_id" name="linea_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($linea as $key => $value)
                            @if( isset($producto) && $value->id == $producto->linea_id)
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}-{{ $value->codigo }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}-{{ $value->codigo }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
		<div class="card-footer">
        	<div class="row">
            	@if ($edit)
        			@include('includes.boton-form-editar')
            	@else
        			@include('includes.boton-form-crear')
            	@endisset
        	</div>
        </div>
    </div>
</div>
