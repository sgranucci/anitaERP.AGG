<template id="template-renglon-cotizacion">
    <tr class="item-cotizacion">
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control" required data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="cotizacionventas[]" class="form-control cotizacionventa" value="0">
        </td>
        <td>
            <input type="text" name="cotizacioncompras[]" class="form-control cotizacioncompra" value="0">
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cotizacion tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>