<template id="template-renglon-cheque">
    <tr class="item-cobranza-cheque">
        <td>
            <input type="date" class="fechapago form-control" name="fechapagos[]" value="">
        </td>
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                <input type="hidden" class="banco_id" name="banco_ids[]" value="" >
                <input type="hidden" class="banco_id_previo" name="banco_id_previos[]" value="" >
                <button type="button" title="Consulta Bancos (F1)" class="btn-accion-tabla consultabanco tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigobanco form-control" name="codigobancos[]" value=""
                       placeholder="C&oacute;d." title="F1 consulta / Enter resuelve"
                       style="width:5.5rem;flex-shrink:0;" autocomplete="off">
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
                <input type="text" class="nombrebanco form-control text-truncate" name="nombrebancos[]" value="" readonly
                       placeholder="Banco" style="min-width:0;flex:1 1 auto;">
            </div>
        </td>
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap:2px;">
                <input type="text" class="numerocheque form-control" name="numerocheques[]" value=""
                       style="min-width:0;flex:1 1 auto;" autocomplete="off">
                <select name="negociables[]" class="negociable form-control form-control-sm flex-shrink-0"
                        style="width:3.1rem;padding:0.15rem 0;font-size:0.7rem;line-height:1.2;"
                        title="F&iacute;sico / e-cheq (Anita negociable)">
                    <option value="N" selected>F&iacute;s</option>
                    <option value="E">eCh</option>
                </select>
            </div>
        </td>
        <td>
            <input type="text" class="sucursalpago form-control" name="sucursalpagos[]" value="">
        </td>
        <td>
            <input type="text" class="cuentalibradora form-control" name="cuentalibradoras[]" value="">
        </td>
        <td>
            <select name="monedacheque_ids[]" data-placeholder="Moneda" class="monedacheque_id form-control required" required data-fouc>
                @foreach($moneda_query as $value)
                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="montocheques[]" class="form-control montocheque text-right" min="0" value="">
        </td>
        <td>
            <input type="number" name="cotizacioncheques[]" class="form-control cotizacioncheque text-right" value="0">
            <input type="hidden" name="cheque_ids[]" class="form-control cheque_id" value="">
        </td>
        <td class="text-nowrap">
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_cheque tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
