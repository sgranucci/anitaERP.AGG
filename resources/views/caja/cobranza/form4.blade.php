@php
    $retencionesCob = $data->cobranza_retenciones ?? collect();
@endphp
<div class="card form4" style="display: none">
    <div class="card-body">
        <div class="border rounded p-2 mb-3" style="background:#f8f9fa;color:#1b2631;">
            <strong>Retenciones</strong>
            <div class="small text-muted mb-0 mt-1">Enter avanza entre campos de la fila. El total resta del a cobrar en la barra superior.</div>
        </div>
        <table class="table table-sm table-bordered table-hover" id="cobranza-retencion-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 28%;">Retenci&oacute;n</th>
                    <th style="width: 12%;">Comprobante</th>
                    <th class="text-right" style="width: 10%;">Tasa</th>
                    <th style="width: 8%;">Moneda</th>
                    <th class="text-right" style="width: 12%;">Monto</th>
                    <th class="text-right" style="width: 12%;">Cotizaci&oacute;n</th>
                    <th style="width: 3rem;"></th>
                </tr>
            </thead>
            <tbody id="tbody-cobranza-retencion-table" class="container-retencion">
            @foreach ($retencionesCob as $retencion)
                <tr class="item-cobranza-retencion">
                    <td>
                        <select name="retencion_cobranza_ids[]" class="retencion_cobranza_id form-control required" required data-fouc>
                            @if (count($retencion_cobranza_query) > 1)
                                <option value="">-- Seleccionar --</option>
                            @endif
                            @foreach($retencion_cobranza_query as $value)
                                <option value="{{ $value->id }}" @selected((int) $value->id === (int) ($retencion->retencion_cobranza_id ?? 0))>{{ $value->nombre }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" class="comprobanteretencion form-control" name="comprobante_retenciones[]" value="{{ $retencion->comprobante ?? '' }}">
                    </td>
                    <td>
                        <input type="text" class="tasaretencion form-control text-right" name="tasa_retenciones[]" value="{{ $retencion->tasa ?? '' }}">
                    </td>
                    <td>
                        <select name="moneda_retencion_ids[]" class="monedaretencion_id form-control required" required data-fouc>
                            @foreach($moneda_query as $value)
                                <option value="{{ $value->id }}" @selected((int) $value->id === (int) ($retencion->moneda_id ?? 0))>{{ $value->abreviatura }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="monto_retenciones[]" class="form-control montoretencion text-right" min="0" value="{{ $retencion->monto ?? '' }}">
                    </td>
                    <td>
                        <input type="number" name="cotizacion_retenciones[]" class="form-control cotizacionretencion text-right" value="{{ $retencion->cotizacion ?? '0' }}">
                    </td>
                    <td class="text-nowrap">
                        <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_retencion tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @include('caja.cobranza.template4')
        <div class="mb-2">
            <button type="button" id="agrega_renglon_retencion" class="btn btn-outline-primary btn-sm">+ Agrega rengl&oacute;n</button>
        </div>
        <div class="form-group row totales-por-moneda-retencion"></div>
        <div class="form-group row totales-cobranza"></div>
    </div>
</div>
