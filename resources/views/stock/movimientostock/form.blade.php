<div class="row">
	<div class="col-sm-6" id="datosmovimientostock" data-tipotransaccion="{{$tipotransaccion_query}}">
        <input type="hidden" id="codigomovimientostock" class="form-control" value="{{old('codigomovimientostock', $movimientostock->codigo ?? '')}}" />
		<div class="form-group row" id="tipotransaccion">
			<label for="recipient-name" class="col-lg-3 col-form-label requerido">Tipo de transacci&oacute;n</label>
			<select name="tipotransaccion_id" id="tipotransaccion_id" data-placeholder="Tipo de transacci&oacute;n" class="col-lg-6 form-control" data-fouc required>
				<option value="">-- Seleccionar transacción  --</option>
				@foreach($tipotransaccion_query as $key => $value)
					@if( (int) $value->id == (int) old('tipotransaccion_id', $movimientostock->tipotransaccion_id ?? ''))
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach	
			</select>
		</div>
		<div class="form-group row">
    		<label for="fecha" class="col-lg-3 col-form-label requerido">Fecha</label>
    		<div class="col-lg-3">
    			<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $movimientostock->fecha ?? date('Y-m-d')),0,10)}}" required>
    		</div>
		</div>
		<div class="form-group row">
			<label for="lote" class="col-lg-3 col-form-label requerido">Lote de stock</label>
			<div class="col-lg-3">
				<input type="text" name="lote" id="lote" class="form-control" value="{{old('lote', $movimientostock->articulos_movimiento[0]->lote ?? 'LOTE DE ALTA')}}" required>
			</div>				
		</div>
		<div class="form-group row">
   			<label for="deposito" class="col-lg-3 col-form-label requerido">Depósito</label>
        	<select name="deposito_id" id="deposito_id" data-placeholder="Depósito" class="col-lg-8 form-control required" data-fouc>
        		<option value="">-- Seleccionar depósito  --</option>
        		@foreach($deposito_query as $key => $value)
        			@if( (int) $value->id == (int) old('deposito_id', $movimientostock->articulos_movimiento[0]->deposito_id ?? '1'))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
		<div class="form-group row" id="marca" data-articulo="{{$articulo_query}}" data-articuloall="{{$articuloall_query}}">
   			<label for="mventa" class="col-lg-3 col-form-label requerido">Marca</label>
        	<select name="mventa_id" id="mventa_id" data-placeholder="Marca de Venta" class="col-lg-3 form-control required" data-fouc>
        		<option value="">-- Seleccionar marca --</option>
        		@foreach($mventa_query as $key => $value)
        			@if( (int) $value->id == (int) old('mventa_id', $movimientostock->mventa_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
		<div class="form-group row" id="divlote">
			<label for="lote" class="col-lg-3 col-form-label">Lote importación</label>
			<select name="loteimportacion_id" id="loteimportacion_id" data-placeholder="Lote de stock" class="col-lg-5 form-control" data-fouc>
				<option value="">-- Seleccionar lote --</option>
				@foreach($lote_query as $key => $value)
					@if( (int) $value->id == (int) old('loteimportacion_id', $movimientostock->articulos_movimiento[0]->loteimportacion_id ?? ''))
						<option value="{{ $value->id }}" selected="select">{{ $value->id }}-{{ $value->numerodespacho }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->id }}-{{ $value->numerodespacho }}</option>    
					@endif
				@endforeach
			</select>				
		</div>
	</div>
</div>

<div class="card">
    <div class="card-body">
    	<table class="table table-hover" id="itemspedido-table">
    		<thead>
    			<tr>
    				<th style="width: 5%;">Item</th>
    				<th style="width: 20%;">Art&iacute;culo</th>
    				<th style="width: 20%;">Combinaci&oacute;n</th>
    				<th style="width: 12%;">M&oacute;dulo</th>
    				<th style="width: 5%;">Cantidad</th>
    				<th style="width: 9%; text-align: right;">Precio</th>
					<th style="width: 1%; margin-right: 0;">A</th>
    				<th style="width: 1%; margin-right: 0;">C</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($movimientostock->articulos_movimiento[0] ?? '') 
					@foreach ($movimientostock->articulos_movimiento as $pedidoitem)
            			<tr class="item-pedido">
                			<td>
								@if ($pedidoitem->estado ?? '' == 'A')
                					<input type="text" style="background-color:red;font-weight:900;" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@else
                					<input type="text" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@endif
                				<input type="hidden" name="medidas[]" class="form-control medidas" readonly value="{{old('medidas', $pedidoitem->articulo_movimiento_talles??'')}}" />
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{old('listaprecios_id', $pedidoitem->listaprecio_id??'')}}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{old('monedas_id', $pedidoitem->moneda_id??'')}}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{old('incluyeimpuestos', $pedidoitem->incluyeimpuesto??'')}}" />
                				<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="{{old('descuentos', $pedidoitem->descuento??'')}}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$pedidoitem->id??''}}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{$pedidoitem->lote ?? 0}}" />
                			</td>
                			<td>
								<div class="form-group row" id="articulo">
                					<select name="articulos_id[]" class="col-lg-11 form-control articulo">
                						<option value="">-- Elija art&iacute;culo --</option>
                						@foreach ($articuloall_query as $articulo)
                							<option value="{{ $articulo['id'] }}"
                							@if (old('articulos_id.' . $loop->parent->index, optional($pedidoitem)->articulo_id) == $articulo['id']) selected @endif
                							>{{$articulo['descripcion']}}-{{$articulo['sku']}}</option>
                						@endforeach
                					</select>
									<button type="button" title="Consulta por SKU" style="padding:0;" class="btn-accion-tabla consultaSKU tooltipsC">
                            			<i class="fa fa-search text-primary"></i>
									</button>
								</div>
        						<input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="{{$pedidoitem->articulo_id ?? ''}}" >
                			</td>
                			<td>
        						<select name="combinaciones_id[]" data-placeholder="Combinaciones" class="form-control combinacion" data-fouc>
        						</select>
        						<input type="hidden" class="combinacion_id_previa" name="combinacion_id_previa[]" value="{{old('combinaciones_id', $pedidoitem->combinacion_id ?? '')}}" >
        						<input type="hidden" class="desc_combinacion" name="desc_combinacion[]" value="{{old('desc_combinacion', $pedidoitem->combinaciones->nombre ?? '')}}" >
                			</td>
                			<td>
        						<select name="modulos_id[]" data-placeholder="Modulos" class="form-control modulo" data-fouc>
        						</select>
        						<input type="hidden" class="modulo_id_previa" name="modulo_id_previa[]" value="{{old('modulo_id', $pedidoitem->modulo_id ?? '')}}" >
        						<input type="hidden" class="desc_modulo" name="desc_modulo[]" class="desc_modulo" value="{{old('desc_modulo', $pedidoitem->desc_modulo ?? '')}}" >
                			</td>
                			<td>
                				<input type="text" id="icantidad" name="cantidades[]" class="form-control cantidad" readonly value="{{number_format(abs($pedidoitem->cantidad),0, '.', '')}}" />
                			</td>
                			<td>
                				<input type="text" style="text-align: right;" id="iprecio" name="precios[]" class="form-control precio" readonly value="{{number_format(old('precios.'.$loop->index, optional($pedidoitem)->precio),2)}}" />
                			</td>
                			<td>
								<input name="checkssinfiltro[]" class="checkSinFiltro" title="Todas los art&iacute;culos" type="checkbox" autocomplete="off"> 
                			</td>
                			<td>
								<input name="checkscomb[]" class="checkCombinacion" title="Todas las combinaciones" type="checkbox" autocomplete="off"> 
                			</td>
                			<td>
								<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
                            		<i class="fa fa-trash text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('stock.movimientostock.template')
        <div class="row col-md-12">
        	<div class="col-md-3">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
			<div class="col-md-6">
               	<!-- textarea -->
               	<div class="form-group">
               		<label>Leyendas</label>
               		<textarea name="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{old('leyenda', $movimientostock->leyenda ?? '')}}</textarea>
               	</div>
            </div>
        	<div class="col-md-3 row">
                <label style="margin-top: 6px;">Total pares:&nbsp</label>
                <input type="text" id="totalparespedido" name="totalparespedido" class="form-control col-sm-3" readonly value="" />
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />

@include('stock.movimientostock.modal')
@include('includes.stock.modalarticuloxsku')
