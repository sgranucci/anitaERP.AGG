/* global carpetaBase */
var __chequeraConsultaCtx = null;
var __chequeraConsultaTimer = null;
var __chequeraConsultaIdx = -1;

function esTeclaF1Chequera(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function modalConsultaChequeraAbierto() {
    var $m = $('#consultachequeraModal');
    return $m.length && ($m.hasClass('show') || $m.is(':visible'));
}

function escapeHtmlChequera(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function badgeTipoChequera(fila) {
    var diferido = String(fila.tipocheque || '') === 'D';
    var cls = diferido ? 'badge-warning' : 'badge-primary';
    return '<span class="badge ' + cls + '">' + escapeHtmlChequera(fila.tipo_nombre || fila.tipo_corto || '') + '</span>';
}

function badgeEstadoChequera(fila) {
    var term = String(fila.estado || '') === 'T';
    var cls = term ? 'badge-secondary' : 'badge-success';
    return '<span class="badge ' + cls + '">' + escapeHtmlChequera(fila.estado_nombre || '') + '</span>';
}

function htmlFilasConsultaChequera(filas, selectedId) {
    filas = filas || [];
    if (!filas.length) {
        return '<tr><td colspan="8" class="text-muted">No hay chequeras para esta cuenta</td></tr>';
    }
    var html = '';
    filas.forEach(function (fila, i) {
        var sugerida = !!fila.preferida;
        var sel = String(fila.id) === String(selectedId || '');
        var cls = 'chequera-consulta-fila';
        if (sugerida) {
            cls += ' chequera-consulta-sugerida';
        }
        if (sel) {
            cls += ' chequera-consulta-activa';
        }
        var disp = fila.disponibles == null ? '—' : String(fila.disponibles);
        var ultimo = fila.ultimo_texto || '—';
        var btnAbm = fila.url_abm
            ? ' <a class="btn btn-info btn-sm" href="' + escapeHtmlChequera(fila.url_abm) + '" target="_blank" rel="noopener">Consultar</a>'
            : '';
        var hint = sugerida ? ' <span class="badge badge-info">Sugerida</span>' : '';
        html += '<tr class="' + cls + '" data-idx="' + i + '" data-id="' + escapeHtmlChequera(fila.id) + '"'
            + ' title="' + escapeHtmlChequera(fila.etiqueta_completa || fila.etiqueta || '') + '">';
        html += '<td>' + escapeHtmlChequera(fila.codigo) + hint + '</td>';
        html += '<td>' + badgeTipoChequera(fila) + '</td>';
        html += '<td>' + escapeHtmlChequera(fila.tipochequera_nombre || '') + '</td>';
        html += '<td>' + escapeHtmlChequera(fila.rango || '—') + '</td>';
        html += '<td>' + escapeHtmlChequera(ultimo) + '</td>';
        html += '<td>' + escapeHtmlChequera(disp) + '</td>';
        html += '<td>' + badgeEstadoChequera(fila) + '</td>';
        html += '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultachequera">Elegir</a>' + btnAbm + '</td>';
        html += '</tr>';
    });
    return html;
}

function filasChequeraEnModal() {
    return $('#datoschequera tr.chequera-consulta-fila');
}

function marcarFilaChequera(idx) {
    var $rows = filasChequeraEnModal();
    if (!$rows.length) {
        __chequeraConsultaIdx = -1;
        return;
    }
    if (idx < 0) {
        idx = 0;
    }
    if (idx >= $rows.length) {
        idx = $rows.length - 1;
    }
    __chequeraConsultaIdx = idx;
    $rows.removeClass('chequera-consulta-activa');
    $rows.eq(idx).addClass('chequera-consulta-activa');
}

function filaChequeraPorTr($tr) {
    var ctx = __chequeraConsultaCtx || {};
    var filas = ctx.filas || [];
    var idx = parseInt($tr.attr('data-idx') || '-1', 10);
    if (idx >= 0 && filas[idx]) {
        return filas[idx];
    }
    var id = parseInt($tr.attr('data-id') || '0', 10);
    return filas.find(function (f) { return parseInt(f.id, 10) === id; }) || null;
}

function elegirChequeraDelModal(fila) {
    var ctx = __chequeraConsultaCtx || {};
    $('#consultachequeraModal').modal('hide');
    if (typeof ctx.onElegir === 'function') {
        ctx.onElegir(fila || null);
    }
}

function elegirChequeraActivaDelModal() {
    var $row = $('#datoschequera tr.chequera-consulta-activa').first();
    if (!$row.length) {
        $row = filasChequeraEnModal().first();
    }
    if (!$row.length) {
        return false;
    }
    elegirChequeraDelModal(filaChequeraPorTr($row));
    return true;
}

function buscar_datos_chequera() {
    var ctx = __chequeraConsultaCtx || {};
    var cuentaId = parseInt(ctx.cuentacajaId || '0', 10);
    if (!(cuentaId > 0)) {
        $('#datoschequera').html('<tr><td colspan="8" class="text-danger">Indique primero la cuenta de tesorería</td></tr>');
        return;
    }
    $('#datoschequera').html('<tr><td colspan="8" class="text-muted">Buscando…</td></tr>');
    $.ajax({
        url: (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/caja/chequera/consulta',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                || ($('input[name="_token"]').first().val() || '')
        },
        data: {
            consulta: String($('#consultachequera').val() || '').trim(),
            cuentacaja_id: cuentaId,
            fecha_pago: ctx.fechaPago || '',
            fecha_emision: ctx.fechaEmision || '',
            incluir_terminadas: $('#consultachequeraTerminadas').is(':checked') ? 1 : 0
        }
    }).done(function (resp) {
        var filas = (resp && resp.data) ? resp.data : [];
        ctx.filas = filas;
        ctx.preferirDiferido = !!(resp && resp.preferir_diferido);
        var cuenta = (resp && resp.cuenta) || null;
        var sub = ctx.cuentaLabel || '';
        if (cuenta) {
            sub = String(cuenta.codigo || '') + ' · ' + String(cuenta.nombre || '');
        }
        if (ctx.preferirDiferido) {
            sub += ' · se sugiere cheque diferido (F. pago posterior)';
        } else {
            sub += ' · se sugiere cheque al día';
        }
        $('#consultachequeraSubtitulo').text(sub.replace(/^ · /, ''));
        $('#datoschequera').html(htmlFilasConsultaChequera(filas, ctx.selectedId));
        var idxSel = filas.findIndex(function (f) { return String(f.id) === String(ctx.selectedId || ''); });
        if (idxSel < 0) {
            idxSel = filas.findIndex(function (f) { return !!f.preferida; });
        }
        marcarFilaChequera(idxSel < 0 ? 0 : idxSel);
    }).fail(function () {
        $('#datoschequera').html('<tr><td colspan="8" class="text-danger">No se pudieron leer las chequeras</td></tr>');
    });
}

function abrirModalConsultaChequera(opts) {
    __chequeraConsultaCtx = opts || {};
    __chequeraConsultaIdx = -1;
    $('#consultachequera').val('');
    $('#consultachequeraTerminadas').prop('checked', false);
    $('#consultachequeraSubtitulo').text(__chequeraConsultaCtx.cuentaLabel || '');
    $('#consultachequeraModal').modal('show');
}

window.abrirModalConsultaChequera = abrirModalConsultaChequera;

$(document).on('keyup', '#consultachequera', function (e) {
    if (e.which === 13 || e.key === 'Enter') {
        return;
    }
    clearTimeout(__chequeraConsultaTimer);
    __chequeraConsultaTimer = setTimeout(buscar_datos_chequera, 180);
});

$(document).on('keydown', '#consultachequera', function (e) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var delta = e.key === 'ArrowDown' ? 1 : -1;
        marcarFilaChequera((__chequeraConsultaIdx < 0 ? 0 : __chequeraConsultaIdx) + delta);
        return;
    }
    if (e.which === 13 || e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        elegirChequeraActivaDelModal();
    }
});

$(document).on('submit', '#consultachequeraModal form', function (e) {
    e.preventDefault();
    elegirChequeraActivaDelModal();
});

$(document).on('click', '#datoschequera tr.chequera-consulta-fila', function () {
    marcarFilaChequera(parseInt($(this).attr('data-idx') || '0', 10));
});

$(document).on('dblclick', '#datoschequera tr.chequera-consulta-fila', function () {
    elegirChequeraDelModal(filaChequeraPorTr($(this)));
});

$(document).on('click', '.eligeconsultachequera', function (e) {
    e.preventDefault();
    e.stopPropagation();
    elegirChequeraDelModal(filaChequeraPorTr($(this).closest('tr')));
});

$(document).on('change', '#consultachequeraTerminadas', function () {
    buscar_datos_chequera();
});

$('#consultachequeraModal').on('shown.bs.modal', function () {
    $('#consultachequera').trigger('focus');
    buscar_datos_chequera();
});

$('#aceptaconsultachequeraModal').on('click', function () {
    elegirChequeraActivaDelModal();
});

$('#sinconsultachequeraModal').on('click', function () {
    elegirChequeraDelModal(null);
});
