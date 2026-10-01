<div class="card">
    <div class="card-body">
        @if (\App\Support\Stock\ArticuloMarketplaceGrillaSupport::uiActiva())
            @include('includes.tabs-activas-estilos')
            <div class="tabs-activas mb-3">
                <ul class="nav nav-tabs" id="tabs-articulo" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" id="botonform1" role="tab">
                            <i class="fa fa-pencil"></i> Datos diseño
                        </a>
                    </li>
                    <li class="nav-item" id="li-botonform10" style="display:none;">
                        <a class="nav-link" href="#" id="botonform10" role="tab">
                            <i class="fa fa-shopping-bag"></i> Marketplaces
                        </a>
                    </li>
                </ul>
            </div>
        @endif
        <div class="form-diseno">
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
    				<label for="subcategoria_id" class="col-lg-4 col-form-label requerido">Subcategor&iacute;a</label>
					<select id="subcategoria_id" name="subcategoria_id" class="col-lg-8 form-control" required>
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
				<div class="form-group row">
    				<label for="forro_id" class="col-lg-4 col-form-label">Forro</label>
					<select id="forro_id" name="forro_id" class="col-lg-8 form-control">
                            <option value=""> -- Seleccionar -- </option>
                            @foreach( $forro as $key => $value)
                            	@if( isset($producto) && $value->id == $producto->forro_id)
                                    <option value="{{ $value->id}}" selected="select"> {{ $value->nombre }} </option>
                                @else
                                    <option value="{{ $value->id}}"> {{ $value->nombre }} </option>
                                @endif
                            @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="compfondo_id" class="col-lg-4 col-form-label">Componente del fondo</label>
					<select id="compfondo_id" name="compfondo_id" class="col-lg-8 form-control">
                            <option value=""> -- Seleccionar -- </option>
                            @foreach( $compfondo as $key => $value)
                            	@if( isset($producto) && $value->id == $producto->compfondo_id)
                                    <option value="{{ $value->id }}" selected="select"> {{ $value->nombre }} </option>
                                @else
                                    <option value="{{ $value->id}}"> {{ $value->nombre }} </option>
                                @endif
                            @endforeach
                    </select>
                </div>
				<div class="form-group row">
    				<label for="material_id" class="col-lg-4 col-form-label">Material</label>
					<select id="material_id" name="material_id" class="col-lg-8 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($capellada as $value)
                            @if( isset($producto) && (int) $value->id == (int) $producto->material_id )
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        @include('stock.articulo.partials.campo_canales_estados_ferli')
        </div>
        @include('stock.articulo.form10_marketplace')
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
