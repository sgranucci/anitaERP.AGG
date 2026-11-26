/**
 * SUSS: Liquidación completa (1Q / DJ) y Generar solicitud de pago del VEP.
 * Requiere window.SUSS_URL_PREVIEW (ruta preview_suss_solicitudpago) definida en la vista.
 */
(function ($) {
    'use strict';

    var DEBOUNCE_MS = 350;
    var timerPreview = null;
    var xhrPreview = null;
    var ultimoPreview = null;
    var montoEditado = false;

    // Campos sincronizados entre el modal de liquidación y el de solicitud.
    var PARES = {
        'suss-liq-saf': 'sp-saf',
        'suss-liq-pago': 'sp-pago',
        'suss-liq-pago-fecha': 'sp-pago-fecha'
    };
    var PARES_INV = {};
    Object.keys(PARES).forEach(function (k) { PARES_INV[PARES[k]] = k; });

    function esc(texto) {
        return $('<div>').text(texto == null ? '' : String(texto)).html();
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseMonto(valor) {
        var s = String(valor || '').trim();
        if (s === '') {
            return NaN;
        }
        if (s.indexOf(',') !== -1) {
            s = s.replace(/\./g, '').replace(',', '.');
        }
        var n = Number(s);

        return isFinite(n) ? n : NaN;
    }

    function tipoActual() {
        return String($('input.js-suss-tipo:checked').first().val() || '1q');
    }

    function esDj() {
        return tipoActual() === 'dj';
    }

    function leerFiltros() {
        var mes = String($('#periodo_mes_num').val() || '');
        var anio = String($('#periodo_anio').val() || '');
        var periodo = String($('#periodo').val() || '');
        if (/^\d{2}$/.test(mes) && /^\d{4}$/.test(anio)) {
            periodo = anio + mes;
        }

        return {
            empresa_id: $('#empresa_id').val() || '',
            periodo: periodo,
            periodo_mes_num: mes,
            periodo_anio: anio,
            liquidacion: $('#liquidacion').val() || '',
            conciliar_contable: $('#conciliar_contable').is(':checked') ? 1 : 0
        };
    }

    function leerLiquidacion() {
        return {
            tipo_liquidacion: tipoActual(),
            saldo_favor_anterior: $('#suss-liq-saf').val() || '',
            pago_cuenta: $('#suss-liq-pago').val() || '',
            pago_cuenta_fecha: $('#suss-liq-pago-fecha').val() || ''
        };
    }

    /** Copia los filtros de la pantalla como inputs ocultos del formulario del modal. */
    function armarHidden($form) {
        $form.find('input.js-filtro-copia').remove();
        var filtros = leerFiltros();
        Object.keys(filtros).forEach(function (nombre) {
            $('<input>', { type: 'hidden', name: nombre, value: filtros[nombre], 'class': 'js-filtro-copia' }).appendTo($form);
        });
    }

    function aplicarTipo() {
        var dj = esDj();
        $('#suss-liq-saf, #suss-liq-pago, #suss-liq-pago-fecha, #sp-saf, #sp-pago, #sp-pago-fecha').prop('disabled', !dj);
        $('#sp-f996-wrap').toggleClass('d-none', !dj);
        $('#sp-sire-wrap').toggleClass('d-none', dj);
        $('#sp-adj-f996').prop('required', dj);
        $('#sp-adj-sire').prop('disabled', dj);
    }

    function sincronizarTipo(valor) {
        $('input.js-suss-tipo[value="' + valor + '"]').prop('checked', true);
    }

    function programarPreview() {
        if (timerPreview) {
            clearTimeout(timerPreview);
        }
        timerPreview = setTimeout(pedirPreview, DEBOUNCE_MS);
    }

    function pedirPreview() {
        timerPreview = null;
        if (xhrPreview) {
            xhrPreview.abort();
        }
        var params = $.extend({}, leerFiltros(), leerLiquidacion());
        $('#suss-liq-cuadro').html('<div class="text-muted small">Calculando…</div>');
        $('#sp-cargando').removeClass('d-none');

        xhrPreview = $.ajax({
            url: window.SUSS_URL_PREVIEW,
            method: 'GET',
            data: params,
            dataType: 'json'
        }).done(function (d) {
            ultimoPreview = d;
            renderLiquidacion(d);
            renderSolicitud(d);
        }).fail(function (xhr, estado) {
            if (estado === 'abort') {
                return;
            }
            var d = xhr.responseJSON || {};
            ultimoPreview = { error: d.error || 'No se pudo armar la vista previa.', puede_generar: false, calculo: null };
            renderLiquidacion(ultimoPreview);
            renderSolicitud(ultimoPreview);
        });
    }

    // ---------------------------------------------------------------- Liquidación completa

    function renderLiquidacion(d) {
        var c = d && d.calculo ? d.calculo : null;
        var $cuadro = $('#suss-liq-cuadro');
        var $avisos = $('#suss-liq-avisos');
        var $zip = $('#suss-liq-zip');
        $avisos.empty();

        if (!c) {
            $cuadro.empty();
            if (d && d.error) {
                $avisos.append(alerta('warning', d.error));
            }
            $zip.prop('disabled', false);

            return;
        }

        var html = '<table class="table table-sm table-bordered mb-1"><tbody>';
        var cu = c.cuadro || {};
        if (c.es_dj) {
            html += fila('Retenciones 1Q (' + c.cantidad_q1 + ' registros)', cu.total_q1);
            html += fila('Retenciones 2Q (' + c.cantidad_q2 + ' registros)', cu.total_q2);
            html += filaNegrita('Impuesto determinado 353', cu.determinado);
            html += fila('(−) Saldo a favor período anterior', cu.saf);
            html += fila('(−) Pagos a cuenta' + (cu.pago_cuenta_fecha ? ' (' + cu.pago_cuenta_fecha.split('-').reverse().join('/') + ')' : ''), cu.pago_cuenta);
            html += filaNegrita('Saldo a favor ARCA (VEP)', cu.vep);
        } else {
            html += fila('Retenciones 1Q (' + c.cantidad_q1 + ' registros)', cu.total_q1);
            html += filaNegrita('Total pago a cuenta (VEP)', cu.vep);
        }
        html += '</tbody></table>';

        if (c.conciliacion && c.conciliacion.length) {
            html += '<table class="table table-sm table-bordered mb-1"><thead style="background:#85C1E9;color:#17202A;"><tr>'
                + '<th>Conciliación contra el mayor</th><th class="text-right">Total SUSS</th><th class="text-right">Total mayor</th>'
                + '<th class="text-right">Diferencia</th><th class="text-center">Estado</th></tr></thead><tbody>';
            c.conciliacion.forEach(function (it) {
                html += '<tr><td>' + esc(it.nombre) + '</td>'
                    + '<td class="text-right">' + fmt(it.total_suss) + '</td>'
                    + '<td class="text-right">' + fmt(it.total_mayor) + '</td>'
                    + '<td class="text-right">' + fmt(it.diferencia) + '</td>'
                    + '<td class="text-center">' + (it.cuadra ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Diferencia</span>') + '</td></tr>';
            });
            html += '</tbody></table>';
        }
        $cuadro.html(html);

        var controles = c.controles || {};
        if (controles.bloquea && controles.mensaje) {
            $avisos.append(alerta('danger', controles.mensaje));
        }
        (controles.avisos || []).forEach(function (a) {
            $avisos.append(alerta('warning', a));
        });

        var cantidad = c.es_dj ? c.cantidad_q2 : c.cantidad_q1;
        var bloqueado = !!controles.bloquea || cantidad === 0;
        if (cantidad === 0) {
            $avisos.append(alerta('warning', 'Sin retenciones SUSS en la quincena.'));
        }
        $zip.prop('disabled', bloqueado);
    }

    function fila(rotulo, importe) {
        return '<tr><td>' + esc(rotulo) + '</td><td class="text-right">' + fmt(importe) + '</td></tr>';
    }

    function filaNegrita(rotulo, importe) {
        return '<tr class="font-weight-bold"><td>' + esc(rotulo) + '</td><td class="text-right">' + fmt(importe) + '</td></tr>';
    }

    function alerta(tipo, texto) {
        return $('<div class="alert alert-' + tipo + ' py-2 small mb-1"></div>').text(texto);
    }

    // ---------------------------------------------------------------- Solicitud de pago

    function renderSolicitud(d) {
        $('#sp-cargando').addClass('d-none');
        $('#sp-contenido').removeClass('d-none');
        aplicarTipo();

        var $error = $('#sp-error');
        if (d && d.error) {
            $error.removeClass('d-none').text(d.error);
        } else {
            $error.addClass('d-none').text('');
        }

        if (!d || !d.empresa_label) {
            limpiarSolicitud();
            validarSolicitud();

            return;
        }

        $('#sp-empresa').text(d.empresa_label);
        $('#sp-fecha').text(d.fecha);
        $('#sp-proveedor').text(d.proveedor_label);
        $('#sp-forma').text(d.formapago_label);
        $('#sp-concepto').text(d.concepto_label);
        $('#sp-sector').text(d.sector_label);
        $('#sp-moneda').text(d.moneda_label);
        $('#sp-detalle').text(d.detalle);
        $('#sp-nombre-pdf').text(d.nombre_pdf);

        var $asiento = $('#sp-asiento').empty();
        (d.asiento || []).forEach(function (l) {
            $asiento.append('<tr><td>' + esc(l.cuenta) + '</td><td>' + esc(l.centrocosto) + '</td>'
                + '<td class="text-center">' + esc(l.dh) + '</td>'
                + '<td class="text-right">' + (l.debe != null ? fmt(l.debe) : '') + '</td>'
                + '<td class="text-right">' + (l.haber != null ? fmt(l.haber) : '') + '</td></tr>');
        });

        if (!montoEditado) {
            $('#sp-monto-vep').val(fmt(d.vep_esperado));
        }

        var $dup = $('#sp-duplicado-wrap');
        if (d.duplicado) {
            $('#sp-duplicado-texto').text(d.duplicado.mensaje);
            $dup.removeClass('d-none');
        } else {
            $dup.addClass('d-none');
            $('#sp-generar-igual').prop('checked', false);
        }

        var $avisos = $('#sp-avisos').empty();
        (d.avisos || []).forEach(function (a) {
            $avisos.append(alerta('warning', a));
        });

        validarSolicitud();
    }

    function limpiarSolicitud() {
        $('#sp-empresa, #sp-fecha, #sp-proveedor, #sp-forma, #sp-concepto, #sp-sector, #sp-moneda, #sp-detalle, #sp-nombre-pdf').text('');
        $('#sp-asiento').empty();
        $('#sp-duplicado-wrap').addClass('d-none');
        $('#sp-avisos').empty();
    }

    function validarSolicitud() {
        var d = ultimoPreview || {};
        var ok = !!d.puede_generar;
        var monto = parseMonto($('#sp-monto-vep').val());
        var esperado = Number(d.vep_esperado || 0);
        var diferencia = !(Math.abs(monto - esperado) <= 0.005);
        var $check = $('#sp-vep-check');

        if (ok && isFinite(monto)) {
            if (diferencia) {
                $check.removeClass('alert-secondary alert-success').addClass('alert-warning')
                    .html('VEP ' + fmt(monto) + ' ≠ saldo calculado ' + fmt(esperado) + '. Revise el importe o marque «Confirmo».');
            } else {
                $check.removeClass('alert-secondary alert-warning').addClass('alert-success')
                    .html('VEP ' + fmt(monto) + ' = saldo ' + (esperado ? (esDj() ? 'de la DJ ' : 'del pago a cuenta ') + fmt(esperado) : '') + ' ✔');
            }
        } else {
            $check.removeClass('alert-success alert-warning').addClass('alert-secondary').text('Ingrese el monto del VEP.');
        }

        $('#sp-confirmo-wrap').toggleClass('d-none', !(ok && diferencia));
        if (!(ok && diferencia)) {
            $('#sp-confirmo').prop('checked', false);
        }

        var bloquea = !ok
            || !isFinite(monto) || monto < 0.01
            || !$('#sp-vencimiento').val()
            || (diferencia && !$('#sp-confirmo').is(':checked'))
            || (d.duplicado && !$('#sp-generar-igual').is(':checked'))
            || !$('#sp-adj-vep')[0] || !$('#sp-adj-vep')[0].files.length
            || (esDj() && (!$('#sp-adj-f996')[0] || !$('#sp-adj-f996')[0].files.length));

        $('#sp-submit').prop('disabled', !!bloquea);
    }

    function resetSolicitud() {
        montoEditado = false;
        $('#sp-monto-vep').val('');
        $('#sp-confirmo').prop('checked', false);
        $('#sp-generar-igual').prop('checked', false);
        $('#sp-vencimiento').val('');
        $('#sp-observacion').val('');
        $('#sp-adj-vep, #sp-adj-f996, #sp-adj-sire').val('');
        $('#sp-cargando').removeClass('d-none');
        $('#sp-contenido').addClass('d-none');
    }

    // ---------------------------------------------------------------- Overlay

    function mostrarOverlay() {
        var overlay = document.getElementById('suss-procesando-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('suss-procesando-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    // ---------------------------------------------------------------- Eventos

    $(function () {
        var $liq = $('#form-suss-liquidacion');
        var $sol = $('#form-suss-solicitud');

        $('input.js-suss-tipo').on('change', function () {
            sincronizarTipo($(this).val());
            aplicarTipo();
            programarPreview();
        });

        $(document).on('input change', '#suss-liq-saf, #suss-liq-pago, #suss-liq-pago-fecha, #sp-saf, #sp-pago, #sp-pago-fecha', function () {
            var id = this.id;
            var otro = PARES[id] || PARES_INV[id];
            var $otro = $('#' + otro);
            if ($otro.length) {
                $otro.val($(this).val());
            }
            programarPreview();
        });

        if ($liq.length) {
            $('#modal-suss-liquidacion').on('show.bs.modal', function () {
                armarHidden($liq);
                programarPreview();
            });

            $liq.on('submit', function () {
                armarHidden($liq);
                if (this.checkValidity && !this.checkValidity()) {
                    return true;
                }
                mostrarOverlay();
                setTimeout(ocultarOverlay, 60000);

                return true;
            });

            var iframe = document.getElementById('suss-liquidacion-frame');
            if (iframe) {
                iframe.addEventListener('load', function () {
                    ocultarOverlay();
                    if (ultimoPreview && ultimoPreview.calculo) {
                        $('#modal-suss-liquidacion').modal('hide');
                    }
                });
            }
        }

        if ($sol.length) {
            $('#modal-suss-solicitud').on('show.bs.modal', function () {
                armarHidden($sol);
                resetSolicitud();
                programarPreview();
            });

            $('#sp-monto-vep').on('input change', function () {
                montoEditado = true;
                validarSolicitud();
            });
            $('#sp-vencimiento, #sp-confirmo, #sp-generar-igual').on('change input', validarSolicitud);
            $('#sp-adj-vep, #sp-adj-f996').on('change', validarSolicitud);

            $sol.on('submit', function () {
                armarHidden($sol);
                if (this.checkValidity && !this.checkValidity()) {
                    return true;
                }
                mostrarOverlay();

                return true;
            });
        }

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
