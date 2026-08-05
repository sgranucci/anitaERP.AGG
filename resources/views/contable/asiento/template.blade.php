<template id="template-renglon-cuenta">
    <tr class="item-cuenta">
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;" id="cuenta">
                <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="1" />
                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="" >
                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="" >
                <button type="button" title="Consulta cuentas contables (F1)" style="padding:1; flex: 0 0 auto;"
                        class="btn-accion-tabla consultacuentacontable tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if (can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false))
                    <a href="#"
                       target="_blank" rel="noopener"
                       class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 d-none"
                       title="Consultar cuenta contable en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" style="flex: 0 0 100px; width: 100px; height: 38px;"
                       class="codigocuentacontable form-control" name="codigos[]" value=""
                       placeholder="C&oacute;d." autocomplete="off">
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
            </div>
        </td>
        <td>
            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]"
                   value="" readonly placeholder="Descripci&oacute;n">
        </td>
        <td>
            <select name="centrocosto_ids[]" data-placeholder="Centro de costo" class="centrocosto form-control" data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($centrocosto_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control" required data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td class="asiento-monto-celda">
            <input type="text" inputmode="decimal" name="debes[]" class="form-control text-right debe" value="">
        </td>
        <td class="asiento-monto-celda">
            <input type="text" inputmode="decimal" name="haberes[]" class="form-control text-right haber" value="">
        </td>
        <td>
            <input type="text" inputmode="decimal" name="cotizaciones[]" class="form-control text-right cotizacion" value="">
        </td>
        <td class="asiento-detalle-celda">
            <textarea name="observaciones[]" class="d-none asiento-ta-detalle observacion" aria-hidden="true"></textarea>
            <div class="d-flex align-items-center" style="gap: 4px;">
                <button type="button" title="Agregar detalle de la línea" class="btn btn-sm btn-outline-secondary asiento-abrir-detalle">
                    <i class="fa fa-align-left"></i>
                </button>
                <span class="asiento-detalle-preview is-empty" title="">—</span>
            </div>
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
