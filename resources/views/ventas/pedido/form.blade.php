<style>
	#itemspedido-table thead th {
		background: #85C1E9;
		color: #17202A;
		font-size: 0.85rem;
		white-space: nowrap;
		vertical-align: middle;
	}
	#itemspedido-table td {
		vertical-align: middle;
	}
	#itemspedido-table .codigoarticulo,
	#itemspedido-table .descripcionarticulo {
		min-height: 2rem;
		height: auto;
	}
	.pedido-totales-grid {
		display: grid;
		grid-template-columns: auto minmax(4.5rem, 1fr);
		gap: 0.35rem 0.5rem;
		align-items: center;
		justify-items: end;
	}
	.pedido-totales-grid label {
		margin: 0;
		white-space: nowrap;
		font-size: 0.875rem;
	}
	.pedido-cabecera-consulta .form-control {
		height: calc(1.9rem + 2px);
		padding-top: 0.25rem;
		padding-bottom: 0.25rem;
	}
	.pedido-cabecera-consulta .codigocliente,
	.pedido-cabecera-consulta .codigotransporte,
	.pedido-cabecera-consulta .codigozonavta {
		max-width: 5.5rem;
	}
	#itemspedido-table.pedido-modo-pesada-manual .pesada {
		background-color: #e8f8f0;
		border-color: #28a745;
		box-shadow: 0 0 0 0.1rem rgba(40, 167, 69, 0.25);
	}
	#itemspedido-table.pedido-modo-pesada-manual .pesada:focus {
		background-color: #fff;
		border-color: #1e7e34;
		box-shadow: 0 0 0 0.15rem rgba(40, 167, 69, 0.45);
	}
</style>

<div class="row pedido-cabecera-consulta">
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query}}" data-formapago="{{$formapago_query}}">
        <input type="hidden" id="codigopedido" class="form-control" value="{{old('codigopedido', $pedido->codigo ?? '')}}" />
		<input type="hidden" id="topedescuento" class="form-control" value="{{config('cliente.TOPE_DESCUENTO')}}" />
		<input type="hidden" id="categoria_secos_id" class="form-control" value="{{config('cliente.CATEGORIA_SECOS_ID')}}" />
		<input type="hidden" id="subcategoria_maquina_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_MAQUINA_ID')}}" />
		<input type="hidden" id="subcategoria_tira_id" class="form-control" value="{{config('cliente.SUBCATEGORIA_TIRA_ID')}}" />
		<div class="form-group row" id="div-cliente">
			<label for="codigocliente" class="col-lg-3 control-label text-right pr-2">Cliente</label>
			<input type="hidden" id="cliente_id" name="cliente_id" value="{{$pedido->cliente_id??''}}" >
			<input type="text" class="col-lg-2 form-control codigocliente" id="codigocliente" name="codigocliente" value="{{$pedido->clientes->codigo??''}}" placeholder="N&ordm;" title="C&oacute;digo; Enter valida; F1 consulta">
			<input type="text" class="col-lg-5 form-control" id="nombrecliente" name="nombrecliente" value="{{ $pedido->clientes->nombre ?? '' }}" readonly>
			<div class="form-group boton-alta-cliente" style="display: none">
				<button type="button" id="botonaltacliente" class="btn btn-primary btn-sm">
					<i class="fa fa-user"></i> Alta Cliente
				</button>
			</div>
			@if ($datos['funcion'] == 'crear')
				<a class="btn-accion-tabla tooltipsC ml-1" href="{{route('crear_cliente', ['tipoalta' => 'P'])}}" id="clienteprovisorio" title="Crear cliente provisorio">
                	<i class="fa fa-user"></i>
            	</a>
			@endif
			<button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente tooltipsC">
					<i class="fa fa-search text-primary"></i>
			</button>
			<label for="Tiposuspension" id="nombretiposuspension" style="padding: 0px;" class="col-form-label text-danger"></label>
		</div>
		<div id="aviso-padron-operacion-pedido" class="alert d-none col-12 mb-2" role="alert"></div>
		@include('ventas.cliente.partials.arca_apoc_operacion_support')
		<div class="form-group row">
   			<label for="vendedor_id" class="col-lg-3 control-label text-right pr-2 requerido">Vendedor</label>
			<div class="col-lg-8">
        		<select name="vendedor_id" id="vendedor_id" data-placeholder="Vendedor" class="form-control pedido-carga-bloqueable" data-fouc required>
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
		</div>
		<div class="form-group row">
			<label for="codigotransporte" class="col-lg-3 control-label text-right pr-2 requerido">Reparto</label>
			<input type="hidden" class="transporte_id" id="transporte_id" name="transporte_id" value="{{$pedido->transporte_id ?? ''}}" >
			<input type="text" class="col-lg-2 form-control codigotransporte pedido-carga-bloqueable" id="codigotransporte" name="codigotransporte" value="{{$pedido->transportes->codigo ?? ''}}" required>
			<input type="text" class="col-lg-5 form-control nombretransporte" id="nombretransporte" name="nombretransporte" value="{{$pedido->transportes->nombre ?? ''}}" readonly>
			<button type="button" title="Consulta repartos" class="btn-accion-tabla consultatransporte tooltipsC">
				<i class="fa fa-search text-primary"></i>
			</button>
		</div>
		<div class="form-group row" id="divlugar">
    		<label for="lugarentrega" id="label-lugarentrega" class="col-lg-3 control-label text-right pr-2">Lugar de entrega</label>
    		<div class="col-lg-8">
    			<div class="input-group input-group-sm">
    				<input type="text" name="lugarentrega" id="lugarentrega" class="form-control pedido-carga-bloqueable" value="{{old('lugarentrega', $pedido->lugarentrega ?? '')}}" placeholder="Seleccione un lugar de entrega del cliente">
    				<div class="input-group-append" id="div-cambiar-lugarentrega" style="display: none;">
    					<button type="button" id="btn-cambiar-lugarentrega" class="btn btn-outline-secondary" title="Cambiar lugar de entrega">
    						Cambiar
    					</button>
    				</div>
    			</div>
    			<small id="aviso-lugarentrega-obligatorio" class="form-text text-danger" style="display: none;">
    				Este cliente tiene lugares de entrega cargados. Debe elegir uno para continuar.
    			</small>
    		</div>
		</div>
		<input type="hidden" name="cliente_entrega_id" id="cliente_entrega_id" value="{{old('cliente_entrega_id', $pedido->cliente_entrega_id ?? '')}}">
		<input type="hidden" id="cliente_entrega_id_previa" name="cliente_entrega_id_previa" value="{{old('cliente_entrega_id', $pedido->cliente_entrega_id ?? '')}}">
		<input type="hidden" id="entrega_nombre" name="entrega_nombre" value="{{old('entrega_nombre', $pedido->entrega_nombre ?? '')}}">
		<input type="hidden" id="fl_cliente_tiene_entrega" value="0">
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
    		<label for="fecha" class="col-lg-3 control-label text-right pr-2">Fecha</label>
    		<div class="col-lg-3">
    			<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $pedido->fecha ?? date('Y-m-d')),0,10)}}" readonly>
    		</div>
			<label for="estadopedido" class="col-lg-3 control-label text-right pr-2">Estado</label>
			<input type="text" id="estadopedido" name="estadopedido" class="col-lg-3 form-control" readonly value="{{ $pedido->estadopedido ?? '' }}">
			<input type="hidden" id="caja_reales" name="caja_reales" value="{{ old('caja_reales', $pedido->caja_reales ?? '') }}">
		</div>
		<div class="form-group row">
    		<label for="fechaentrega" class="col-lg-3 control-label text-right pr-2 required">Entrega</label>
    		<div class="col-lg-3">
    			<input type="date" name="fechaentrega" id="fechaentrega" class="form-control pedido-carga-bloqueable" value="{{substr(old('fechaentrega', $pedido->fechaentrega ?? date('Y-m-d')),0,10)}}" requerido>
    		</div>
			<div class="col-lg-6" id="divlote">
				<div class="form-group row mb-0">
					<label for="lote_id" class="col-lg-3 control-label text-right pr-2">Lote</label>
					<div class="col-lg-9">
						<select name="lote_id" id="lote_id" data-placeholder="Lote de stock" class="form-control" data-fouc>
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
			</div>
		</div>
		<div class="form-group row">
			<label for="codigozonavta" class="col-lg-3 control-label text-right pr-2">Zona de venta</label>
			<input type="hidden" id="zonavta_id_previa" name="zonavta_id_previa" value="{{old('zonavta_id', $pedido->zonavta_id ?? '')}}" >
			<input type="hidden" id="desc_zonavta" name="desc_zonavta" value="{{old('desc_zonavta', $pedido->desc_zonavta ?? '')}}" >
			<input type="hidden" class="zonavta_id" id="zonavta_id" name="zonavta_id" value="{{$pedido->zonavta_id ?? ''}}" >
			<input type="text" class="form-control col-lg-2 codigozonavta" id="codigozonavta" name="codigozonavta" value="{{$pedido->zonavtas->codigo ?? ''}}" title="C&oacute;digo; Enter valida y pasa al art&iacute;culo; F1 consulta">
			<input type="text" class="form-control col-lg-4 nombrezonavta" id="nombrezonavta" name="nombrezonavta" value="{{$pedido->zonavtas->nombre ?? ''}}" readonly>
			<button type="button" title="Consulta zonas de venta" class="btn-accion-tabla consultazonavta tooltipsC">
				<i class="fa fa-search text-primary"></i>
			</button>
		</div>
       	<input type="hidden" name="condicionventa_id" id="condicionventa_id" value="{{$pedido->condicionventa_id??''}}">
	</div>
</div>

<div class="card card-outline card-info" id="pedido-carga-contenido">
	<div class="card-header py-2 d-flex align-items-center justify-content-between">
		<strong>Artículos del pedido</strong>
		<small class="text-muted mb-0">Enter avanza; F1 / lupa consultan. Máx. 42 ítems.</small>
	</div>
    <div class="card-body p-2">
		<div class="table-responsive">
    	<table class="table table-sm table-bordered table-hover mb-2" id="itemspedido-table">
    		<thead>
    			<tr>
    				<th style="width: 4%;">Item</th>
    				<th style="width: 12%;">Art&iacute;culo</th>
					<th style="width: 18%;">Descripci&oacute;n</th>
					<th style="width: 6%;">UMD</th>
    				<th style="width: 8%;">Cajas</th>
    				<th style="width: 8%;">Piezas</th>
    				<th style="width: 8%;">Kilos</th>
					<th style="width: 8%;">Pesada</th>
					<th style="width: 10%;">Descuento</th>
    				<th style="width: 9%; text-align: right;">Precio</th>
					<th style="width: 5%;"></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($pedido->pedido_articulos ?? '')
					@foreach (old('items', $pedido->pedido_articulos->count() ? $pedido->pedido_articulos : ['']) as $pedidoitem)
            			<tr class="item-pedido">
                			<td>
								@if ($pedidoitem->estado == 'A')
                					<input type="text" style="background-color:red;font-weight:900;" name="items[]" class="form-control form-control-sm item" value="{{ $loop->index+1 }}" readonly>
								@else
                					<input type="text" name="items[]" class="form-control form-control-sm item" value="{{ $loop->index+1 }}" readonly>
								@endif
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{old('listaprecios_id', $pedidoitem->listaprecio_id??'')}}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{old('monedas_id', $pedidoitem->moneda_id??'')}}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{old('incluyeimpuestos', $pedidoitem->incluyeimpuesto??'')}}" />
                				<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="{{old('descuentos', $pedidoitem->descuento??'')}}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$pedidoitem->id??''}}" />
								<input type="hidden" name="estados[]" class="form-control estados" value="{{$pedidoitem->estado??'P'}}" />
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
                                <div class="form-group row mb-0" id="articulo">
                                    <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$pedidoitem->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="{{$pedidoitem->articulo_id ?? ''}}" >
									<input type="hidden" class="categoria_id" name="categoria_ids[]" value="{{$pedidoitem->articulos->categoria_id ?? ''}}" >
									<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="{{$pedidoitem->articulos->subcategoria_id ?? ''}}" >
                                    <button type="button" title="Consulta articulos" class="btn-accion-tabla consultaarticulo tooltipsC" data-solo-facturable="1">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="codigoarticulo codigoarticulolocal form-control form-control-sm" name="codigoarticulos[]" value="{{$pedidoitem->articulos->sku ?? ''}}" style="width: 7.5rem; display: inline-block;" >
                                    <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="{{$pedidoitem->articulos->sku ?? ''}}" >
                                </div>
                            </td>
                            <td>
                                <input type="text" class="descripcionarticulo form-control form-control-sm" name="descripcionarticulos[]" value="{{$pedidoitem->articulos->descripcion ?? ''}}" readonly>
                            </td>
							<td>
								<select name="unidadmedida_ids[]" data-placeholder="Unidad de Medida" class="unidadmedida_id form-control form-control-sm" data-fouc>
									@foreach($unidadmedida_query as $key => $value)
										@if( (int) $value['id'] == (int) old('unidadmedida_ids', $pedidoitem->unidadmedida_id ?? ''))
											<option value="{{ $value['id'] }}" selected="select">{{ $value['abreviatura'] }}</option>
										@else
											<option value="{{ $value['id'] }}">{{ $value['abreviatura'] }}</option>
										@endif
									@endforeach
								</select>
								<input type="hidden" name="unidadmedidas[]" class="form-control unidadmedida" value="" />
							</td>
                			<td>
								<input type="text" name="cajas[]" class="form-control form-control-sm caja" value="{{number_format(old('cajas.'.$loop->index, optional($pedidoitem)->caja),2,'.','')}}" />
                			</td>
                			<td>
								<input type="text" name="piezas[]" class="form-control form-control-sm pieza" value="{{number_format(old('piezas.'.$loop->index, optional($pedidoitem)->pieza),2,'.','')}}" />
                			</td>
                			<td>
								<input type="text" name="kilos[]" class="form-control form-control-sm kilo" value="{{number_format(old('kilos.'.$loop->index, optional($pedidoitem)->kilo),2,'.','')}}" />
                			</td>
							<td>
								<input type="text" name="pesadas[]" class="form-control form-control-sm pesada" value="{{number_format(old('pesadas.'.$loop->index, optional($pedidoitem)->pesada),2,'.','')}}" />
                			</td>
							<td>
								<select name="descuentoventa_ids[]" data-placeholder="Descuento" class="descuentoventa_id form-control form-control-sm" data-fouc>
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
                				<input type="text" style="text-align: right;" name="precios[]" class="form-control form-control-sm precio" readonly value="{{number_format($pedidoitem->precio,2,'.','')}}" />
                			</td>
                			<td class="text-nowrap">
								@if ($pedidoitem->estado == 'A')
									<button type="button" title="Recupera Item" class="btn-accion-tabla anulaitem tooltipsC">
                            			<i class="fa fa-window-close text-success ianulaItem"></i>
								@else
									<button type="button" title="Anula Item" class="btn-accion-tabla anulaitem tooltipsC">
                            			<i class="fa fa-window-close text-danger ianulaItem"></i>
								@endif
								</button>
								@if (can('borrar-items-pedidos', false))
									<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
										<i class="fa fa-trash text-danger"></i>
									</button>
								@endif
								@if (count($pedidoitem->pedido_articulo_estados) > 0)
									<button type="button" title="Historia de anulaci&oacute;nes" class="btn-accion-tabla historiaitem tooltipsC">
                            			<i class="fa fa-book text-danger"></i>
									</button>
									<input type="hidden" class="historiaanulacion" value="{{$pedidoitem->pedido_articulo_estados}}" >
								@endif
								@if (can('entregar-articulo-sin-cargo-pedido-venta', false))
									<button type="button" title="Artículo sin cargo" class="btn-accion-tabla botonsincargo tooltipsC">
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
		</div>
		@include('ventas.pedido.template')
        <div class="row align-items-start">
        	<div class="col-md-2 mb-2">
        		<button type="button" id="agrega_renglon" class="btn btn-sm btn-outline-primary pedido-carga-bloqueable">
					<i class="fa fa-plus"></i> Agrega rengl&oacute;n
				</button>
        	</div>
			<div class="col-md-5 mb-2">
               	<div class="form-group mb-0">
               		<label class="mb-1" for="leyenda">Leyendas</label>
               		<textarea name="leyenda" id="leyenda" class="form-control pedido-carga-bloqueable" rows="3" placeholder="Leyendas ...">{{old('leyenda', $pedido->leyenda ?? '')}}</textarea>
               	</div>
            </div>
        	<div class="col-md-5 mb-2">
				<div class="pedido-totales-grid">
					<label title="Editable: al preparar/pesar suelen usar m&aacute;s cajas que la suma de l&iacute;neas">Total cajas</label>
					<input type="number" id="totalcajaspedido" name="totalcajaspedido" class="form-control form-control-sm text-right pedido-carga-bloqueable" step="1" min="0" inputmode="numeric" value="" title="Se puede corregir si usan m&aacute;s cajas al preparar. Queda como cajas reales del pedido." />
					<label>Total piezas</label>
					<input type="text" id="totalpiezaspedido" name="totalpiezaspedido" class="form-control form-control-sm text-right" readonly value="" />
					<label>Total kilos</label>
					<input type="text" id="totalkilospedido" name="totalkilospedido" class="form-control form-control-sm text-right" readonly value="" />
					<label>Total pesados</label>
					<input type="text" id="totalkilospesados" name="totalkilospesados" class="form-control form-control-sm text-right" readonly value="" />
					<label for="descuento" class="text-right">Descuento</label>
					<input type="number" id="descuento" name="descuento" class="form-control form-control-sm text-right pedido-carga-bloqueable" step="0.01" min="0" value="{{ old('descuento', $pedido->descuento ?? '0') }}" title="Descuento del cliente. Se carga al terminar los productos." />
				</div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="tiposuspension_id" name="tiposuspension_id" value="{{$tiposuspension_id ?? ''}}" >
<input type="hidden" id="tiposuspensioncliente_query" value="{{$tiposuspensioncliente_query ?? ''}}" >

<input type="hidden" id="estadocliente" value="{{ $pedido->clientes->estado ?? '' }}">
<input type="hidden" id="estado" name="estado" value="{{ $pedido->estado ?? '' }}">
<input type="hidden" id="nombretiposuspensioncliente" value="{{ $pedido->clientes->tipossuspensioncliente->nombre ?? ''}}">
<input type="hidden" id="tiposuspensioncliente_id" value="{{ $pedido->clientes->tiposuspension_id ?? ''}}">
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
@include('includes.ventas.modalconsultazonavta')
@include('includes.ventas.modalseleccionclienteentrega')
@include('ventas.ordentrabajo.modalcrearordentrabajo')
@include('ventas.pedido.modalfacturapedido')
@include('ventas.pedido.modalpesada')
@include('includes.proceso-overlay-pedido')
