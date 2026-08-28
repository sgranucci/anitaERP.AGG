@php
	$layoutItemsPedido = $layoutItemsPedido ?? facturaUsaLayoutItemsPedido();
	$tipotransaccionSeleccionada = old('tipotransaccion_id', $data->tipotransaccion_id ?? ($tipotransacciondefault_id ?? ''));
	$unidadmedida_query = $unidadmedida_query ?? [];
	$descuentoventa_query = $descuentoventa_query ?? collect();
	$clienteIdFactura = old('cliente_id', $data->cliente_id ?? '');
	$clienteCodigoFactura = old('codigocliente', isset($data->clientes) ? ($data->clientes->codigo ?? '') : '');
	$clienteNombreFactura = old('nombrecliente', (isset($data->clientes) ? ($data->clientes->nombre ?? '') : null) ?? ($data->nombrecliente ?? ''));
	$vendedorIdFactura = old('vendedor_id', $data->vendedor_id ?? '');
	$vendedorSel = collect($vendedor_query ?? [])->firstWhere('id', (int) $vendedorIdFactura);
	$transporteIdFactura = old('transporte_id', $data->transporte_id ?? '');
	$transporteFactura = collect($transporte_query ?? [])->firstWhere('id', (int) $transporteIdFactura);
	$puedeAbrirAbmCliente = can('editar-clientes', false) || can('listar-clientes', false);
	$puedeAbrirAbmVendedor = can('editar-vendedores', false) || can('listar-vendedores', false);
@endphp
<style>
	#itemspedido-table thead th,
	#total-factura-table thead th {
		background: #85C1E9;
		color: #17202A;
	}
	#itemspedido-table td,
	#total-factura-table td {
		vertical-align: middle;
	}
</style>
<div class="form1">
<div class="card card-outline card-info mb-3">
	<div class="card-header py-2">
		<h3 class="card-title mb-0">Datos del comprobante</h3>
	</div>
	<div class="card-body pb-2">
<div class="row">
	<div class="col-sm-6" id="datosfactura" data-puntoventa="{{$puntoventa_query}}" data-tipotransaccion="{{$tipotransaccion_query}}" data-incoterm="{{$incoterm_query ?? ''}}" data-formapago="{{$formapago_query ?? ''}}" data-layout-items-pedido="{{ $layoutItemsPedido ? '1' : '0' }}">
		<input type="hidden" id="codigofactura" class="form-control" value="{{old('codigofactura', $data->codigo ?? '')}}" />
		<div class="form-group row" id="tipotransaccion">
			<label for="tipotransaccion_id" class="col-lg-3 control-label text-right pr-2 requerido">Tipo de transacci&oacute;n</label>
			<select name="tipotransaccion_id" id="tipotransaccion_id" data-placeholder="Tipo de transacci&oacute;n" class="col-lg-8 form-control" data-fouc required>
				<option value="">-- Seleccionar transacción  --</option>
				@php $flPrimero = true; @endphp
				@foreach($tipotransaccion_query as $key => $value)
					@if (isset($flGeneraNotaDeCredito) && $flPrimero)
						<option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}" selected="select">{{ $value->nombre }}</option>
						@php $flPrimero = false; @endphp
					@else
						@if( (int) $value->id == (int) $tipotransaccionSeleccionada)
							<option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}">{{ $value->nombre }}</option>    
						@endif
					@endif
				@endforeach	
			</select>
			<div class="col-lg-8 offset-lg-3">
				<small id="aviso-tipo-fce" class="form-text text-info d-none"></small>
			</div>
		</div>
		<div class="form-group row" id="puntoventa">
			<label for="puntoventa_id" class="col-lg-3 control-label text-right pr-2 requerido">Punto de venta</label>
			<input type="hidden" id="puntoventadefault_id" class="form-control" value="{{old('puntoventadefault_id', $puntoventadefault_id ?? ($data->puntoventa_id ?? ''))}}" />
			<select name="puntoventa_id" id="puntoventa_id" data-placeholder="Punto de venta" class="col-lg-5 form-control required" data-fouc>
			</select>
			<label for="actividad_arca_id" class="col-lg-2 control-label text-right pr-2 requerido">Actividad</label>
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
		<div class="form-group row tm-cliente-campo">
   			<label for="codigocliente" class="col-lg-3 control-label text-right pr-2 requerido">Cliente</label>
			<div class="col-lg-8">
				<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
					<input type="hidden" class="cliente_id" id="cliente_id" name="cliente_id" value="{{ $clienteIdFactura }}">
					<button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente tooltipsC flex-shrink-0">
						<i class="fa fa-search text-primary"></i>
					</button>
					@if ($puedeAbrirAbmCliente)
						<a href="{{ ((int) $clienteIdFactura > 0) ? route('editar_cliente', ['id' => (int) $clienteIdFactura, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
							id="link-editar-cliente-factura" target="_blank" rel="noopener"
							class="btn-accion-tabla tooltipsC flex-shrink-0 {{ ((int) $clienteIdFactura > 0) ? '' : 'd-none' }}"
							title="Consultar cliente en ABM">
							<i class="fa fa-edit"></i>
						</a>
					@endif
					@if ($datos['funcion'] == 'crear')
						<a href="{{route('crear_cliente', ['tipoalta' => 'P'])}}" id="clienteprovisorio" class="btn-accion-tabla tooltipsC flex-shrink-0" title="Crear cliente provisorio">
							<i class="fa fa-user"></i>
						</a>
					@endif
					<input type="text" class="form-control codigocliente flex-shrink-0" id="codigocliente" name="codigocliente"
						value="{{ $clienteCodigoFactura }}" placeholder="C&oacute;d." autocomplete="off"
						title="C&oacute;digo; Enter valida; F1 consulta" style="width: 5.5rem;">
					<input type="text" class="form-control nombrecliente text-truncate" id="nombrecliente" name="nombrecliente"
						value="{{ $clienteNombreFactura }}" placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
				</div>
				<label id="nombretiposuspension" class="text-danger small mb-0"></label>
			</div>
		</div>
		<div id="aviso-padron-operacion-factura" class="alert d-none col-12 mb-2" role="alert"></div>
		@include('ventas.cliente.partials.arca_apoc_operacion_support')
		<div class="form-group row tm-vendedor-campo">
   			<label for="codigovendedor" class="col-lg-3 control-label text-right pr-2 requerido">Vendedor</label>
			<div class="col-lg-8">
				<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
					<input type="hidden" class="vendedor_id" name="vendedor_id" id="vendedor_id" value="{{ $vendedorIdFactura }}" required>
					<button type="button" title="Consulta vendedores (F1)" class="btn-accion-tabla consultavendedor tooltipsC flex-shrink-0 factura-carga-bloqueable">
						<i class="fa fa-search text-primary"></i>
					</button>
					@if ($puedeAbrirAbmVendedor)
						<a href="{{ ((int) $vendedorIdFactura > 0) ? route('editar_vendedor', ['id' => (int) $vendedorIdFactura, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
							target="_blank" rel="noopener"
							class="btn-accion-tabla btn-link-editar-vendedor tooltipsC flex-shrink-0 {{ ((int) $vendedorIdFactura > 0) ? '' : 'd-none' }}"
							title="Consultar vendedor en ABM">
							<i class="fa fa-edit"></i>
						</a>
					@endif
					<input type="text" class="form-control codigovendedor factura-carga-bloqueable flex-shrink-0" id="codigovendedor" name="codigovendedor"
						value="{{ old('codigovendedor', $vendedorSel?->codigo ?? '') }}"
						placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="width: 5.5rem;">
					<input type="text" class="form-control nombrevendedor text-truncate" id="nombrevendedor" name="nombrevendedor"
						value="{{ old('nombrevendedor', $vendedorSel?->nombre ?? '') }}"
						placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
				</div>
			</div>
		</div>
		<div class="form-group row tm-transporte-campo">
   			<label for="codigotransporte" class="col-lg-3 control-label text-right pr-2">{{ config('app.empresa') == 'EL BIERZO' ? 'Reparto' : 'Transporte' }}</label>
			<div class="col-lg-8">
				<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
					<input type="hidden" class="transporte_id" id="transporte_id" name="transporte_id" value="{{ $transporteIdFactura }}">
					<button type="button" title="Consulta {{ config('app.empresa') == 'EL BIERZO' ? 'repartos' : 'transportes' }} (F1)" class="btn-accion-tabla consultatransporte tooltipsC flex-shrink-0 factura-carga-bloqueable">
						<i class="fa fa-search text-primary"></i>
					</button>
					<input type="text" class="form-control codigotransporte factura-carga-bloqueable flex-shrink-0" id="codigotransporte" name="codigotransporte"
						value="{{ old('codigotransporte', $transporteFactura?->codigo ?? '') }}"
						placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="width: 5.5rem;">
					<input type="text" class="form-control nombretransporte text-truncate" id="nombretransporte" name="nombretransporte"
						value="{{ old('nombretransporte', $transporteFactura?->nombre ?? '') }}"
						placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
				</div>
				<div id="aviso-deposito-facturacion-factura" class="aviso-deposito-facturacion small text-muted d-none mt-1 mb-0" role="status" style="font-size: 11px; line-height: 1.3;"></div>
			</div>
		</div>
		<div class="form-group row" id="divlugar">
    		<label for="lugarentrega" class="col-lg-3 control-label text-right pr-2">Lugar de entrega</label>
    		<div class="col-lg-8">
    			<input type="text" name="lugarentrega" id="lugarentrega" class="form-control" value="{{old('lugarentrega', $data->lugarentrega ?? '')}}">
    		</div>
		</div>
		<div class="form-group row" id="divcodigoentrega">
        	<label for="cliente_entrega_id" class="col-lg-3 control-label text-right pr-2">Entrega en</label>
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
			<label for="fechafactura" class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
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
				<label for="hora_creacion_factura" class="col-lg-2 control-label text-right pr-2">Hora creaci&oacute;n</label>
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
			<label for="descuentolinea" class="col-lg-4 control-label text-right pr-2">Descuento de l&iacute;nea</label>
			<div class="col-lg-4">
				<input type="number" id="descuentolinea" name="descuentolinea" class="form-control" value="">
			</div>
		</div>
		<div class="form-group row">
			<label for="descuentopie" class="col-lg-4 control-label text-right pr-2">Descuento pie factura</label>
			<div class="col-lg-4">
				<input type="number" id="descuentopie" name="descuentopie" class="form-control" value="{{$data->descuento ?? ''}}">
				<input type="hidden" id="descuentoimportepie" name="descuentoimportepie" value="">
			</div>
		</div>
		<div class="form-group row" id="puntoventaremito">
			<label for="puntoventaremito_id" class="col-lg-4 control-label text-right pr-2 requerido">Pto. venta del remito</label>
			<input type="hidden" id="puntoventaremitoori_id" class="form-control" value="{{old('puntoventaremitoori_id', $data->puntoventaremito_id ?? '')}}" />
			<select name="puntoventaremito_id" id="puntoventaremito_id" data-placeholder="Punto de venta del remito" class="col-lg-5 form-control required" data-fouc>
			</select>
		</div>
		<input type="hidden" id="cantidadbulto" name="cantidadbulto" value="0"></input>
		<div class="form-group row">
			<label for="moneda_id" class="col-lg-4 control-label text-right pr-2 requerido">Moneda</label>
			<select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="col-lg-6 form-control required" data-fouc>
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
		@php
			$depositoIdDefault = (int) config('facturacion.DEPOSITO_VENTA_ID', 1);
			$depositoIdDesdeEmision = null;
			if (! empty($data->venta_emisiones[0] ?? null)) {
				$depositoIdDesdeEmision = $data->venta_emisiones[0]->deposito_id ?? null;
			}
			$depositoIdSeleccionado = old('deposito_id', $data->deposito_id ?? $depositoIdDesdeEmision ?? $depositoIdDefault);
			$depositoSeleccionado = collect($deposito_query ?? [])->firstWhere('id', (int) $depositoIdSeleccionado);
			$pvIdEmpresa = (int) old('puntoventa_id', $data->puntoventa_id ?? ($puntoventadefault_id ?? 0));
			$pvEmpresa = ($pvIdEmpresa > 0 && isset($puntoventa_query))
				? collect($puntoventa_query)->firstWhere('id', $pvIdEmpresa)
				: null;
			$empresaIdFactura = $pvEmpresa?->empresa_id
				?? (isset($data->puntoventas) ? $data->puntoventas->empresa_id : '');
		@endphp
		<input type="hidden" id="empresa_id" value="{{ $empresaIdFactura }}">
		@include('stock.partials.campo_consulta_deposito', [
			'prefix' => 'factura',
			'layout' => 'form_row',
			'inputName' => 'deposito_id',
			'inputId' => 'deposito_id',
			'depositoId' => $depositoIdSeleccionado,
			'codigo' => old('deposito_codigo', $depositoSeleccionado?->codigo ?? ''),
			'descripcion' => old('deposito_descripcion', $depositoSeleccionado?->nombre ?? ''),
			'col_label' => 'col-lg-4 control-label text-right pr-2',
			'col_input' => 'col-lg-8',
			'codigoExtraClass' => 'factura-carga-bloqueable',
		])
	</div>
</div>
	</div>
</div>

<div class="card card-outline card-info" id="factura-carga-contenido">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">&Iacute;tems</h3>
    </div>
    <div class="card-body">
    	<table class="table table-sm table-bordered table-hover" id="itemspedido-table">
    		<thead style="background:#85C1E9;color:#17202A;">
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
						@php
							$articuloItem = $item->articulos;
							$unidadMedidaIdItem = old('unidadmedida_ids.'.$loop->index, $articuloItem?->unidadmedida_id ?? '');
							$unidadMedidaAbrevItem = $articuloItem?->unidadesdemedidas?->abreviatura ?? '';
							$kiloItem = old('kilos.'.$loop->index, old('cantidades.'.$loop->index, $item->cantidad ?? 0));
							$cajaItem = old('cajas.'.$loop->index, $item->caja ?? 0);
							$piezaItem = old('piezas.'.$loop->index, $item->pieza ?? 0);
							if (old('cajas.'.$loop->index) === null && old('piezas.'.$loop->index) === null
								&& (float) $cajaItem == 0.0 && (float) $piezaItem == 0.0 && (float) $kiloItem != 0.0) {
								$pesoArt = (float) ($articuloItem?->peso ?? 0);
								$uxenvArt = (float) ($articuloItem?->unidadesxenvase ?? 0);
								if ($pesoArt > 0) {
									$piezaItem = round(((float) $kiloItem) / $pesoArt, 2);
									if ($uxenvArt > 0) {
										$cajaItem = round($piezaItem / $uxenvArt, 2);
									}
								}
							}
							$descuentoVentaIdItem = old('descuentoventa_ids.'.$loop->index, '');
							$valorOldIndice = static function (string $campo, int $indice, $default = '') {
								$valor = old($campo.'.'.$indice);
								if ($valor === null) {
									$valor = old($campo, $default);
								}
								if (is_array($valor)) {
									$valor = $valor[$indice] ?? $default;
								}
								if (is_array($valor) || is_object($valor)) {
									$valor = $default;
								}

								return $valor ?? $default;
							};
							$numeroOldIndice = static function (string $campo, int $indice, $default = 0) use ($valorOldIndice): float {
								$valor = $valorOldIndice($campo, $indice, $default);
								if ($valor === null || $valor === '') {
									$valor = $default;
								}
								if (is_string($valor)) {
									$valor = str_replace([' ', ','], '', $valor);
								}

								return (float) $valor;
							};
							$idxItem = (int) $loop->index;
						@endphp
            			<tr class="{{ $layoutItemsPedido ? 'item-pedido' : 'item-factura' }}">
               				<td>
               					<input type="text" name="items[]" class="form-control item" value="{{ $loop->index+1 }}" readonly>
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{ $valorOldIndice('listasprecios_id', $idxItem, $item->listaprecio_id ?? '') }}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{ $valorOldIndice('monedas_id', $idxItem, $item->moneda_id ?? '') }}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{ $valorOldIndice('incluyeimpuestos', $idxItem, $item->incluyeimpuesto ?? '') }}" />
                				<input type="hidden" name="impuesto_ids[]" class="form-control impuesto_id" readonly value="{{ $valorOldIndice('impuesto_ids', $idxItem, $item->impuesto_id ?? '') }}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{$item->id??''}}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{ $item->lotes?->id ?? '' }}" />
								@if ($layoutItemsPedido)
									<input type="hidden" name="cantidades[]" class="form-control cantidad" value="{{ number_format((float) $kiloItem, 2, '.', '') }}" />
									<input type="hidden" name="descuentos[]" class="form-control descuento" value="0" />
								@endif
                			</td>
                            <td>
                                <div class="form-group row" id="articulo">
                                    <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="{{ $loop->index+1 }}" />
                                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{$item->articulo_id ?? ''}}" >
                                    <input type="hidden" class="articulo_id_previa" name="articulo_id_previa[]" value="{{$item->articulo_id ?? ''}}" >
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
							<td>
								<select name="unidadmedida_ids[]" data-placeholder="Unidad de Medida" class="unidadmedida_id form-control" data-fouc>
									@foreach($unidadmedida_query as $key => $value)
										@if ((int) $value['id'] == (int) $unidadMedidaIdItem)
											<option value="{{ $value['id'] }}" selected="select">{{ $value['abreviatura'] }}</option>
										@else
											<option value="{{ $value['id'] }}">{{ $value['abreviatura'] }}</option>
										@endif
									@endforeach
								</select>
								<input type="hidden" name="unidadmedidas[]" class="form-control unidadmedida" value="{{ $unidadMedidaAbrevItem }}" />
							</td>
							<td>
								<input type="text" name="cajas[]" class="form-control caja" value="{{ number_format((float) $cajaItem, 2, '.', '') }}" />
							</td>
							<td>
								<input type="text" name="piezas[]" class="form-control pieza" value="{{ number_format((float) $piezaItem, 2, '.', '') }}" />
							</td>
							<td>
								<input type="text" name="kilos[]" class="form-control kilo" value="{{ number_format((float) $kiloItem, 2, '.', '') }}" />
							</td>
							<td>
								<select name="descuentoventa_ids[]" data-placeholder="Descuento" class="descuentoventa_id form-control" data-fouc>
									<option value="">-Descuento-</option>
									@foreach($descuentoventa_query as $key => $value)
										@if ((string) $value->id === (string) $descuentoVentaIdItem)
											<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
										@else
											<option value="{{ $value->id }}">{{ $value->nombre }}</option>
										@endif
									@endforeach
								</select>
								<input type="hidden" name="descuentoventaanterior_ids[]" class="form-control descuentoventaanterior_id" value="{{ $descuentoVentaIdItem }}" />
							</td>
							@else
							<td>
								<input type="text" name="cantidades[]" class="form-control cantidad" value="{{ number_format($numeroOldIndice('cantidades', $idxItem, optional($item)->cantidad ?? 0), 2) }}" />
                			</td>		
							<td>
								<input type="text" name="descuentos[]" class="form-control descuento" value="{{ number_format($numeroOldIndice('descuentos', $idxItem, optional($item)->descuento ?? 0), 2) }}" />
                			</td>
							@endif								
                			<td>
                				<input type="text" style="text-align: right;" name="precios[]" class="form-control precio" readonly value="{{ \App\Support\Ventas\VentaNotaCreditoPrecioLiteralSupport::formatLiteral($valorOldIndice('precios', $idxItem, optional($item)->precio ?? 0)) }}" />
                			</td>							
                			<td>
								<button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
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
	    <div class="mb-3">
        	<button type="button" id="agrega_renglon" class="btn btn-outline-primary btn-sm factura-carga-bloqueable">
				<i class="fa fa-plus"></i> Agregar rengl&oacute;n
			</button>
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
				<table class="table table-sm table-bordered" id="total-factura-table">
					<thead style="background:#85C1E9;color:#17202A;">
						<tr>
							<th style="width: 25%;">Concepto</th>
							<th style="width: 10%;">Tasa</th>
							<th style="width: 15%;">Importe</th>
						</tr>
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
					<label for="incoterm_id" class="col-lg-4 control-label text-right pr-2 requerido">Condiciones de venta (incoterms)</label>
					<select name="incoterm_id" id="incoterm_id" data-placeholder="Incoterms" class="col-lg-6 form-control required" data-fouc>
					</select>
				</div>
				<div class="form-group row" id="div_formapago">
					<label for="formapago_id" class="col-lg-4 control-label text-right pr-2 requerido">Forma de pago</label>
					<select name="formapago_id" id="formapago_id" data-placeholder="Forma de pago" class="col-lg-6 form-control required" data-fouc>
					</select>
				</div>
				<div class="form-group row" id="div_mercaderia">
					<label for="mercaderia" class="col-lg-4 control-label text-right pr-2">Mercader&iacute;a</label>
					<input type="text" class="col-lg-6 form-control" id="mercaderia" name="marcaderia" value=""></input>
				</div>
			</div>
        </div>
    </div>
</div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="tipotransacciondefault_id" name="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />
<input type="hidden" id="puntoventaremitodefault_id" class="form-control" value="{{ $puntoventaremitodefault_id ?? '' }}">
<input type="hidden" id="modofacturacion" name="modofacturacion" class="form-control" value="{{$data->puntoventas->modofacturacion ?? ''}}" />
<input type="hidden" id="ordenventa_id" name="ordenventa_id" class="form-control" value="{{$data->ordenventa_id ?? ''}}" />
<input type="hidden" id="estadocliente" value="{{ $data->clientes->estado ?? '' }}">

@include('ventas.factura.modal')
@include('ventas.factura.templatetotalfactura')
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultavendedor')
@include('includes.ventas.modalconsultatransporte')
