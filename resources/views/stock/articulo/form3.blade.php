<div class="form3" style="display: none">    
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Sku</label>
    				<div class="col-lg-5">
    					<input type="text" name="sku" id="sku" class="form-control sku" value="{{old('sku', $producto->sku ?? '')}}" required readonly/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control descripcion" value="{{old('descripcion', $producto->descripcion ?? '')}}" required disabled/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="unidadmedida3_id" class="col-lg-4 col-form-label requerido">Unidad de medida</label>
					<select id="unidadmedida3_id" name="unidadmedida3_id" class="col-lg-4 form-control unidadmedida" required>
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
    				<label for="unidadmedidaalternativa3_id" class="col-lg-4 col-form-label requerido">Unidad de medida alternativa</label>
					<select id="unidadmedidaalternativa3_id" name="unidadmedidaalternativa3_id" class="col-lg-4 form-control unidadmedidaalternativa" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($unidadmedida as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('unidadmedidaalternativa_id', $producto->unidadmedidaalternativa_id ?? ''))
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
            </div>
            <div class="col-sm-6">
                <div class="form-group row">
                    <label for="oficinacompra_id" class="col-lg-4 col-form-label requerido">Oficina de compras</label>
                    <select id="oficinacompra_id" name="oficinacompra_id" class="col-lg-5 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($oficinacompra_query as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('oficinacompra_id', $producto->oficinacompra_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                </div>                      
            	<div class="form-group row">
    				<label for="depositoentrega_id" class="col-lg-4 col-form-label">Depósito entrega</label>
					<select id="depositoentrega_id" name="depositoentrega_id" class="col-lg-5 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($deposito_query as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('depositoentrega_id', $producto->depositoentrega_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{$value->codigo}}-{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{$value->codigo}}-{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>    
            	<div class="form-group row">
    				<label for="periodicidadcompra_id" class="col-lg-4 col-form-label">Periodicidad de compra</label>
					<select id="periodicidadcompra_id" name="periodicidadcompra_id" class="col-lg-5 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($periodicidadcompra_query as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('periodicidadcompra_id', $producto->periodicidadcompra_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>         
            	<div class="form-group row">
    				<label for="condicionentrega_id" class="col-lg-4 col-form-label">Condición de entrega</label>
					<select id="condicionentrega_id" name="condicionentrega_id" class="col-lg-5 form-control">
                        <option value="">-- Seleccionar --</option>
                        @foreach($condicionentrega_query as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('condicionentrega_id', $producto->condicionentrega_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
              	</div>                                
            </div>                      
        </div>
    </div>
</div>