@php $remito = $remito ?? null; @endphp
<div class="row">
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query}}" data-formapago="{{$formapago_query}}">
        <input type="hidden" id="codigoremito" class="form-control" value="{{old('codigoremito', $remito->codigo ?? '')}}" />
		<input type="hidden" id="topedescuento" class="form-control" value="{{config('cliente.TOPE_DESCUENTO')}}" />
		<input type="hidden" id="porcentaje_valor_asegurado" value="{{ \App\Support\Ventas\RemitoValorAseguradoSupport::porcentaje() }}" />
		<input type="hidden" id="categoria_secos_id" class="form-control" value="{{config('cliente.CATEGORIA_SECOS_ID')}}" />
		<input type="hidden" id="subcategoria_maquina_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_MAQUINA_ID')}}" />
		<input type="hidden" id="subcategoria_tira_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_TIRA_ID')}}" />
		<div class="form-group row" id="div-cliente">
			<label for="cliente" class="col-lg-3 col-form-label">Cliente</label>
			<input type="hidden" class="col-lg-2" id="cliente_id" name="cliente_id" value="{{$remito->cliente_id??''}}" >
			@php
				$codigoClientePedidoForm = trim((string) ($remito->clientes->codigo ?? ''));
				$nombreClientePedidoForm = trim((string) ($remito->clientes->nombre ?? ''));
				$nombreClientePedidoDisplay = $codigoClientePedidoForm !== '' && $nombreClientePedidoForm !== ''
					? $codigoClientePedidoForm.' - '.$nombreClientePedidoForm
					: $nombreClientePedidoForm;
			@endphp
			<input type="text" class="col-lg-2 codigocliente" id="codigocliente" name="codigocliente" value="{{$remito->clientes->codigo??''}}" placeholder="N&ordm;" title="N&uacute;mero de cliente">
			<input type="text" class="col-lg-5 form-control" id="nombrecliente" name="nombrecliente" value="{{ $nombreClientePedidoDisplay }}" readonly>
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
		<div id="aviso-padron-operacion-pedido" class="alert d-none col-12 mb-2" role="alert"></div>
		@include('ventas.cliente.partials.arca_apoc_operacion_support')
		<div class="form-group row">
   			<label for="vendedor" class="col-lg-3 col-form-label requerido">Vendedor</label>
        	<select name="vendedor_id" id="vendedor_id" data-placeholder="Vendedor" class="col-lg-8 form-control remito-carga-bloqueable" data-fouc required>
        		<option value="">-- Seleccionar vendedor --</option>
        		@foreach($vendedor_query as $key => $value)
        			@if( (int) $value->id == (int) old('vendedor_id', $remito->vendedor_id ?? ''))
        				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        			@else
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endif
        		@endforeach
        	</select>
		</div>
		<div class="form-group row">
			<label for="transporte" class="col-lg-3 col-form-label requerido">Reparto</label>
			<input type="hidden" class="col-form-label transporte_id" id="transporte_id" name="transporte_id" value="{{$remito->transporte_id ?? ''}}" >
			<input type="text" class="col-lg-2 codigotransporte remito-carga-bloqueable" id="codigotransporte" name="codigotransporte" value="{{$remito->transportes->codigo ?? ''}}" required>
			<input type="text" class="col-lg-5 form-control nombretransporte" id="nombretransporte" name="nombretransporte" value="{{$remito->transportes->nombre ?? ''}}" readonly>
			<button type="button" title="Consulta repartos" style="padding:1;" class="btn-accion-tabla consultatransporte tooltipsC">
				<i class="fa fa-search text-primary"></i>
			</button>
			<input type="hidden" name="nombretransporte" id="nombretransporte" class="form-control" value="{{old('nombretransporte', $remito->transportes->nombre ?? '')}}">
		</div>		
		<div class="form-group row" id="divlugar">
    		<label for="lugarentrega" id="label-lugarentrega" class="col-lg-3 col-form-label">Lugar de Entrega</label>
    		<div class="col-lg-8">
    			<div class="input-group">
    				<input type="text" name="lugarentrega" id="lugarentrega" class="form-control remito-carga-bloqueable" value="{{old('lugarentrega', $remito->lugarentrega ?? '')}}" placeholder="Seleccione un lugar de entrega del cliente">
    				<div class="input-group-append" id="div-cambiar-lugarentrega" style="display: none;">
    					<button type="button" id="btn-cambiar-lugarentrega" class="btn btn-outline-secondary btn-sm" title="Cambiar lugar de entrega">
    						Cambiar
    					</button>
    				</div>
    			</div>
    			<small id="aviso-lugarentrega-obligatorio" class="form-text text-danger" style="display: none;">
    				Este cliente tiene lugares de entrega cargados. Debe elegir uno para continuar.
    			</small>
    		</div>
		</div>
		<input type="hidden" name="cliente_entrega_id" id="cliente_entrega_id" value="{{old('cliente_entrega_id', $remito->cliente_entrega_id ?? '')}}">
		<input type="hidden" id="cliente_entrega_id_previa" name="cliente_entrega_id_previa" value="{{old('cliente_entrega_id', $remito->cliente_entrega_id ?? '')}}">
		<input type="hidden" id="entrega_nombre" name="entrega_nombre" value="{{old('entrega_nombre', $remito->entrega_nombre ?? '')}}">
		<input type="hidden" id="fl_cliente_tiene_entrega" value="0">
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
    		<label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
    		<div class="col-lg-3">
    			<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $remito->fecha ?? date('Y-m-d')),0,10)}}" readonly>
    		</div>
			<label for="estado" class="col-lg-3 col-form-label">Estado</label>
			<input type="text" id="estadoremito" name="estadoremito" class="col-lg-3 form-control" readonly value="{{ $remito->estadoremito ?? '' }}">
			<input type="hidden" id="caja_reales" value="{{ $remito->pedidos?->caja_reales ?? '' }}">
		</div>
		<div class="form-group row">
    		<label for="fechaentrega" class="col-lg-3 col-form-label required">Entrega</label>
    		<div class="col-lg-3 row">
    			<input type="date" name="fechaentrega" id="fechaentrega" class="form-control remito-carga-bloqueable" value="{{substr(old('fechaentrega', $remito->fechaentrega ?? date('Y-m-d')),0,10)}}" requerido>
    		</div>
			<div class="col-lg-6 row" id="divlote">
				<label for="lote" class="col-lg-2 col-form-label">Lote</label>
				<select name="lote_id" id="lote_id" data-placeholder="Lote de stock" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar lote --</option>
					@foreach($lote_query as $key => $value)
						@if( (int) $value->id == (int) old('lote_id', ($remito && $remito->remito_articulos->count()) ? ($remito->remito_articulos[0]->lotes->id ?? '') : ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->numerodespacho }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->numerodespacho}}</option>    
						@endif
					@endforeach
        		</select>				
			</div>
		</div>
		<div class="form-group row">
			<label for="zonavta" class="col-lg-3 col-form-label">Zona de venta</label>
				<input type="hidden" id="zonavta_id_previa" name="zonavta_id_previa" value="{{old('zonavta_id', $remito->zonavta_id ?? '')}}" >
				<input type="hidden" id="desc_zonavta" name="desc_zonavta" value="{{old('desc_zonavta', $remito->desc_zonavta ?? '')}}" >
				<input type="hidden" class="col-form-label zonavta_id" id="zonavta_id" name="zonavta_id" value="{{$remito->zonavta_id ?? ''}}" >
				<input type="text" class="form-control col-lg-2 codigozonavta" id="codigozonavta" name="codigozonavta" value="{{$remito->zonavtas->codigo ?? ''}}" >
				<input type="text" class="form-control col-lg-4 nombrezonavta" id="nombrezonavta" name="nombrezonavta" value="{{$remito->zonavtas->nombre ?? ''}}" readonly>
				<button type="button" title="Consulta zonavtaes" style="padding:1;" class="btn-accion-tabla consultazonavta tooltipsC">
					<i class="fa fa-search text-primary"></i>
				</button>
				<input type="hidden" name="nombrezonavta" id="nombrezonavta" class="form-control" value="{{old('nombrezonavta', $remito->zonavtas->nombre ?? '')}}">
		</div>
       	<input type="hidden" name="condicionventa_id" id="condicionventa_id" value="{{$remito->condicionventa_id??''}}">
        <input type="hidden" id="descuento" name="descuento" class="form-control col-lg-2" value="{{$remito->descuento ?? '0'}}" />
	</div>
</div>

<div class="card" id="remito-carga-contenido">
    <div class="card-body">
    	<table class="table table-hover" id="itemsremito-table">
    		<thead>
    			<tr>
    				<th style="width: 5%;">Item</th>
    				<th style="width: 12%;">Art&iacute;culo</th>
					<th style="width: 16%;">Descripción Artículo</th>
					<th>UMD</th>
    				<th style="width: 9%;">Cajas</th>
    				<th style="width: 9%;">Piezas</th>
    				<th style="width: 9%;">Kilos</th>
					<th>Descuento</th>
    				<th style="width: 9%; text-align: right;">Precio</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($remito && ($remito->remito_articulos ?? null))
					@foreach (old('items', $remito->remito_articulos->count() ? $remito->remito_articulos : ['']) as $remitoitem)
            			<tr class="item-remito">
                			<td>
								@if ($remitoitem->estado == 'A')
                					<input type="text" style="background-color:red;font-weight:900;" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@else
                					<input type="text" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
								@endif
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{old('listaprecios_id', $remitoitem->listaprecio_id??'')}}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{old('monedas_id', $remitoitem->moneda_id??'')}}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{old('incluyeimpuestos', $remitoitem->incluyeimpuesto??'')}}" />
                				<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="{{old('descuentos', $remitoitem->descuento??'')}}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$remitoitem->id??''}}" />
								<input type="hidden" name="estados[]" class="form-control estados" value="{{$remitoitem->estado??'P'}}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{$remitoitem->lotes->id ?? ''}}" />
                			</td>
                            <td>
                                <div class="form-group row" id="articulo">
                                    <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$remitoitem->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="{{$remitoitem->articulo_id ?? ''}}" >
									<input type="hidden" class="categoria_id" name="categoria_ids[]" value="{{$remitoitem->articulos->categoria_id ?? ''}}" >
									<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="{{$remitoitem->articulos->subcategoria_id ?? ''}}" >
                                    <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC" data-solo-facturable="1">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulo codigoarticulolocal form-control" name="codigoarticulos[]" value="{{$remitoitem->articulos->sku ?? ''}}" >
                                    <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="{{$remitoitem->articulos->sku ?? ''}}" >
                                </div>
                            </td>		
                            <td>
                                <input type="text" style="WIDTH: 220px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{$remitoitem->articulos->descripcion ?? ''}}" readonly>
                            </td>										
							<td>
								<select name="unidadmedida_ids[]" data-placeholder="Unidad de Medida" class="unidadmedida_id form-control" data-fouc>
									@foreach($unidadmedida_query as $key => $value)
										@if( (int) $value['id'] == (int) old('unidadmedida_ids', $remitoitem->unidadmedida_id ?? ''))
											<option value="{{ $value['id'] }}" selected="select">{{ $value['abreviatura'] }}</option>    
										@else
											<option value="{{ $value['id'] }}">{{ $value['abreviatura'] }}</option>    
										@endif
									@endforeach
								</select>	
								<input type="hidden" name="unidadmedidas[]" class="form-control unidadmedida" value="" />								
							</td>										
                			<td>
								<input type="text" name="cajas[]" class="form-control caja" value="{{number_format(old('cajas.'.$loop->index, optional($remitoitem)->caja),2,'.','')}}" />
                			</td>
                			<td>
								<input type="text" name="piezas[]" class="form-control pieza" value="{{number_format(old('piezas.'.$loop->index, optional($remitoitem)->pieza),2,'.','')}}" />
                			</td>
                			<td>
								<input type="text" name="kilos[]" class="form-control kilo" value="{{number_format(old('kilos.'.$loop->index, optional($remitoitem)->kilo),2,'.','')}}" />
                			</td>
							<td>
								<select name="descuentoventa_ids[]" data-placeholder="Descuento" class="descuentoventa_id form-control" data-fouc>
									<option value="">-Descuento-</option>
									@foreach($descuentoventa_query as $key => $value)
										@if( (int) $value->id == (int) old('descuentoventa_ids', $remitoitem->descuentoventa_id ?? ''))
											<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
										@else
											<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
										@endif
									@endforeach
								</select>	
								<input type="hidden" name="descuentoventaanterior_ids[]" class="form-control descuentoventaanterior_id" value="{{$remitoitem->descuentoventa_id}}" />
							</td>				
                			<td>
                				<input type="text" style="text-align: right;" name="precios[]" class="form-control precio" readonly value="{{number_format($remitoitem->precio,2,'.','')}}" />
                			</td>
                			<td>
								@if ($remitoitem->estado == 'A')
									<button type="button" title="Recupera Item" style="padding:0;" class="btn-accion-tabla anulaitem tooltipsC">
                            			<i class="fa fa-window-close text-success ianulaItem"></i>
								@else
									<button type="button" title="Anula Item" style="padding:0;" class="btn-accion-tabla anulaitem tooltipsC">
                            			<i class="fa fa-window-close text-danger ianulaItem"></i>
								@endif
								</button>
								@if (can('borrar-remitos', false))
									<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
										<i class="fa fa-trash text-danger"></i>
									</button>
								@endif
								<input name="checks[]" style="display:none;" class="checkImpresion" type="checkbox" autocomplete="off"> 
								<input type="hidden" style="text-align: right;" name="observaciones[]" class="form-control observacion" value="" />
								<input type="hidden" style="text-align: right;" name="sincargos[]" class="form-control sincargo" value="{{$remitoitem->precio == 0 ? 'S' : 'N'}}" />
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.remito.template')
        <div class="row col-md-12">
        	<div class="col-md-2">
        		<button id="agrega_renglon" class="pull-right btn btn-danger remito-carga-bloqueable">+ Agrega rengl&oacute;n</button>
        	</div>
			<div class="col-md-6">
               	<!-- textarea -->
               	<div class="form-group">
               		<label>Leyendas</label>
               		<textarea name="leyenda" class="form-control remito-carga-bloqueable" rows="3" placeholder="Leyendas ...">{{old('leyenda', $remito->leyenda ?? '')}}</textarea>
               	</div>
            </div>
        	<div class="col-md-4 row">
				<label style="margin-top: 6px;">Total cajas:&nbsp</label>
                <input type="text" id="totalcajasremito" name="totalcajasremito" class="form-control col-sm-3" readonly value="" />
                <label style="margin-top: 6px;">Total piezas:&nbsp</label>
                <input type="text" id="totalpiezasremito" name="totalpiezasremito" class="form-control col-sm-3" readonly value="" />
				<label style="margin-top: 6px;">Total kilos:&nbsp</label>
                <input type="text" id="totalkilosremito" name="totalkilosremito" class="form-control col-sm-3" readonly value="" />
            </div>
        </div>
        <div class="row col-md-12 mt-2">
            <div class="col-md-8"></div>
            <div class="col-md-4">
                <div class="form-group row align-items-center mb-0">
                    <label for="valoraseguradoremito" class="col-form-label pr-2">Valor asegurado</label>
                    <input type="text" id="valoraseguradoremito" name="valor_asegurado" class="form-control col-sm-6" readonly value="" style="text-align: right; font-weight: 600;" />
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="tiposuspension_id" name="tiposuspension_id" value="{{$tiposuspension_id ?? ''}}" >
<input type="hidden" id="tiposuspensioncliente_query" value="{{$tiposuspensioncliente_query ?? ''}}" >

<input type="hidden" id="estadocliente" value="{{ $remito->clientes->estado ?? '' }}">
<input type="hidden" id="estado" name="estado" value="{{ $remito->estado ?? '' }}">
<input type="hidden" id="origen_remito" name="origen" value="{{ old('origen', $remito->origen ?? 'manual') }}">
<input type="hidden" id="nombretiposuspensioncliente" value="{{ $remito->clientes->tipossuspensioncliente->nombre ?? ''}}">
<input type="hidden" id="tiposuspensioncliente_id" value="{{ $remito->clientes->tiposupension_id ?? ''}}">
<input type="hidden" id="tipoalta" value="{{ $remito->clientes->tipoalta ?? ''}}">
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="puntoventadefault_id" class="form-control" value="{{$puntoventadefault_id}}" />
<input type="hidden" id="puntoventaremitodefault_id" class="form-control" value="{{$puntoventaremitodefault_id}}" />
<input type="hidden" id="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />

@include('ventas.remito.modal')
@include('ventas.remito.modal2')
@include('ventas.remito.modal3')
@include('includes.stock.modalconsultaarticulo')
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultatransporte')
@include('includes.ventas.modalconsultazonavta')
@include('includes.ventas.modalseleccionclienteentrega')
@include('ventas.ordentrabajo.modalcrearordentrabajo')
@include('ventas.remito.modalkilosvillafranca')
@include('ventas.remito.modalfacturaremito')

