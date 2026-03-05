<template id="template-renglon-ordenventa-concepto">
    <tr class="item-ordenventa-concepto">
        <td>
            <input type="hidden" name="conceptos[]" class="form-control iiconcepto" readonly value="1" />
            <select name="concepto_ordenventa_ids[]" id="concepto_ordenventa_id" data-placeholder="Concepto" class="form-control required" data-fouc required>
                @foreach($concepto_ordenventa_query as $key => $value)
                    @if( (int) $value->id == (int) old('concepto_ordenventa_id', $concepto->concepto_ordenventa_id ?? ''))
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                    @endif
                @endforeach
            </select>            
        </td>							
        <td>
            <textarea id="detalle" name="detalleconceptos[]" class="form-control required" rows="3" required placeholder="Detalle ..."></textarea>
        </td>
        <td>
            <input type="number" name="cantidadconceptos[]" class="form-control cantidadconcepto" value="">
        </td>
        <td>
            <input type="number" name="montoconceptos[]" class="form-control montoconcepto" value="">
        </td>        
        <td>
            <button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_ordenventa_concepto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>