@php
    use App\Models\Stock\Depmae;
    use App\Support\Stock\MovimientoStockFormLineasSupport;
    use App\Support\Stock\TransferenciaBienUsoSupport;

    $lineasFormulario = MovimientoStockFormLineasSupport::lineasParaFormulario($movimientostock);
    $tipoTransaccionSeleccionada = (int) old(
        'tipotransaccion_stock_id',
        $movimientostock->tipotransaccion_stock_id ?? ($tipotransacciondefault_id ?? 0)
    );
    $ccDefault = old(
        'centrocosto_destino_id',
        $movimientostock->centrocosto_destino_id ?? auth()->user()->centrocosto_id ?? ''
    );
    $tipoActualId = $tipoTransaccionSeleccionada;
    $tipoActual = $tipotransaccion_query->firstWhere('id', $tipoActualId);
    $tipoActualManejaCont = (bool) ($tipoActual?->maneja_contabilidad ?? false);
    $esTransferenciaActual = ($tipoActual?->operacion ?? '') === 'T';
    $movimientoStockModoFerli = $movimientoStockModoFerli ?? \App\Support\Stock\MovimientoStockFerliSupport::esCalzadosFerli();
    $bienesUsoActivos = $bienesUsoActivos ?? collect();
    $transferenciaVinculada = $transferenciaVinculada ?? null;

    $depositoIdMov = (int) old(
        'deposito_id',
        $movimientostock->articulos_movimiento[0]->deposito_id ?? ($deposito_query->count() === 1 ? $deposito_query->first()->id : 0)
    );
    $depositoModelMov = $depositoIdMov > 0 ? Depmae::find($depositoIdMov) : null;

    $depositoSalidaId = (int) old(
        'deposito_salida_id',
        $transferenciaVinculada?->deposito_origen_id ?? $depositoIdMov
    );
    $depositoEntradaId = (int) old(
        'deposito_entrada_id',
        $transferenciaVinculada?->deposito_destino_id ?? 0
    );
    $depositoSalidaModel = $depositoSalidaId > 0 ? Depmae::find($depositoSalidaId) : null;
    $depositoEntradaModel = $depositoEntradaId > 0 ? Depmae::find($depositoEntradaId) : null;
    $bienUsoOrigenId = (int) old('bien_uso_origen_id', $transferenciaVinculada?->bien_uso_origen_id ?? 0);
    $bienUsoDestinoId = (int) old('bien_uso_destino_id', $transferenciaVinculada?->bien_uso_destino_id ?? 0);
    $mostrarPanelTransferencia = $esTransferenciaActual && ! $transferenciaVinculada;
    $mostrarDepositoSimple = ! $mostrarPanelTransferencia;
@endphp

@if($mostrarSolapaAsiento ?? false)
<div class="text-center py-2 border-bottom rounded-top bg-white mb-3">
    <button type="button" id="ms-boton-principal" class="btn btn-primary btn-sm mx-1 ms-tab-solapa font-weight-bold">Movimiento</button>
    <button type="button" id="ms-boton-asiento-contable" class="btn btn-info btn-sm mx-1 ms-tab-solapa" style="display:none;">
        <span class="fa fa-calculator"></span> Asiento contable
        @if(! empty($asientoPreview['error']))
        <span class="badge badge-warning ml-1 ms-badge-asiento-error" title="Revise el cuadre antes de guardar">!</span>
        @elseif(! empty($movimientostock->asiento_id))
        <span class="badge badge-light ml-1">OK</span>
        @endif
    </button>
</div>
@endif

<div id="ms-solapa-principal" class="ms-solapa">
<div class="card card-outline card-secondary mb-3 ms-form-cabecera">
    <div class="card-body py-3">
        <input type="hidden" id="codigomovimientostock" value="{{ old('codigomovimientostock', $movimientostock->codigo ?? '') }}" />
        <div class="row" id="datosmovimientostock">
            <div class="col-md-6">
                @include('stock.partials.campo_consulta_tipotransaccion_stock', [
                    'prefix' => 'movimientostock',
                    'tipoId' => $tipoTransaccionSeleccionada,
                    'abreviatura' => old('tipotransaccion_abreviatura', $tipoActual->abreviatura ?? ''),
                    'nombre' => old('tipotransaccion_nombre', $tipoActual->nombre ?? ''),
                    'operacion' => $tipoActual->operacion ?? '',
                    'maneja_contabilidad' => (bool) ($tipoActual?->maneja_contabilidad ?? false),
                    'origen_bien_uso' => (bool) ($tipoActual?->origen_bien_uso ?? false),
                    'destino_bien_uso' => (bool) ($tipoActual?->destino_bien_uso ?? false),
                    'col_label' => 'col-lg-4 col-form-label',
                    'col_input' => 'col-lg-8',
                ])
                <div class="form-group row mb-2">
                    <label for="fecha" class="col-lg-4 col-form-label requerido">Fecha</label>
                    <div class="col-lg-8">
                        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ substr(old('fecha', $movimientostock->fecha ?? date('Y-m-d')), 0, 10) }}" required>
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label for="lote" class="col-lg-4 col-form-label requerido">Lote de stock</label>
                    <div class="col-lg-8">
                        <input type="text" name="lote" id="lote" class="form-control" value="{{ old('lote', $movimientostock->articulos_movimiento[0]->lote ?? 'LOTE DE ALTA') }}" required>
                    </div>
                </div>
                @include('includes.form-empresa-asignada', [
                    'empresa_query' => $empresa_query,
                    'empresa_id' => $empresa_id ?? null,
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                ])
            </div>
            <div class="col-md-6">
                @if ($transferenciaVinculada)
                    <div class="alert alert-info py-2" id="ms_transferencia_vinculada">
                        <strong>Transferencia {{ $transferenciaVinculada->codigo }}</strong>
                        @php
                            $tvOrigen = $transferenciaVinculada->depositoOrigen?->nombre
                                ?? TransferenciaBienUsoSupport::etiquetaBien($transferenciaVinculada->bienUsoOrigen);
                            $tvDestino = $transferenciaVinculada->depositoDestino?->nombre
                                ?? TransferenciaBienUsoSupport::etiquetaBien($transferenciaVinculada->bienUsoDestino);
                        @endphp
                        <div class="small mt-1">
                            Origen: {{ $tvOrigen }} &rarr; Destino: {{ $tvDestino }}
                        </div>
                        <a href="{{ route('consultar_transferencia_movimientostock', ['id' => $transferenciaVinculada->id]) }}"
                           class="btn btn-xs btn-outline-primary mt-1" target="_blank" rel="noopener">
                            Ver transferencia completa
                        </a>
                    </div>
                @endif

                <div id="ms_deposito_simple" @if(! $mostrarDepositoSimple) style="display:none;" @endif>
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'movimientostock',
                        'layout' => 'form_row',
                        'label' => 'Depósito',
                        'inputName' => 'deposito_id',
                        'inputId' => 'deposito_id',
                        'depositoId' => $depositoIdMov,
                        'codigo' => old('deposito_codigo', $depositoModelMov->codigo ?? ''),
                        'descripcion' => old('deposito_descripcion', $depositoModelMov->nombre ?? ''),
                        'tipodeposito' => $depositoModelMov->tipodeposito ?? '',
                        'col_label' => 'col-lg-4 col-form-label',
                        'col_input' => 'col-lg-8',
                    ])
                </div>

                <div id="ms_panel_transferencia" @if(! $mostrarPanelTransferencia) style="display:none;" @endif>
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'salida',
                        'layout' => 'form_row',
                        'label' => 'Depósito origen',
                        'inputName' => 'deposito_salida_id',
                        'inputId' => 'deposito_salida_id',
                        'depositoId' => $depositoSalidaId,
                        'codigo' => old('deposito_salida_codigo', $depositoSalidaModel->codigo ?? ''),
                        'descripcion' => old('deposito_salida_descripcion', $depositoSalidaModel->nombre ?? ''),
                        'tipodeposito' => $depositoSalidaModel->tipodeposito ?? '',
                        'col_label' => 'col-lg-4 col-form-label',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="form-group row mb-2" id="ms_panel_bien_origen" style="display:none;">
                        <label for="bien_uso_origen_id" class="col-lg-4 col-form-label requerido">Bien de uso origen</label>
                        <div class="col-lg-8">
                            <select name="bien_uso_origen_id" id="bien_uso_origen_id" class="form-control">
                                <option value="">— Seleccionar bien —</option>
                                @foreach ($bienesUsoActivos as $bien)
                                    <option value="{{ $bien->id }}" @if($bienUsoOrigenId === (int) $bien->id) selected @endif>
                                        @if ($bien->codigo_inventario)#{{ $bien->codigo_inventario }} — @endif{{ $bien->hostname }}@if($bien->modelo) ({{ $bien->modelo }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'entrada',
                        'layout' => 'form_row',
                        'label' => 'Depósito destino',
                        'inputName' => 'deposito_entrada_id',
                        'inputId' => 'deposito_entrada_id',
                        'depositoId' => $depositoEntradaId,
                        'codigo' => old('deposito_entrada_codigo', $depositoEntradaModel->codigo ?? ''),
                        'descripcion' => old('deposito_entrada_descripcion', $depositoEntradaModel->nombre ?? ''),
                        'tipodeposito' => $depositoEntradaModel->tipodeposito ?? '',
                        'col_label' => 'col-lg-4 col-form-label',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="form-group row mb-2" id="ms_panel_bien_destino" style="display:none;">
                        <label for="bien_uso_destino_id" class="col-lg-4 col-form-label requerido">Bien de uso destino</label>
                        <div class="col-lg-8">
                            <select name="bien_uso_destino_id" id="bien_uso_destino_id" class="form-control">
                                <option value="">— Seleccionar bien —</option>
                                @foreach ($bienesUsoActivos as $bien)
                                    <option value="{{ $bien->id }}" @if($bienUsoDestinoId === (int) $bien->id) selected @endif>
                                        @if ($bien->codigo_inventario)#{{ $bien->codigo_inventario }} — @endif{{ $bien->hostname }}@if($bien->modelo) ({{ $bien->modelo }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group row mb-2" id="ms_panel_centrocosto" style="{{ $tipoActualManejaCont ? '' : 'display:none;' }}">
                    <label for="centrocosto_destino_id" class="col-lg-4 col-form-label requerido">Centro costo destino</label>
                    <div class="col-lg-8">
                        <select name="centrocosto_destino_id" id="centrocosto_destino_id" data-placeholder="Centro de costo destino" class="form-control" data-fouc>
                            <option value="">-- Seleccionar centro de costo --</option>
                            @foreach($centrocosto_query ?? [] as $cc)
                                <option value="{{ $cc->id }}" @if((int) $ccDefault === (int) $cc->id) selected @endif>
                                    {{ $cc->codigo }} — {{ $cc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row mb-2" id="divlote">
                    <label for="loteimportacion_id" class="col-lg-4 col-form-label">Lote importaci&oacute;n</label>
                    <div class="col-lg-8">
                        <select name="loteimportacion_id" id="loteimportacion_id" data-placeholder="Lote de importaci&oacute;n" class="form-control" data-fouc>
                            <option value="">-- Seleccionar lote --</option>
                            @foreach($lote_query as $key => $value)
                                <option value="{{ $value->id }}"
                                    @if((int) $value->id === (int) old('loteimportacion_id', $movimientostock->articulos_movimiento[0]->loteimportacion_id ?? '')) selected @endif>
                                    {{ $value->id }}-{{ $value->numerodespacho }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if($movimientoStockModoFerli)
                <div class="form-group row mb-2" id="marca" data-articulo="{{ $articulo_query }}" data-articuloall="{{ $articuloall_query }}">
                    <label for="mventa_id" class="col-lg-4 col-form-label requerido">Marca</label>
                    <div class="col-lg-8">
                        <select name="mventa_id" id="mventa_id" data-placeholder="Marca de venta" class="form-control required" data-fouc>
                            <option value="">-- Seleccionar marca --</option>
                            @foreach($mventa_query as $key => $value)
                                <option value="{{ $value->id }}"
                                    @if((int) $value->id === (int) old('mventa_id', $movimientostock->mventa_id ?? '')) selected @endif>
                                    {{ $value->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

        <div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">&Iacute;tems</h5>
            <button type="button" id="agrega_renglon" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agregar art&iacute;culo
            </button>
        </div>
        <div class="table-responsive">
    	<table class="table table-sm table-bordered table-hover table-ms-items-compact" id="tabla-items-movimientostock">
    		<thead class="thead-light">
    			<tr>
    				<th class="col-num">#</th>
    				<th class="col-art">Art.</th>
    				<th class="col-desc">Descripci&oacute;n</th>
    				<th class="col-saldo-orig text-right" title="Saldo en dep&oacute;sito origen">Saldo orig.</th>
                    @if($movimientoStockModoFerli)
    				<th class="col-comb">Combinaci&oacute;n</th>
    				<th class="col-mod">M&oacute;dulo</th>
    				<th class="col-qty text-right">Cantidad</th>
    				<th class="col-precio text-right">Precio</th>
					<th class="col-flag" title="Todos los art&iacute;culos">A</th>
    				<th class="col-flag" title="Todas las combinaciones">C</th>
                    @else
                    <th class="col-umd text-center" title="Unidad de medida del art&iacute;culo de la l&iacute;nea">UM</th>
                    <th class="col-qty text-right">Cantidad</th>
                    <th class="col-umd text-center" title="Unidad de medida alternativa del art&iacute;culo (p. ej. envase)">UM alt.</th>
                    <th class="col-qty-alt text-right" title="Cantidad en unidad alternativa">Cant. alt.</th>
                    <th class="col-insumo-dest ms-col-conversion-formula" title="Art&iacute;culo de stock destino (entrada en dep. F&oacute;rmulas) o art&iacute;culo de compra equivalente (salida)">SKU dest.</th>
                    <th class="col-qty-dest text-right ms-col-conversion-formula" title="Cantidad convertida a la unidad destino">Cant. dest.</th>
                    <th class="col-umd-dest text-center ms-col-conversion-formula" title="Unidad de medida del art&iacute;culo destino">UM dest.</th>
                    <th class="col-precio text-right">Precio</th>
                    @endif
    				<th class="col-acc"></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@foreach ($lineasFormulario as $pedidoitem)
            			<tr class="item-pedido">
                			<td class="align-middle">
								<input type="text" name="items[]" class="form-control form-control-sm item text-center" value="{{ $loop->index+1 }}" readonly style="@if ($pedidoitem->estado ?? '' == 'A') background-color:red;font-weight:900; @endif">
                				<input type="hidden" name="medidas[]" class="form-control medidas" readonly value="{{ MovimientoStockFormLineasSupport::medidasHidden($loop->index, $pedidoitem) }}" />
                				<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="{{ MovimientoStockFormLineasSupport::valorLinea($loop->index, 'listasprecios_id', $pedidoitem->listaprecio_id ?? '') }}" />
                				<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="{{ MovimientoStockFormLineasSupport::valorLinea($loop->index, 'monedas_id', $pedidoitem->moneda_id ?? '') }}" />
                				<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="{{ MovimientoStockFormLineasSupport::valorLinea($loop->index, 'incluyeimpuestos', $pedidoitem->incluyeimpuesto ?? '') }}" />
                				<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="{{ MovimientoStockFormLineasSupport::valorLinea($loop->index, 'descuentos', $pedidoitem->descuento ?? '') }}" />
                				<input type="hidden" name="ids[]" class="form-control ids" value="{{ MovimientoStockFormLineasSupport::valorLinea($loop->index, 'ids', $pedidoitem->id ?? '') }}" />
								<input type="hidden" name="loteids[]" class="form-control loteids" value="{{ MovimientoStockFormLineasSupport::valorLinea($loop->index, 'loteids', $pedidoitem->lote ?? 0) }}" />
                				<input type="hidden" name="articulos_id[]" class="articulo_id" value="{{ old('articulos_id.' . $loop->index, $pedidoitem->articulo_id ?? '') }}">
                				<input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="{{ $pedidoitem->articulo_id ?? '' }}">
                                @unless($movimientoStockModoFerli)
                                <input type="hidden" name="combinaciones_id[]" value="">
                                <input type="hidden" name="modulos_id[]" value="">
                                @endunless
                			</td>
                			<td class="align-middle">
                				@include('stock.movimientostock.partials.fila_articulo_celda', [
                				    'articuloId' => (int) ($pedidoitem->articulo_id ?? 0),
                				    'sku' => old('sku.' . $loop->index, $pedidoitem->sku ?? optional($pedidoitem->articulos)->sku ?? ''),
                				    'descripcion' => old('descripcion.' . $loop->index, optional($pedidoitem->articulos)->descripcion ?? ''),
                				])
                			</td>
                			<td class="align-middle col-desc-celda">
                				<input type="text" class="descripcionarticulo form-control form-control-sm" value="{{ old('descripcion.' . $loop->index, optional($pedidoitem->articulos)->descripcion ?? '') }}" readonly title="{{ old('descripcion.' . $loop->index, optional($pedidoitem->articulos)->descripcion ?? '') }}">
                			</td>
                            @include('stock.movimientostock.partials.fila_saldo_origen')
                            @if($movimientoStockModoFerli)
                			@include('stock.movimientostock.partials.fila_item_ferli', [
                			    'combinacionIdPrev' => MovimientoStockFormLineasSupport::valorLinea($loop->index, 'combinaciones_id', $pedidoitem->combinacion_id ?? ''),
                			    'descCombinacion' => MovimientoStockFormLineasSupport::valorLinea($loop->index, 'desc_combinacion', optional($pedidoitem->combinaciones)->nombre ?? ''),
                			    'moduloIdPrev' => MovimientoStockFormLineasSupport::valorLinea($loop->index, 'modulos_id', $pedidoitem->modulo_id ?? ''),
                			    'descModulo' => MovimientoStockFormLineasSupport::valorLinea($loop->index, 'desc_modulo', $pedidoitem->desc_modulo ?? ''),
                			    'cantidad' => number_format(abs($pedidoitem->cantidad), 0, '.', ''),
                			    'precio' => number_format((float) old('precios.'.$loop->index, optional($pedidoitem)->precio ?? 0), 2),
                			])
                            @else
                            @php
                                $art = $pedidoitem->articulos;
                                $uxenv = (float) ($art->unidadesxenvase ?? 0);
                                $cantStock = abs((float) $pedidoitem->cantidad);
                                $cantAlt = (float) ($pedidoitem->pieza ?? 0);
                                if ($cantAlt == 0 && $uxenv > 0 && $cantStock > 0) {
                                    $cantAlt = $cantStock * $uxenv;
                                }
                            @endphp
                            @include('stock.movimientostock.partials.fila_item_estandar', [
                                'abrevUmd' => optional($art->unidadesdemedidas)->abreviatura ?? '',
                                'abrevUmdAlter' => optional($art->unidadesdemedidasalternativas)->abreviatura ?? '',
                                'unidadesxenvase' => $uxenv > 0 ? $uxenv : '',
                                'cantidad' => $cantStock > 0 ? rtrim(rtrim(number_format($cantStock, 4, '.', ''), '0'), '.') : '',
                                'cantUnidad' => $cantAlt > 0 ? rtrim(rtrim(number_format($cantAlt, 4, '.', ''), '0'), '.') : '',
                                'precio' => number_format((float) old('precios.'.$loop->index, optional($pedidoitem)->precio ?? 0), 2),
                            ])
                            @endif
                			<td class="align-middle text-center">
								<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
                            		<i class="fa fa-trash text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
       		</tbody>
       	</table>
        </div>
		@include('stock.movimientostock.template')
        <div class="row mt-2">
        	<div class="col-md-8">
               	<div class="form-group mb-0">
               		<label for="leyenda">Leyendas</label>
               		<textarea name="leyenda" id="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{ old('leyenda', $movimientostock->leyenda ?? '') }}</textarea>
               	</div>
            </div>
        	<div class="col-md-4 @unless($movimientoStockModoFerli) d-none @endunless">
                <div class="form-group mb-0">
                    <label for="totalparespedido">Total pares</label>
                    <input type="text" id="totalparespedido" name="totalparespedido" class="form-control text-right font-weight-bold" readonly value="" />
                </div>
            </div>
        </div>
    </div>
</div>
</div>{{-- /ms-solapa-principal --}}

@if($mostrarSolapaAsiento ?? false)
<div id="ms-solapa-asiento-contable" class="ms-solapa" style="display:none;">
    @include('stock.movimientostock.partials.solapa_asiento_contable', [
        'asientoPreview' => $asientoPreview ?? ['activo' => false],
    ])
</div>
@endif

<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
<input type="hidden" id="tipotransacciondefault_id" class="form-control" value="{{$tipotransacciondefault_id}}" />
<input type="hidden" id="ms-saldo-origen-url" value="{{ route('movimientostock_saldo_articulo') }}">

@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultatipotransaccionstock')
@if(\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
@include('includes.stock.modal_kardex_deposito')
<input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
@endif
@include('stock.movimientostock.partials.modal_elegir_articulo_compra')
@if($movimientoStockModoFerli)
@include('stock.movimientostock.modal')
@endif

<style>
    .ms-form-cabecera .form-group.row {
        margin-left: 0;
        margin-right: 0;
    }
    .ms-form-cabecera .col-form-label {
        padding-top: calc(0.375rem + 1px);
        font-size: 0.875rem;
    }
    .ms-form-cabecera .form-control,
    .ms-form-cabecera select.form-control {
        font-size: 0.875rem;
    }
    .ms-conversion-formula {
        line-height: 1.25;
        word-break: break-word;
    }
    #tabla-items-movimientostock.table-ms-items-compact {
        font-size: 0.8125rem;
    }
    #tabla-items-movimientostock .col-num { width: 2.5rem; }
    #tabla-items-movimientostock .col-art { width: 10rem; min-width: 9rem; }
    #tabla-items-movimientostock .col-desc { width: 8rem; min-width: 6rem; max-width: 8rem; }
    #tabla-items-movimientostock .col-desc-celda .descripcionarticulo {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #tabla-items-movimientostock .col-saldo-orig { width: 4.75rem; min-width: 4.25rem; white-space: nowrap; }
    #tabla-items-movimientostock .col-comb { min-width: 8rem; }
    #tabla-items-movimientostock .col-mod { min-width: 7rem; }
    #tabla-items-movimientostock .col-umd { width: 3.25rem; }
    #tabla-items-movimientostock .col-qty { width: 5rem; }
    #tabla-items-movimientostock .col-qty-alt { width: 5rem; }
    #tabla-items-movimientostock:not(.ms-tabla-conversion-formula) th.ms-col-conversion-formula,
    #tabla-items-movimientostock:not(.ms-tabla-conversion-formula) td.ms-col-conversion-formula {
        display: none;
    }
    #tabla-items-movimientostock .col-insumo-dest { width: 11rem; min-width: 9rem; max-width: 14rem; }
    #tabla-items-movimientostock .col-qty-dest { width: 5rem; }
    #tabla-items-movimientostock .col-umd-dest { width: 3.25rem; }
    #tabla-items-movimientostock .col-precio { width: 5.5rem; }
    #tabla-items-movimientostock .ms-insumo-destino-sku {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.75rem;
    }
    #tabla-items-movimientostock .col-flag { width: 1.75rem; }
    #tabla-items-movimientostock .col-acc { width: 2rem; }
    #tabla-items-movimientostock .codigoarticulo { min-width: 5rem; max-width: 8rem; }
    #tabla-items-movimientostock thead th {
        background-color: #85C1E9;
        color: #17202A;
    }
</style>
