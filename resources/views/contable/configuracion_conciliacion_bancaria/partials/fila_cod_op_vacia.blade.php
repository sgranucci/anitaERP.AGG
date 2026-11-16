<tr>
    <td>
        <input type="hidden" name="cod_op[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="cod_op[{{ $i }}][cod_op]" value="" placeholder="917" autocomplete="off">
    </td>
    <td>
        <select class="form-control form-control-sm" name="cod_op[{{ $i }}][codigo]">
            @foreach ($codigosGasto as $op)
                <option value="{{ $op['valor'] }}">{{ $op['etiqueta'] }}</option>
            @endforeach
        </select>
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
