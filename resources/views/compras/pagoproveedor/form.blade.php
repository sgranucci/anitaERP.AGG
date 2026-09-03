@php
    $esEdicion = isset($data) && isset($data->id);
@endphp
<div class="card form1">
    <div class="card-body">
        <div class="form-group row">
            <label class="col-lg-2 col-form-label text-right">Empresa</label>
            <div class="col-lg-4">
                <select name="empresa_id" id="empresa_id" class="form-control" required>
                    <option value="">Seleccione</option>
                    @foreach($empresa_query as $e)
                        <option value="{{ $e->id }}" @selected(old('empresa_id', $data->empresa_id ?? session('empresa_id')) == $e->id)>{{ $e->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <label class="col-lg-2 col-form-label text-right">Fecha</label>
            <div class="col-lg-3">
                <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', isset($data->fecha) ? optional($data->fecha)->format('Y-m-d') : date('Y-m-d')) }}">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-2 col-form-label text-right">Proveedor</label>
            <div class="col-lg-6" id="div-proveedor">
                <div class="input-group">
                    <input type="hidden" name="proveedor_id" id="proveedor_id" class="proveedor_id" value="{{ old('proveedor_id', $data->proveedor_id ?? '') }}">
                    <input type="text" class="form-control codigoproveedor" id="codigoproveedor" placeholder="Código" autocomplete="off" value="{{ old('codigoproveedor', $data->proveedores->codigo ?? '') }}">
                    <input type="text" class="form-control descripcionproveedor nombreproveedor" id="descripcionproveedor" readonly placeholder="Nombre" value="{{ old('descripcionproveedor', $data->proveedores->nombre ?? '') }}">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info consultaproveedor" title="Consultar"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
            <label class="col-lg-1 col-form-label text-right">Caja</label>
            <div class="col-lg-2">
                <select name="caja_id" id="caja_id" class="form-control">
                    <option value="">—</option>
                    @foreach($caja_query as $c)
                        <option value="{{ $c->id }}" @selected(old('caja_id', $data->caja_id ?? '') == $c->id)>{{ $c->nombre ?? $c->id }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-2 col-form-label text-right">Moneda</label>
            <div class="col-lg-2">
                <select name="moneda_id" id="moneda_id" class="form-control" required>
                    @foreach($moneda_query as $m)
                        <option value="{{ $m->id }}" @selected(old('moneda_id', $data->moneda_id ?? 1) == $m->id)>{{ $m->abreviatura }}</option>
                    @endforeach
                </select>
            </div>
            <label class="col-lg-2 col-form-label text-right">Cotización</label>
            <div class="col-lg-2">
                <input type="number" step="0.00000001" name="cotizacion" id="cotizacion" class="form-control" value="{{ old('cotizacion', $data->cotizacion ?? 1) }}">
            </div>
            <label class="col-lg-2 col-form-label text-right">Modo cotiz.</label>
            <div class="col-lg-2">
                <select name="modo_cotizacion" id="modo_cotizacion" class="form-control">
                    @foreach($modos as $modo)
                        <option value="{{ $modo['valor'] }}" @selected(old('modo_cotizacion', $data->modo_cotizacion ?? config('pagoproveedor.modo_cotizacion_default')) === $modo['valor'])>{{ $modo['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @include('compras.partials.campo_cbu_pago', [
            'col_label' => 'col-lg-2 col-form-label text-right',
            'col_input' => 'col-lg-6',
            'data' => $data ?? null,
        ])
        <div class="form-group row">
            <label class="col-lg-2 col-form-label text-right">Detalle</label>
            <div class="col-lg-8">
                <input type="text" name="detalle" class="form-control" maxlength="255" value="{{ old('detalle', $data->detalle ?? '') }}">
            </div>
            <div class="col-lg-2">
                <select name="estado" class="form-control">
                    <option value="CONFIRMADA" @selected(old('estado', $data->estado ?? 'CONFIRMADA') === 'CONFIRMADA')>Confirmada</option>
                    <option value="PRE CARGA" @selected(old('estado', $data->estado ?? '') === 'PRE CARGA')>Pre carga</option>
                </select>
            </div>
        </div>
        @if($esEdicion)
        <div class="form-group row">
            <label class="col-lg-2 col-form-label text-right">OP</label>
            <div class="col-lg-4 col-form-label">
                <strong>{{ $data->etiquetaComprobante() }}</strong>
                <a class="btn btn-sm btn-secondary ml-2" target="_blank" rel="noopener" href="{{ route('imprimir_pagoproveedor', $data->id) }}"><i class="fa fa-print"></i> Imprimir</a>
            </div>
        </div>
        @endif

        <hr>
        <h5>Comprobantes a pagar</h5>
        <p class="text-muted small mb-2">
            El monto a aplicar va en moneda de la factura (alineado a la derecha, como el saldo). Si la OP está en otra moneda se convierte con la cotización de liquidación
            (factura o del día según el modo). La DC se asienta; no abre un ítem extra en pesos.
        </p>
        <div class="table-responsive mb-2 pp-resumen-deuda-cards">
            <table class="table table-sm table-bordered mb-0" style="background:#fff;">
                <thead style="background:#d6eaf8;color:#1b2631;">
                    <tr>
                        <th class="text-right">Saldo</th>
                        <th class="text-right">A aplicar</th>
                        <th class="text-right">Equiv. OP</th>
                        <th class="text-right">DC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-right font-weight-bold" id="pp-card-saldo">0,00</td>
                        <td class="text-right font-weight-bold" id="pp-card-aplicado">0,00</td>
                        <td class="text-right font-weight-bold" id="pp-card-equiv">0,00</td>
                        <td class="text-right font-weight-bold" id="pp-card-dc">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <style>
            #tabla-deuda-proveedor .pp-col-aplicar { width: 9.5rem; min-width: 8.5rem; }
            #tabla-deuda-proveedor .pp-monto-aplicar {
                width: 100%;
                max-width: 9rem;
                margin-left: auto;
                display: block;
                text-align: right;
            }
        </style>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover" id="tabla-deuda-proveedor">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th class="text-center" style="width:2.5rem;">Incl.</th>
                        <th>Comprobante</th>
                        <th>Vto</th>
                        <th>Moneda</th>
                        <th class="text-right">Saldo</th>
                        <th class="text-right pp-col-aplicar">A aplicar</th>
                        <th class="text-right">Cot. liq.</th>
                        <th class="text-right">Equiv. OP</th>
                        <th class="text-right">DC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="9" class="text-muted text-center">Seleccione empresa y proveedor</td></tr>
                </tbody>
                <tfoot class="pp-deuda-tfoot" style="display:none;background:#e9ecef;font-weight:700;">
                    <tr>
                        <td colspan="4" class="text-right">Totales</td>
                        <td class="text-right" id="pp-tfoot-saldo">0,00</td>
                        <td class="text-right" id="pp-tfoot-aplicado">0,00</td>
                        <td></td>
                        <td class="text-right" id="pp-tfoot-equiv">0,00</td>
                        <td class="text-right" id="pp-tfoot-dc">—</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <input type="hidden" name="monto" id="monto" value="{{ old('monto', $data->monto ?? 0) }}">
        <input type="hidden" name="importe_neto_retencion" id="importe_neto_retencion" value="{{ old('importe_neto_retencion', $data->monto ?? 0) }}">
        <input type="hidden" name="importe_iva_retencion" id="importe_iva_retencion" value="0">
        <input type="hidden" id="pp-retenciones-json" value="">
        <div class="form-group row totales-por-moneda mt-2"></div>
        <div class="form-group row totales-pagoproveedor mt-1"></div>
    </div>
</div>
