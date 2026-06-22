<h4 class="mb-3">Conceptos de IVA compra</h4>
<p class="text-muted small">Agregue uno o más renglones. Los montos deben coincidir con el desglose de la factura.</p>

<table class="table table-bordered" id="concepto-table">
    <thead style="background-color:#85C1E9;color:#17202A;">
        <tr>
            <th style="width:42%;">Concepto</th>
            <th style="width:22%;">Monto</th>
            <th style="width:8%;" class="text-center" title="Cuenta contable DEBE en el maestro">Cta.</th>
            <th style="width:8%;"></th>
        </tr>
    </thead>
    <tbody id="tbody-concepto-table">
        @php $conceptosLista = old('concepto_ivacompra_ids') ? collect(old('concepto_ivacompra_ids'))->zip(old('montos', [])) : ($conceptos ?? collect()); @endphp
        @if (old('concepto_ivacompra_ids'))
            @foreach (old('concepto_ivacompra_ids', []) as $idx => $conceptoId)
                <tr class="item-concepto">
                    <td>
                        <select name="concepto_ivacompra_ids[]" class="form-control concepto_ivacompra_id">
                            <option value="">-- Elija concepto --</option>
                            @foreach ($concepto_ivacompra_query as $concepto)
                                @include('compras.comprobante_proveedor.partials.option_concepto_ivacompra', [
                                    'concepto' => $concepto,
                                    'selectedId' => $conceptoId,
                                ])
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="montos[]" class="form-control monto" value="{{ old('montos.'.$idx) }}">
                    </td>
                    <td class="text-center align-middle cp-celda-aviso-concepto">
                        <span class="cp-aviso-concepto-cuenta text-muted" title=""></span>
                    </td>
                    <td>
                        <button type="button" class="btn-accion-tabla eliminar_concepto tooltipsC" title="Eliminar línea">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @elseif (($conceptos ?? collect())->isNotEmpty())
            @foreach ($conceptos as $renglon)
                <tr class="item-concepto">
                    <td>
                        <select name="concepto_ivacompra_ids[]" class="form-control concepto_ivacompra_id">
                            <option value="">-- Elija concepto --</option>
                            @foreach ($concepto_ivacompra_query as $concepto)
                                @include('compras.comprobante_proveedor.partials.option_concepto_ivacompra', [
                                    'concepto' => $concepto,
                                    'selectedId' => $renglon->concepto_ivacompra_id ?? 0,
                                ])
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="montos[]" class="form-control monto" value="{{ $renglon->monto }}">
                    </td>
                    <td class="text-center align-middle cp-celda-aviso-concepto">
                        <span class="cp-aviso-concepto-cuenta text-muted" title=""></span>
                    </td>
                    <td>
                        <button type="button" class="btn-accion-tabla eliminar_concepto tooltipsC" title="Eliminar línea">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
<div class="row">
    <div class="col-md-12">
        <button type="button" id="agrega_renglon_concepto" class="pull-right btn btn-danger">+ Agrega renglón</button>
    </div>
</div>
