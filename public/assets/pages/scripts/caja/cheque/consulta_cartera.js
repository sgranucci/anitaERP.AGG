/* global carpetaBase */
var __chequeCarteraCtx = null;
var __chequeCarteraTimer = null;
var __chequeCarteraIdx = -1;

function esTeclaF1ChequeCartera(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
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
    elegirChequeCarteraDelModal(filaChequeCarteraPorTr($row));
    return true;
}

function buscar_datos_cheque_cartera() {
    var ctx = __chequeCarteraCtx || {};
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
            consulta: String($('#consultachequecartera').val() || '').trim(),
            empresa_id: empresaId || '',
            limite: ctx.limite || 80
        }
    }).done(function (resp) {
        var filas = (resp && resp.data) ? resp.data : [];
        ctx.filas = filas;
        $('#datoschequecartera').html(htmlFilasConsultaChequeCartera(filas));
        marcarFilaChequeCartera(0);
    }).fail(function () {
        $('#datoschequecartera').html('<tr><td colspan="9" class="text-danger">No se pudo consultar la cartera</td></tr>');
    });
}

function abrirModalConsultaChequeCartera(opts) {
    __chequeCarteraCtx = opts || {};
    __chequeCarteraIdx = -1;
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

$(document).on('shown.bs.modal', '#consultachequecarteraModal', function () {
    $('#consultachequecartera').trigger('focus');
    buscar_datos_cheque_cartera();
});

$(document).on('keyup', '#consultachequecartera', function (e) {
    if (e.which === 13 || e.key === 'Enter') {
        return;
    }
    clearTimeout(__chequeCarteraTimer);
    __chequeCarteraTimer = setTimeout(buscar_datos_cheque_cartera, 180);
});

$(document).on('keydown', '#consultachequecartera', function (e) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var delta = e.key === 'ArrowDown' ? 1 : -1;
        marcarFilaChequeCartera((__chequeCarteraIdx < 0 ? 0 : __chequeCarteraIdx) + delta);
        return;
    }
    if (e.which === 13 || e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        elegirChequeCarteraActivaDelModal();
    }
});

$(document).on('click', '.eligeconsultachequecartera', function (e) {
    e.preventDefault();
    elegirChequeCarteraDelModal(filaChequeCarteraPorTr($(this).closest('tr')));
});

$(document).on('dblclick', '#datoschequecartera tr.cheque-cartera-fila', function () {
    elegirChequeCarteraDelModal(filaChequeCarteraPorTr($(this)));
});

$(document).on('click', '#aceptaconsultachequecarteraModal', function () {
    elegirChequeCarteraActivaDelModal();
});
