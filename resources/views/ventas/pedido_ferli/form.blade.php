<div class="row">
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query}}" data-formapago="{{$formapago_query}}">
        <input type="hidden" id="codigopedido" class="form-control" value="{{old('codigopedido', $pedido->codigo ?? '')}}" />
		<div class="form-group row">
   			<label for="cliente" class="col-lg-3 col-form-label requerido">Cliente</label>
        	<select name="cliente_id" id="cliente_id" data-placeholder="Cliente" class="col-lg-8 form-control required" data-fouc>
        		<option value="">-- Seleccionar cliente --</option>
        		@foreach($cliente_query as $key => $value)
        			@if( (int) $value->id == (int) old('cliente_id', $pedido->cliente_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
			@if ($datos['funcion'] == 'crear')
				<a href="{{route('crear_cliente', ['tipoalta' => 'P'])}}" id="clienteprovisorio" class="btn-accion-tabla tooltipsC" title="Crear cliente provisorio">
                	<i class="fa fa-user"></i>
            	</a>
			@endif
			<label for="Tiposuspension" id="nombretiposuspension" style="padding: 0px;" class="col-form-label text-danger"></label>
		</div>
		<div class="form-group row">
   			<label for="vendedor" class="col-lg-3 col-form-label requerido">Vendedor</label>
        	<select name="vendedor_id" id="vendedor_id" data-placeholder="Vendedor" class="col-lg-8 form-control required" data-fouc>
        		<option value="">-- Seleccionar vendedor --</option>
        		@foreach($vendedor_query as $key => $value)
        			@if( (int) $value->id == (int) old('vendedor_id', $pedido->vendedor_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
		<div class="form-group row">
   			<label for="transporte" class="col-lg-3 col-form-label">Transporte</label>
        	<select name="transporte_id" id="transporte_id" data-placeholder="Transporte" class="col-lg-8 form-control" data-fouc>
        		<option value="">-- Seleccionar transporte --</option>
        		@foreach($transporte_query as $key => $value)
        			@if( (int) $value->id == (int) old('transporte_id', $pedido->transporte_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
		<div class="form-group row" id="divlugar">
    		<label for="lugarentrega" class="col-lg-3 col-form-label">Lugar de Entrega</label>
    		<div class="col-lg-8">
    			<input type="text" name="lugarentrega" id="lugarentrega" class="form-control" value="{{old('lugarentrega', $pedido->entrega_nombre ?? '')}}">
    		</div>
		</div>
		<div class="form-group row" id="divcodigoentrega">
        	<label class="col-lg-3 col-form-label">Entrega en</label>
        	<select name="cliente_entrega_id" id='cliente_entrega_id' data-placeholder="Entrega" class="col-lg-8 form-control" data-fouc>
        		@if($pedido->cliente_entrega_id ?? '')
					@if($pedido->cliente_entrega_id == "")
        				<option selected></option>
        			@else
        				<option value="{{old('cliente_entrega_id', $pedido->cliente_entrega_id)}}" selected>{{$pedido->entrega_nombre}}</option>
					@endif
        		@endif
        	</select>
        	<input type="hidden" id="cliente_entrega_id_previa" name="cliente_entrega_id_previa" value="{{old('cliente_entrega_id', $pedido->cliente_entrega_id ?? '')}}" >
        	<input type="hidden" id="entrega_nombre" name="entrega_nombre" value="{{old('entrega_nombre', $pedido->entrega_nombre ?? '')}}" >
        </div>
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
    		<label for="fecha" class="col-lg-3 col-form-label requerido">Fecha</label>
    		<div class="col-lg-3">
    			<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $pedido->fecha ?? date('Y-m-d')),0,10)}}" required>
    		</div>
		</div>
		<div class="form-group row">
    		<label for="fechaentrega" class="col-lg-3 col-form-label">Entrega</label>
    		<div class="col-lg-3 row">
    			<input type="date" name="fechaentrega" id="fechaentrega" class="form-control" value="{{substr(old('fechaentrega', $pedido->fechaentrega ?? date('Y-m-d')),0,10)}}">
    		</div>
			<div class="col-lg-6 row" id="divlote">
				<label for="lote" class="col-lg-2 col-form-label">Lote</label>
				<select name="lote_id" id="lote_id" data-placeholder="Lote de stock" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar lote --</option>
					@foreach($lote_query as $key => $value)
						@if( (int) $value->id == (int) old('lote_id', $pedido->pedido_combinaciones[0]->lotes->id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->numerodespacho }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->numerodespacho}}</option>    
						@endif
					@endforeach
        		</select>				
			</div>
		</div>
		<div class="form-group row">
   			<label for="condicionventa" class="col-lg-3 col-form-label requerido">Cond. de Vta.</label>
        	<select name="condicionventa_id" id="condicionventa_id" data-placeholder="Condicion de Venta" class="col-lg-8 form-control required" data-fouc>
        		<option value="">-- Seleccionar cond. de vta.  --</option>
        		@foreach($condicionventa_query as $key => $value)
        			@if( (int) $value->id == (int) old('condicionventa_id', $pedido->condicionventa_id ?? ''))
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
        			@if( (int) $value->id == (int) old('mventa_id', $pedido->mventa_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
   			<label for="descuento" class="col-lg-3 col-form-label requerido">Descuento</label>
            <input type="text" id="descuento" name="descuento" class="form-control col-lg-2" value="{{number_format(old('descuento', $pedido->descuento ?? '0'),2)}}" />
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
    				<th>Combinaci&oacute;n</th>
    				<th style="width: 12%;">M&oacute;dulo</th>
    				<th style="width: 5%;">Cantidad</th>
    				<th style="width: 9%; text-align: right;">Precio</th>
    				<th style="width: 8%; margin-right: 0;">O.T.</th>
    				<th style="width: 15%; margin-right: 0;">Observaci&oacute;n</th>
    				<th style="width: 1%; margin-right: 0;">A</th>
    				<th style="width: 1%; margin-right: 0;">C</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($pedido->pedido_combinaciones ?? '') 
					@foreach (old('items', $pedido->pedido_combinaciones->count() ? $pedido->pedido_combinaciones : ['']) as $pedidoitem)
            			<tr class="item-pedido">
                			<td>
								@if ($pedidoitem->estado ?? '' == 'A')
                					<input type="text" style="background-color:red;font-weight:900;" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@else
                					<input type="text" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@endif
                				<input type="hidden" name="medidas[]" class="form-control medidas" readonly value="{{old('medidas', $pedidoitem->pedido_combinacion_talles??'')}}" />
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{old('listaprecios_id', $pedidoitem->listaprecio_id??'')}}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{old('monedas_id', $pedidoitem->moneda_id??'')}}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{old('incluyeimpuestos', $pedidoitem->incluyeimpuesto??'')}}" />
                				<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="{{old('descuentos', $pedidoitem->descuento??'')}}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$pedidoitem->id??''}}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{$pedidoitem->lotes->id ?? 0}}" />
								@foreach ($pedidoitem->pedido_combinacion_estados as $estado)
									@php 
										$ultnombrecliente = $estado->clientes->nombre ?? ''; 
										$ultnombremotivocierrepedido = $estado->motivoscierrepedido->nombre ?? ''; 
									@endphp
								@endforeach

								<input type="hidden" name="clientesanulacion[]" class="form-control clientesanulacion" value="{{$ultnombrecliente ?? ''}}" />
								<input type="hidden" name="motivosanulacion[]" class="form-control motivosanulacion" value="{{$ultnombremotivocierrepedido ?? ''}}" />
                			</td>
                			<td>
								<div class="form-group row" id="articulo">
                					<select name="articulos_id[]" class="col-lg-11 form-control articulo">
                						<option value="">-- Elija art&iacute;culo --</option>
                						@foreach ($articulo_query as $articulo)
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
        						<input type="hidden" class="combinacion_id_previa" name="combinacion_id_previa[]" value="{{old('combinacion_id', $pedidoitem->combinacion_id ?? '')}}" >
        						<input type="hidden" class="desc_combinacion" name="desc_combinacion[]" value="{{old('desc_combinacion', $pedidoitem->combinaciones->nombre ?? '')}}" >
                			</td>
                			<td>
        						<select name="modulos_id[]" data-placeholder="Modulos" class="form-control modulo" data-fouc>
        						</select>
        						<input type="hidden" class="modulo_id_previa" name="modulo_id_previa[]" value="{{old('modulo_id', $pedidoitem->modulo_id ?? '')}}" >
        						<input type="hidden" class="desc_modulo" name="desc_modulo[]" class="desc_modulo" value="{{old('desc_modulo', $pedidoitem->desc_modulo ?? '')}}" >
                			</td>
                			<td>
                				<input type="text" id="icantidad" name="cantidades[]" class="form-control cantidad" readonly value="{{number_format(old('cantidades.'.$loop->index, optional($pedidoitem)->cantidad),0)}}" />
                			</td>
                			<td>
                				<input type="text" style="text-align: right;" id="iprecio" name="precios[]" class="form-control precio" readonly value="{{number_format(old('precios.'.$loop->index, optional($pedidoitem)->precio),2)}}" />
                			</td>
                			<td>
                				<input type="text" name="ot_codigos[]" class="form-control otcodigo" 
                					value="{{ (old('ot_ids.' . $loop->index) ?? optional($pedidoitem)->ordenestrabajo)->codigo ?? '-1' }}" readonly> 
                				<input type="hidden" name="ot_ids[]" class="form-control ot" 
                					value="{{ (old('ot_ids.' . $loop->index) ?? optional($pedidoitem)->ordenestrabajo)->id ?? '-1' }}"> 
                			</td>
                			<td>
                				<input type="text" id="iobservacion" name="observaciones[]" class="form-control observacion" value="{{old('observaciones.'.$loop->index, optional($pedidoitem)->observacion)}}" />
                			</td>
                			<td>
								<input name="checkssinfiltro[]" class="checkSinFiltro" title="Todas los art&iacute;culos" type="checkbox" autocomplete="off"> 
                			</td>
                			<td>
								<input name="checkscomb[]" class="checkCombinacion" title="Todas las combinaciones" type="checkbox" autocomplete="off"> 
                			</td>
                			<td>
								<button type="button" title="Genera OT" style="padding:0;" class="btn-accion-tabla generaot tooltipsC">
                            		<i class="fa fa-industry text-success"></i>
								</button>
								<button type="button" title="Imprime OT" style="padding:0;" class="btn-accion-tabla imprimeot tooltipsC">
                            		<i class="fa fa-print text-success"></i>
								</button>
								@if ($pedidoitem->estado == 'A')
									<button type="button" title="Recupera Item" style="padding:0;" class="btn-accion-tabla anulaitem tooltipsC">
                            			<i class="fa fa-window-close text-success ianulaItem"></i>
								@else
									<button type="button" title="Anula Item" style="padding:0;" class="btn-accion-tabla anulaitem tooltipsC">
                            			<i class="fa fa-window-close text-danger ianulaItem"></i>
								@endif
								</button>
								@if (can('borrar-items-pedidos', false))
									<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
										<i class="fa fa-trash text-danger"></i>
									</button>
								@endif
								@if (count($pedidoitem->pedido_combinacion_estados) > 0)
									<button type="button" title="Historia de anulaci&oacute;nes" style="padding:0;" class="btn-accion-tabla historiaitem tooltipsC">
                            			<i class="fa fa-book text-danger"></i>
									</button>
									<input type="hidden" class="historiaanulacion" value="{{$pedidoitem->pedido_combinacion_estados}}" >
								@endif
								<input name="checks[]" style="display:none;" class="checkImpresion" type="checkbox" autocomplete="off"> 
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.pedido_ferli.template')
        <div class="row col-md-12">
        	<div class="col-md-3">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
			<div class="col-md-6">
               	<!-- textarea -->
               	<div class="form-group">
               		<label>Leyendas</label>
               		<textarea name="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{old('leyenda', $pedido->leyenda ?? '')}}</textarea>
               	</div>
            </div>
        	<div class="col-md-3 row">
                <label style="margin-top: 6px;">Total pares:&nbsp</label>
                <input type="text" id="totalparespedido" name="totalparespedido" class="form-control col-sm-3" readonly value="" />
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="tiposuspension_id" name="tiposuspension_id" value="{{$tiposuspension_id ?? ''}}" >
<input type="hidden" id="tiposuspensioncliente_query" value="{{$tiposuspensioncliente_query ?? ''}}" >

<input type="hidden" id="estadocliente" value="{{ $pedido->clientes->estado ?? '' }}">
<input type="hidden" id="nombretiposuspensioncliente" value="{{ $pedido->clientes->tipossuspensioncliente->nombre ?? ''}}">
<input type="hidden" id="tiposuspensioncliente_id" value="{{ $pedido->clientes->tiposupension_id ?? ''}}">
<input type="hidden" id="tipoalta" value="{{ $pedido->clientes->tipoalta ?? ''}}">
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="puntoventadefault_id" class="form-control" value="{{$puntoventadefault_id}}" />
<input type="hidden" id="puntoventaremitodefault_id" class="form-control" value="{{$puntoventaremitodefault_id}}" />
<input type="hidden" id="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />

@include('ventas.pedido_ferli.modal')
@include('ventas.pedido_ferli.modal2')
@include('ventas.pedido_ferli.modal3')
@include('includes.stock.modalarticuloxsku')
@include('ventas.ordentrabajo.modalcrearordentrabajo')
@include('ventas.ordentrabajo_ferli.modalfacturaordentrabajo')
