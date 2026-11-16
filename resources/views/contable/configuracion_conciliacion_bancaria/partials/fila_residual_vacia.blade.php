<tr>
    <td>
        <input type="hidden" name="residual[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][clave]" value="" placeholder="interes" autocomplete="off" maxlength="40">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][descripcion]" value="" placeholder="Intereses bancarios" autocomplete="off" maxlength="120">
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][patrones]" value="" placeholder="INTERES|INT." autocomplete="off" maxlength="500"
            title="Patrones separados por |">
    </td>
    <td>
        <select class="form-control form-control-sm" name="residual[{{ $i }}][dh]">
            <option value="D" selected>D</option>
            <option value="H">H</option>
        </select>
    </td>
    <td>
        <div class="d-flex align-items-center" style="gap:4px;">
            <input type="text" class="form-control form-control-sm js-doc-cuenta-codigo"
                name="residual[{{ $i }}][cuenta_codigo]" value="" placeholder="532050000" autocomplete="off"
                title="Código del plan. F1 o lupa = consulta">
            <button type="button" class="btn-accion-tabla js-consulta-cuenta-doc" title="Consultar cuenta (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
        </div>
    </td>
    <td>
        <div class="d-flex align-items-center" style="gap:4px;">
            <input type="text" class="form-control form-control-sm js-doc-concepto-codigo"
                name="residual[{{ $i }}][concepto_ivacompra_codigo]" value="" placeholder="150" autocomplete="off"
                title="Código concepto IVA. F1 o lupa = catálogo">
            <button type="button" class="btn-accion-tabla js-consulta-concepto-doc" title="Consultar concepto IVA (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
        </div>
    </td>
    <td>
        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][tipo_documento]" value="EGR" placeholder="EGR" autocomplete="off" maxlength="10">
    </td>
    <td class="text-center align-middle">
        <input type="hidden" name="residual[{{ $i }}][activo]" value="0">
        <input type="checkbox" name="residual[{{ $i }}][activo]" value="1" checked>
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
