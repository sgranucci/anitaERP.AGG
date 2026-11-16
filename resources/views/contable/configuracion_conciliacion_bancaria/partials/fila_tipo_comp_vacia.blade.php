<tr>
    <td>
        <input type="hidden" name="tipo_comp[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="tipo_comp[{{ $i }}][tipo_comp]" value="" placeholder="OPP" autocomplete="off">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="tipo_comp[{{ $i }}][patrones]" value="" placeholder="TRF.DATA|CH DEP|…" autocomplete="off">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
