$(function () {
    $('#cv-agrega_renglon_cuentacontable').on('click', agregaRenglonCuentacontableConceptoVenta);
    $(document).on('click', '.eliminar_cv_cuentacontable', borraRenglonCuentacontableConceptoVenta);
    $('#cv-agrega_renglon_precio').on('click', agregaRenglonPrecioConceptoVenta);
    $(document).on('click', '.eliminar_cv_precio', borraRenglonPrecioConceptoVenta);
    $('#cv-agrega_renglon_tag').on('click', agregaRenglonTagConceptoVenta);
    $(document).on('click', '.eliminar_cv_tag', borraRenglonTagConceptoVenta);
    $(document).on('change', '.cv-tag-obligatorio', sincronizarCheckboxObligatorioTag);
    $('#cv-detectar-tags-plantilla').on('click', detectarTagsPlantillaConceptoVenta);
    $('#cv-insertar-tag-descripcion').on('click', insertarTagEnDescripcionConceptoVenta);
    $('#tbody-cv-tag-table .cv-tag-obligatorio').each(function () {
        sincronizarCheckboxObligatorioTag.call(this);
    });
    $('#form-general').on('submit', function () {
        $('#tbody-cv-tag-table .cv-tag-obligatorio').each(function () {
            sincronizarCheckboxObligatorioTag.call(this);
        });
    });
    activa_eventos_concepto_venta(true);
});

function activa_eventos_concepto_venta(flInicio) {
    if (!flInicio) {
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
    }
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
    if (typeof activa_eventos_consultacentrocosto === 'function') {
        activa_eventos_consultacentrocosto();
    }
}

function agregaRenglonCuentacontableConceptoVenta(event) {
    event.preventDefault();
    var renglon = $('#cv-template-renglon-cuentacontable').html();
    $('#tbody-cv-cuentacontable-table').append(renglon);
    actualizaRenglonesCuentacontableConceptoVenta();
    activa_eventos_concepto_venta(false);
}

function borraRenglonCuentacontableConceptoVenta(event) {
    event.preventDefault();
    $(this).parents('tr').remove();
    actualizaRenglonesCuentacontableConceptoVenta();
}

function actualizaRenglonesCuentacontableConceptoVenta() {
    var item = 1;
    $('#tbody-cv-cuentacontable-table .cv-iicuenta').each(function () {
        $(this).val(item++);
    });
}

function agregaRenglonPrecioConceptoVenta(event) {
    event.preventDefault();
    var renglon = $('#cv-template-renglon-precio').html();
    $('#tbody-cv-precio-table').append(renglon);
}

function borraRenglonPrecioConceptoVenta(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}

function agregaRenglonTagConceptoVenta(event) {
    if (event) {
        event.preventDefault();
    }
    var orden = $('#tbody-cv-tag-table tr.item-cv-tag').length + 1;
    var $renglon = $($('#cv-template-renglon-tag').html());
    $renglon.find('input[name="tag_ordenes[]"]').val(orden);
    $('#tbody-cv-tag-table').append($renglon);
    return $renglon;
}

function borraRenglonTagConceptoVenta(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}

function sincronizarCheckboxObligatorioTag() {
    var $cb = $(this);
    var $hidden = $cb.closest('td').find('.cv-tag-obligatorio-hidden');
    $hidden.val($cb.is(':checked') ? '1' : '0');
}

function clavesTagEnGrilla() {
    var map = {};
    $('#tbody-cv-tag-table .cv-tag-clave').each(function () {
        var c = normalizarClaveTagConcepto($(this).val());
        if (c) {
            map[c] = true;
        }
    });
    return map;
}

function normalizarClaveTagConcepto(valor) {
    return String(valor || '')
        .toLowerCase()
        .replace(/[^a-z0-9_]/g, '')
        .substring(0, 40);
}

function extraerClavesTagDePlantilla(texto) {
    var re = /@([a-z][a-z0-9_]{0,39})@/g;
    var out = [];
    var m;
    var vistos = {};
    while ((m = re.exec(String(texto || ''))) !== null) {
        var c = normalizarClaveTagConcepto(m[1]);
        if (c && !vistos[c]) {
            vistos[c] = true;
            out.push(c);
        }
    }
    return out;
}

function detectarTagsPlantillaConceptoVenta(event) {
    event.preventDefault();
    var claves = extraerClavesTagDePlantilla($('#descripcion').val());
    if (!claves.length) {
        alert('La descripción no tiene tags con formato @clave@.');
        return;
    }
    var existentes = clavesTagEnGrilla();
    var agregados = 0;
    claves.forEach(function (clave) {
        if (existentes[clave]) {
            return;
        }
        var $tr = agregaRenglonTagConceptoVenta(null);
        $tr.find('.cv-tag-clave').val(clave);
        $tr.find('input[name="tag_etiquetas[]"]').val(clave.replace(/_/g, ' '));
        $tr.find('.cv-tag-obligatorio').prop('checked', true);
        sincronizarCheckboxObligatorioTag.call($tr.find('.cv-tag-obligatorio')[0]);
        existentes[clave] = true;
        agregados++;
    });
    if (agregados === 0) {
        alert('Todos los tags de la plantilla ya están en la grilla.');
    }
}

function insertarTagEnDescripcionConceptoVenta(event) {
    event.preventDefault();
    var clave = normalizarClaveTagConcepto(window.prompt('Clave del tag (sin @):', 'periodo') || '');
    if (!clave || !/^[a-z][a-z0-9_]{0,39}$/.test(clave)) {
        if (clave) {
            alert('Clave inválida. Use letra inicial y solo a-z, 0-9, _.');
        }
        return;
    }
    var $desc = $('#descripcion');
    var el = $desc[0];
    var tag = '@' + clave + '@';
    if (el && typeof el.selectionStart === 'number') {
        var start = el.selectionStart;
        var end = el.selectionEnd;
        var val = $desc.val() || '';
        $desc.val(val.slice(0, start) + tag + val.slice(end));
        el.focus();
        el.setSelectionRange(start + tag.length, start + tag.length);
    } else {
        $desc.val(($desc.val() || '') + tag);
    }
    var existentes = clavesTagEnGrilla();
    if (!existentes[clave]) {
        var $tr = agregaRenglonTagConceptoVenta(null);
        $tr.find('.cv-tag-clave').val(clave);
        $tr.find('input[name="tag_etiquetas[]"]').val(clave.replace(/_/g, ' '));
        sincronizarCheckboxObligatorioTag.call($tr.find('.cv-tag-obligatorio')[0]);
    }
}
