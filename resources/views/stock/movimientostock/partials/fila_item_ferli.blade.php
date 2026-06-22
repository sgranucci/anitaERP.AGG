        <td class="align-middle">
            <select name="combinaciones_id[]" data-placeholder="Combinaciones" class="form-control form-control-sm combinacion" data-fouc></select>
            <input type="hidden" class="combinacion_id_previa" name="combinacion_id_previa[]" value="{{ $combinacionIdPrev ?? '' }}">
            <input type="hidden" class="desc_combinacion" name="desc_combinacion[]" value="{{ $descCombinacion ?? '' }}">
        </td>
        <td class="align-middle">
            <select name="modulos_id[]" data-placeholder="Modulos" class="form-control form-control-sm modulo" data-fouc></select>
            <input type="hidden" class="modulo_id_previa" name="modulo_id_previa[]" value="{{ $moduloIdPrev ?? '' }}">
            <input type="hidden" class="desc_modulo" name="desc_modulo[]" value="{{ $descModulo ?? '' }}">
        </td>
        <td class="align-middle">
            <input type="text" name="cantidades[]" class="form-control form-control-sm cantidad text-right" readonly value="{{ $cantidad ?? '' }}" />
            <input type="hidden" name="cajas[]" class="caja" value="0">
            <input type="hidden" name="piezas[]" class="pieza" value="0">
        </td>
        <td class="align-middle">
            <input type="text" style="text-align: right;" name="precios[]" class="form-control form-control-sm precio" readonly value="{{ $precio ?? '' }}" />
        </td>
        <td class="align-middle text-center">
            <input name="checkssinfiltro[]" class="checkSinFiltro" title="Todos los art&iacute;culos" type="checkbox" autocomplete="off">
        </td>
        <td class="align-middle text-center">
            <input name="checkscomb[]" class="checkCombinacion" title="Todas las combinaciones" type="checkbox" autocomplete="off">
        </td>
