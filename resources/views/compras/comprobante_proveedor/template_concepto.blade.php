<template id="template-renglon-concepto">
    <tr class="item-concepto">
        <td>
            <input type="hidden" name="concepto_ivacompra_ids[]" class="concepto_ivacompra_id" value="">
            <div class="d-flex flex-wrap align-items-center">
                <input type="text" class="form-control form-control-sm codigo_concepto_ivacompra mr-1"
                    value="" style="width:5.5rem;" autocomplete="off"
                    title="Código + Enter · F1 consulta" placeholder="Cód.">
                <input type="text" class="form-control form-control-sm nombre_concepto_ivacompra mr-1"
                    value="" readonly style="min-width:8rem;flex:1;" placeholder="Descripción">
                <button type="button" class="btn btn-outline-primary btn-sm consultaconcepto_ivacompra tooltipsC flex-shrink-0"
                    title="Consulta conceptos (F1)">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </td>
        <td>
            <input type="text" inputmode="decimal" name="montos[]" class="form-control form-control-sm monto js-monto-ar text-right" value="" />
        </td>
        <td class="text-center align-middle cp-celda-aviso-concepto">
            <span class="cp-aviso-concepto-cuenta text-muted" title=""></span>
        </td>
        <td class="text-center align-middle">
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_concepto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
