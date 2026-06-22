<h4 class="mb-3">Plan de cuotas</h4>
@if ($cuotas_escaladas ?? false)
    <div class="alert alert-warning">Las cuotas fueron escaladas desde la OC porque el total de la factura difiere del comprobante a venir.</div>
@endif

@if (empty($cuotas) && ($conceptos ?? collect())->isEmpty())
    <p class="text-muted">Sin cuotas precargadas. Si hay OC vinculada, se generarán al guardar según el comprobante a venir.</p>
@endif

<table class="table table-bordered" id="cuotas-table">
    <thead style="background-color:#85C1E9;color:#17202A;">
        <tr>
            <th>Cuota</th>
            <th>Vencimiento</th>
            <th>Monto</th>
            <th>Moneda</th>
            <th>Cotización</th>
            <th>Forma pago</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody id="tbody-cuotas-table">
        @foreach ($cuotas ?? [] as $idx => $cuota)
            <tr class="item-cuota">
                <td>
                    <input type="number" name="cuota_numero[]" class="form-control form-control-sm"
                        value="{{ $cuota['numero_cuota'] ?? ($idx + 1) }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                </td>
                <td>
                    <input type="date" name="cuota_fechavencimiento[]" class="form-control form-control-sm"
                        value="{{ $cuota['fechavencimiento'] ?? '' }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                </td>
                <td>
                    <input type="number" step="0.01" name="cuota_monto[]" class="form-control form-control-sm"
                        value="{{ $cuota['monto'] ?? 0 }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                </td>
                <td>
                    <input type="number" name="cuota_moneda_id[]" class="form-control form-control-sm"
                        value="{{ $cuota['moneda_id'] ?? ($data->moneda_id ?? 1) }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                </td>
                <td>
                    <input type="number" step="0.0001" name="cuota_cotizacion[]" class="form-control form-control-sm"
                        value="{{ $cuota['cotizacion'] ?? '' }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                </td>
                <td>
                    <input type="number" name="cuota_formapago_id[]" class="form-control form-control-sm"
                        value="{{ $cuota['formapago_id'] ?? 1 }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                </td>
                <td>
                    <input type="text" name="cuota_detalle[]" class="form-control form-control-sm"
                        value="{{ $cuota['detalle'] ?? '' }}"
                        @if (! ($permite_edicion_cuotas ?? true)) readonly @endif>
                    <input type="hidden" name="cuota_ordencompra_comprobante_cuota_id[]"
                        value="{{ $cuota['ordencompra_comprobante_cuota_id'] ?? '' }}">
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
