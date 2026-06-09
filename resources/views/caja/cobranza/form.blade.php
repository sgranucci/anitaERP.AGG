<div class="card form1">
    <div id="form-errors"></div>
    <div class="row">
        <div class="col-sm-6">
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $data->empresa_id ?? session('empresa_id'),
                'mostrar_id' => true,
                'col_label' => 'col-lg-3',
                'col_input' => 'col-lg-7',
            ])
            <div class="form-group row">
                <label for="tipotransaccion_caja" class="col-lg-3 col-form-label">Tipo de transacción</label>
                <select name="tipotransaccion_caja_id" id="tipotransaccion_caja_id" data-placeholder="Tipo de transacción" class="col-lg-7 form-control required" data-fouc required>
                    <option value="">-- Seleccionar --</option>
                    @foreach($tipotransaccion_caja_query as $key => $value)
                        @if( (int) $value->id == (int) old('tipotransaccion_caja_id', $data->tipotransaccion_caja_id ?? session('tipotransaccioncobranza_caja_id')))
                            <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group row" id="div-cliente">
                <label for="cliente" class="col-lg-2 col-form-label">Cliente</label>
                <input type="text" class="col-lg-1 cliente_id cliente_id_local" id="cliente_id" name="cliente_id" value="{{$data->cliente_id ?? ''}}" >
                <input type="text" class="col-lg-6 nombrecliente" id="nombrecliente" name="nombrecliente" value="{{$data->clientes->nombre ?? ''}}" readonly>
                <button type="button" title="Busca clientes" style="padding:1;" class="btn-accion-tabla consultacliente tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <a href="{{route('editar_cliente', ['id' => $data->cliente_id ?? 0])}}" style="display: flex; align-items: center;" class="btn-accion-tabla tooltipsC editarcliente" title="Editar este registro">
                    <i class="fa fa-edit"></i>
                </a>                
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
            <div class="form-group row">
                <label for="estado" class="col-lg-3 col-form-label">Estado</label>
                <input type="text" name="estado" id="estado" class="col-lg-4 form-control" value="{{old('estado', $data->estado ?? 'PRE CARGA')}}" readonly>
                <button type="button" id="botonconfirmar" class="btn btn-success btn-sm">
                    <span class="fa fa-check"></span> Confirma Cobranza
                </button>     
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="detalle" class="col-lg-3 col-form-label">Detalle</label>
        <div class="col-lg-8">
            <input type="text" name="detalle" id="detalle" class="form-control" value="{{old('detalle', $data->detalle ?? '')}}">
        </div>
    </div>
    <input type="hidden" id="numerotransaccion" name="numerotransaccion" value="{{ $data->numerotransaccion ?? '' }}" />
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" id="cotizacion_cobranza" name="cotizacion_cobranza" value="{{ 1 }}" />
    <input type="hidden" id="caja_id" name="caja_id" value="{{ $caja_id ?? '' }}" />
    <input type="hidden" id="caja_movimiento_id" name="caja_movimiento_id" value="{{$data->caja_movimientos[0]->id ?? ''}}" />
    <input type="hidden" id="venta_id" name="venta_id" value="{{$venta_id ?? ''}}" />
    <input type="hidden" id="ordenventa_id" name="ordenventa_id" value="{{$ordenventa_id ?? ''}}" />
    <input type="hidden" id="referer" name="referer" value="{{$referer ?? ''}}" />
    <h2 id="loading"style="display:none">Guardando cobranza ...</h2>
    <h3>Comprobantes</h3>
    <div class="card-body">
        <table class="table" id="comprobante-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Comprobante</th>
                    <th style="width: 8%;">Fecha</th>
                    <th style="width: 8%;">Vencimiento</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 12%; text-align: right;">Cotización</th>
                    <th style="width: 12%; text-align: right;">Monto</th>
                    <th style="width: 12%; text-align: right;">Aplicado</th>
                    <th style="width: 12%; text-align: right;">Saldo</th>
                    <th></th>
                    <th>Ap</th>
                </tr>
            </thead>
            <tbody id="tbody-comprobante-table">
            @php
                $comprobantesCobranza = old('cuenta');
                if ($comprobantesCobranza === null) {
                    $comprobantesCobranza = ($data->cobranza_comprobantes ?? null)?->isNotEmpty()
                        ? $data->cobranza_comprobantes
                        : collect();
                } else {
                    $comprobantesCobranza = collect($comprobantesCobranza);
                }
            @endphp
            @if ($comprobantesCobranza->isNotEmpty())
                @foreach ($comprobantesCobranza as $comprobante)
                    @if (! is_object($comprobante))
                        @continue
                    @endif
                    @php
                        $ventaOrigenId = $comprobante->cliente_cuentacorrientes->venta_id ?? null;
                        $descuentoFila = null;
                        if ($ventaOrigenId && isset($data) && ($data->cobranza_descuentos ?? null)) {
                            $descuentoFila = $data->cobranza_descuentos
                                ->first(fn ($d) => (int) $d->venta_origen_id === (int) $ventaOrigenId
                                    && $d->estado === \App\Models\Caja\Cobranza_Descuento::ESTADO_PENDIENTE);
                        }
                        $tieneDescuentoPendiente = $descuentoFila && (float) $descuentoFila->importe_calculado > 0;
                    @endphp
                    <tr class="item-comprobante{{ $tieneDescuentoPendiente ? ' tiene-descuento-cobranza' : '' }}">
                        <td>
                            <input type="text" class="codigocomprobante form-control" name="codigocomprobantes[]" value="{{$comprobante->cliente_cuentacorrientes->ventas->codigo ?? ''}}" >
                        </td>							
                        <td>
                            <input type="date" class="fechacomprobante form-control" name="fechacomprobantes[]" value="{{$comprobante->cliente_cuentacorrientes->ventas->fecha ?? ''}}" readonly>
                        </td>
                        <td>
                            <input type="date" class="fechavencimientocomprobante form-control" name="fechavencimientocomprobantes[]" value="{{$comprobante->cliente_cuentacorrientes->fechavencimiento ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="monedacomprobante_ids[]" data-placeholder="Moneda" class="monedacomprobante form-control required" required readonly data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $comprobante->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" style="text-align: right;" name="cotizacioncomprobantes[]" class="form-control cotizacioncomprobante" value="{{old('cotizaciones[]', $comprobante->cotizacion ?? '0')}}"  readonly>                            
                        </td>
                        <td>
                            <input type="number" style="text-align: right;" name="montocomprobantes[]" class="form-control montocomprobante" value="{{old('montocomprobantes[]', abs($comprobante->cliente_cuentacorrientes->total) ?? '')}}"  readonly>
                        </td>
                        <td>
                            <input type="number" style="text-align: right;" name="montoaplicadocomprobantes[]" class="form-control montoaplicadocomprobante" value="{{old('montoaplicados[]', abs($comprobante->montoaplicado) ?? '')}}">
                        </td>
                        <td>
                            <input type="number" style="text-align: right;" name="saldocomprobantes[]" class="form-control saldocomprobante" value="{{old('saldocomprobantes[]', abs($comprobante->cliente_cuentacorrientes->total)-abs($comprobante->montoaplicado) ?? '')}}"  readonly>
                        </td>
                        <td>
                            @if (can('editar-factura', false))
                                <a href="{{route('editar_factura', ['id' => $comprobante->cliente_cuentacorrientes->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                <i class="fa fa-edit"></i>
                                </a>
                            @endif
                            @if (($puede_descuento_cobranza ?? false))
                                <button type="button" class="btn-accion-tabla tooltipsC btn-descuento-comprobante" title="Descuento (genera NC al confirmar)">
                                    <i class="fa fa-percent {{ $tieneDescuentoPendiente ? 'text-success' : 'text-warning' }}"></i>
                                </button>
                            @endif
                            @if (can('generar-nota-de-credito', false))
                                @if ($comprobante->total > 0)
                                    <a href="{{route('generar_notadecredito', ['id' => $comprobante->cliente_cuentacorrientes->id])}}" class="btn-accion-tabla tooltipsC" title="Generar nota de crédito">
                                    <i class="fa fa-undo text-danger"></i>
                                    </a>
                                @endif
                            @endif                            
                            @if (can('listar-factura', false))
                                <a href="{{route('lista_una_factura', ['id' => $comprobante->cliente_cuentacorrientes->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el Comprobante de Venta">
                                <i class="fa fa-print"></i>
                                </a>
                            @endif                                             
                        </td>
                        <td>
                            <input name="checkaplicaciones[]" class="checkaplicacion" type="checkbox" autocomplete="off"> 
                            <input type="hidden" class="idcuentacorriente form-control" name="idcuentacorrientes[]" value="{{$comprobante->cliente_cuentacorrientes->id}}" >
                            <input type="hidden" class="idventa form-control" name="idventas[]" value="{{$comprobante->cliente_cuentacorrientes->venta_id}}" >
                            <input type="hidden" class="descuento_tipo" name="descuento_tipos[]" value="{{ $descuentoFila->tipo ?? '' }}" />
                            <input type="hidden" class="descuento_valor" name="descuento_valores[]" value="{{ $descuentoFila->valor ?? '' }}" />
                            <input type="hidden" class="descuento_importe" name="descuento_importes[]" value="{{ $descuentoFila->importe_calculado ?? '' }}" />
                            <input type="hidden" class="descuento_venta_origen_id" name="descuento_venta_origen_ids[]" value="{{ $tieneDescuentoPendiente ? $descuentoFila->venta_origen_id : '' }}" />
                            <input type="hidden" class="descuento_cc_origen_id" name="descuento_cc_origen_ids[]" value="{{ $tieneDescuentoPendiente ? $descuentoFila->cliente_cuentacorriente_origen_id : '' }}" />
                            <input type="hidden" class="descuento_leyenda" name="descuento_leyendas[]" value="{{ $descuentoFila->leyenda ?? '' }}" />
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
            <tbody id="tbody-nc-pendiente-table"></tbody>
        </table>
        @include('caja.cobranza.template')
        @include('caja.cobranza.template_nc_pendiente')
        <div class="form-group row totales-descuentos-cobranza">
        </div>
        <div class="form-group row totales-por-comprobante">
        </div>
        <div class="form-group row totales-por-moneda">
        </div>
        <div class="form-group row totales-por-moneda-cheque">
        </div>
        <div class="form-group row totales-por-moneda-retencion">
        </div>      
        <div class="form-group row totales-cobranza">
        </div>   
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.contable.modalconsultacuentacontable')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.ventas.modalconsultacliente')
@include('caja.cobranza.revertircobranzamodal')
@include('caja.cobranza.modal_descuento_comprobante')


