<template id="mv-template-renglon-cuenta">
    <tr class="item-cuenta-mv">
        <td>
            <div class="mv-cc-cuenta-wrap">
                <input type="hidden" class="cuentacaja_id" value="">
                <button type="button" title="Consulta cuentas de caja" class="btn-accion-tabla consultacuentacaja tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm mv-cc-codigo codigo" value="" placeholder="C&oacute;d." autocomplete="off">
                <input type="text" class="form-control form-control-sm mv-cc-nombre nombre" value="" placeholder="Descripci&oacute;n" readonly>
            </div>
        </td>
        <td class="mv-cc-moneda text-center font-weight-bold text-muted">—</td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm mv-cc-monto monto" value="" placeholder="0,00">
            <input type="hidden" class="cotizacion" value="1">
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla mv-quitar-renglon-cuenta" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
