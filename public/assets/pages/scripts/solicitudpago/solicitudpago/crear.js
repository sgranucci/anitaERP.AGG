$(function () {
    $('#agrega_renglon_sp_cuenta').on('click', function (e) {
        agregaRenglonCuenta(e);
        spActualizarEstadoAsiento();
    });
    $(document).on('click', '.eliminar_sp_cuenta', function (e) {
        borraRenglonCuenta.call(this, e);
        spActualizarEstadoAsiento();
    });

    $('#agrega_renglon_sp_cuota').on('click', agregaRenglonCuota);
    $(document).on('click', '.eliminar_sp_cuota', borraRenglonCuota);
    $('#btn-importar-cuotas-sp').on('click', importarCuotasSolicitudpagoExcel);

    $('#agrega_renglon_archivo').on('click', agregaRenglonArchivo);
    $(document).on('click', '.eliminararchivo', borraRenglonArchivo);
    $(document).on('click', '.eliminar-archivo-solicitudpago', borraTarjetaArchivoSolicitudpago);

    $(document).on('input change', '.monto-debe, .monto-haber', function () {
        spSincronizarHiddenFila($(this).closest('tr'));
        spActualizarEstadoAsiento();
    });
    $(document).on('change', 'select.debe_haber', function () {
        spAplicarLadoDebeHaber($(this).closest('tr'), true);
        spActualizarEstadoAsiento();
    });

    $('#tratamiento').on('change', actualizarVisibilidadCuotas);
    $(document).on('change.spConcepto', '#concepto_solicitudpago_id', onConceptoSolicitudpagoChange);
    $('#sector_solicitudpago_id').on('change', onSectorSolicitudpagoChange);

    $('#form-general').on('submit', function (e) {
        // Antes de validar HTML5 / funciones.js: deshabilitar cuotas si no aplican.
        actualizarVisibilidadCuotas();
        spSincronizarHiddenTodas();
        if (!spAsientoValido()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            spActualizarEstadoAsiento();
            var $tabCuentas = $('#tab-cuentas-link');
            if ($tabCuentas.length && typeof $tabCuentas.tab === 'function') {
                $tabCuentas.tab('show');
            } else if ($tabCuentas.length) {
                $tabCuentas.trigger('click');
            }
            alert('Debe cargar el asiento contable (solapa Cuentas) con al menos una cuenta e importe en Debe o Haber antes de grabar.');
            return false;
        }
        if (!spAsientoBalanceado()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            spActualizarEstadoAsiento();
            var $tabBal = $('#tab-cuentas-link');
            if ($tabBal.length && typeof $tabBal.tab === 'function') {
                $tabBal.tab('show');
            } else if ($tabBal.length) {
                $tabBal.trigger('click');
            }
            alert('El asiento no balancea: el total Debe debe ser igual al total Haber.');
            return false;
        }
        if (!spCuotasValidas()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var $tabCuotas = $('#tab-cuotas-link');
            if ($tabCuotas.length && typeof $tabCuotas.tab === 'function') {
                $tabCuotas.tab('show');
            } else if ($tabCuotas.length) {
                $tabCuotas.trigger('click');
            }
            alert('El concepto requiere cuotas: cargue al menos una cuota con vencimiento e importe (solapa Cuotas).');
            return false;
        }
        return true;
    });

    $('.botonsubmit').on('click', function () {
        $('#form-general').submit();
    });

    actualizarVisibilidadCuotas();
    activaEventos(true);
    spActualizarEstadoAsiento();

    $('#tab-arbol-link').on('shown.bs.tab', function () {
        leeArbolSolicitudpago();
    });
});

function fechaMovimientoArbolSpTexto(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return '';
    }
    var s = String(raw);
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (m) {
        return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5];
    }
    return s.substring(0, 19).replace('T', ' ');
}

function leeArbolSolicitudpago() {
    var id = $('#solicitudpago_id').val();
    var $w = $('#solicitudpago-arbol-table tbody.container-arbol');
    if (!$w.length) {
        return;
    }
    if (!id) {
        $w.empty().append(
            '<tr><td colspan="7" class="text-center text-muted">Guarde la solicitud para disparar el árbol del concepto.</td></tr>'
        );
        return;
    }
    var url = carpetaBase + '/arbolaprobacion/leer_movimiento_aprobacion/SP/' + id;
    $w.empty().append(
        '<tr><td colspan="7" class="text-center text-muted">Cargando movimientos del árbol de aprobación…</td></tr>'
    );
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        cache: false
    }).done(function (resp) {
        var rows = Array.isArray(resp) ? resp : (resp.movimientos || []);
        $w.empty();
        if (window.AnitaArbolPanelIa && !Array.isArray(resp)) {
            window.AnitaArbolPanelIa.render(resp.ai_contexto_arbol || null, '#sp-panel-ia-arbol');
        }
        if (!rows.length) {
            $w.append(
                '<tr><td colspan="7" class="text-center text-muted">Sin movimientos registrados en el árbol.</td></tr>'
            );
            spActualizarBadgeArbol(0);
            return;
        }
        var pendientes = 0;
        $.each(rows, function (_, value) {
            if (String(value.estado || '').toLowerCase() === 'pendiente') {
                pendientes++;
            }
            var $tr = $('<tr></tr>');
            $tr.append($('<td></td>').text(fechaMovimientoArbolSpTexto(value.fechaenvio) || '—'));
            $tr.append($('<td></td>').text((value.enviousuarios && value.enviousuarios.nombre) || '—'));
            $tr.append($('<td></td>').text(value.nivel !== undefined && value.nivel !== null ? value.nivel : '—'));
            $tr.append($('<td></td>').text(value.estado || '—'));
            $tr.append($('<td></td>').text(fechaMovimientoArbolSpTexto(value.fechaproceso) || '—'));
            $tr.append($('<td></td>').text((value.destinatariousuarios && value.destinatariousuarios.nombre) || '—'));
            var obs = value.observacion || '';
            $tr.append($('<td></td>').attr('title', obs).text(obs !== '' ? obs : '—'));
            $w.append($tr);
        });
        spActualizarBadgeArbol(pendientes);
    }).fail(function () {
        $w.empty().append(
            '<tr><td colspan="7" class="text-center text-danger">No se pudieron cargar los movimientos del árbol de aprobación.</td></tr>'
        );
    });
}

function spActualizarBadgeArbol(pendientes) {
    var $link = $('#tab-arbol-link');
    if (!$link.length) {
        return;
    }
    $link.find('.badge').remove();
    if (pendientes > 0) {
        $link.append(
            $('<span class="badge badge-warning ml-1" title="Pendientes de aprobación"></span>').text(pendientes)
        );
    }
}

function activaEventos(flInicio) {
    if (!flInicio) {
        $('.consultacuentacontable').off('click');
        $('.codigocuentacontable').off('change');
    }

    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }
    if (typeof activa_eventos_consulta_cuentacontable === 'function') {
        activa_eventos_consulta_cuentacontable();
    }
    if (typeof activa_eventos_consultaconcepto_solicitudpago === 'function') {
        activa_eventos_consultaconcepto_solicitudpago();
    }
}

/**
 * Misma técnica que concepto_solicitudpago/crear.js: append del HTML del <template>.
 * No usar $(html) sobre <tr> suelto (el browser lo descarta).
 */
function spHtmlFilaCuentaTemplate() {
    var $tpl = $('#template-renglon-sp-cuenta');
    if (!$tpl.length) {
        console.error('SP: no existe #template-renglon-sp-cuenta');
        return '';
    }
    var html = $tpl.html();
    if ((!html || !$.trim(html)) && $tpl[0] && $tpl[0].content) {
        var tmp = document.createElement('tbody');
        tmp.appendChild($tpl[0].content.cloneNode(true));
        html = tmp.innerHTML;
    }
    return $.trim(html || '');
}

function spAgregarFilaCuentaVacia() {
    var html = spHtmlFilaCuentaTemplate();
    if (!html) {
        return $();
    }
    var $tbody = $('#tbody-solicitudpago-cuenta-table');
    $tbody.append(html);
    return $tbody.find('tr.item-sp-cuenta').last();
}

function agregaRenglonCuenta(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    var $row = spAgregarFilaCuentaVacia();
    if (!$row.length) {
        alert('No se pudo agregar la fila de cuenta (plantilla vacía).');
        return;
    }
    spAplicarLadoDebeHaber($row, false);
    activaEventos(false);
}

function borraRenglonCuenta(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
}

function agregaRenglonCuota(event) {
    event.preventDefault();
    var html = $('#template-renglon-sp-cuota').html();
    $('#tbody-solicitudpago-cuota-table').append(html);
    renumerarCuotas();
}

function borraRenglonCuota(event) {
    event.preventDefault();
    $(this).closest('tr').remove();
    renumerarCuotas();
}

function renumerarCuotas() {
    var n = 1;
    $('#tbody-solicitudpago-cuota-table .nro-cuota').each(function () {
        $(this).val(n++);
    });
}

function importarCuotasSolicitudpagoExcel(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    var $btn = $('#btn-importar-cuotas-sp');
    var url = $btn.data('url');
    var fileInput = document.getElementById('archivo_cuotas_import');
    if (!url || !fileInput || !fileInput.files || !fileInput.files.length) {
        alert('Seleccione un archivo Excel de cuotas.');
        return;
    }
    var $form = $('<form>', { method: 'POST', action: url, enctype: 'multipart/form-data' });
    $form.append($('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val() }));
    // Mover el file al form temporal (clone no copia FileList)
    var $file = $(fileInput);
    var $clone = $file.clone();
    $file.attr('name', 'archivo_cuotas');
    $form.append($file);
    $('body').append($form);
    $form.trigger('submit');
    // Restaurar input en pantalla
    $('#sp-importar-cuotas-wrap').prepend($clone.attr('id', 'archivo_cuotas_import').removeAttr('name'));
}

function agregaRenglonArchivo(event) {
    event.preventDefault();
    var renglon = $('#template-renglon-archivo').html();
    $('#tbody-tabla-archivo').append(renglon);
}

function borraRenglonArchivo(event) {
    event.preventDefault();
    $(this).parents('tr').remove();
}

function borraTarjetaArchivoSolicitudpago(event) {
    event.preventDefault();
    var $wrap = $(this).closest('.solicitudpago-archivo-item');
    if ($wrap.length) {
        $wrap.remove();
        return;
    }
    $(this).closest('.col-md-6').remove();
}

function actualizaArchivo(elem) {
    var fn = $(elem).val();
    var filename = fn.match(/[^\\/]*$/)[0];
    $(elem).parents('tr').find('.nombresanteriores').val(filename);
}

function spFormaPagoConcepto() {
    return String($('#concepto_forma_pago').val() || '').toUpperCase();
}

/** SP hija: el plan de cuotas está en la madre. */
function spEsHija() {
    return parseInt($('input[name="solicitudpago_madre_id"]').val() || '0', 10) > 0;
}

/** Solo obligatorio si el concepto es CUOTAS y no es SP hija. */
function spUsaCuotas() {
    if (spEsHija()) {
        return false;
    }
    return spFormaPagoConcepto() === 'CUOTAS';
}

/** Mostrar bloque: CUOTAS, o Plan/Recurrente sin concepto cargado. Nunca en hijas. */
function spMuestraBloqueCuotas() {
    if (spEsHija()) {
        return false;
    }
    var formaPago = spFormaPagoConcepto();
    if (formaPago === 'CUOTAS') {
        return true;
    }
    if (formaPago === 'SIN_CUOTAS') {
        return false;
    }
    var conceptoId = parseInt($('#concepto_solicitudpago_id').val() || '0', 10);
    if (conceptoId > 0) {
        return false;
    }
    var tratamiento = ($('#tratamiento').val() || '').toUpperCase();
    return tratamiento === 'PLAN_DE_PAGO' || tratamiento === 'RECURRENTE';
}

function actualizarVisibilidadCuotas() {
    var mostrar = spMuestraBloqueCuotas();
    var esHija = spEsHija();
    var $bloque = $('#bloque-cuotas');
    if (mostrar) {
        $bloque.show();
        $('#bloque-cuotas-aviso').hide();
        $bloque.find('input, select, textarea, button').prop('disabled', false);
    } else {
        $bloque.hide();
        // En hijas el aviso propio está en el partial; no mostrar el genérico.
        if (esHija) {
            $('#bloque-cuotas-aviso').hide();
        } else {
            $('#bloque-cuotas-aviso').show();
        }
        // Evita que required ocultos / jquery.validate / validarCamposObligatorios bloqueen el grabar.
        $bloque.find('input, select, textarea').prop('disabled', true);
        if (!esHija) {
            $('#tbody-solicitudpago-cuota-table').empty();
        }
    }
}

function spCuotasValidas() {
    if (!spUsaCuotas()) {
        return true;
    }
    var ok = false;
    $('#tbody-solicitudpago-cuota-table tr.item-sp-cuota').each(function () {
        var $row = $(this);
        var vto = String($row.find('input[name="fecha_vencimientos_cuota[]"]').val() || '').trim();
        var monto = parseFloat(String($row.find('input[name="montos_cuota[]"]').val() || '0').replace(',', '.')) || 0;
        if (vto !== '' && monto > 0) {
            ok = true;
            return false;
        }
    });
    return ok;
}

function onSectorSolicitudpagoChange() {
    if (window.__spIgnorarCambioSector) {
        return;
    }
    var sectorId = String($('#sector_solicitudpago_id').val() || '');
    var conceptoId = parseInt($('#concepto_solicitudpago_id').val() || '0', 10);
    if (!conceptoId) {
        return;
    }
    $.get(carpetaBase + '/solicitudpago/concepto_solicitudpago/leer/' + conceptoId)
        .done(function (data) {
            if (!data || !data.id) {
                if (typeof limpiarConceptoSolicitudpagoEnPantalla === 'function') {
                    limpiarConceptoSolicitudpagoEnPantalla();
                }
                spAplicarCuentasDesdeConcepto([]);
                return;
            }
            var conceptoSector = data.sector_solicitudpago_id ? String(data.sector_solicitudpago_id) : '';
            if (sectorId !== '' && conceptoSector !== '' && sectorId !== conceptoSector) {
                if (typeof limpiarConceptoSolicitudpagoEnPantalla === 'function') {
                    limpiarConceptoSolicitudpagoEnPantalla();
                }
                spAplicarCuentasDesdeConcepto([]);
                alert('El concepto cargado no pertenece al sector seleccionado. Seleccione otro concepto.');
            }
        });
}

function onConceptoSolicitudpagoChange(event, data) {
    actualizarVisibilidadCuotas();

    // Ya se aplicó/disparó la carga en aplicarConceptoSolicitudpagoEnPantalla.
    if (data && data.__cuentasYaAplicadas) {
        return;
    }

    var conceptoId = parseInt($('#concepto_solicitudpago_id').val() || '0', 10);

    if (data && Object.prototype.hasOwnProperty.call(data, 'cuentas') && Array.isArray(data.cuentas) && data.cuentas.length) {
        spAplicarCuentasDesdeConcepto(data.cuentas);
        return;
    }

    if (!conceptoId) {
        spAplicarCuentasDesdeConcepto([]);
        return;
    }

    spCargarCuentasDesdeServidor(conceptoId);
}

/**
 * Fuente de verdad: GET cuentas-template del concepto.
 * Usa secuencia para ignorar respuestas viejas si el usuario cambia de concepto rápido.
 */
window.spCargarCuentasDesdeServidor = function (conceptoId) {
    conceptoId = parseInt(conceptoId || '0', 10);
    if (!conceptoId) {
        spAplicarCuentasDesdeConcepto([]);
        return;
    }

    window.__spCuentasReqSeq = (window.__spCuentasReqSeq || 0) + 1;
    var seq = window.__spCuentasReqSeq;

    var params = {};
    var empresaId = parseInt($('#empresa_id').val() || '0', 10);
    if (empresaId > 0) {
        params.empresa_id = empresaId;
    }

    $.ajax({
        url: carpetaBase + '/solicitudpago/concepto_solicitudpago/' + conceptoId + '/cuentas-template',
        method: 'GET',
        dataType: 'json',
        data: params
    })
        .done(function (resp) {
            if (seq !== window.__spCuentasReqSeq) {
                return;
            }
            var cuentas = (resp && resp.cuentas) ? resp.cuentas : [];
            spAplicarCuentasDesdeConcepto(cuentas);
            if (!cuentas.length) {
                console.warn('SP: el concepto '+conceptoId+' no tiene cuentas contables para la empresa seleccionada');
            }
        })
        .fail(function (xhr) {
            if (seq !== window.__spCuentasReqSeq) {
                return;
            }
            console.error('SP cuentas-template error', xhr && xhr.status, xhr && xhr.responseText);
            alert('No se pudieron cargar las cuentas contables del concepto.');
        });
};

/**
 * @param {Array|null} cuentas  null = no tocar; [] = vaciar; array = reemplazar
 */
window.spAplicarCuentasDesdeConcepto = function (cuentas) {
    if (cuentas === null || cuentas === undefined) {
        return;
    }

    var $tbody = $('#tbody-solicitudpago-cuenta-table');
    if (!$tbody.length) {
        console.error('SP: no existe #tbody-solicitudpago-cuenta-table');
        return;
    }

    var lista = Array.isArray(cuentas) ? cuentas : [];
    // Si viene como objeto indexado, convertir a array.
    if (!Array.isArray(cuentas) && cuentas && typeof cuentas === 'object') {
        lista = Object.keys(cuentas).map(function (k) { return cuentas[k]; });
    }

    $tbody.empty();

    if (lista.length === 0) {
        activaEventos(false);
        spActualizarEstadoAsiento();
        return;
    }

    var montoCabecera = parseFloat(String($('#monto').val() || '0').replace(',', '.')) || 0;
    var agregadas = 0;

    lista.forEach(function (cta, idx) {
        if (!cta) {
            return;
        }
        var $row = spAgregarFilaCuentaVacia();
        if (!$row || !$row.length) {
            console.error('SP: no se pudo agregar fila de cuenta', idx);
            return;
        }

        var empresaId = cta.empresa_id || '';
        if (empresaId) {
            $row.find('select[name="empresa_ids[]"]').val(String(empresaId));
            $row.find('input[name="empresa_ids[]"]').val(String(empresaId));
        }

        $row.find('.cuentacontable_id').val(cta.cuentacontable_id || '');
        $row.find('.cuentacontable_id_previa').val(cta.cuentacontable_id || '');
        $row.find('.codigocuentacontable').val(cta.codigo != null ? cta.codigo : '');
        $row.find('.nombrecuentacontable').val(cta.nombre != null ? cta.nombre : '');
        $row.find('.codigo_previo').val(cta.codigo != null ? cta.codigo : '');

        if (cta.centrocosto_id) {
            $row.find('select[name="centrocosto_ids[]"]').val(String(cta.centrocosto_id));
        }

        var dh = (String(cta.debe_haber || 'D').toUpperCase() === 'H') ? 'H' : 'D';
        // La plantilla del concepto suele mandar monto 0: usar cabecera en la 1ª línea.
        var montoRaw = cta.monto;
        var montoNum = parseFloat(String(montoRaw != null ? montoRaw : '').replace(',', '.'));
        var montoFila;
        if (!isFinite(montoNum) || montoNum === 0) {
            montoFila = idx === 0 ? montoCabecera : 0;
        } else {
            montoFila = montoNum;
        }

        $row.find('select.debe_haber').val(dh);
        $row.find('.monto-debe').val(dh === 'D' && montoFila > 0 ? montoFila : '');
        $row.find('.monto-haber').val(dh === 'H' && montoFila > 0 ? montoFila : '');
        spAplicarLadoDebeHaber($row, false);

        agregadas++;
    });

    if (!agregadas) {
        console.error('SP: no se agregó ninguna fila de cuenta (revisar plantilla HTML)');
    }

    activaEventos(false);
    spActualizarEstadoAsiento();
};

function spParseMonto($input) {
    return parseFloat(String($input.val() || '0').replace(',', '.')) || 0;
}

/**
 * Habilita solo el importe del lado elegido (D → Debe, H → Haber).
 * @param {boolean} limpiarOtro  si true, borra el importe del lado que se deshabilita
 */
function spAplicarLadoDebeHaber($row, limpiarOtro) {
    if (!$row || !$row.length) {
        return;
    }
    var dh = (String($row.find('select.debe_haber').val() || 'D').toUpperCase() === 'H') ? 'H' : 'D';
    var $debe = $row.find('.monto-debe');
    var $haber = $row.find('.monto-haber');

    if (dh === 'D') {
        $debe.prop('readonly', false).removeClass('bg-light');
        if (limpiarOtro) {
            $haber.val('');
        }
        $haber.prop('readonly', true).addClass('bg-light');
    } else {
        $haber.prop('readonly', false).removeClass('bg-light');
        if (limpiarOtro) {
            $debe.val('');
        }
        $debe.prop('readonly', true).addClass('bg-light');
    }

    spSincronizarHiddenFila($row);
}

function spSincronizarHiddenFila($row) {
    if (!$row || !$row.length) {
        return;
    }
    var dh = (String($row.find('select.debe_haber').val() || 'D').toUpperCase() === 'H') ? 'H' : 'D';
    var debe = spParseMonto($row.find('.monto-debe'));
    var haber = spParseMonto($row.find('.monto-haber'));
    var monto = dh === 'H' ? haber : debe;
    $row.find('.monto_cuenta').val(monto);
}

function spSincronizarHiddenTodas() {
    $('#tbody-solicitudpago-cuenta-table tr.item-sp-cuenta').each(function () {
        spAplicarLadoDebeHaber($(this), false);
        spSincronizarHiddenFila($(this));
    });
}

function spAsientoValido() {
    var ok = false;
    $('#tbody-solicitudpago-cuenta-table tr.item-sp-cuenta').each(function () {
        var $row = $(this);
        var cuentaId = parseInt($row.find('.cuentacontable_id').val(), 10) || 0;
        var dh = (String($row.find('select.debe_haber').val() || 'D').toUpperCase() === 'H') ? 'H' : 'D';
        var monto = dh === 'H'
            ? spParseMonto($row.find('.monto-haber'))
            : spParseMonto($row.find('.monto-debe'));
        if (cuentaId > 0 && monto > 0) {
            ok = true;
            return false;
        }
    });
    return ok;
}

function spTotalesAsiento() {
    var totalDebe = 0;
    var totalHaber = 0;
    $('#tbody-solicitudpago-cuenta-table tr.item-sp-cuenta').each(function () {
        var $row = $(this);
        totalDebe += spParseMonto($row.find('.monto-debe'));
        totalHaber += spParseMonto($row.find('.monto-haber'));
    });
    return { debe: totalDebe, haber: totalHaber };
}

function spAsientoBalanceado() {
    var t = spTotalesAsiento();
    return Math.abs(t.debe - t.haber) < 0.009;
}

function spActualizarEstadoAsiento() {
    var totalDebe = 0;
    var totalHaber = 0;
    $('#tbody-solicitudpago-cuenta-table tr.item-sp-cuenta').each(function () {
        var $row = $(this);
        spAplicarLadoDebeHaber($row, false);
        totalDebe += spParseMonto($row.find('.monto-debe'));
        totalHaber += spParseMonto($row.find('.monto-haber'));
        spSincronizarHiddenFila($row);
    });

    var fmt = function (n) {
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    $('#sp-total-debe').text(fmt(totalDebe));
    $('#sp-total-haber').text(fmt(totalHaber));

    var valido = spAsientoValido();
    var balanceado = spAsientoBalanceado();
    $('#sp-aviso-asiento-vacio').toggleClass('d-none', valido);
    $('#sp-aviso-asiento-desbalance').toggleClass('d-none', !valido || balanceado);
    $('#sp-badge-asiento').toggleClass('d-none', valido && balanceado);
}
