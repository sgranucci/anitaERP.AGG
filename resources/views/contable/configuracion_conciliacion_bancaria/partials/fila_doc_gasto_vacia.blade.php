<tr>
    <td>
        <input type="hidden" name="doc_gasto[{{ $i }}][id]" value="">
        <input type="text" class="form-control form-control-sm" name="doc_gasto[{{ $i }}][codigo]" value="" placeholder="2" autocomplete="off">
    </td>
    <td>
        <div class="d-flex align-items-center" style="gap:4px;">
            <input type="text" class="form-control form-control-sm js-doc-cuenta-codigo"
                name="doc_gasto[{{ $i }}][cuenta_codigo]" value="" placeholder="532050000" autocomplete="off"
                title="Código del plan. F1 o lupa = consulta">
            <button type="button" class="btn-accion-tabla js-consulta-cuenta-doc" title="Consultar cuenta (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
        </div>
    </td>
    <td>
        <div class="d-flex align-items-center" style="gap:4px;">
            <input type="text" class="form-control form-control-sm js-doc-concepto-codigo"
                name="doc_gasto[{{ $i }}][concepto_ivacompra_codigo]" value="" placeholder="150" autocomplete="off"
                title="Código concepto IVA. F1 o lupa = catálogo">
            <button type="button" class="btn-accion-tabla js-consulta-concepto-doc" title="Consultar concepto IVA (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
        </div>
    </td>
    <td class="small text-muted align-middle"></td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
