<div class="form2" style="display: none">    
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
    				<label for="unidadmedida2_id" class="col-lg-4 col-form-label requerido">Unidad de medida</label>
					<select id="unidadmedida2_id" name="unidadmedida2_id" class="col-lg-4 form-control unidadmedida" required>
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
    				<label for="unidadmedidaalternativa2_id" class="col-lg-4 col-form-label requerido">Unidad de medida alternativa</label>
					<select id="unidadmedidaalternativa2_id" name="unidadmedidaalternativa2_id" class="col-lg-4 form-control unidadmedidaalternativa" required>
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
    				<label for="skualternativo" class="col-lg-4 col-form-label">SKU alternativo</label>
    				<div class="col-lg-5">
    					<input type="text" name="skualternativo" id="skualternativo" class="form-control" value="{{old('skualternativo', $producto->skualternativo ?? '')}}"/>
                	</div>
                </div>     
                <div class="form-group row">
    				<label for="unidadesxenvase" class="col-lg-4 col-form-label">Unidades x envase</label>
    				<div class="col-lg-2">
    					<input type="number" name="unidadesxenvase" id="unidadesxenvase" class="form-control" value="{{old('unidadesxenvase', $producto->unidadesxenvase ?? '')}}"/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="coeficienteconversion" class="col-lg-4 col-form-label">Coeficiente de Conversión</label>
    				<div class="col-lg-2">
    					<input type="number" name="coeficienteconversion" id="coeficienteconversion" class="form-control" value="{{old('coeficienteconversion', $producto->coeficienteconversion ?? '')}}"/>
                	</div>
                </div>    
                <div class="form-group row">
    				<label for="formula" class="col-lg-4 col-form-label">Fórmula</label>
    				<div class="col-lg-2">
    					<input type="number" name="formula" id="formula" class="form-control" value="{{old('formula', $producto->formula ?? '')}}"/>
                	</div>
                </div>                                        
            </div>
        </div>
    </div>
</div>