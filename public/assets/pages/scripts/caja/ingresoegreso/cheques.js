var cuentacajaxcodigoEmitido;
var cuentacajaxcodigoReemplazo;

function esTeclaF1ChequeEmitido(e) {
    return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
}

function esTeclaEnterChequeEmitido(e) {
    return e && (e.key === 'Enter' || e.keyCode === 13 || e.which === 13);
}

function enfocarCampoCheque(el) {
    if (!el) {
        return;
    }
    setTimeout(function () {
        el.focus();
        if (typeof el.select === 'function' && el.tagName === 'INPUT' && el.type !== 'hidden') {
            el.select();
        }
    }, 0);
}

function abrirConsultaCuentaChequeEmitido($tr) {
    if (!$('#empresa_id').val()) {
        alert('Debe ingresar empresa');
        return;
    }
    cuentacajaxcodigoEmitido = $tr;
    $('#consultacuentacaja').val('');
    $('#datoscuentacaja').html('');
    $('#consultacuentacajaModal').modal('show');
}

function chequeraDesdeLista(lista, idActual, preferirDiferido) {
    lista = lista || [];
    if (!lista.length) {
        return null;
    }
    var actual = lista.find(function (c) { return String(c.id) === String(idActual || ''); });
    if (actual) {
        return actual;
    }
    var preferidas = lista.filter(function (c) {
        return preferirDiferido ? String(c.tipocheque) === 'D' : String(c.tipocheque) !== 'D';
    });
    var pool = preferidas.length ? preferidas : lista;
    pool.sort(function (a, b) { return ((b.preferida ? 1 : 0) - (a.preferida ? 1 : 0)); });
    return pool[0] || null;
}

function pintarChequeraEmitido($tr, ch) {
    $tr.find('.chequera_emitido_id').val(ch && ch.id ? ch.id : '');
    $tr.find('.chequera_emitido_tipo').val(ch && ch.tipocheque ? ch.tipocheque : '');
    var etiqueta = ch ? (ch.etiqueta_completa || ch.etiqueta || '') : '';
    $tr.find('.chequera_emitido_lbl').val(etiqueta);
    $tr.find('.chequera_emitido_lbl').attr('title', etiqueta || 'F1 consulta chequera de la cuenta');
}

function filtrarChequerasChequeEmitido($tr, cuentacajaId, preferirDiferido, lista) {
    if (!$tr || !$tr.length || !$tr.find('.chequera_emitido_id').length) {
        return;
    }
    if (!(parseInt(cuentacajaId || '0', 10) > 0)) {
        $tr.data('pp-skip-chequera', 1);
        pintarChequeraEmitido($tr, null);
        $tr.removeData('chequeras');
        $tr.removeData('pp-skip-chequera');
        return;
    }
    if (lista && lista.length) {
        $tr.data('chequeras', lista);
    }
    lista = lista || $tr.data('chequeras') || [];
    if (!lista.length) {
        return;
    }
    var actual = $tr.find('.chequera_emitido_id').val();
    var ch = chequeraDesdeLista(lista, actual, preferirDiferido);
    $tr.data('pp-skip-chequera', 1);
    pintarChequeraEmitido($tr, ch);
    $tr.removeData('pp-skip-chequera');
}

function abrirConsultaChequeraEmitido($tr) {
    if (!$tr || !$tr.length) {
        return;
    }
    var cuentaId = parseInt($tr.find('.cuentacaja_emitido_id').val() || '0', 10);
    if (!(cuentaId > 0)) {
        alert('Primero indique la cuenta de tesorería');
        return;
    }
    if (typeof abrirModalConsultaChequera !== 'function') {
        alert('No está disponible la consulta de chequeras');
        return;
    }
    var codigo = String($tr.find('.codigo_emitido').val() || '').trim();
    var nombre = String($tr.find('.nombre_emitido').val() || '').trim();
    abrirModalConsultaChequera({
        cuentacajaId: cuentaId,
        cuentaLabel: (codigo + (nombre ? ' · ' + nombre : '')).trim(),
        fechaPago: $tr.find('.fechapago_emitido').val() || $('#fecha').val() || '',
        fechaEmision: $('#fecha').val() || '',
        selectedId: $tr.find('.chequera_emitido_id').val(),
        onElegir: function (ch) {
            aplicarChequeraDesdeConsulta($tr, ch);
        }
    });
}

function aplicarChequeraDesdeConsulta($tr, ch) {
    $tr.data('pp-skip-chequera', 1);
    pintarChequeraEmitido($tr, ch);
    $tr.removeData('pp-skip-chequera');
    if (typeof flModificaAsiento !== 'undefined') {
        flModificaAsiento = true;
    }
    if (parseInt($tr.find('.cuentacaja_emitido_id').val() || '0', 10) <= 0) {
        return;
    }
    var extras = { forzarNumero: true };
    var tipo = String((ch && ch.tipocheque) || '').toUpperCase();
    if (tipo === 'D' || tipo === 'N' || tipo === 'C') {
        extras.diferido = tipo === 'D' ? 1 : 0;
    }
    cargarEmisionChequeEmitido($tr, extras);
}

window.abrirConsultaChequeraEmitido = abrirConsultaChequeraEmitido;
window.pintarChequeraEmitido = pintarChequeraEmitido;

function aplicarCuentaChequeEmitido($tr, data, forzarNumero) {
    if (!$tr || !$tr.length || !data || !(parseInt(data.id, 10) > 0)) {
        return;
    }
    $tr.find('.cuentacaja_emitido_id').val(data.id);
    if (data.codigo != null) {
        $tr.find('.codigo_emitido').val(data.codigo);
    }
    $tr.find('.nombre_emitido').val(data.nombre || '');
    if (data.moneda_id) {
        $tr.find('.moneda_emitido_id').val(data.moneda_id);
    }
    $tr.find('.tctes_numero_emitido').val(data.tctes_numero || '');
    $tr.find('.tctes_clave_emitido').val(data.tctes_clave || '');
    filtrarChequerasChequeEmitido($tr, data.id, !!data.diferido, data.chequeras || []);
    var $nro = $tr.find('.numerocheque_emitido');
    var auto = $nro.data('auto') === 1 || !$nro.val();
    if (data.proximo_numero && (forzarNumero || auto)) {
        $nro.val(data.proximo_numero).data('auto', 1);
    }
    var titulo = 'Numerador Anita';
    if (data.tctes_clave) {
        titulo += ' ' + data.tctes_clave;
        if (data.tctes_desc) {
            titulo += ' — ' + data.tctes_desc;
        }
        if (data.tctes_numero) {
            titulo += ' (ref. ' + data.tctes_numero + ')';
        }
    }
    $nro.attr('title', titulo);
    if (data.aviso && !data.proximo_numero) {
        $nro.attr('title', data.aviso);
    }
    var lbl = '';
    if (data.tctes_clave) {
        lbl = data.tctes_clave;
        if (data.tctes_desc) {
            lbl += ' · ' + data.tctes_desc;
        }
        lbl += data.diferido ? ' (diferido)' : ' (al día)';
    } else if (data.aviso) {
        lbl = data.aviso;
    }
    $tr.find('.tctes_emitido_lbl').text(lbl);
    if (typeof flModificaAsiento !== 'undefined') {
        flModificaAsiento = true;
    }
}

function cargarEmisionChequeEmitido($tr, extras, onOk) {
    var empresaId = parseInt($('#empresa_id').val() || '0', 10);
    var cuentaId = parseInt($tr.find('.cuentacaja_emitido_id').val() || '0', 10);
    var codigo = String($tr.find('.codigo_emitido').val() || '').trim();
    extras = extras || {};
    var params = {
        empresa_id: empresaId,
        fecha_pago: $tr.find('.fechapago_emitido').val() || $('#fecha').val() || '',
        fecha_emision: $('#fecha').val() || ''
    };
    if (extras.diferido === 1 || extras.diferido === 0) {
        params.diferido = extras.diferido;
    }
    var url = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/caja/cuentacaja/api/cheque-emision';
    if (cuentaId > 0 && !extras.porCodigo) {
        params.cuentacaja_id = cuentaId;
    } else if (codigo) {
        url += '/' + encodeURIComponent(codigo);
    } else {
        if (typeof onOk === 'function') {
            onOk(false);
        }
        return;
    }
    $.getJSON(url, params)
        .done(function (data) {
            aplicarCuentaChequeEmitido($tr, data, !!extras.forzarNumero);
            if (typeof onOk === 'function') {
                onOk(true);
            }
        })
        .fail(function (xhr) {
            if (typeof avisoCuentaInexistente === 'function') {
                avisoCuentaInexistente(xhr);
            } else {
                alert((xhr && xhr.responseJSON && xhr.responseJSON.error) || 'No existe la cuenta de caja');
            }
            if (typeof onOk === 'function') {
                onOk(false);
            }
        });
}

window.aplicarCuentaChequeEmitido = aplicarCuentaChequeEmitido;
window.cargarEmisionChequeEmitido = cargarEmisionChequeEmitido;
window.abrirConsultaCuentaChequeEmitido = abrirConsultaCuentaChequeEmitido;

function activarTecladoChequeEmitido() {
    if (window.__chequeEmitidoTecladoActivo) {
        return;
    }
    window.__chequeEmitidoTecladoActivo = true;
    document.addEventListener('keydown', function (e) {
        var target = e.target;
        if (!target || !target.closest) {
            return;
        }
        var tabla = target.closest('#cheque-emitido-table');
        if (!tabla) {
            return;
        }
        var $tr = $(target).closest('tr.item-cheque-emitido');
        if (!$tr.length) {
            return;
        }
        if (esTeclaF1ChequeEmitido(e)) {
            if ($(target).hasClass('chequera_emitido_lbl')) {
                var $modalCh = $('#consultachequeraModal');
                if ($modalCh.length && ($modalCh.hasClass('show') || $modalCh.is(':visible'))) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                abrirConsultaChequeraEmitido($tr);
                return;
            }
            if (!$(target).hasClass('codigo_emitido') && !$(target).hasClass('nombre_emitido')) {
                return;
            }
            var $modal = $('#consultacuentacajaModal');
            if ($modal.length && ($modal.hasClass('show') || $modal.is(':visible'))) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            abrirConsultaCuentaChequeEmitido($tr);
            return;
        }
        if (!esTeclaEnterChequeEmitido(e)) {
            return;
        }
        if (document.querySelector('.modal.show')) {
            return;
        }
        if ($(target).hasClass('chequera_emitido_lbl')) {
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaChequeraEmitido($tr);
            return;
        }
        if (!$(target).is('.codigo_emitido, .numerocheque_emitido, .montocheque_emitido, .anombrede_emitido')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        if ($(target).hasClass('codigo_emitido')) {
            if (!String(target.value || '').trim()) {
                $tr.find('.cuentacaja_emitido_id, .nombre_emitido, .numerocheque_emitido, .tctes_numero_emitido, .tctes_clave_emitido').val('');
                $tr.find('.tctes_emitido_lbl').text('');
                filtrarChequerasChequeEmitido($tr, '', false, []);
                return;
            }
            cargarEmisionChequeEmitido($tr, { porCodigo: true, forzarNumero: true }, function (ok) {
                if (ok) {
                    enfocarCampoCheque($tr.find('.fechapago_emitido')[0] || $tr.find('.numerocheque_emitido')[0]);
                } else {
                    enfocarCampoCheque(target);
                }
            });
            return;
        }
        if ($(target).hasClass('numerocheque_emitido')) {
            enfocarCampoCheque($tr.find('.fechapago_emitido')[0]);
            return;
        }
        if ($(target).hasClass('anombrede_emitido')) {
            enfocarCampoCheque($tr.find('.montocheque_emitido')[0]);
            return;
        }
        if ($(target).hasClass('montocheque_emitido')) {
            if (typeof sumaMonto === 'function') {
                sumaMonto();
            }
            enfocarCampoCheque($tr.find('.cotizacioncheque_emitido')[0]);
        }
    }, true);
}

function activaEventosChequesIngresoEgreso() {
    $('#agrega_renglon_cheque_emitido').on('click', agregaRenglonChequeEmitido);
    $('#agrega_renglon_cheque_recibido').on('click', agregaRenglonChequeRecibido);
    $('#agrega_renglon_cheque_reemplazo').on('click', agregaRenglonChequeReemplazo);

    $(document).on('click', '.eliminar_cheque_emitido', borraRenglonChequeEmitido);
    $(document).on('click', '.eliminar_cheque_recibido', borraRenglonChequeRecibido);
    $(document).on('click', '.eliminar_cheque_reemplazo', borraRenglonChequeReemplazo);

    $(document).on('change', '.montocheque_emitido, .cotizacioncheque_emitido, .moneda_emitido_id', function () {
        if (typeof sumaMonto === 'function') sumaMonto();
        flModificaAsiento = true;
    });
    $(document).on('change', '.montocheque_recibido, .cotizacioncheque_recibido, .monedacheque_recibido_id', function () {
        if (typeof sumaMonto === 'function') sumaMonto();
        flModificaAsiento = true;
    });
    $(document).on('change', '.montocheque_reemplazo, .origen_reemplazo', function () {
        toggleBloqueReemplazo($(this).closest('tr'));
        if (typeof sumaMonto === 'function') sumaMonto();
        flModificaAsiento = true;
    });

    $(document).on('click', '.consultacuentacaja_emitido', function () {
        abrirConsultaCuentaChequeEmitido($(this).closest('tr'));
    });

    $(document).on('click', '.consultachequera_emitido, .chequera_emitido_lbl', function (e) {
        e.preventDefault();
        abrirConsultaChequeraEmitido($(this).closest('tr'));
    });

    $(document).on('input', '.numerocheque_emitido', function () {
        $(this).data('auto', 0);
    });

    $(document).on('change', '.fechapago_emitido', function () {
        var $tr = $(this).closest('tr');
        if (parseInt($tr.find('.cuentacaja_emitido_id').val() || '0', 10) > 0) {
            cargarEmisionChequeEmitido($tr, { forzarNumero: $tr.find('.numerocheque_emitido').data('auto') === 1 });
        }
    });

    $(document).on('change', '.chequera_emitido_id', function () {
        var $tr = $(this).closest('tr');
        if ($tr.data('pp-skip-chequera')) {
            return;
        }
        if (parseInt($tr.find('.cuentacaja_emitido_id').val() || '0', 10) <= 0) {
            return;
        }
        var tipo = String($tr.find('.chequera_emitido_tipo').val() || '').toUpperCase();
        var extras = { forzarNumero: true };
        if (tipo === 'D' || tipo === 'N' || tipo === 'C') {
            extras.diferido = tipo === 'D' ? 1 : 0;
        }
        cargarEmisionChequeEmitido($tr, extras);
    });

    activarTecladoChequeEmitido();
    $('#tbody-cheque-emitido-table tr.item-cheque-emitido').each(function () {
        filtrarChequerasChequeEmitido($(this), $(this).find('.cuentacaja_emitido_id').val(), false);
    });

    $(document).on('click', '.consultacuentacaja_reemplazo', function () {
        cuentacajaxcodigoReemplazo = $(this).closest('tr');
        if (!$('#empresa_id').val()) {
            alert('Debe ingresar empresa');
            return;
        }
        $('#consultacuentacajaModal').modal('show');
    });

    $(document).on('click', '.consultabanco_recibido', function () {
        var $campo = $(this).closest('tr');
        ptrCampoBanco = $campo;
        ptrbanco_id = $campo.find('.banco_recibido_id');
        ptrcodigobanco = $campo.find('.codigobanco_recibido');
        ptrnombrebanco = $campo.find('.nombrebanco_recibido');
        $('#consultabanco').val('');
        if (typeof buscar_datos_banco === 'function') {
            buscar_datos_banco('');
        }
        $('#consultabancoModal').modal('show');
    });

    $(document).on('click', '.consultabanco_reemplazo', function () {
        var $campo = $(this).closest('tr');
        ptrCampoBanco = $campo;
        ptrbanco_id = $campo.find('.banco_reemplazo_id');
        ptrcodigobanco = $campo.find('.codigobanco_reemplazo');
        ptrnombrebanco = $campo.find('.nombrebanco_reemplazo');
        $('#consultabanco').val('');
        if (typeof buscar_datos_banco === 'function') {
            buscar_datos_banco('');
        }
        $('#consultabancoModal').modal('show');
    });

    $(document).on('click', '.buscar_cheque_anulado', function () {
        var row = $(this).closest('tr');
        var numero = row.find('.numerocheque_anulado_buscar').val();
        var empresa_id = $('#empresa_id').val();
        if (!numero || !empresa_id) {
            alert('Indique empresa y n\u00famero de cheque a anular');
            return;
        }
        $.post(carpetaBase + '/caja/ingresoegreso/buscar-cheque', {
            _token: $('input[name=_token]').val(),
            empresa_id: empresa_id,
            numerocheque: numero,
            banco_id: row.find('.banco_reemplazo_id').val() || 0
        }, function (data) {
            if (data.mensaje !== 'ok') {
                alert('Cheque no encontrado');
                return;
            }
            row.find('.cheque_anulado_id').val(data.cheque.id);
            row.find('.numerocheque_anulado').val(data.cheque.numerocheque + ' (' + data.cheque.banco + ')');
            row.find('.montocheque_reemplazo').val(data.cheque.monto);
            row.find('.moneda_reemplazo_id').val(data.cheque.moneda_id);
            row.find('.cotizacioncheque_reemplazo').val(data.cheque.cotizacion);
            row.find('.origen_reemplazo').val(data.cheque.origen === 'E' ? 'E' : 'R');
            toggleBloqueReemplazo(row);
            flModificaAsiento = true;
        });
    });
}

function toggleBloqueReemplazo(row) {
    var tipo = row.find('.origen_reemplazo').val();
    if (tipo === 'R') {
        row.find('.bloque-reemplazo-emitido').hide();
        row.find('.bloque-reemplazo-recibido').show();
    } else {
        row.find('.bloque-reemplazo-recibido').hide();
        row.find('.bloque-reemplazo-emitido').show();
    }
}

function agregaRenglonChequeEmitido(e) {
    e.preventDefault();
    var html = $('#template-renglon-cheque-emitido').html();
    $('#tbody-cheque-emitido-table').append(html);
    var row = $('#tbody-cheque-emitido-table tr:last');
    row.find('.fechapago_emitido').val($('#fecha').val());
    filtrarChequerasChequeEmitido(row, '', false);
    enfocarCampoCheque(row.find('.codigo_emitido')[0]);
    flModificaAsiento = true;
}

function agregaRenglonChequeRecibido(e) {
    e.preventDefault();
    var html = $('#template-renglon-cheque-recibido').html();
    $('#tbody-cheque-recibido-table').append(html);
    $('#tbody-cheque-recibido-table tr:last').find('.fechapago_recibido').val($('#fecha').val());
    flModificaAsiento = true;
}

function agregaRenglonChequeReemplazo(e) {
    e.preventDefault();
    var html = $('#template-renglon-cheque-reemplazo').html();
    $('#tbody-cheque-reemplazo-table').append(html);
    var row = $('#tbody-cheque-reemplazo-table tr:last');
    row.find('.fechapago_reemplazo').val($('#fecha').val());
    toggleBloqueReemplazo(row);
    flModificaAsiento = true;
}

function borraRenglonChequeEmitido(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
    if (typeof sumaMonto === 'function') {
        sumaMonto();
    } else {
        sumaMontosChequesIngresoEgreso();
    }
    flModificaAsiento = true;
}

function borraRenglonChequeRecibido(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
    if (typeof sumaMonto === 'function') {
        sumaMonto();
    } else {
        sumaMontosChequesIngresoEgreso();
    }
    flModificaAsiento = true;
}

function borraRenglonChequeReemplazo(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
    if (typeof sumaMonto === 'function') {
        sumaMonto();
    } else {
        sumaMontosChequesIngresoEgreso();
    }
    flModificaAsiento = true;
}

function sumaMontosChequesIngresoEgreso() {
    var extraDebe = 0;
    var extraHaber = 0;
    var monedaDefault = $("#tbody-cuenta-table").children(':first').find('.moneda').val() || 1;

    $("#tbody-cheque-emitido-table .montocheque_emitido").each(function () {
        var monto = parseFloat($(this).val()) || 0;
        if (monto <= 0) return;
        var moneda = $(this).closest('tr').find('.moneda_emitido_id').val();
        var cot = $(this).closest('tr').find('.cotizacioncheque_emitido').val();
        var coef = calculaCoeficienteMoneda(monedaDefault, moneda, cot);
        extraHaber += monto * coef;
    });

    $("#tbody-cheque-recibido-table .montocheque_recibido").each(function () {
        var monto = parseFloat($(this).val()) || 0;
        if (monto <= 0) return;
        var moneda = $(this).closest('tr').find('.monedacheque_recibido_id').val();
        var cot = $(this).closest('tr').find('.cotizacioncheque_recibido').val();
        var coef = calculaCoeficienteMoneda(monedaDefault, moneda, cot);
        extraDebe += monto * coef;
    });

    return { extraDebe: extraDebe, extraHaber: extraHaber };
}

function serializarChequesEmitidos() {
    var datos = [];
    $('#tbody-cheque-emitido-table tr').each(function () {
        var monto = parseFloat($(this).find('.montocheque_emitido').val()) || 0;
        if (monto <= 0) return;
        datos.push({
            cuentacaja_ids: $(this).find('.cuentacaja_emitido_id').val(),
            moneda_ids: $(this).find('.moneda_emitido_id').val(),
            montos: monto,
            cotizaciones: $(this).find('.cotizacioncheque_emitido').val(),
            fechapagos: $(this).find('.fechapago_emitido').val()
        });
    });
    return JSON.stringify(datos);
}

function serializarChequesRecibidos() {
    var datos = [];
    $('#tbody-cheque-recibido-table tr').each(function () {
        var monto = parseFloat($(this).find('.montocheque_recibido').val()) || 0;
        if (monto <= 0) return;
        datos.push({
            moneda_ids: $(this).find('.monedacheque_recibido_id').val(),
            montos: monto,
            cotizaciones: $(this).find('.cotizacioncheque_recibido').val()
        });
    });
    return JSON.stringify(datos);
}

function serializarChequesReemplazo() {
    var datos = [];
    $('#tbody-cheque-reemplazo-table tr').each(function () {
        var idAnulado = $(this).find('.cheque_anulado_id').val();
        if (!idAnulado) return;
        datos.push({
            cheque_anulado_id: idAnulado,
            origen_anulado: 'R',
            origen_reemplazo: $(this).find('.origen_reemplazo').val(),
            monto_anulado: parseFloat($(this).find('.montocheque_reemplazo').val()) || 0,
            monto_reemplazo: parseFloat($(this).find('.montocheque_reemplazo').val()) || 0,
            moneda_ids: $(this).find('.moneda_reemplazo_id').val(),
            cotizaciones: $(this).find('.cotizacioncheque_reemplazo').val(),
            cuentacaja_reemplazo_ids: $(this).find('.cuentacaja_reemplazo_id').val(),
            fechapago_reemplazo: $(this).find('.fechapago_reemplazo').val()
        });
    });
    return JSON.stringify(datos);
}
