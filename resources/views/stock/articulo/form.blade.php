<div id="tab1"  class="card form1 tab-content">
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Sku</label>
    				<div class="col-lg-5">
    					<input type="text" name="sku" id="sku" class="form-control sku" value="{{old('sku', $producto->sku ?? '')}}" required/>
                	</div>
                </div>
                <div class="form-group row">
    				<label for="sku" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
    				<div class="col-lg-8">
    					<input type="text" name="descripcion" id="descripcion" class="form-control descripcion" value="{{old('descripcion', $producto->descripcion ?? '')}}" required/>
                	</div>
                </div>
				<div class="form-group row">
    				<label for="tipoarticulo_id" class="col-lg-4 col-form-label requerido">Tipo del art&iacute;culo</label>
					<select id="tipoarticulo_id" name="tipoarticulo_id" class="col-lg-8 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($tiposArticulos as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('tipoarticulo_id', $producto->tipoarticulo_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
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
    				<label for="usoarticulo_id" class="col-lg-4 col-form-label requerido">Uso de art&iacute;culo</label>
					<select id="usoarticulo_id" name="usoarticulo_id" class="col-lg-3 form-control" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach($usosArticulos as $key => $value)
                            @if( isset($producto) && (int) $value->id == (int) old('usoarticulo_id', $producto->usoarticulo_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
                    <label for="estado" class="col-lg-2 col-form-label">Estado</label>
                    <input type="text" name="estado" id="estado" class="col-lg-2 form-control" value="{{old('estado', $producto->estado ?? 'ACTIVO')}}" readonly>
              	</div>
				<div class="form-group row">
    				<label for="unidadmedida" class="col-lg-4 col-form-label requerido">Unidad de medida</label>
					<select id="unidadmedida_id" name="unidadmedida_id" class="col-lg-4 form-control unidadmedida" required>
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
    				<label for="unidadmedidaalternativa_id" class="col-lg-4 col-form-label requerido">Unidad de medida alternativa</label>
					<select id="unidadmedidaalternativa_id" name="unidadmedidaalternativa_id" class="col-lg-4 form-control unidadmedidaalternativa" required>
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
				<div class="form-group row">
    				<label for="numeroparte" class="col-lg-4 col-form-label">Número de parte</label>
					<select id="numeroparte" name="numeroparte" class="col-lg-3 form-control">
                        @foreach($numeroparte_enum as $key => $value)
                            @if( isset($producto) && (int) $value['id'] == (int) old('numeroparte', $producto->numeroparte ?? ''))
                                <option value="{{ $value['id'] }}" selected="select">{{ $value['nombre'] }}</option>    
                            @else
                                <option value="{{ $value['id'] }}">{{ $value['nombre'] }}</option>    
                            @endif
                        @endforeach
                    </select>
                    <label for="ubicacionparte" class="col-lg-2 col-form-label">Ubic. de parte</label>
                    <input type="text" name="ubicacionparte" id="ubicacionparte" class="col-lg-2 form-control" value="{{old('ubicacionparte', $producto->ubicacionparte ?? '')}}"/>
              	</div>  
				<div class="form-group row">
    				<label for="mventa_id" class="col-lg-4 col-form-label">Marca</label>
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
    				<label for="linea_id" class="col-lg-4 col-form-label">Linea</label>
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
        <div class="form-group row">
            <label for="detalle" class="col-lg-2 col-form-label">Detalle</label>
            <div class="col-lg-8">
                <input type="text" name="detalle" id="detalle" class="form-control" value="{{old('detalle', $producto->detalle ?? '')}}"/>
            </div>
        </div>  
        <div class="form-group row">
            <label for="foto" class="col-lg-3 col-form-label">Foto</label>
            <div class="col-lg-5">
                <input type="file" name="foto_up" id="foto" data-initial-preview="{{isset($producto->foto) ? asset("storage/imagenes/fotos_articulos/$producto->foto") : ''}}" accept="image/*"/>
                @if ($producto->foto ?? '')
                    <img src="{{ asset("storage/imagenes/fotos_articulos/$producto->foto") }}" alt="Foto del artículo" style="max-width: 200px;">
                @endif
            </div>
        </div>      
    </div>
    <input type="hidden" name="fechaalta" value="{{$producto->fechaalta}}">
    <input type="hidden" name="etiqueta_id" value="{{$producto->etiqueta_id}}">
    <input type="hidden" name="unidadenvasado" value="{{$producto->unidadenvasado}}">
    <input type="hidden" name="leyendanofacturar" value="{{$producto->leyendanofacturar}}">
    <input type="hidden" name="skuproveedor" value="{{$producto->skuproveedor}}">
    <input type="hidden" name="skuproveedor2" value="{{$producto->skuproveedor2}}">
    <input type="hidden" name="posicionaracelaria" value="{{$producto->posicionaracelaria}}">
    <input type="hidden" name="vigenteenlista" value="{{$producto->vigenteenlista}}">
    <input type="hidden" name="cuentacontablevariacionprecio_id" value="{{$producto->cuentacontablevariacionprecio_id}}">
    <input type="hidden" name="centrocostovariacionprecio_id" value="{{$producto->centrocostovariacionprecio_id}}">
    <input type="hidden" name="centrocostocompra_id" value="{{$producto->centrocostocompra_id}}">
    <input type="hidden" name="abc" value="{{$producto->abc}}">
    <input type="hidden" name="punto" value="{{$producto->punto}}">
    <input type="hidden" name="lote" value="{{$producto->lote}}">
    <input type="hidden" name="coeficientelitro" value="{{$producto->coeficientelitro}}">
    <input type="hidden" name="estadobloqueo_id" value="{{$producto->estadobloqueo_id}}">
    <input type="hidden" name="estuche" value="{{$producto->estuche}}">
    <input type="hidden" name="skuetiqueta" value="{{$producto->skuetiqueta}}">
    <input type="hidden" name="skulistaprecio" value="{{$producto->skulistaprecio}}">
    <input type="hidden" name="clase" value="{{$producto->clase}}">
    <input type="hidden" name="fechaprimeraventa" value="{{$producto->fechaprimeraventa}}">
    <input type="hidden" name="fechaprimeringreso" value="{{$producto->fechaprimeringreso}}">
    <input type="hidden" name="estadofacturacion" value="{{$producto->estadofacturacion}}">
</div>
