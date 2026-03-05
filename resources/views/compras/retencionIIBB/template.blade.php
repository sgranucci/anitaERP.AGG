<template id="template-renglon">
        <tr class="item-cuota">
            <td>
                <input type="number" id="icuota" name="cuotas[]" class="form-control iicuota" readonly value="1" />
            </td>
            <td>
                <select name="condicionIIBB_ids[]" data-placeholder="Condiciones de IIBB" class="form-control condicionIIBB" data-fouc>
                        <option value="">-- Seleccionar Condición de IIBB --</option>
                        @foreach ($condicionIIBB_query as $condicionIIBB)
                            <option value="{{ $condicionIIBB->id }}">{{ $condicionIIBB->nombre }}</option>
                        @endforeach
                </select>
			</td>           
            <td>
                <input type="number" id="minimoretencion" name="minimoretenciones[]" class="form-control minimoretencion" value="0"/>
            </td>
            <td>
                <input type="number" id="minimoimponibl" name="minimoimponibles[]" class="form-control minimoimponible" value="0" />
            </td>
            <td>
                <input type="number" id="porcentajeretencion" name="porcentajeretenciones[]" class="form-control porcentajeretencion" value="0" />
            </td>
            <td>
                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                    <i class="fa fa-times-circle text-danger"></i>
                </button>
            </td>
        </tr>
</template>