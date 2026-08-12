<template id="template-renglon-condicioniva">
    <tr class="item-condicioniva">
        <td>
            <input type="text" name="condicioniva[]" class="form-control form-control-sm iicondicioniva" readonly value="1" />
        </td>
        <td>
            <select name="condicioniva_ids[]" class="form-control form-control-sm condicioniva_id">
                <option value="">-- Elija condición de IVA --</option>
                @foreach ($condicioniva_query as $condicioniva)
                    <option value="{{ $condicioniva->id }}">{{ $condicioniva->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td class="text-center">
            <button type="button" title="Eliminar renglón" class="btn-accion-tabla eliminar_condicioniva tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
