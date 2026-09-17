@php
    $movCajaCob = ($data->caja_movimientos ?? collect())->first();
    $cuentasCajaCob = $movCajaCob->caja_movimiento_cuentacajas ?? collect();
@endphp
<div class="card form2" style="display: none">
    <div class="card-body">
        <div class="border rounded p-2 mb-3" style="background:#f8f9fa;color:#1b2631;" id="cob-ref-cuentas-caja">
            <strong>Liquidaci&oacute;n:</strong>
            Aplicado <span id="cob-ref-aplicado-txt">0,00</span>
            − Retenciones <span id="cob-ref-retenciones-txt">0,00</span>
            = A cobrar <strong id="cob-ref-acobrar-txt">0,00</strong>
            · Medios <strong id="cob-ref-medios-txt">0,00</strong>
            · Cuadra <strong id="cob-ref-dif-txt">0,00</strong>
            <div class="small text-muted mb-0 mt-1">
                Carg&aacute; en esta grilla el neto a cobrar (aplicado − retenciones). F1 o lupa en el c&oacute;digo; Enter resuelve y avanza.
            </div>
        </div>
        <h5 class="mb-2">Cuentas de caja</h5>
        <table class="table table-sm table-bordered table-hover" id="cuenta-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 12%;">C&oacute;digo</th>
                    <th style="width: 22%;">Descripci&oacute;n</th>
                    <th style="width: 7%;">Moneda</th>
                    <th class="text-right" style="width: 12%;">Monto</th>
                    <th class="text-right" style="width: 10%;">Cotizaci&oacute;n</th>
                    <th>Observaci&oacute;n</th>
                    <th style="width: 3rem;"></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @foreach ($cuentasCajaCob as $cuenta)
                <tr class="item-cuenta">
                    <td>
                        <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                            <input type="hidden" name="cuentacaja[]" class="form-control iicuenta" readonly value="{{ $loop->iteration }}" />
                            <input type="hidden" class="cuentacaja_id" name="cuentacaja_ids[]" value="{{ $cuenta->cuentacaja_id ?? '' }}" >
                            <input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="{{ $cuenta->cuentacaja_id ?? '' }}" >
                            <button type="button" title="Consulta cuentas (F1)" class="btn-accion-tabla consultacuentacaja tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="codigo form-control" name="codigos[]" value="{{ $cuenta->cuentacajas->codigo ?? '' }}"
                                   placeholder="C&oacute;d." title="F1 consulta / Enter resuelve"
                                   style="width:5.5rem;flex-shrink:0;" autocomplete="off">
                            <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ $cuenta->cuentacajas->codigo ?? '' }}" >
                        </div>
                    </td>
                    <td>
                        <input type="text" class="nombre form-control" name="nombres[]" value="{{ $cuenta->cuentacajas->nombre ?? '' }}" readonly>
                    </td>
                    <td>
                        <select name="moneda_ids[]" class="moneda form-control required" required readonly data-fouc>
                            <option value="">-- Seleccionar --</option>
                            @foreach($moneda_query as $value)
                                <option value="{{ $value->id }}" @selected((int) $value->id === (int) ($cuenta->moneda_id ?? 0))>{{ $value->abreviatura }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="montos[]" class="form-control monto text-right" value="{{ abs((float) ($cuenta->monto ?? 0)) }}">
                    </td>
                    <td>
                        <input type="number" name="cotizaciones[]" class="form-control cotizacion text-right" value="{{ $cuenta->cotizacion ?? '0' }}">
                    </td>
                    <td>
                        <input type="text" name="observaciones[]" class="form-control observacion" value="{{ $cuenta->observacion ?? '' }}">
                    </td>
                    <td class="text-nowrap">
                        <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @include('caja.cobranza.template2')
        <div class="mb-2">
            <button type="button" id="agrega_renglon_cuenta" class="btn btn-outline-primary btn-sm">+ Agrega rengl&oacute;n</button>
        </div>
        <div class="form-group row totales-por-moneda"></div>
        <div class="form-group row totales-cobranza"></div>
    </div>
</div>
