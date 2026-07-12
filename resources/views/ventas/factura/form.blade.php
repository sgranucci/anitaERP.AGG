<div class="card form1">
<div class="row">
	@php
		$layoutItemsPedido = $layoutItemsPedido ?? facturaUsaLayoutItemsPedido();
		$tipotransaccionSeleccionada = old('tipotransaccion_id', $data->tipotransaccion_id ?? ($tipotransacciondefault_id ?? ''));
		$unidadmedida_query = $unidadmedida_query ?? [];
		$descuentoventa_query = $descuentoventa_query ?? collect();
	@endphp
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query ?? ''}}" data-formapago="{{$formapago_query ?? ''}}" data-layout-items-pedido="{{ $layoutItemsPedido ? '1' : '0' }}">
		<input type="hidden" id="codigofactura" class="form-control" value="{{old('codigofactura', $data->codigo ?? '')}}" />
		<div class="form-group row" id="tipotransaccion">
			<label for="recipient-name" class="col-lg-3 col-form-label requerido">Tipo de transacci&oacute;n</label>
			<select name="tipotransaccion_id" id="tipotransaccion_id" data-placeholder="Tipo de transacci&oacute;n" class="col-lg-6 form-control" data-fouc required>
				<option value="">-- Seleccionar transacción  --</option>
				@php $flPrimero = true; @endphp
				@foreach($tipotransaccion_query as $key => $value)
					@if (isset($flGeneraNotaDeCredito) && $flPrimero)
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
						@php $flPrimero = false; @endphp
					@else
						@if( (int) $value->id == (int) $tipotransaccionSeleccionada)
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endif
				@endforeach	
			</select>
		</div>
		<div class="form-group row" id="puntoventa">
			<label for="recipient-name" class="col-lg-3 col-form-label requerido">Punto de venta</label>
			<input type="hidden" id="puntoventadefault_id" class="form-control" value="{{old('puntoventadefault_id', $puntoventadefault_id ?? ($data->puntoventa_id ?? ''))}}" />
			<select name="puntoventa_id" id="puntoventa_id" data-placeholder="Punto de venta" class="col-lg-5 form-control required" data-fouc>
			</select>
			<label for="recipient-name" class="col-lg-2 col-form-label requerido">Actividad</label>
			<input type="hidden" id="actividad_arcadefault_id" class="form-control" value="{{old('actividad_arcadefault_id', $data->puntoventas->actividad_arca_id ?? '')}}" />
			<select name="actividad_arca_id" id="actividad_arca_id" data-placeholder="Actividad ARCA" class="col-lg-2 form-control required" data-fouc>
				<option value="">-- Seleccionar --</option>
				@foreach($actividad_arca_query as $key => $value)
					@if( (int) $value->id == (int) old('actividad_arca_id', $data->actividad_arca_id ?? ''))
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach					
			</select>
		</div>
		<div class="form-group row">
   			<label for="cliente" class="col-lg-3 col-form-label requerido">Cliente</label>
			<input type="text" class="col-lg-2" id="cliente_id" name="cliente_id" value="{{$data->cliente_id??''}}" >
			<button type="button" title="Consulta clientes" style="padding:1;" class="btn-accion-tabla consultacliente tooltipsC">
					<i class="fa fa-search text-primary"></i>
			</button>
			<input type="text" class="col-lg-5 form-control" id="nombrecliente" name="nombrecliente" value="{{$data->clientes->nombre??$data->nombrecliente??''}}" >
			@if ($datos['funcion'] == 'crear')
				<a href="{{route('crear_cliente', ['tipoalta' => 'P'])}}" id="clienteprovisorio" class="btn-accion-tabla tooltipsC" title="Crear cliente provisorio">
                	<i class="fa fa-user"></i>
            	</a>
			@endif
			<a href="{{route('editar_cliente', ['id' => $data->cliente_id ?? 0])}}" style="display: flex; align-items: center;" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                <i class="fa fa-edit"></i>
            </a>                
			<label for="Tiposuspension" id="nombretiposuspension" style="padding: 0px;" class="col-form-label text-danger"></label>
		</div>
		<div id="aviso-padron-operacion-factura" class="alert d-none col-12 mb-2" role="alert"></div>
		@include('ventas.cliente.partials.arca_apoc_operacion_support')
		<div class="form-group row">
   			<label for="vendedor" class="col-lg-3 col-form-label requerido">Vendedor</label>
        	<select name="vendedor_id" id="vendedor_id" data-placeholder="Vendedor" class="col-lg-8 form-control factura-carga-bloqueable required" data-fouc>
        		<option value="">-- Seleccionar vendedor --</option>
        		@foreach($vendedor_query as $key => $value)
        			@if( (int) $value->id == (int) old('vendedor_id', $data->vendedor_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
		<div class="form-group row">
   			<label for="transporte" class="col-lg-3 col-form-label">@if (config('app.empresa') == 'EL BIERZO') Reparto @else Transporte @endif</label>
			@if (config('app.empresa') == 'EL BIERZO')
				@php
					$transporteFactura = null;
					if (! empty($data->transporte_id ?? null) && isset($transporte_query)) {
						$transporteFactura = $transporte_query->firstWhere('id', (int) $data->transporte_id);
					}
				@endphp
				<input type="hidden" class="col-form-label transporte_id" id="transporte_id" name="transporte_id" value="{{ old('transporte_id', $data->transporte_id ?? '') }}">
				<input type="text" class="col-lg-2 codigotransporte factura-carga-bloqueable" id="codigotransporte" name="codigotransporte" value="{{ old('codigotransporte', $transporteFactura->codigo ?? '') }}">
				<input type="text" class="col-lg-5 form-control nombretransporte" id="nombretransporte" name="nombretransporte" value="{{ old('nombretransporte', $transporteFactura->nombre ?? '') }}" readonly>
				<button type="button" title="Consulta repartos" style="padding:1;" class="btn-accion-tabla consultatransporte tooltipsC">
					<i class="fa fa-search text-primary"></i>
				</button>
			@else
        	<select name="transporte_id" id="transporte_id" data-placeholder="Transporte" class="col-lg-8 form-control factura-carga-bloqueable" data-fouc>
        		<option value="">-- Seleccionar transporte --</option>
        		@foreach($transporte_query as $key => $value)
        			@if( (int) $value->id == (int) old('transporte_id', $data->transporte_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
			@endif
		</div>
		<div class="form-group row" id="divlugar">
    		<label for="lugarentrega" class="col-lg-3 col-form-label">Lugar de Entrega</label>
    		<div class="col-lg-8">
    			<input type="text" name="lugarentrega" id="lugarentrega" class="form-control" value="{{old('lugarentrega', $data->lugarentrega ?? '')}}">
    		</div>
		</div>
		<div class="form-group row" id="divcodigoentrega">
        	<label class="col-lg-3 col-form-label">Entrega en</label>
        	<select name="cliente_entrega_id" id='cliente_entrega_id' data-placeholder="Entrega" class="col-lg-8 form-control" data-fouc>
        		@if($data->cliente_entrega_id ?? '')
					@if($data->cliente_entrega_id == "")
        				<option selected></option>
        			@else
        				<option value="{{old('cliente_entrega_id', $data->cliente_entrega_id)}}" selected>{{$data->lugarentrega}}</option>
					@endif
        		@endif
        	</select>
        	<input type="hidden" id="cliente_entrega_id_previa" name="cliente_entrega_id_previa" value="{{old('cliente_entrega_id', $data->cliente_entrega_id ?? '')}}" >
        	<input type="hidden" id="entrega_nombre" name="entrega_nombre" value="{{old('entrega_nombre', $data->lugarentrega ?? '')}}" >
        </div>
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
			<label for="fechafactura" class="col-lg-4 col-form-label requerido">Fecha</label>
			@if (! empty($consultaFacturasDia))
				@php
					$fechaVenta = old('fechafactura', $data->fecha ?? date('Y-m-d'));
					$fechaVentaYmd = substr((string) $fechaVenta, 0, 10);
				@endphp
				<div class="col-lg-3">
					<input type="text" id="fechafactura_display" class="form-control" readonly
					       value="{{ $fechaVentaYmd !== '' ? \Illuminate\Support\Carbon::parse($fechaVentaYmd)->format('d-m-Y') : '' }}">
					<input type="hidden" name="fechafactura" id="fechafactura" value="{{ $fechaVentaYmd }}">
				</div>
				<label for="hora_creacion_factura" class="col-lg-2 col-form-label">Hora creación</label>
				<div class="col-lg-3">
					<input type="text" id="hora_creacion_factura" class="form-control" readonly
					       value="{{ $data->created_at ? $data->created_at->format('H:i:s') : '—' }}">
				</div>
			@else
				<div class="col-lg-3">
					<input type="date" name="fechafactura" id="fechafactura" class="form-control" value="{{substr(old('fechafactura', $data->fecha ?? date('Y-m-d')),0,10)}}" required>
				</div>
			@endif
		</div>
		<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Descuento de l&iacute;nea</label>
			<input type="number" id="descuentolinea" name="descuentolinea" value=""></input>
		</div>
		<div class="form-group row">
			<label for="recipient-name" class="col-lg-4 col-form-label">Descuento pie factura</label>
			<input type="number" id="descuentopie" name="descuentopie" value="{{$data->descuento ?? ''}}"></input>
			<input type="hidden" id="descuentoimportepie" name="descuentoimportepie" value=""></input>
		</div>
		<div class="form-group row" id="puntoventaremito">
			<label for="recipient-name" class="col-lg-4 col-form-label requerido">Pto.venta del remito</label>
			<input type="hidden" id="puntoventaremitoori_id" class="form-control" value="{{old('puntoventaremitoori_id', $data->puntoventaremito_id ?? '')}}" />
			<select name="puntoventaremito_id" id="puntoventaremito_id" data-placeholder="Punto de venta del remito" class="col-lg-5 form-control required" data-fouc>
			</select>
		</div>
		<input type="hidden" id="cantidadbulto" name="cantidadbulto" value="0"></input>
		<div class="form-group row">
			<label for="moneda" class="col-lg-3 col-form-label requerido">Moneda</label>
			<select name="moneda_id" id="moneda_id" data-placeholder="Depósito" class="col-lg-3 form-control required" data-fouc>
				<option value="">-- Seleccionar moneda  --</option>
				@foreach($moneda_query as $key => $value)
					@if( (int) $value->id == (int) old('moneda_id', $data->moneda_id ?? '1'))
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>		
		<div class="form-group row">
			<label for="deposito" class="col-lg-3 col-form-label requerido">Depósito</label>
			<select name="deposito_id" id="deposito_id" data-placeholder="Depósito" class="col-lg-8 form-control factura-carga-bloqueable required" data-fouc>
				<option value="">-- Seleccionar depósito  --</option>
				@foreach($deposito_query as $key => $value)
					@if( (int) $value->id == (int) old('deposito_id', $data->deposito_id ?? '1'))
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
	</div>
</div>

<div class="card" id="factura-carga-contenido">
    <div class="card-body">
    	<table class="table table-hover" id="itemspedido-table">
    		<thead>
    			<tr>
    				<th style="width: 5%;">Item</th>
    				<th style="width: 12%;">Art&iacute;culo</th>
					@if ($layoutItemsPedido)
					<th style="width: 16%;">Descripci&oacute;n Art&iacute;culo</th>
					<th>UMD</th>
    				<th style="width: 9%;">Cajas</th>
    				<th style="width: 9%;">Piezas</th>
    				<th style="width: 9%;">Kilos</th>
					<th>Descuento</th>
					@else
					<th style="width: 50%;">Detalle</th>
    				<th style="width: 10%;">Cantidad</th>
					<th style="width: 10%;">Descuento</th>
					@endif
    				<th style="width: 9%; text-align: right;">Precio</th>
    			</tr>				
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($data->venta_emisiones[0] ?? '') 
					@foreach ($data->venta_emisiones as $item)
            			<tr class="{{ $layoutItemsPedido ? 'item-pedido' : 'item-factura' }}">
               				<td>
               					<input type="text" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{old('listaprecios_id', $item->listaprecio_id??'')}}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{old('monedas_id', $item->moneda_id??'')}}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{old('incluyeimpuestos', $item->incluyeimpuesto??'')}}" />
                				<input type="hidden" name="impuesto_ids[]" class="form-control impuesto_id" readonly value="{{old('impuesto_ids', $item->impuesto_id??'')}}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$item->id??''}}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{$item->lotes->id ?? ''}}" />
                			</td>
                            <td>
                                <div class="form-group row" id="articulo">
                                    <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$item->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="{{$item->articulo_id ?? ''}}" >
									<input type="hidden" class="categoria_id" name="categoria_ids[]" value="{{$item->articulos->categoria_id ?? ''}}" >
									<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="{{$item->articulos->subcategoria_id ?? ''}}" >
                                    <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC" data-solo-facturable="1">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulo codigoarticulolocal form-control" name="codigoarticulos[]" value="{{$item->articulos->sku ?? ''}}" >
                                    <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="{{$item->articulos->sku ?? ''}}" >
                                </div>
                            </td>		
                            <td>
                                <input type="text" style="WIDTH: {{ $layoutItemsPedido ? '220' : '700' }}px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{$item->detalle ?? ''}}" @if($layoutItemsPedido) readonly @endif>
                            </td>
							@if ($layoutItemsPedido)
							<td></td>
							<td><input type="text" name="cajas[]" class="form-control caja" value="" /></td>
							<td><input type="text" name="piezas[]" class="form-control pieza" value="" /></td>
							<td><input type="text" name="kilos[]" class="form-control kilo" value="{{number_format(old('cantidades.'.$loop->index, optional($item)->cantidad),2)}}" /></td>
							<td></td>
							@else
							<td>
								<input type="text" name="cantidades[]" class="form-control cantidad" value="{{number_format(old('cantidades.'.$loop->index, optional($item)->cantidad),2)}}" />
                			</td>		
							<td>
								<input type="text" name="descuentos[]" class="form-control descuento" value="{{number_format(old('descuentos.'.$loop->index, optional($item)->descuento),2)}}" />
                			</td>
							@endif								
                			<td>
                				<input type="text" style="text-align: right;" name="precios[]" class="form-control precio" readonly value="{{number_format(old('precios.'.$loop->index, optional($item)->precio),2)}}" />
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
		@if ($layoutItemsPedido)
			<input type="hidden" id="categoria_secos_id" class="form-control" value="{{config('cliente.CATEGORIA_SECOS_ID')}}" />
			<input type="hidden" id="subcategoria_maquina_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_MAQUINA_ID')}}" />
			<input type="hidden" id="subcategoria_tira_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_TIRA_ID')}}" />
			<input type="hidden" id="topedescuento" class="form-control" value="{{config('cliente.TOPE_DESCUENTO')}}" />
			@include('ventas.factura.template_bierzo')
		@else
			@include('ventas.factura.template')
		@endif
	    <div class="row col-md-12">
        	<div class="col-md-2">
        		<button id="agrega_renglon" class="pull-right btn btn-danger factura-carga-bloqueable">+ Agrega rengl&oacute;n</button>
        	</div>
		</div>
		<div class="row">
			<div class="col-sm-6">
               	<!-- textarea -->
			   <div class="form-group" id="div_leyendafacturacion">
	                <label>Leyendas</label>
    	            <textarea id="leyendafactura" name="leyendafactura" class="form-control factura-carga-bloqueable" cols="80" rows="6" placeholder="Leyendas de factura ...">{{$data->leyenda ?? ''}}</textarea>
            	</div>
			</div>
			<div class="col-sm-6">
				<table class="table table-sm" id="total-factura-table">
					<thead>
						<th style="width: 25%;"></th>
						<th style="width: 10%;"></th>
						<th style="width: 15%;"></th>
					</thead>
					<tbody id="tbody-tabla-total-factura">
						@if ($data->venta_impuestos[0] ?? '') 
							@foreach ($data->venta_impuestos as $impuesto)
								<tr class="item-total-factura">
									<td>
										<input type="text" style="" class="conceptototal form-control" name="conceptototales[]" value="{{$impuesto->concepto}}" readonly>
									</td>	
									<td>
										@if ($impuesto->tasa != 0)
											<input type="text" style="text-align:right;" name="tasatotales[]" class="form-control tasatotal" value="{{number_format($impuesto->tasa,2)}}" readonly/>
										@else
											<input type="text" style="text-align:right;" name="tasatotales[]" class="form-control tasatotal" value="" readonly/>
										@endif
									</td>			
									<td>
										@if ($impuesto->concepto == 'Total')
											<input type="text" style="text-align:right; font-weight: bold;" name="montototales[]" class="form-control importetotal" value="{{number_format($impuesto->importe,2)}}" readonly/>
										@else
											<input type="text" style="text-align:right;" name="montototales[]" class="form-control importetotal" value="{{number_format($impuesto->importe,2)}}" readonly/>
										@endif
										<input type="hidden" name="baseimponibles[]" class="baseimponible" value="{{$impuesto->baseimponible}}"/>
										<input type="hidden" name="provincia_ids[]" class="provincia_id" value="{{$impuesto->provincia_id}}"/>
										<input type="hidden" name="impuestototal_ids[]" class="impuestototal_id" value="{{$impuesto->impuesto_id}}"/>
									</td>
								</tr>
							@endforeach
						@endif
					</tbody>
				</table>
			</div>                        
		</div>
		<div class="row col-md-12" id="datos_exportacion" style="display: none">
			<div class="col-md-6">
				<div class="form-group" id="div_leyendaexportacion">
                	<label>Leyenda Exportaci&oacute;n</label>
                	<textarea id="leyendaexportacion" class="form-control" cols="90" rows="6" placeholder="Leyendas de exportación ..."></textarea>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group row" id="div_incoterm">
					<label for="recipient-name" class="col-lg-4 col-form-label requerido">Condiciones de venta (incoterms)</label>
					<select name="incoterm_id" id="incoterm_id" data-placeholder="Incoterms" class="col-lg-6 form-control required" data-fouc>
					</select>
				</div>
				<div class="form-group row" id="div_formapago">
					<label for="recipient-name" class="col-lg-4 col-form-label requerido">Forma de pago</label>
					<select name="formapago_id" id="formapago_id" data-placeholder="Forma de pago" class="col-lg-6 form-control required" data-fouc>
					</select>
				</div>
				<div class="form-group row" id="div_mercaderia">
					<label for="recipient-name" class="col-lg-4 col-form-label">Mercader&iacute;a</label>
					<input type="text" class="col-lg-6 form-control" id="mercaderia" name="marcaderia" value=""></input>
				</div>
			</div>
        </div>
    </div>
</div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="tipotransacciondefault_id" name="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />
<input type="hidden" id="modofacturacion" name="modofacturacion" class="form-control" value="{{$data->puntoventas->modofacturacion ?? ''}}" />
<input type="hidden" id="ordenventa_id" name="ordenventa_id" class="form-control" value="{{$data->ordenventa_id ?? ''}}" />
<input type="hidden" id="estadocliente" value="{{ $data->clientes->estado ?? '' }}">

@include('ventas.factura.modal')
@include('ventas.factura.templatetotalfactura')
@include('includes.stock.modalconsultaarticulo')
@include('includes.ventas.modalconsultacliente')
@if (config('app.empresa') == 'EL BIERZO')
@include('includes.ventas.modalconsultatransporte')
@endif
