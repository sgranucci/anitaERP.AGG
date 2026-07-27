<script type="text/template" id="template-linea-requisicion-sala">
<tr class="item-requisicion-sala-articulo">
    <td class="align-middle">
        <input type="hidden" class="requisicion_sala_articulo_id" name="requisicion_sala_articulo_ids[]" value="">
        <input type="hidden" class="articulo_id" name="articulo_ids[]" value="">
        <input type="hidden" class="articulo_lleva_npu" value="0">
        <div class="celda-articulo-ms-wrapper">
            <div class="celda-articulo-ms d-flex align-items-center flex-nowrap mb-0">
                <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0" style="padding:1px 4px;">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigoarticulo form-control form-control-sm flex-grow-1" value="" autocomplete="off" placeholder="SKU">
            </div>
        </div>
    </td>
    <td class="col-desc-celda align-middle">
        <input type="text" class="descripcionarticulo form-control form-control-sm" readonly value="">
        <small class="rs-leyenda-resumen text-info d-block text-truncate mt-1 d-none" title="">
            <i class="fa fa-comment-o" aria-hidden="true"></i> <span class="rs-leyenda-resumen-texto"></span>
        </small>
    </td>
    <td class="align-middle"><input type="number" step="0.0001" name="cantidades[]" class="form-control form-control-sm cantidad-linea" value="1"></td>
    <td class="align-middle">
        <select name="fueradeservicios[]" class="form-control form-control-sm fueradeservicio-linea">
            <option value="N" selected>N</option>
            <option value="S">S</option>
        </select>
    </td>
    <td class="align-middle">
        <input type="text" name="uids[]" class="form-control form-control-sm uid-linea" value="" maxlength="50" placeholder="Obligatorio si F/S = S">
    </td>
    <td class="align-middle"><input type="text" name="numeropartes[]" class="form-control form-control-sm numeroparte-linea" value=""></td>
    <td class="align-middle">
        <select name="destinos[]" class="form-control form-control-sm">
            @foreach ($destino_enum ?? [] as $d)
            <option value="{{ $d['valor'] }}">{{ $d['nombre'] }}</option>
            @endforeach
        </select>
    </td>
    <td class="align-middle text-center p-1">
        <button type="button"
            class="btn btn-sm btn-outline-secondary rs-toggle-leyenda"
            title="Agregar leyenda"
            aria-expanded="false"
            aria-label="Leyenda de la línea">
            <i class="fa fa-comment-o" aria-hidden="true"></i>
        </button>
    </td>
    <td class="align-middle text-center"><button type="button" class="btn-accion-tabla eliminar_linea_sala"><i class="fa fa-times-circle text-danger"></i></button></td>
</tr>
<tr class="item-requisicion-sala-leyenda d-none">
    <td colspan="9" class="rs-leyenda-celda px-2 py-2">
        <div class="rs-leyenda-panel">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="mb-0 small font-weight-bold text-secondary">
                    <i class="fa fa-comment-o mr-1" aria-hidden="true"></i> Leyenda de la línea
                </label>
                <span class="rs-leyenda-preview text-muted small text-truncate ml-2 d-none" title=""></span>
            </div>
            <textarea name="detalle_articulos[]"
                class="form-control form-control-sm rs-leyenda-linea"
                rows="2"
                maxlength="2000"
                placeholder="Observaciones, detalle o nota específica de este ítem…"></textarea>
        </div>
    </td>
</tr>
</script>
