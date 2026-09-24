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
    $tr.find('.chequera_emitido_tipochequera').val(ch && ch.tipochequera ? ch.tipochequera : '');
    var etiqueta = ch ? (ch.etiqueta_completa || ch.etiqueta || '') : '';
    $tr.find('.chequera_emitido_lbl').val(etiqueta);
    $tr.find('.chequera_emitido_lbl').attr('title', etiqueta || 'F1 consulta chequera de la cuenta');
    if (ch && ch.tipochequera) {
        $tr.find('.negociable_emitido').val(String(ch.tipochequera).toUpperCase() === 'E' ? 'E' : 'N');
    } else {
        $tr.find('.negociable_emitido').val('E');
    }
    sincronizarNroEcheqEmitido($tr);
}

function sincronizarNroEcheqEmitido($tr) {
    if (!$tr || !$tr.length) {
        return;
    }
    var neg = String($tr.find('.negociable_emitido').val() || 'E').toUpperCase();
    var nro = String($tr.find('.numerocheque_emitido').val() || '').trim();
    $tr.find('.nro_echeq_emitido').val(neg === 'E' ? nro : '');
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
    if (data.chequera_id && !$tr.find('.chequera_emitido_id').val()) {
        var chAuto = chequeraDesdeLista(data.chequeras || [], data.chequera_id, !!data.diferido);
        if (chAuto) {
            pintarChequeraEmitido($tr, chAuto);
        }
    }
    var $nro = $tr.find('.numerocheque_emitido');
    var auto = $nro.data('auto') === 1 || !$nro.val();
    if (data.proximo_numero && (forzarNumero || auto)) {
        $nro.val(data.proximo_numero).data('auto', 1);
    }
    var titulo = data.fuente_numero === 'chequera'
        ? 'Próximo de la chequera (talonario ERP)'
        : 'Numerador Anita';
    if (data.tctes_clave) {
        titulo += ' · ' + data.tctes_clave;
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
    if (data.fuente_numero === 'chequera' && data.proximo_numero) {
        lbl = 'Chequera · nro. ' + data.proximo_numero;
        if (data.tctes_clave) {
            lbl += ' · Anita ' + data.tctes_clave;
        }
        lbl += data.diferido ? ' (diferido)' : ' (al día)';
    } else if (data.tctes_clave) {
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
    if (parseInt($tr.find('.chequera_emitido_id').val() || '0', 10) > 0) {
        params.chequera_id = $tr.find('.chequera_emitido_id').val();
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

function pintarChequeraReemplazo($tr, ch) {
    $tr.find('.chequera_reemplazo_id').val(ch && ch.id ? ch.id : '');
    $tr.find('.chequera_reemplazo_tipo').val(ch && ch.tipocheque ? ch.tipocheque : '');
    $tr.find('.chequera_reemplazo_tipochequera').val(ch && ch.tipochequera ? ch.tipochequera : '');
    var etiqueta = ch ? (ch.etiqueta_completa || ch.etiqueta || '') : '';
    $tr.find('.chequera_reemplazo_lbl').val(etiqueta);
    $tr.find('.chequera_reemplazo_lbl').attr(
        'title',
        etiqueta || 'F1 / lupa: elegir chequera (puede ser distinta a la anulada)'
    );
}

function filtrarChequerasChequeReemplazo($tr, cuentacajaId, preferirDiferido, lista) {
    if (!$tr || !$tr.length || !$tr.find('.chequera_reemplazo_id').length) {
        return;
    }
    if (!(parseInt(cuentacajaId || '0', 10) > 0)) {
        pintarChequeraReemplazo($tr, null);
        $tr.removeData('chequeras_reemplazo');
        return;
    }
    if (lista && lista.length) {
        $tr.data('chequeras_reemplazo', lista);
    }
    lista = lista || $tr.data('chequeras_reemplazo') || [];
    if (!lista.length) {
        return;
    }
    var actual = $tr.find('.chequera_reemplazo_id').val();
    var ch = chequeraDesdeLista(lista, actual, preferirDiferido);
    pintarChequeraReemplazo($tr, ch);
}

function abrirConsultaChequeraReemplazo($tr) {
    if (!$tr || !$tr.length) {
        return;
    }
    var cuentaId = parseInt($tr.find('.cuentacaja_reemplazo_id').val() || '0', 10);
    if (!(cuentaId > 0)) {
        alert('Primero indique la cuenta de tesorer\u00eda');
        return;
    }
    if (typeof abrirModalConsultaChequera !== 'function') {
        alert('No est\u00e1 disponible la consulta de chequeras');
        return;
    }
    var codigo = String($tr.find('.codigo_reemplazo').val() || '').trim();
    var nombre = String($tr.find('.nombre_reemplazo').val() || '').trim();
    abrirModalConsultaChequera({
        cuentacajaId: cuentaId,
        cuentaLabel: (codigo + (nombre ? ' · ' + nombre : '')).trim(),
        fechaPago: $tr.find('.fechapago_reemplazo').val() || $('#fecha').val() || '',
        fechaEmision: $('#fecha').val() || '',
        selectedId: $tr.find('.chequera_reemplazo_id').val(),
        onElegir: function (ch) {
            pintarChequeraReemplazo($tr, ch);
            if (typeof flModificaAsiento !== 'undefined') {
                flModificaAsiento = true;
            }
            cargarEmisionChequeReemplazo($tr, { forzarNumero: true });
        }
    });
}

function aplicarCuentaChequeReemplazo($tr, data, forzarNumero) {
    if (!$tr || !$tr.length || !data || !(parseInt(data.id, 10) > 0)) {
        return;
    }
    $tr.find('.cuentacaja_reemplazo_id').val(data.id);
    if (data.codigo != null) {
        $tr.find('.codigo_reemplazo').val(data.codigo);
    }
    $tr.find('.nombre_reemplazo').val(data.nombre || '');
    if (data.moneda_id) {
        $tr.find('.moneda_reemplazo_id').val(data.moneda_id);
    }
    filtrarChequerasChequeReemplazo($tr, data.id, !!data.diferido, data.chequeras || []);
    if (data.chequera_id && !$tr.find('.chequera_reemplazo_id').val()) {
        var chAutoR = chequeraDesdeLista(data.chequeras || [], data.chequera_id, !!data.diferido);
        if (chAutoR) {
            pintarChequeraReemplazo($tr, chAutoR);
        }
    }
    var $nro = $tr.find('.numerocheque_reemplazo');
    var auto = $nro.data('auto') === 1 || !$nro.val();
    if (data.proximo_numero && (forzarNumero || auto)) {
        $nro.val(data.proximo_numero).data('auto', 1);
    }
    var titulo = data.fuente_numero === 'chequera'
        ? 'Próximo de la chequera (talonario ERP)'
        : 'Numerador Anita';
    $nro.attr('title', titulo);
    if (data.aviso && !data.proximo_numero) {
        $nro.attr('title', data.aviso);
    }
    var lbl = '';
    if (data.fuente_numero === 'chequera' && data.proximo_numero) {
        lbl = 'Chequera · nro. ' + data.proximo_numero;
        if (data.tctes_clave) {
            lbl += ' · Anita ' + data.tctes_clave;
        }
        lbl += data.diferido ? ' (diferido)' : ' (al d\u00eda)';
    } else if (data.tctes_clave) {
        lbl = data.tctes_clave;
        if (data.tctes_desc) {
            lbl += ' · ' + data.tctes_desc;
        }
        lbl += data.diferido ? ' (diferido)' : ' (al d\u00eda)';
    } else if (data.aviso) {
        lbl = data.aviso;
    }
    $tr.find('.tctes_reemplazo_lbl').text(lbl);
    if (typeof flModificaAsiento !== 'undefined') {
        flModificaAsiento = true;
    }
}

function cargarEmisionChequeReemplazo($tr, extras, onOk) {
    var empresaId = parseInt($('#empresa_id').val() || '0', 10);
    var cuentaId = parseInt($tr.find('.cuentacaja_reemplazo_id').val() || '0', 10);
    var codigo = String($tr.find('.codigo_reemplazo').val() || '').trim();
    extras = extras || {};
    var params = {
        empresa_id: empresaId,
        fecha_pago: $tr.find('.fechapago_reemplazo').val() || $('#fecha').val() || '',
        fecha_emision: $('#fecha').val() || ''
    };
    if (extras.diferido === 1 || extras.diferido === 0) {
        params.diferido = extras.diferido;
    }
    if (parseInt($tr.find('.chequera_reemplazo_id').val() || '0', 10) > 0) {
        params.chequera_id = $tr.find('.chequera_reemplazo_id').val();
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
            aplicarCuentaChequeReemplazo($tr, data, !!extras.forzarNumero);
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

function empresaIngresoEgresoId() {
    return String($('#empresa_id').val() || '').trim();
}

function actualizarAvisoEmpresaReemplazo() {
    var ok = !!empresaIngresoEgresoId();
    var $banner = $('#ie-reemplazo-aviso-empresa-banner');
    if ($banner.length) {
        $banner.toggleClass('d-none', ok);
    }
    $('.ie-reemplazo-aviso-empresa').toggle(!ok);
}

function irADatosPrincipalesEmpresa() {
    $('#botonform1').trigger('click');
    setTimeout(function () {
        var $emp = $('#empresa_id');
        if ($emp.length && $emp.is('select')) {
            $emp.focus();
        }
    }, 80);
}

window.aplicarCuentaChequeReemplazo = aplicarCuentaChequeReemplazo;
window.cargarEmisionChequeReemplazo = cargarEmisionChequeReemplazo;
window.abrirConsultaChequeraReemplazo = abrirConsultaChequeraReemplazo;
window.pintarChequeraReemplazo = pintarChequeraReemplazo;
window.actualizarAvisoEmpresaReemplazo = actualizarAvisoEmpresaReemplazo;
window.empresaIngresoEgresoId = empresaIngresoEgresoId;
window.irADatosPrincipalesEmpresa = irADatosPrincipalesEmpresa;

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
    $('#agrega_renglon_cheque_cartera').on('click', function (e) {
        e.preventDefault();
        abrirCarteraParaNuevaFilaRecibido();
    });
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
    $(document).on('change', '.montocheque_reemplazo, .cotizacioncheque_reemplazo, .moneda_reemplazo_id, .origen_reemplazo', function () {
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
        sincronizarNroEcheqEmitido($(this).closest('tr'));
    });

    $(document).on('change', '.negociable_emitido', function () {
        sincronizarNroEcheqEmitido($(this).closest('tr'));
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
        var $tr = $(this).closest('tr');
        if (!empresaIngresoEgresoId()) {
            actualizarAvisoEmpresaReemplazo();
            alert('Indique la empresa en Datos principales');
            irADatosPrincipalesEmpresa();
            return;
        }
        cuentacajaxcodigoReemplazo = $tr;
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').modal('show');
    });

    $(document).on('click', '.consultachequera_reemplazo, .chequera_reemplazo_lbl', function (e) {
        e.preventDefault();
        abrirConsultaChequeraReemplazo($(this).closest('tr'));
    });

    $(document).on('input', '.numerocheque_reemplazo', function () {
        $(this).data('auto', 0);
    });

    $(document).on('change', '.fechapago_reemplazo', function () {
        var $tr = $(this).closest('tr');
        if ($tr.find('.origen_reemplazo').val() === 'E'
            && parseInt($tr.find('.cuentacaja_reemplazo_id').val() || '0', 10) > 0) {
            cargarEmisionChequeReemplazo($tr, {
                forzarNumero: $tr.find('.numerocheque_reemplazo').data('auto') === 1
            });
        }
    });

    $(document).on('keydown', '.codigo_reemplazo', function (e) {
        var $tr = $(this).closest('tr');
        if (esTeclaF1ChequeEmitido(e)) {
            e.preventDefault();
            e.stopPropagation();
            $tr.find('.consultacuentacaja_reemplazo').trigger('click');
            return;
        }
        if (esTeclaEnterChequeEmitido(e)) {
            e.preventDefault();
            e.stopPropagation();
            cargarEmisionChequeReemplazo($tr, { porCodigo: true, forzarNumero: true });
        }
    });

    $(document).on('keydown', '.numerocheque_anulado_buscar', function (e) {
        if (esTeclaEnterChequeEmitido(e)) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('tr').find('.buscar_cheque_anulado').trigger('click');
        }
    });

    $(document).on('change', '#empresa_id', function () {
        actualizarAvisoEmpresaReemplazo();
        var modo = String($('#ie_modo_uso').val() || '');
        if (modo === 'canje_cheques' && empresaIngresoEgresoId()) {
            $('#botonform2').trigger('click');
            var $tabReemp = $('#tabs-cheques-ingresoegreso a[href="#panel-cheques-reemplazo"]');
            if ($tabReemp.length) {
                $tabReemp.trigger('click');
            }
            if ($('#tbody-cheque-reemplazo-table tr.item-cheque-reemplazo').length === 0) {
                agregaRenglonChequeReemplazo({ preventDefault: function () {} });
            }
        }
    });
    actualizarAvisoEmpresaReemplazo();

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

    $(document).on('click', '.consultachequecartera_recibido', function (e) {
        e.preventDefault();
        abrirCarteraParaFilaRecibido($(this).closest('tr'));
    });

    $(document).on('keydown', '.numerocheque_recibido', function (e) {
        if (typeof esTeclaF1ChequeCartera === 'function' && esTeclaF1ChequeCartera(e)) {
            e.preventDefault();
            e.stopPropagation();
            abrirCarteraParaFilaRecibido($(this).closest('tr'), String($(this).val() || '').trim());
            return;
        }
        if (e.which === 13 || e.key === 'Enter') {
            var val = String($(this).val() || '').trim();
            if (val === '') {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            resolverChequeCarteraEnFila($(this).closest('tr'), val);
        }
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
        var numero = String(row.find('.numerocheque_anulado_buscar').val() || '').trim();
        var empresa_id = empresaIngresoEgresoId();
        actualizarAvisoEmpresaReemplazo();
        if (!empresa_id) {
            alert('Indique la empresa en Datos principales y el n\u00famero de cheque a anular');
            irADatosPrincipalesEmpresa();
            return;
        }
        if (!numero) {
            alert('Indique el n\u00famero de cheque a anular');
            enfocarCampoCheque(row.find('.numerocheque_anulado_buscar')[0]);
            return;
        }
        $.post(carpetaBase + '/caja/ingresoegreso/buscar-cheque', {
            _token: $('input[name=_token]').val(),
            empresa_id: empresa_id,
            numerocheque: numero,
            banco_id: 0
        })
            .done(function (data) {
                if (!data || data.mensaje !== 'ok' || !data.cheque) {
                    alert('Cheque no encontrado para esa empresa (verifique n\u00famero exacto y que no est\u00e9 anulado)');
                    enfocarCampoCheque(row.find('.numerocheque_anulado_buscar')[0]);
                    return;
                }
                aplicarChequeAnuladoEnFilaReemplazo(row, data.cheque);
                flModificaAsiento = true;
                precargarDetalleCanjeChequesConIa();
            })
            .fail(function (xhr) {
                var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                    || 'Error al buscar el cheque';
                alert(msg);
            });
    });
}

function precargarDetalleCanjeChequesConIa() {
    var modo = String($('#ie_modo_uso').val() || '').trim();
    if (modo !== 'canje_cheques' && !(typeof esCanjeChequesIngresoEgreso === 'function' && esCanjeChequesIngresoEgreso())) {
        return;
    }

    var cheques = [];
    $('#tbody-cheque-reemplazo-table tr.item-cheque-reemplazo').each(function () {
        var $tr = $(this);
        var id = parseInt($tr.find('.cheque_anulado_id').val() || '0', 10);
        var nroLbl = String($tr.find('.numerocheque_anulado').val() || '').trim();
        var nroBuscar = String($tr.find('.numerocheque_anulado_buscar').val() || '').trim();
        var nro = nroBuscar || nroLbl.replace(/\s*\(.*$/, '');
        if (id <= 0 && nro === '') {
            return;
        }
        cheques.push({
            id: id,
            numerocheque: nro,
            origen: $tr.find('.origen_anulado').val() || $tr.find('.origen_reemplazo').val() || 'R',
            monto: $tr.find('.montocheque_reemplazo').val() || '',
            banco: String($tr.find('.nombrebanco_reemplazo').val() || '').trim()
                || (nroLbl.match(/\(([^)]+)\)/) ? nroLbl.match(/\(([^)]+)\)/)[1] : ''),
            anombrede: String($tr.find('.anombrede_reemplazo').val() || '').trim(),
            fechapago: $tr.find('.fechapago_reemplazo').val() || '',
            cuentacaja_nombre: String($tr.find('.nombre_reemplazo').val() || '').trim(),
            cuentacaja_codigo: String($tr.find('.codigo_reemplazo').val() || '').trim()
        });
    });

    if (cheques.length === 0) {
        return;
    }

    var $detalle = $('#detalle');
    if (!$detalle.length) {
        return;
    }
    if (!$detalle.data('ie-canje-detalle-bound')) {
        $detalle.data('ie-canje-detalle-bound', 1);
        $detalle.on('input.ieCanjeDetalle', function () {
            $(this).data('ie-canje-detalle', 0);
        });
    }
    var actual = String($detalle.val() || '').trim();
    var generadoPorCanje = $detalle.data('ie-canje-detalle') === 1;
    if (actual !== '' && !generadoPorCanje) {
        return;
    }

    $.post(carpetaBase + '/caja/ingresoegreso/sugerir-detalle-canje-cheque', {
        _token: $('input[name=_token]').val(),
        cheques: cheques
    })
        .done(function (data) {
            if (!data || data.mensaje !== 'ok' || !data.detalle) {
                return;
            }
            var texto = String(data.detalle || '').trim();
            if (texto === '') {
                return;
            }
            var actualAhora = String($detalle.val() || '').trim();
            var sigueGenerado = $detalle.data('ie-canje-detalle') === 1;
            if (actualAhora !== '' && !sigueGenerado) {
                return;
            }
            $detalle.off('input.ieCanjeDetalle');
            $detalle.val(texto);
            $detalle.data('ie-canje-detalle', 1);
            $detalle.on('input.ieCanjeDetalle', function () {
                $(this).data('ie-canje-detalle', 0);
            });
        });
}

function aplicarChequeAnuladoEnFilaReemplazo(row, cheque) {
    var origen = cheque.origen === 'E' ? 'E' : 'R';
    row.find('.cheque_anulado_id').val(cheque.id);
    row.find('.origen_anulado').val(origen);
    row.find('.numerocheque_anulado').val(
        (cheque.numerocheque || '') + (cheque.banco ? ' (' + cheque.banco + ')' : '')
    );
    row.find('.origen_reemplazo').val(origen);
    row.find('.montocheque_reemplazo').val(cheque.monto);
    row.find('.moneda_reemplazo_id').val(cheque.moneda_id);
    row.find('.cotizacioncheque_reemplazo').val(cheque.cotizacion != null ? cheque.cotizacion : 1);
    if (cheque.fechapago) {
        row.find('.fechapago_reemplazo').val(cheque.fechapago);
    } else if (!$('#fecha').val()) {
        // noop
    } else if (!row.find('.fechapago_reemplazo').val()) {
        row.find('.fechapago_reemplazo').val($('#fecha').val());
    }

    if (origen === 'E') {
        row.find('.cuentacaja_reemplazo_id').val(cheque.cuentacaja_id || '');
        row.find('.codigo_reemplazo').val(cheque.cuentacaja_codigo || '');
        row.find('.nombre_reemplazo').val(cheque.cuentacaja_nombre || '');
        row.find('.chequera_reemplazo_id').val(cheque.chequera_id || '');
        row.find('.chequera_reemplazo_tipo').val(cheque.chequera_tipo || '');
        row.find('.chequera_reemplazo_tipochequera').val(cheque.chequera_tipochequera || '');
        row.find('.chequera_reemplazo_lbl').val(cheque.chequera_etiqueta || '');
        row.find('.anombrede_reemplazo').val(cheque.anombrede || '');
        row.find('.banco_reemplazo_id').val(cheque.banco_id || '');
        row.find('.codigobanco_reemplazo').val(cheque.banco_codigo || '');
        row.find('.nombrebanco_reemplazo').val(cheque.banco || '');
        row.find('.sucursalpago_reemplazo').val('');
        row.find('.cuentalibradora_reemplazo').val('');
    } else {
        row.find('.banco_reemplazo_id').val(cheque.banco_id || '');
        row.find('.codigobanco_reemplazo').val(cheque.banco_codigo || '');
        row.find('.nombrebanco_reemplazo').val(cheque.banco || '');
        row.find('.sucursalpago_reemplazo').val(cheque.sucursalpago || '');
        row.find('.cuentalibradora_reemplazo').val(cheque.cuentalibradora || '');
        row.find('.cuentacaja_reemplazo_id').val('');
        row.find('.codigo_reemplazo').val('');
        row.find('.nombre_reemplazo').val('');
        row.find('.chequera_reemplazo_id').val('');
        row.find('.chequera_reemplazo_lbl').val('');
        row.find('.anombrede_reemplazo').val('');
    }

    // Próximo número: misma lógica que emitidos / OP (chequera ERP, fallback Anita).
    row.find('.numerocheque_reemplazo').val('').data('auto', 1);
    row.find('.tctes_reemplazo_lbl').text('');
    toggleBloqueReemplazo(row);
    if (origen === 'E' && parseInt(row.find('.cuentacaja_reemplazo_id').val() || '0', 10) > 0) {
        var extras = { forzarNumero: true };
        var tipo = String(row.find('.chequera_reemplazo_tipo').val() || '').toUpperCase();
        if (tipo === 'D' || tipo === 'N' || tipo === 'C') {
            extras.diferido = tipo === 'D' ? 1 : 0;
        }
        cargarEmisionChequeReemplazo(row, extras, function () {
            if (typeof sumaMonto === 'function') {
                sumaMonto();
            }
            enfocarCampoCheque(row.find('.numerocheque_reemplazo')[0]);
        });
    } else {
        if (typeof sumaMonto === 'function') {
            sumaMonto();
        }
        enfocarCampoCheque(row.find('.numerocheque_reemplazo')[0]);
    }
}

function toggleBloqueReemplazo(row) {
    var tipo = row.find('.origen_reemplazo').val();
    if (tipo === 'R') {
        row.find('.bloque-reemplazo-emitido').hide();
        row.find('.bloque-reemplazo-recibido').show();
        row.find('.bloque-reemplazo-emitido-extra').hide();
        row.find('.bloque-reemplazo-recibido-extra').show();
    } else {
        row.find('.bloque-reemplazo-recibido').hide();
        row.find('.bloque-reemplazo-emitido').show();
        row.find('.bloque-reemplazo-recibido-extra').hide();
        row.find('.bloque-reemplazo-emitido-extra').show();
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

function abrirCarteraParaNuevaFilaRecibido() {
    var html = $('#template-renglon-cheque-recibido').html();
    $('#tbody-cheque-recibido-table').append(html);
    var $tr = $('#tbody-cheque-recibido-table tr.item-cheque-recibido').last();
    $tr.find('.fechapago_recibido').val($('#fecha').val());
    flModificaAsiento = true;
    abrirCarteraParaFilaRecibido($tr);
}

function abrirCarteraParaFilaRecibido($tr, consultaInicial) {
    if (typeof abrirModalConsultaChequeCartera !== 'function') {
        alert('No est\u00e1 cargado el modal de cartera de cheques');
        return;
    }
    abrirModalConsultaChequeCartera({
        empresaId: $('#empresa_id').val() || '',
        consultaInicial: consultaInicial || '',
        onElegir: function (fila) {
            if (typeof aplicarChequeCarteraAFila === 'function') {
                aplicarChequeCarteraAFila($tr, fila);
            }
        }
    });
}

function resolverChequeCarteraEnFila($tr, valor) {
    $.ajax({
        url: (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/caja/cheque/resolver-cartera',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                || ($('input[name="_token"]').first().val() || '')
        },
        data: {
            valor: valor,
            empresa_id: $('#empresa_id').val() || ''
        }
    }).done(function (resp) {
        if (resp && resp.mensaje === 'ok' && resp.data && typeof aplicarChequeCarteraAFila === 'function') {
            aplicarChequeCarteraAFila($tr, resp.data);
            return;
        }
        abrirCarteraParaFilaRecibido($tr, valor);
    }).fail(function () {
        abrirCarteraParaFilaRecibido($tr, valor);
    });
}

function agregaRenglonChequeReemplazo(e) {
    e.preventDefault();
    actualizarAvisoEmpresaReemplazo();
    if (!empresaIngresoEgresoId()) {
        alert('Indique la empresa en Datos principales antes de agregar el reemplazo');
        irADatosPrincipalesEmpresa();
        return;
    }
    var html = $('#template-renglon-cheque-reemplazo').html();
    $('#tbody-cheque-reemplazo-table').append(html);
    var row = $('#tbody-cheque-reemplazo-table tr:last');
    row.find('.fechapago_reemplazo').val($('#fecha').val());
    toggleBloqueReemplazo(row);
    actualizarAvisoEmpresaReemplazo();
    enfocarCampoCheque(row.find('.numerocheque_anulado_buscar')[0]);
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

    // Canje / anulación+reemplazo: el asiento sale de estos renglones (sin cuentas de caja).
    $("#tbody-cheque-reemplazo-table tr.item-cheque-reemplazo").each(function () {
        var idAnulado = parseInt($(this).find('.cheque_anulado_id').val() || '0', 10) || 0;
        if (idAnulado <= 0) return;
        var monto = parseFloat($(this).find('.montocheque_reemplazo').val()) || 0;
        if (monto <= 0) return;
        var origen = String($(this).find('.origen_reemplazo').val() || 'R').toUpperCase();
        var moneda = $(this).find('.moneda_reemplazo_id').val();
        var cot = $(this).find('.cotizacioncheque_reemplazo').val();
        var coef = calculaCoeficienteMoneda(monedaDefault, moneda, cot);
        if (origen === 'E') {
            extraHaber += monto * coef;
        } else {
            extraDebe += monto * coef;
        }
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
            fechapagos: $(this).find('.fechapago_emitido').val(),
            numerocheques: $(this).find('.numerocheque_emitido').val()
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
        var origenAnulado = String($(this).find('.origen_anulado').val() || '').toUpperCase();
        if (origenAnulado !== 'E' && origenAnulado !== 'R') {
            origenAnulado = String($(this).find('.origen_reemplazo').val() || 'R').toUpperCase();
        }
        datos.push({
            cheque_anulado_id: idAnulado,
            origen_anulado: origenAnulado,
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
