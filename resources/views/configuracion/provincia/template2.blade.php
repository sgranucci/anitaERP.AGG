<template id="template-renglon-tasaiibb">
    <tr class="item-tasaiibb">
        <td>
            <select name="condicioniibb_ids[]" data-placeholder="condicioniibb" class="condicioniibb form-control" data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($condicioniibb_query as $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="tasas[]" min="0" max="100" step="0.01" value="" class="form-control tasa" placeholder="Tasa de percepción por defecto">
        </td>
        <td>
            <input type="number" name="minimonetos[]" step="0.01" value="" class="form-control minimoneto" placeholder="Mínimo neto sujeto a percepción">
        </td>
        <td>
            <input type="number" name="minimopercepciones[]" step="0.01" value="" class="form-control minimopercepcion" placeholder="Monto mínimo de percepción">
        </td>
        <td class="text-center">
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_tasaiibb tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
            <input type="hidden" name="creousuario_tasa_ids[]" class="form-control creousuario_tasa_id" value="{{ auth()->id() }}"/>
        </td>
    </tr>
</template>
