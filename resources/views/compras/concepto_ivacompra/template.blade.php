<template id="template-renglon-condicioniva">
    <tr class="item-condicioniva">
        <td>
            <input type="text" name="condicioniva[]" class="form-control iicondicioniva" readonly value="1" />
        </td>
        <td>
            <select name="condicioniva_ids[]" id="condicioniva_ids" data-placeholder="Condición de iva" class="form-control condicioniva_id" data-fouc>
                <option value="">-- Elija condición de iva --</option>
                @foreach ($condicioniva_query as $condicioniva)
                    <option value="{{ $condicioniva->id }}">{{ $condicioniva->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <button type="button" style="width: 7%;" title="Elimina esta linea" class="btn-accion-tabla eliminar_condicioniva tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>