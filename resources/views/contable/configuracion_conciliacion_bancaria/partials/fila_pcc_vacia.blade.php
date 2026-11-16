<tr>
    <td>
        <input type="hidden" name="pcc[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][pcc]" value="" placeholder="6 6 5" autocomplete="off">
    </td>
    <td>
        <select class="form-control form-control-sm" name="pcc[{{ $i }}][codigo]">
            @foreach ($codigosGasto as $op)
                <option value="{{ $op['valor'] }}">{{ $op['etiqueta'] }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][descripcion]" value="" maxlength="120" autocomplete="off">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][patrones_concepto]" value="" autocomplete="off">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][patrones_descripcion]" value="" autocomplete="off">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
