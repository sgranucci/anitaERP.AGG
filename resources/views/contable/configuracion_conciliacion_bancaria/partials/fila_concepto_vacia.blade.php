<tr>
    <td>
        <input type="hidden" name="concepto[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="concepto[{{ $i }}][patron]" value="" placeholder="TRF.DATA" autocomplete="off">
    </td>
    <td>
        <select class="form-control form-control-sm" name="concepto[{{ $i }}][codigo]">
            @foreach ($codigosGasto as $op)
                <option value="{{ $op['valor'] }}">{{ $op['etiqueta'] }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="concepto[{{ $i }}][descripcion]" value="" maxlength="120" autocomplete="off">
    </td>
    <td class="text-center align-middle">
        <input type="hidden" name="concepto[{{ $i }}][es_acumulado]" value="0">
        <input type="checkbox" name="concepto[{{ $i }}][es_acumulado]" value="1">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
