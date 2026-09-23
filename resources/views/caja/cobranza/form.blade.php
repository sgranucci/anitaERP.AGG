@php
    $clienteCodigo = old('codigocliente', $data->clientes->codigo ?? '');
    $clienteNombre = old('nombrecliente', $data->clientes->nombre ?? '');
    $clienteIdVal = old('cliente_id', $data->cliente_id ?? '');
@endphp
<div class="card form1">
    <div id="form-errors"></div>
    <div class="card-body">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $data->empresa_id ?? session('empresa_id'),
            'mostrar_id' => true,
            'col_label' => 'col-lg-2 text-right pr-2',
            'col_input' => 'col-lg-4',
        ])
        <div class="form-group row">
            <label for="fecha" class="col-lg-2 control-label text-right pr-2">Fecha</label>
            <div class="col-lg-3">
                <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', isset($data->fecha) ? (is_string($data->fecha) ? $data->fecha : optional($data->fecha)->format('Y-m-d')) : date('Y-m-d')) }}">
            </div>
            <label for="tipotransaccion_caja_id" class="col-lg-2 control-label text-right pr-2">Tipo de transacci&oacute;n</label>
            <div class="col-lg-4">
                @php
                    $tipoTransaccionSel = old(
                        'tipotransaccion_caja_id',
                        $data->tipotransaccion_caja_id ?? ($tipotransaccion_caja_id ?? session('tipotransaccioncobranza_caja_id'))
                    );
                @endphp
                <select name="tipotransaccion_caja_id" id="tipotransaccion_caja_id" data-placeholder="Tipo de transacci&oacute;n" class="form-control required" data-fouc required>
                    <option value="">-- Seleccionar --</option>
                    @foreach($tipotransaccion_caja_query as $value)
                        <option value="{{ $value->id }}" data-abreviatura="{{ strtoupper(trim((string) ($value->abreviatura ?? ''))) }}" @selected((int) $tipoTransaccionSel === (int) $value->id)>
                            {{ $value->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
            <div class="col-lg-2">
                @php
                    $estadoFormCob = old('estado', $data->estado ?? (config('cobranza.GRABACION') === 'CON_PRECARGA' ? 'PRE CARGA' : 'CONFIRMADA'));
                @endphp
                <input type="text" name="estado" id="estado" class="form-control" value="{{ $estadoFormCob }}" readonly>
            </div>
            @if ($estadoFormCob === 'PRE CARGA')
            <div class="col-lg-2" id="div-botonconfirmar">
                <button type="button" id="botonconfirmar" class="btn btn-success btn-sm btn-block">
                    <span class="fa fa-check"></span> Confirmar
                </button>
            </div>
            @endif
        </div>
        <div class="form-group row tm-cliente-campo gastro-campo-consulta" id="div-cliente">
            <label for="codigocliente" class="col-lg-2 control-label text-right pr-2">Cliente</label>
            <div class="col-lg-7">
                <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                    <input type="hidden" name="cliente_id" id="cliente_id" class="cliente_id" value="{{ $clienteIdVal }}">
                    <button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" class="form-control codigocliente" id="codigocliente" name="codigocliente"
                        value="{{ $clienteCodigo }}" placeholder="C&oacute;d." autocomplete="off"
                        title="C&oacute;digo de cliente. F1 = consulta, Enter = resolver"
                        style="width: 5.5rem; flex-shrink: 0;">
                    <input type="text" class="form-control nombrecliente text-truncate" id="nombrecliente" name="nombrecliente"
                        value="{{ $clienteNombre }}" placeholder="Nombre" readonly
                        style="min-width: 0; flex: 1 1 auto;">
                    <a href="{{ route('editar_cliente', ['id' => $clienteIdVal ?: 0]) }}"
                       class="btn-accion-tabla tooltipsC editarcliente flex-shrink-0"
                       title="Consultar / editar cliente"
                       target="_blank" rel="noopener"
                       @if (! $clienteIdVal) style="display:none;" @endif>
                        <i class="fa fa-edit"></i>
                    </a>
                </div>
                <small class="form-text text-muted">F1 o lupa consulta; Enter resuelve por c&oacute;digo.</small>
                <label id="nombretiposuspension" class="col-form-label text-danger mb-0 d-block"></label>
            </div>
        </div>
        <div class="form-group row">
            <label for="detalle" class="col-lg-2 control-label text-right pr-2">Detalle</label>
            <div class="col-lg-8">
                <input type="text" name="detalle" id="detalle" class="form-control" value="{{ old('detalle', $data->detalle ?? '') }}">
            </div>
        </div>

        <input type="hidden" id="numerotransaccion" name="numerotransaccion" value="{{ $data->numerotransaccion ?? '' }}" />
        <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
        <input type="hidden" id="cotizacion_cobranza" name="cotizacion_cobranza" value="{{ 1 }}" />
        <input type="hidden" id="caja_id" name="caja_id" value="{{ $caja_id ?? ($data->caja_id ?? '') }}" />
        <input type="hidden" id="caja_movimiento_id" name="caja_movimiento_id" value="{{ $data->caja_movimientos[0]->id ?? '' }}" />
        <input type="hidden" id="venta_id" name="venta_id" value="{{ $venta_id ?? '' }}" />
        <input type="hidden" id="ordenventa_id" name="ordenventa_id" value="{{ $ordenventa_id ?? '' }}" />
        <input type="hidden" id="referer" name="referer" value="{{ $referer ?? '' }}" />
        <h2 id="loading" style="display:none">Guardando cobranza ...</h2>

        <hr>
        <h5 class="mb-1">Comprobantes a cobrar</h5>
        <p class="text-muted small mb-2">
            Tild&aacute; <strong>Ap</strong> o carg&aacute; el monto aplicado. Las herramientas de cada factura quedan en una sola fila.
            Destildar limpia el aplicado. <strong>Aplicar por monto</strong> reparte secuencialmente desde el primer comprobante.
        </p>
        <div class="form-group row align-items-center mb-2 cob-aplicar-monto-toolbar">
            <label for="cob-monto-aplicar-secuencial" class="col-lg-2 col-form-label text-right pr-2 mb-0">Aplicar por monto</label>
            <div class="col-lg-3">
                <div class="input-group input-group-sm">
                    <input type="number" step="0.01" min="0" id="cob-monto-aplicar-secuencial" class="form-control text-right" placeholder="0.00" title="Monto a repartir en orden de la grilla">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-primary" id="cob-btn-aplicar-secuencial" title="Aplica el monto en orden a las facturas pendientes">Aplicar</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cob-btn-limpiar-aplicaciones" title="Quita todos los montos aplicados">Limpiar aplicados</button>
            </div>
        </div>
        <div class="row no-gutters mb-2 cob-resumen-deuda-cards">
            <div class="col-6 col-md-3 pr-1 mb-1">
                <div class="border rounded px-2 py-1 h-100" style="background:#d6eaf8;">
                    <div class="small text-muted text-right">Saldo deuda</div>
                    <div class="font-weight-bold text-right text-nowrap" id="cob-card-saldo" style="font-size:1.05rem;">0,00</div>
                </div>
            </div>
            <div class="col-6 col-md-3 px-1 mb-1">
                <div class="border rounded px-2 py-1 h-100" style="background:#d6eaf8;">
                    <div class="small text-muted text-right">A aplicar</div>
                    <div class="font-weight-bold text-right text-nowrap" id="cob-card-aplicado" style="font-size:1.05rem;">0,00</div>
                </div>
            </div>
            <div class="col-6 col-md-3 px-1 mb-1">
                <div class="border rounded px-2 py-1 h-100" style="background:#d6eaf8;">
                    <div class="small text-muted text-right">Descuentos / NC</div>
                    <div class="font-weight-bold text-right text-nowrap" id="cob-card-descuentos" style="font-size:1.05rem;">0,00</div>
                </div>
            </div>
            <div class="col-6 col-md-3 pl-1 mb-1">
                <div class="border rounded px-2 py-1 h-100" style="background:#d6eaf8;">
                    <div class="small text-muted text-right">Neto a cobrar</div>
                    <div class="font-weight-bold text-right text-nowrap" id="cob-card-neto" style="font-size:1.05rem;">0,00</div>
                </div>
            </div>
        </div>
        <style>
            #comprobante-table .cob-col-aplicar { width: 9.5rem; min-width: 8.5rem; }
            #comprobante-table .cob-monto-aplicar {
                width: 100%;
                max-width: 9rem;
                margin-left: auto;
                display: block;
                text-align: right;
            }
            #comprobante-table .cob-col-acciones {
                width: 9.5rem;
                min-width: 9.5rem;
                white-space: nowrap;
            }
            #comprobante-table .cob-acciones {
                display: inline-flex;
                flex-wrap: nowrap;
                align-items: center;
                gap: 2px;
                white-space: nowrap;
            }
            #comprobante-table .cob-acciones .btn-accion-tabla {
                flex-shrink: 0;
            }
            #comprobante-table tr.tiene-descuento-cobranza { background: #e8f8f0; }
            #comprobante-table tr.item-comprobante-credito .montocomprobante,
            #comprobante-table tr.item-comprobante-credito .saldocomprobante {
                color: #c0392b;
            }
        </style>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover" id="comprobante-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th class="text-center" style="width:2.5rem;" title="Aplicar">Ap</th>
                        <th>Comprobante</th>
                        <th>Fecha</th>
                        <th>Vencimiento</th>
                        <th>Moneda</th>
                        <th class="text-right">Cotizaci&oacute;n</th>
                        <th class="text-right">Monto</th>
                        <th class="text-right cob-col-aplicar">Aplicado</th>
                        <th class="text-right">Saldo</th>
                        <th class="cob-col-acciones">Acciones</th>
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
                            $ccId = $comprobante->cliente_cuentacorrientes->id ?? null;
                            $ventaIdFila = $comprobante->cliente_cuentacorrientes->venta_id ?? null;
                            $ccFila = $comprobante->cliente_cuentacorrientes;
                            $totalCc = (float) ($ccFila->total ?? 0);
                            $aplicadoCc = (float) ($ccFila->cliente_cuentacorriente_aplicaciones?->sum('total') ?? 0);
                            $esCredito = $totalCc < 0;
                            $saldoPendiente = \App\Support\Ventas\ClienteCuentacorrienteGrillaSupport::saldoPendiente(
                                $totalCc,
                                $aplicadoCc
                            );
                            // Si esta cobranza ya imputó en CC, reincorporar ese monto al disponible editable.
                            $cobranzaIdEdit = (int) ($data->id ?? 0);
                            $aplicadoEstaCobranza = $cobranzaIdEdit > 0
                                ? (float) ($ccFila->cliente_cuentacorriente_aplicaciones
                                    ?->where('cobranza_id', $cobranzaIdEdit)
                                    ->sum('total') ?? 0)
                                : 0.0;
                            $montoAplicadoEsta = abs((float) ($comprobante->montoaplicado ?? 0));
                            $saldoDisponible = round($saldoPendiente - $aplicadoEstaCobranza, 2);
                            $saldoMostrar = $esCredito
                                ? round($saldoDisponible + $montoAplicadoEsta, 2)
                                : round($saldoDisponible - $montoAplicadoEsta, 2);
                        @endphp
                        <tr class="item-comprobante{{ $tieneDescuentoPendiente ? ' tiene-descuento-cobranza' : '' }}{{ $esCredito ? ' item-comprobante-credito' : '' }}" data-lado="{{ $esCredito ? 'credito' : 'deuda' }}">
                            <td class="text-center align-middle">
                                <input name="checkaplicaciones[]" class="checkaplicacion" type="checkbox" autocomplete="off" @checked($montoAplicadoEsta > 0.009)>
                                <input type="hidden" class="idcuentacorriente form-control" name="idcuentacorrientes[]" value="{{ $ccId }}" >
                                <input type="hidden" class="idventa form-control" name="idventas[]" value="{{ $ventaIdFila }}" >
                                <input type="hidden" class="descuento_tipo" name="descuento_tipos[]" value="{{ $descuentoFila->tipo ?? '' }}" />
                                <input type="hidden" class="descuento_valor" name="descuento_valores[]" value="{{ $descuentoFila->valor ?? '' }}" />
                                <input type="hidden" class="descuento_importe" name="descuento_importes[]" value="{{ $descuentoFila->importe_calculado ?? '' }}" />
                                <input type="hidden" class="descuento_venta_origen_id" name="descuento_venta_origen_ids[]" value="{{ $tieneDescuentoPendiente ? $descuentoFila->venta_origen_id : '' }}" />
                                <input type="hidden" class="descuento_cc_origen_id" name="descuento_cc_origen_ids[]" value="{{ $tieneDescuentoPendiente ? $descuentoFila->cliente_cuentacorriente_origen_id : '' }}" />
                                <input type="hidden" class="descuento_leyenda" name="descuento_leyendas[]" value="{{ $descuentoFila->leyenda ?? '' }}" />
                            </td>
                            <td>
                                <input type="text" class="codigocomprobante form-control" name="codigocomprobantes[]" value="{{ $comprobante->cliente_cuentacorrientes->ventas->codigo ?? '' }}" >
                            </td>
                            <td>
                                @php
                                    $ccFila = $comprobante->cliente_cuentacorrientes ?? null;
                                    $fechaComp = optional(optional($ccFila)->ventas)->fecha ?? optional($ccFila)->fecha;
                                    $fechaVenc = optional($ccFila)->fechavencimiento;
                                    $fechaCompYmd = $fechaComp instanceof \DateTimeInterface ? $fechaComp->format('Y-m-d') : (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $fechaComp, $mFc) ? $mFc[1] : '');
                                    $fechaVencYmd = $fechaVenc instanceof \DateTimeInterface ? $fechaVenc->format('Y-m-d') : (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $fechaVenc, $mFv) ? $mFv[1] : '');
                                @endphp
                                <input type="date" class="fechacomprobante form-control" name="fechacomprobantes[]" value="{{ $fechaCompYmd }}" readonly>
                            </td>
                            <td>
                                <input type="date" class="fechavencimientocomprobante form-control" name="fechavencimientocomprobantes[]" value="{{ $fechaVencYmd }}" readonly>
                            </td>
                            <td>
                                <select name="monedacomprobante_ids[]" data-placeholder="Moneda" class="monedacomprobante form-control required" required readonly data-fouc>
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($moneda_query as $value)
                                        <option value="{{ $value->id }}" @selected((int) old('moneda_ids[]', $comprobante->moneda_id ?? '') === (int) $value->id)>
                                            {{ $value->abreviatura }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" style="text-align: right;" name="cotizacioncomprobantes[]" class="form-control cotizacioncomprobante" value="{{ old('cotizaciones[]', $comprobante->cotizacion ?? '0') }}" readonly>
                            </td>
                            <td>
                                <input type="number" style="text-align: right;" name="montocomprobantes[]" class="form-control montocomprobante" value="{{ old('montocomprobantes[]', $totalCc != 0.0 ? number_format($totalCc, 2, '.', '') : '') }}" readonly>
                            </td>
                            <td class="cob-col-aplicar">
                                <input type="number" name="montoaplicadocomprobantes[]" class="form-control montoaplicadocomprobante cob-monto-aplicar" value="{{ old('montoaplicados[]', $montoAplicadoEsta > 0 ? number_format($montoAplicadoEsta, 2, '.', '') : '') }}">
                            </td>
                            <td>
                                <input type="number" style="text-align: right;" name="saldocomprobantes[]" class="form-control saldocomprobante" value="{{ old('saldocomprobantes[]', number_format($saldoMostrar, 2, '.', '')) }}" data-saldo-disponible="{{ number_format($saldoDisponible, 2, '.', '') }}" readonly>
                            </td>
                            <td class="cob-col-acciones align-middle">
                                <div class="cob-acciones">
                                    @if (can('editar-factura', false) && $ventaIdFila)
                                        <a href="{{ route('editar_factura', ['id' => $ventaIdFila]) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC editarfactura" title="Editar factura">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if ($puede_descuento_cobranza ?? false)
                                        <button type="button" class="btn-accion-tabla tooltipsC btn-descuento-comprobante" title="Descuento (genera NC al confirmar)">
                                            <i class="fa fa-percent {{ $tieneDescuentoPendiente ? 'text-success' : 'text-warning' }}"></i>
                                        </button>
                                    @endif
                                    @if (can('generar-nota-de-credito', false) && ($comprobante->total ?? 0) > 0 && $ventaIdFila)
                                        <a href="{{ route('generar_notadecredito', ['id' => $ventaIdFila]) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC generarnotadecredito" title="Generar nota de cr&eacute;dito">
                                            <i class="fa fa-undo text-danger"></i>
                                        </a>
                                    @endif
                                    @if (can('listar-factura', false) && $ventaIdFila)
                                        <a href="{{ route('lista_una_factura', ['id' => $ventaIdFila]) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC listarfactura" title="Imprimir comprobante">
                                            <i class="fa fa-print"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @endif
                </tbody>
                <tbody id="tbody-nc-pendiente-table"></tbody>
                <tfoot class="cob-deuda-tfoot" style="display:none;background:#e9ecef;font-weight:700;">
                    <tr>
                        <td colspan="6" class="text-right">Totales</td>
                        <td class="text-right" id="cob-tfoot-monto">0,00</td>
                        <td class="text-right" id="cob-tfoot-aplicado">0,00</td>
                        <td class="text-right" id="cob-tfoot-saldo">0,00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @include('caja.cobranza.template')
        @include('caja.cobranza.template_nc_pendiente')
        <div class="form-group row totales-descuentos-cobranza"></div>
        <div class="form-group row totales-por-comprobante"></div>
        <div class="form-group row totales-por-moneda"></div>
        <div class="form-group row totales-por-moneda-cheque"></div>
        <div class="form-group row totales-por-moneda-retencion"></div>
        <div class="form-group row totales-cobranza"></div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{ csrf_token() }}" />
@include('includes.contable.modalconsultacuentacontable')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.ventas.modalconsultacliente')
@include('caja.cobranza.revertircobranzamodal')
@include('caja.cobranza.modal_descuento_comprobante')
