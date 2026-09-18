<template id="template-renglon-cuenta">
    <tr class="item-cuenta">
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                <input type="hidden" name="cuentacaja[]" class="form-control iicuenta" readonly value="1" />
                <input type="hidden" class="cuentacaja_id" name="cuentacaja_ids[]" value="" >
                <input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="" >
                <button type="button" title="Consulta cuentas (F1)" class="btn-accion-tabla consultacuentacaja tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigo form-control" name="codigos[]" value=""
                       placeholder="C&oacute;d." title="F1 consulta / Enter resuelve"
                       style="width:5.5rem;flex-shrink:0;" autocomplete="off">
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
            </div>
        </td>
        <td>
            <input type="text" class="nombre form-control" name="nombres[]" value="" readonly placeholder="Descripci&oacute;n">
        </td>
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control" readonly data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $value)
                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="montos[]" class="form-control monto text-right" value="">
        </td>
        <td>
            <input type="number" name="cotizaciones[]" class="form-control cotizacion text-right" value="0">
        </td>
        <td>
            <input type="text" name="observaciones[]" class="form-control observacion" value="">
        </td>
        <td class="text-nowrap">
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
