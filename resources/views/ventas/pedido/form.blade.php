<div class="row">
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query}}" data-formapago="{{$formapago_query}}">
        <input type="hidden" id="codigopedido" class="form-control" value="{{old('codigopedido', $pedido->codigo ?? '')}}" />
		<input type="hidden" id="topedescuento" class="form-control" value="{{config('cliente.TOPE_DESCUENTO')}}" />
		<input type="hidden" id="categoria_secos_id" class="form-control" value="{{config('cliente.CATEGORIA_SECOS_ID')}}" />
		<input type="hidden" id="subcategoria_maquina_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_MAQUINA_ID')}}" />
		<input type="hidden" id="subcategoria_tira_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_TIRA_ID')}}" />
		<div class="form-group row" id="div-cliente">
			<label for="cliente" class="col-lg-3 col-form-label">Cliente</label>
			<input type="hidden" class="col-lg-2" id="cliente_id" name="cliente_id" value="{{$pedido->cliente_id??''}}" >
			<input type="text" class="col-lg-2 codigocliente" id="codigocliente" name="codigocliente" value="{{$pedido->clientes->codigo??''}}" >
			<input type="text" class="col-lg-5 form-control" id="nombrecliente" name="nombrecliente" value="{{$pedido->clientes->nombre??''}}" readonly>
			<div class="form-group boton-alta-cliente" style="display: none">
				<button type="button" id="botonaltacliente" class="btn btn-primary btn-sm">
					<i class="fa fa-user"></i>Alta Cliente
				</button>
			</div>
			@if ($datos['funcion'] == 'crear')
				<a style="text-align: center; margin: 6px; padding-left: 1px;display: inline-block; " href="{{route('crear_cliente', ['tipoalta' => 'P'])}}" id="clienteprovisorio" class="btn-accion-tabla tooltipsC" title="Crear cliente provisorio">
                	<i class="fa fa-user"></i>
            	</a>
			@endif	
			<button type="button" title="Consulta clientes" style="padding:1;" class="btn-accion-tabla consultacliente tooltipsC">
					<i class="fa fa-search text-primary"></i>
			</button>			
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
			<label for="transporte" class="col-lg-3 col-form-label">Reparto</label>
			<input type="hidden" class="col-form-label transporte_id" id="transporte_id" name="transporte_id" value="{{$pedido->transporte_id ?? ''}}" >
			<input type="text" class="col-lg-2 codigotransporte" id="codigotransporte" name="codigotransporte" value="{{$pedido->transportes->codigo ?? ''}}" >
			<input type="text" class="col-lg-5 form-control nombretransporte" id="nombretransporte" name="nombretransporte" value="{{$pedido->transportes->nombre ?? ''}}" readonly>
			<button type="button" title="Consulta repartos" style="padding:1;" class="btn-accion-tabla consultatransporte tooltipsC">
				<i class="fa fa-search text-primary"></i>
			</button>
			<input type="hidden" name="nombretransporte" id="nombretransporte" class="form-control" value="{{old('nombretransporte', $pedido->transportes->nombre ?? '')}}">
		</div>		
		<div class="form-group row" id="divlugar">
    		<label for="lugarentrega" class="col-lg-3 col-form-label">Lugar de Entrega</label>
    		<div class="col-lg-8">
    			<input type="text" name="lugarentrega" id="lugarentrega" class="form-control" value="{{old('lugarentrega', $pedido->lugarentrega ?? '')}}">
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
    		<label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
    		<div class="col-lg-3">
    			<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $pedido->fecha ?? date('Y-m-d')),0,10)}}" readonly>
    		</div>
		</div>
		<div class="form-group row">
    		<label for="fechaentrega" class="col-lg-3 col-form-label required">Entrega</label>
    		<div class="col-lg-3 row">
    			<input type="date" name="fechaentrega" id="fechaentrega" class="form-control" value="{{substr(old('fechaentrega', $pedido->fechaentrega ?? date('Y-m-d')),0,10)}}" requerido>
    		</div>
			<div class="col-lg-6 row" id="divlote">
				<label for="lote" class="col-lg-2 col-form-label">Lote</label>
				<select name="lote_id" id="lote_id" data-placeholder="Lote de stock" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar lote --</option>
					@foreach($lote_query as $key => $value)
						@if( (int) $value->id == (int) old('lote_id', $pedido->pedido_articulos[0]->lotes->id ?? ''))
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
		<div class="form-group row" id="marca">
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
    				<th style="width: 12%;">Art&iacute;culo</th>
					<th style="width: 16%;">Descripción Artículo</th>
					<th>UMD</th>
    				<th style="width: 9%;">Cajas</th>
    				<th style="width: 9%;">Piezas</th>
    				<th style="width: 9%;">Kilos</th>
					<th style="width: 9%;">Pesada</th>
					<th>Descuento</th>
    				<th style="width: 9%; text-align: right;">Precio</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($pedido->pedido_articulos ?? '') 
					@foreach (old('items', $pedido->pedido_articulos->count() ? $pedido->pedido_articulos : ['']) as $pedidoitem)
            			<tr class="item-pedido">
                			<td>
								@if ($pedidoitem->estado ?? '' == 'A')
                					<input type="text" style="background-color:red;font-weight:900;" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@else
                					<input type="text" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@endif
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{old('listaprecios_id', $pedidoitem->listaprecio_id??'')}}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{old('monedas_id', $pedidoitem->moneda_id??'')}}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{old('incluyeimpuestos', $pedidoitem->incluyeimpuesto??'')}}" />
                				<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="{{old('descuentos', $pedidoitem->descuento??'')}}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$pedidoitem->id??''}}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{$pedidoitem->lotes->id ?? ''}}" />
								@foreach ($pedidoitem->pedido_articulo_estados as $estado)
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
                                    <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$pedidoitem->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="{{$pedidoitem->articulo_id ?? ''}}" >
									<input type="hidden" class="categoria_id" name="categoria_ids[]" value="{{$pedidoitem->articulos->categoria_id ?? ''}}" >
									<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="{{$pedidoitem->articulos->subcategoria_id ?? ''}}" >
                                    <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulo codigoarticulolocal form-control" name="codigoarticulos[]" value="{{$pedidoitem->articulos->sku ?? ''}}" >
                                    <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="{{$pedidoitem->articulos->sku ?? ''}}" >
                                </div>
                            </td>		
                            <td>
                                <input type="text" style="WIDTH: 220px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{$pedidoitem->articulos->descripcion ?? ''}}" readonly>
                            </td>										
							<td>
								<select name="unidadmedida_ids[]" data-placeholder="Unidad de Medida" class="unidadmedida_id form-control" data-fouc>
									@foreach($unidadmedida_query as $key => $value)
										@if( (int) $value->id == (int) old('unidadmedida_ids', $pedidoitem->unidadmedida_id ?? ''))
											<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
										@else
											<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
										@endif
									@endforeach
								</select>	
								<input type="hidden" name="unidadmedidas[]" class="form-control unidadmedida" value="" />								
							</td>										
                			<td>
								<input type="text" name="cajas[]" class="form-control caja" value="{{number_format(old('cajas.'.$loop->index, optional($pedidoitem)->caja),2)}}" />
                			</td>
                			<td>
								<input type="text" name="piezas[]" class="form-control pieza" value="{{number_format(old('piezas.'.$loop->index, optional($pedidoitem)->pieza),2)}}" />
                			</td>
                			<td>
								<input type="text" name="kilos[]" class="form-control kilo" value="{{number_format(old('kilos.'.$loop->index, optional($pedidoitem)->kilo),2)}}" />
                			</td>	
							<td>
								<input type="text" name="pesadas[]" class="form-control pesada" value="{{number_format(old('pesadas.'.$loop->index, optional($pedidoitem)->pesada),2)}}" />
                			</td>		
							<td>
								<select name="descuentoventa_ids[]" data-placeholder="Descuento" class="descuentoventa_id form-control" data-fouc>
									<option value="">-Descuento-</option>
									@foreach($descuentoventa_query as $key => $value)
										@if( (int) $value->id == (int) old('descuentoventa_ids', $pedidoitem->descuentoventa_id ?? ''))
											<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
										@else
											<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
										@endif
									@endforeach
								</select>	
								<input type="hidden" name="descuentoventaanterior_ids[]" class="form-control descuentoventaanterior_id" value="{{$pedidoitem->descuentoventa_id}}" />
							</td>				
                			<td>
                				<input type="text" style="text-align: right;" name="precios[]" class="form-control precio" readonly value="{{number_format(old('precios.'.$loop->index, optional($pedidoitem)->precio),2)}}" />
                			</td>
                			<td>
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
								@if (count($pedidoitem->pedido_articulo_estados) > 0)
									<button type="button" title="Historia de anulaci&oacute;nes" style="padding:0;" class="btn-accion-tabla historiaitem tooltipsC">
                            			<i class="fa fa-book text-danger"></i>
									</button>
									<input type="hidden" class="historiaanulacion" value="{{$pedidoitem->pedido_combinacion_estados}}" >
								@endif
								@if (can('entregar-articulo-sin-cargo-pedido-venta', false))
									<button type="button" title="Artículo sin cargo" style="padding:0;" class="btn-accion-tabla botonsincargo tooltipsC">
										<i class="fa fa-gift text-success"></i>
									</button>
								@endif								
								<input name="checks[]" style="display:none;" class="checkImpresion" type="checkbox" autocomplete="off"> 
								<input type="hidden" style="text-align: right;" name="observaciones[]" class="form-control observacion" value="" />
								<input type="hidden" style="text-align: right;" name="sincargos[]" class="form-control sincargo" value="{{$pedidoitem->precio == 0 ? 'S' : 'N'}}" />
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.pedido.template')
        <div class="row col-md-12">
        	<div class="col-md-2">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
			<div class="col-md-6">
               	<!-- textarea -->
               	<div class="form-group">
               		<label>Leyendas</label>
               		<textarea name="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{old('leyenda', $pedido->leyenda ?? '')}}</textarea>
               	</div>
            </div>
        	<div class="col-md-4 row">
				<label style="margin-top: 6px;">Total cajas:&nbsp</label>
                <input type="text" id="totalcajaspedido" name="totalcajaspedido" class="form-control col-sm-3" readonly value="" />
                <label style="margin-top: 6px;">Total piezas:&nbsp</label>
                <input type="text" id="totalpiezaspedido" name="totalpiezaspedido" class="form-control col-sm-3" readonly value="" />
				<label style="margin-top: 6px;">Total kilos:&nbsp</label>
                <input type="text" id="totalkilospedido" name="totalkilospedido" class="form-control col-sm-3" readonly value="" />
				<label style="margin-top: 6px;">Total pesados:&nbsp</label>
                <input type="text" id="totalkilospesados" name="totalkilospesados" class="form-control col-sm-3" readonly value="" />				
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="tiposuspension_id" name="tiposuspension_id" value="{{$tiposuspension_id ?? ''}}" >
<input type="hidden" id="tiposuspensioncliente_query" value="{{$tiposuspensioncliente_query ?? ''}}" >

<input type="hidden" id="estadocliente" value="{{ $pedido->clientes->estado ?? '' }}">
<input type="hidden" id="estado" name="estado" value="{{ $pedido->estado ?? '' }}">
<input type="hidden" id="estadopedido" name="estadopedido" value="{{ $pedido->estadopedido ?? '' }}">
<input type="hidden" id="nombretiposuspensioncliente" value="{{ $pedido->clientes->tipossuspensioncliente->nombre ?? ''}}">
<input type="hidden" id="tiposuspensioncliente_id" value="{{ $pedido->clientes->tiposupension_id ?? ''}}">
<input type="hidden" id="tipoalta" value="{{ $pedido->clientes->tipoalta ?? ''}}">
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="puntoventadefault_id" class="form-control" value="{{$puntoventadefault_id}}" />
<input type="hidden" id="puntoventaremitodefault_id" class="form-control" value="{{$puntoventaremitodefault_id}}" />
<input type="hidden" id="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />

@include('ventas.pedido.modal')
@include('ventas.pedido.modal2')
@include('ventas.pedido.modal3')
@include('includes.stock.modalconsultaarticulo')
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultatransporte')
@include('ventas.ordentrabajo.modalcrearordentrabajo')
@include('ventas.ordentrabajo.modalfacturaordentrabajo')
@include('ventas.pedido.modalpesada')

