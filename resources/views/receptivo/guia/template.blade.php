<template id="template-renglon">
        <tr class="item-idioma">
            <td>
                <input type="number" id="iitem" name="items[]" class="form-control iiitem" readonly value="1" />
            </td>
            <td>
                <select name="idioma_ids[]" id="idioma_ids" data-placeholder="Idioma" class="form-control idioma" data-fouc>
                    <option value=""></option>
                    @foreach($idioma_query as $key => $value)
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endforeach
                </select>
            </td>
            <td>
                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                    <i class="fa fa-times-circle text-danger"></i>
                </button>
            </td>
        </tr>
</template>