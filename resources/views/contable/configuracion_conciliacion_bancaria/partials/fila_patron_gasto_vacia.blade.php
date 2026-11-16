<tr>
    <td>
        <input type="hidden" name="patron_gasto[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="patron_gasto[{{ $i }}][codigo]" value="" placeholder="7" autocomplete="off">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="patron_gasto[{{ $i }}][descripcion]" value="" maxlength="120" autocomplete="off">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="patron_gasto[{{ $i }}][patrones]" value="" autocomplete="off">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
