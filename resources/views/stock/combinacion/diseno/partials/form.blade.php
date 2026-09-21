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
				@php
					$uiFerliCombo = \App\Support\Stock\CombinacionEstadoCanalSupport::uiFerliActiva();
					$estadoFab = old('estado_fabrica', $combinacion->estado_fabrica ?? $combinacion->estado ?? 'A');
					$estadoLoc = old('estado_local', $combinacion->estado_local ?? $combinacion->estado ?? 'A');
					$estadoLegacy = old('estado', $combinacion->estado ?? 'A');
				@endphp
				@if ($uiFerliCombo)
            	<div class="form-group row">
    				<label for="estado_fabrica" class="col-lg-4 col-form-label requerido">Estado f&aacute;brica</label>
    				<div class="col-lg-3">
						<select name="estado_fabrica" id="estado_fabrica" class="form-control">
							<option value="A" @if ($estadoFab === 'A') selected @endif>Activo</option>
							<option value="I" @if ($estadoFab === 'I') selected @endif>Inactivo</option>
						</select>
    				</div>
				</div>
            	<div class="form-group row">
    				<label for="estado_local" class="col-lg-4 col-form-label requerido">Estado local</label>
    				<div class="col-lg-3">
						<select name="estado_local" id="estado_local" class="form-control">
							<option value="A" @if ($estadoLoc === 'A') selected @endif>Activo</option>
							<option value="I" @if ($estadoLoc === 'I') selected @endif>Inactivo</option>
						</select>
    				</div>
					<small class="col-lg-8 offset-lg-4 form-text text-muted">
						Local define el POS de locales. F&aacute;brica define pedidos/OT/Anita (no se pisan entre s&iacute;).
					</small>
				</div>
				@else
            	<div class="form-group row">
    				<label for="estado" class="col-lg-4 col-form-label requerido">Estado</label>
                	<div class="form-check">
                       	<input class="form-check-input" type="radio" name="estado" id="exampleRadios1" value="A"
                       	{{ $estadoLegacy == 'A' ? 'checked' : '' }} >
                         	<label class="form-check-label" for="exampleRadios1">
                           	Activo
                         	</label>
                	</div>
                </div>
            	<div class="form-group row">
    				<label for="estado2" class="col-lg-4 col-form-label"></label>
                	<div class="form-check">
                         <input class="form-check-input" type="radio" name="estado" id="exampleRadios2" value="I"
                          {{ $estadoLegacy == 'I' ? 'checked' : '' }}>
                          <label class="form-check-label" for="exampleRadios2">
                            Inactivo
                          </label>
                	</div>
                	@if(isset($sku))
                        <input type="hidden" id="combinacion_producto" name="combinacion_producto" class="" value="{{ $sku }}" />
                	@endif
            	</div>
				@endif
				@if(isset($sku))
					<input type="hidden" id="combinacion_producto" name="combinacion_producto" value="{{ $sku }}" />
				@endif
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
