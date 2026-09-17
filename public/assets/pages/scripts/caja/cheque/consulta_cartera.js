/* global carpetaBase */
var __chequeCarteraCtx = null;
var __chequeCarteraTimer = null;
var __chequeCarteraIdx = -1;
/** Si true, al terminar la búsqueda con exactamente 1 fila se acepta sola. */
var __chequeCarteraAceptarSiUnico = false;
/** Última consulta ya aplicada en la grilla (para no aceptar lista stale). */
var __chequeCarteraConsultaAplicada = null;
var __chequeCarteraReqSeq = 0;

function esTeclaF1ChequeCartera(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function esTeclaEnterChequeCartera(e) {
    return e && (e.key === 'Enter' || e.keyCode === 13 || e.which === 13);
}

function modalConsultaChequeCarteraAbierto() {
    var $m = $('#consultachequecarteraModal');
    return $m.length && ($m.hasClass('show') || $m.is(':visible'));
}

function escapeHtmlChequeCartera(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatoMontoChequeCartera(n) {
    var v = parseFloat(n);
    if (isNaN(v)) {
        return '';
    }
    return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function htmlFilasConsultaChequeCartera(filas) {
    filas = filas || [];
    if (!filas.length) {
        return '<tr><td colspan="9" class="text-muted">No hay cheques en cartera con ese criterio</td></tr>';
    }
    var html = '';
    filas.forEach(function (fila, i) {
        var btnAbm = fila.url_abm
            ? ' <a class="btn btn-info btn-sm" href="' + escapeHtmlChequeCartera(fila.url_abm) + '" target="_blank" rel="noopener">Consultar</a>'
            : '';
        html += '<tr class="cheque-cartera-fila" data-idx="' + i + '" data-id="' + escapeHtmlChequeCartera(fila.id) + '">';
        html += '<td>' + escapeHtmlChequeCartera(fila.nro_interno_anita || '—') + '</td>';
        html += '<td>' + escapeHtmlChequeCartera(fila.numerocheque || '') + '</td>';
        html += '<td>' + escapeHtmlChequeCartera(fila.fechapago || '') + '</td>';
        html += '<td>' + escapeHtmlChequeCartera((fila.banco_codigo ? fila.banco_codigo + ' · ' : '') + (fila.banco_nombre || '')) + '</td>';
        html += '<td>' + escapeHtmlChequeCartera(fila.cliente_nombre || fila.entregado || '') + '</td>';
        html += '<td class="text-right">' + escapeHtmlChequeCartera(formatoMontoChequeCartera(fila.monto)) + '</td>';
        html += '<td>' + escapeHtmlChequeCartera(fila.moneda_abrev || '') + '</td>';
        html += '<td>' + escapeHtmlChequeCartera(fila.empresa_nombre || '') + '</td>';
        html += '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultachequecartera">Elegir</a>' + btnAbm + '</td>';
        html += '</tr>';
    });
    return html;
}

function filasChequeCarteraEnModal() {
    return $('#datoschequecartera tr.cheque-cartera-fila');
}

function marcarFilaChequeCartera(idx) {
    var $rows = filasChequeCarteraEnModal();
    if (!$rows.length) {
        __chequeCarteraIdx = -1;
        return;
    }
    if (idx < 0) {
        idx = 0;
    }
    if (idx >= $rows.length) {
        idx = $rows.length - 1;
    }
    __chequeCarteraIdx = idx;
    $rows.removeClass('cheque-cartera-activa');
    $rows.eq(idx).addClass('cheque-cartera-activa');
}

function filaChequeCarteraPorTr($tr) {
    var ctx = __chequeCarteraCtx || {};
    var filas = ctx.filas || [];
    var idx = parseInt($tr.attr('data-idx') || '-1', 10);
    if (idx >= 0 && filas[idx]) {
        return filas[idx];
    }
    var id = parseInt($tr.attr('data-id') || '0', 10);
    return filas.find(function (f) { return parseInt(f.id, 10) === id; }) || null;
}

function elegirChequeCarteraDelModal(fila) {
    var ctx = __chequeCarteraCtx || {};
    __chequeCarteraAceptarSiUnico = false;
    $('#consultachequecarteraModal').modal('hide');
    if (typeof ctx.onElegir === 'function') {
        ctx.onElegir(fila || null);
    }
}

function elegirChequeCarteraActivaDelModal() {
    var $row = $('#datoschequecartera tr.cheque-cartera-activa').first();
    if (!$row.length) {
        $row = filasChequeCarteraEnModal().first();
    }
    if (!$row.length) {
        return false;
    }
    var fila = filaChequeCarteraPorTr($row);
    if (!fila) {
        return false;
    }
    elegirChequeCarteraDelModal(fila);
    return true;
}

/**
 * Enter: si la grilla ya refleja la consulta y hay filas → acepta activa/primera;
 * si hay exactamente 1 fila → acepta aunque el debounce aún no marcó la consulta;
 * si no → busca ya y acepta solo cuando queda una.
 */
function enterEnBuscadorChequeCartera() {
    clearTimeout(__chequeCarteraTimer);
    var consulta = String($('#consultachequecartera').val() || '').trim();
    var n = filasChequeCarteraEnModal().length;

    if (n === 1) {
        elegirChequeCarteraActivaDelModal();
        return;
    }
    if (n > 1 && __chequeCarteraConsultaAplicada === consulta) {
        elegirChequeCarteraActivaDelModal();
        return;
    }

    __chequeCarteraAceptarSiUnico = true;
    buscar_datos_cheque_cartera();
}

function buscar_datos_cheque_cartera() {
    var ctx = __chequeCarteraCtx;
    if (!ctx) {
        ctx = {};
        __chequeCarteraCtx = ctx;
    }
    var aceptarSiUnico = __chequeCarteraAceptarSiUnico;
    __chequeCarteraAceptarSiUnico = false;
    var consulta = String($('#consultachequecartera').val() || '').trim();
    var seq = ++__chequeCarteraReqSeq;

    $('#datoschequecartera').html('<tr><td colspan="9" class="text-muted">Buscando…</td></tr>');
    var empresaId = ctx.empresaId;
    if (!(parseInt(empresaId || '0', 10) > 0) && typeof $ !== 'undefined') {
        empresaId = $('#empresa_id').val() || '';
    }
    $.ajax({
        url: (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/caja/cheque/consulta-cartera',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                || ($('input[name="_token"]').first().val() || '')
        },
        data: {
            consulta: consulta,
            empresa_id: empresaId || '',
            limite: ctx.limite || 80
        }
    }).done(function (resp) {
        if (seq !== __chequeCarteraReqSeq) {
            return;
        }
        var filas = (resp && resp.data) ? resp.data : [];
        ctx.filas = filas;
        __chequeCarteraConsultaAplicada = consulta;
        $('#datoschequecartera').html(htmlFilasConsultaChequeCartera(filas));
        marcarFilaChequeCartera(0);
        if (aceptarSiUnico && filas.length === 1) {
            elegirChequeCarteraActivaDelModal();
        }
    }).fail(function () {
        if (seq !== __chequeCarteraReqSeq) {
            return;
        }
        __chequeCarteraConsultaAplicada = null;
        $('#datoschequecartera').html('<tr><td colspan="9" class="text-danger">No se pudo consultar la cartera</td></tr>');
    });
}

function abrirModalConsultaChequeCartera(opts) {
    __chequeCarteraCtx = opts || {};
    __chequeCarteraIdx = -1;
    __chequeCarteraAceptarSiUnico = false;
    __chequeCarteraConsultaAplicada = null;
    $('#consultachequecartera').val(__chequeCarteraCtx.consultaInicial || '');
    var sub = 'Valores disponibles para entregar / endosar';
    if (__chequeCarteraCtx.subtitulo) {
        sub = __chequeCarteraCtx.subtitulo;
    }
    $('#consultachequecarteraSubtitulo').text(sub);
    $('#consultachequecarteraModal').modal('show');
}

window.abrirModalConsultaChequeCartera = abrirModalConsultaChequeCartera;
window.aplicarChequeCarteraAFila = aplicarChequeCarteraAFila;

function aplicarChequeCarteraAFila($tr, fila) {
    if (!$tr || !$tr.length || !fila) {
        return;
    }
    $tr.find('.cheque_recibido_id').val(fila.id || '');
    $tr.find('.nro_interno_anita_recibido').val(fila.nro_interno_anita || '');
    $tr.find('.banco_recibido_id').val(fila.banco_id || '');
    $tr.find('.codigobanco_recibido').val(fila.banco_codigo || '');
    $tr.find('.nombrebanco_recibido').val(fila.banco_nombre || '');
    $tr.find('.numerocheque_recibido').val(fila.numerocheque || '');
    $tr.find('.fechapago_recibido').val(fila.fechapago || '');
    $tr.find('.sucursalpago_recibido').val(fila.sucursalpago || '');
    $tr.find('.cuentalibradora_recibido').val(fila.cuentalibradora || '');
    if (fila.moneda_id) {
        $tr.find('.monedacheque_recibido_id').val(String(fila.moneda_id));
    }
    $tr.find('.montocheque_recibido').val(fila.monto != null ? fila.monto : '');
    $tr.find('.cotizacioncheque_recibido').val(fila.cotizacion != null ? fila.cotizacion : '1');
    $tr.addClass('cheque-desde-cartera');
    if (typeof sumaMonto === 'function') {
        sumaMonto();
    }
    if (typeof flModificaAsiento !== 'undefined') {
        flModificaAsiento = true;
    }
}

function manejarTecladoModalChequeCartera(e) {
    if (!modalConsultaChequeCarteraAbierto()) {
        return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        var tag = (e.target && e.target.tagName) ? String(e.target.tagName).toLowerCase() : '';
        if (tag === 'textarea') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        var delta = e.key === 'ArrowDown' ? 1 : -1;
        marcarFilaChequeCartera((__chequeCarteraIdx < 0 ? 0 : __chequeCarteraIdx) + delta);
        return;
    }
    if (!esTeclaEnterChequeCartera(e)) {
        return;
    }
    // No interceptar Enter sobre el enlace Consultar (ABM).
    if (e.target && $(e.target).closest('a[href]').length && !$(e.target).closest('.eligeconsultachequecartera').length) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') {
        e.stopImmediatePropagation();
    }
    enterEnBuscadorChequeCartera();
}

if (!window.__chequeCarteraTecladoActivo) {
    window.__chequeCarteraTecladoActivo = true;
    document.addEventListener('keydown', manejarTecladoModalChequeCartera, true);
}

$(document).on('shown.bs.modal', '#consultachequecarteraModal', function () {
    $('#consultachequecartera').trigger('focus');
    buscar_datos_cheque_cartera();
});

$(document).on('keyup', '#consultachequecartera', function (e) {
    if (esTeclaEnterChequeCartera(e)) {
        return;
    }
    clearTimeout(__chequeCarteraTimer);
    __chequeCarteraAceptarSiUnico = false;
    __chequeCarteraTimer = setTimeout(buscar_datos_cheque_cartera, 180);
});

$(document).on('submit', '#consultachequecarteraModal form', function (e) {
    e.preventDefault();
    enterEnBuscadorChequeCartera();
    return false;
});

$(document).on('click', '#datoschequecartera tr.cheque-cartera-fila', function () {
    marcarFilaChequeCartera(parseInt($(this).attr('data-idx') || '0', 10));
    $('#consultachequecartera').trigger('focus');
});

$(document).on('click', '.eligeconsultachequecartera', function (e) {
    e.preventDefault();
    e.stopPropagation();
    elegirChequeCarteraDelModal(filaChequeCarteraPorTr($(this).closest('tr')));
});

$(document).on('dblclick', '#datoschequecartera tr.cheque-cartera-fila', function () {
    elegirChequeCarteraDelModal(filaChequeCarteraPorTr($(this)));
});

$(document).on('click', '#aceptaconsultachequecarteraModal', function () {
    elegirChequeCarteraActivaDelModal();
});
