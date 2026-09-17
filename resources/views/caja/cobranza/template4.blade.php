<template id="template-renglon-retencion">
    <tr class="item-cobranza-retencion">
        <td>
            <select name="retencion_cobranza_ids[]" data-placeholder="Retenci&oacute;n" class="retencion_cobranza_id form-control required" required data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($retencion_cobranza_query as $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" class="comprobanteretencion form-control" name="comprobante_retenciones[]" value="">
        </td>
        <td>
            <input type="text" class="tasaretencion form-control text-right" name="tasa_retenciones[]" value="">
        </td>
        <td>
            <select name="moneda_retencion_ids[]" data-placeholder="Moneda" class="monedaretencion_id form-control required" required data-fouc>
                @foreach($moneda_query as $value)
                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="monto_retenciones[]" class="form-control montoretencion text-right" min="0" value="">
        </td>
        <td>
            <input type="number" name="cotizacion_retenciones[]" class="form-control cotizacionretencion text-right" value="">
        </td>
        <td class="text-nowrap">
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_retencion tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
