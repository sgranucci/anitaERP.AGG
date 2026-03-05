<div class="card">
    <div class="card-header">
        Datos Diseño
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
				<div class="form-group row">
    				<label for="articulo_id" class="col-lg-4 col-form-label requerido">Codigo de art&iacute;culo</label>
					<select id="articulo_id" name="articulo_id" class="col-lg-8 form-control" readonly>
						@if (!isset($edit))
                        	<option value="{{ $articulo->id }}" selected="select">{{ $articulo->descripcion }}-{{ $articulo->sku }}</option>   
						@else
                        	@foreach($articulo as $key => $value)
                            	@if( isset($combinacion) && (int) $value->id == (int) $combinacion->articulo_id )
                                	<option value="{{ $value->id }}" selected="select">{{ $value->descripcion }}-{{ $value->sku }}</option>   
                            	@endif
                        	@endforeach
						@endif
                    </select>
                </div>
				<div class="form-group row">
    				<label for="combinacion" class="col-lg-4 col-form-label requerido">Combinaci&oacute;n</label>
    				<div class="col-lg-3">
    				<input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $combinacion->codigo ?? '')}}" required/>
    				</div>
				</div>
				<div class="form-group row">
    				<label for="nombre" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    				<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $combinacion->nombre ?? '')}}" required/>
    				</div>
				</div>
				<div class="form-group row">
    				<label for="observacion" class="col-lg-4 col-form-label">Observaci&oacute;n</label>
    				<div class="col-lg-8">
    				<input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $combinacion->observacion ?? '')}}">
    				</div>
				</div>
            	<div class="form-group row">
    				<label for="estado" class="col-lg-4 col-form-label requerido">Estado</label>
                	<div class="form-check">
                       	<input class="form-check-input" type="radio" name="estado" id="exampleRadios1" value="A" 
                       	{{ isset($combinacion)?( $combinacion->estado == 'A' )?'checked' :'' : 'checked' }} >
                         	<label class="form-check-label" for="exampleRadios1">
                           	Activo 
                         	</label>
                	</div>
                </div>
            	<div class="form-group row">
    				<label for="estado2" class="col-lg-4 col-form-label"></label>
                	<div class="form-check">
                         <input class="form-check-input" type="radio" name="estado" id="exampleRadios2" value="I" 
                          {{ isset($combinacion)?( $combinacion->estado == 'I' )?'checked' :'' : '' }}>
                          <label class="form-check-label" for="exampleRadios2"  >
                            Inactivo
                          </label>
                	</div>
                	@if(isset($sku))
                        <input type="hidden" id="combinacion_producto" name="combinacion_producto" class="" value="{{ $sku }}" />
                	@endif
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
