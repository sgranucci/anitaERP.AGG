/**
 * Depósito CHT individual y masivo.
 */
(function ($) {
    'use strict';

    var chequeIdActual = 0;
    var chequeIdsMasivo = [];
    var chequeTotalTexto = '';
    var abriendoConsultaCc = false;

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function carpetaApp() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function urlConId(plantilla, id) {
        return String(plantilla || '').replace(':id', String(id)).replace('{id}', String(id));
    }

    function formatMonto(n) {
        var num = Number(n) || 0;
        var parts = (Math.round(num * 100) / 100).toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts[0] + ',' + parts[1];
    }

    function textoTotales(porMoneda) {
        var keys = Object.keys(porMoneda);
        if (!keys.length) {
            return '';
        }
        return keys.map(function (mon) {
            return mon + ' ' + formatMonto(porMoneda[mon]);
        }).join(' · ');
    }

    function idsSeleccionados() {
        var ids = [];
        $('.cheque-select-row:checked').each(function () {
            var id = parseInt($(this).val(), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function empresaIdDeSeleccion($origen) {
        if ($origen && $origen.length) {
            var delBoton = parseInt($origen.attr('data-empresa-id') || '0', 10) || 0;
            if (delBoton > 0) {
                return delBoton;
            }
        }
        var vistas = {};
        $('.cheque-select-row:checked').each(function () {
            var emp = parseInt($(this).attr('data-empresa-id') || '0', 10) || 0;
            if (emp > 0) {
                vistas[emp] = true;
            }
        });
        var keys = Object.keys(vistas);
        if (keys.length === 1) {
            return parseInt(keys[0], 10) || 0;
        }
        return 0;
    }

    function $campoCuentacaja() {
        return $('#modalDepositoCheque .tm-cuentacaja-campo');
    }

    function limpiarCuentacajaDeposito(mantenerCodigo) {
        var $ctx = $campoCuentacaja();
        $ctx.find('.cuentacaja_id').val('');
        if (!mantenerCodigo) {
            $ctx.find('.codigocuentacaja').val('');
        }
        $ctx.find('.codigocuentacaja').removeAttr('data-cuentacaja-invalido');
        $ctx.find('.descripcioncuentacaja').val('');
        $ctx.find('.btn-link-editar-cuentacaja').attr('href', '#').addClass('d-none');
    }

    function asignarCuentacajaDeposito(data) {
        var $ctx = $campoCuentacaja();
        if (!$ctx.length || !data) {
            return;
        }
        var id = parseInt(data.id, 10) || 0;
        $ctx.find('.cuentacaja_id').val(id > 0 ? String(id) : '');
        $ctx.find('.codigocuentacaja').val(data.codigo || '').removeAttr('data-cuentacaja-invalido');
        $ctx.find('.descripcioncuentacaja').val(data.nombre || '');
        var $link = $ctx.find('.btn-link-editar-cuentacaja');
        if ($link.length) {
            if (id > 0) {
                $link.attr('href', carpetaApp() + '/caja/cuentacaja/' + id + '/editar?origen=modal_consulta&vista=consulta')
                    .removeClass('d-none');
            } else {
                $link.attr('href', '#').addClass('d-none');
            }
        }
    }

    function setEmpresaDeposito(empresaId) {
        var id = parseInt(empresaId, 10) || 0;
        $('#modalDepositoCheque #empresa_id').val(id > 0 ? String(id) : '');
    }

    function modalConsultaCuentacajaAbierto() {
        var $m = $('#consultacuentacajaModal');
        return $m.hasClass('show') || $m.hasClass('showing') || $m.is(':visible') || abriendoConsultaCc;
    }

    function abrirModalConsultaCuentacaja() {
        abriendoConsultaCc = true;
        $('#consultaempresacaja_id').val($('#modalDepositoCheque #empresa_id').val() || '');
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').css('z-index', 1065);
        $('#consultacuentacajaModal').one('shown.bs.modal.chequeDepCc', function () {
            abriendoConsultaCc = false;
            $('.modal-backdrop').last().css('z-index', 1060);
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function resolverCuentacajaPorCodigo(alertar) {
        var $ctx = $campoCuentacaja();
        var $codigo = $ctx.find('.codigocuentacaja');
        var codigo = $.trim($codigo.val() || '');
        if (codigo === '') {
            limpiarCuentacajaDeposito(false);
            return;
        }
        if ($codigo.attr('data-cuentacaja-invalido') === codigo) {
            return;
        }
        $.ajax({
            url: carpetaApp() + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo),
            type: 'GET',
            dataType: 'json',
            data: { empresa_id: $('#modalDepositoCheque #empresa_id').val() || '' }
        }).done(function (data) {
            if (data && data.id) {
                asignarCuentacajaDeposito(data);
                return;
            }
            limpiarCuentacajaDeposito(true);
            $codigo.attr('data-cuentacaja-invalido', codigo);
            if (alertar) {
                avisarEnDeposito('Cuenta de caja no encontrada.');
                setTimeout(function () { $codigo.trigger('focus'); }, 0);
            }
        }).fail(function (xhr) {
            limpiarCuentacajaDeposito(true);
            $codigo.attr('data-cuentacaja-invalido', codigo);
            if (alertar) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'Cuenta de caja no encontrada.';
                avisarEnDeposito(msg);
                setTimeout(function () { $codigo.trigger('focus'); }, 0);
            }
        });
    }

    function resumenSeleccion() {
        var n = 0;
        var porMoneda = {};
        $('.cheque-select-row:checked').each(function () {
            n += 1;
            var monto = parseFloat($(this).attr('data-monto') || '0') || 0;
            var mon = String($(this).attr('data-moneda') || '$').trim() || '$';
            porMoneda[mon] = (porMoneda[mon] || 0) + monto;
        });
        return { n: n, porMoneda: porMoneda, texto: textoTotales(porMoneda) };
    }

    function syncBotonMasivo() {
        var resumen = resumenSeleccion();
        var n = resumen.n;
        var $btn = $('#btn-deposito-masivo');
        var $resumen = $('#cheque-seleccion-resumen');
        if ($btn.length) {
            // No usar disabled nativo: no dispara click y parece "roto".
            $btn.toggleClass('disabled', n === 0);
            $btn.attr('aria-disabled', n === 0 ? 'true' : 'false');
            $btn.css('opacity', n === 0 ? '0.55' : '1');
            $btn.attr('title', n
                ? ('Depositar ' + n + ' cheque(s) — ' + resumen.texto)
                : 'Seleccioná cheques con el checkbox y luego depositá');
            $btn.html('<i class="fa fa-university"></i> Depositar sel.' + (n ? ' (' + n + ')' : ''));
        }
        if ($resumen.length) {
            if (n > 0) {
                $resumen
                    .text('Total: ' + n + ' cheque' + (n === 1 ? '' : 's') + ' · ' + resumen.texto)
                    .show();
            } else {
                $resumen.text('').hide();
            }
        }
    }

    function avisarEnDeposito(msg) {
        var $consulta = $('#consultacuentacajaModal');
        if ($consulta.hasClass('show')) {
            $consulta.one('hidden.bs.modal', function () {
                setTimeout(function () { alert(msg); }, 0);
            });
            $consulta.modal('hide');
            return;
        }
        setTimeout(function () { alert(msg); }, 0);
    }

    function avisar(msg) {
        var $modal = $('#modalDepositoCheque');
        if ($modal.hasClass('show')) {
            $modal.one('hidden.bs.modal', function () {
                setTimeout(function () { alert(msg); }, 0);
            });
            $modal.modal('hide');
        } else {
            setTimeout(function () { alert(msg); }, 0);
        }
    }

    function setTotalModal(texto) {
        chequeTotalTexto = texto || '';
        var $wrap = $('#deposito-cheque-total-wrap');
        var $val = $('#deposito-cheque-total');
        if (!$wrap.length) {
            return;
        }
        if (chequeTotalTexto) {
            $val.text(chequeTotalTexto);
            $wrap.removeClass('d-none');
        } else {
            $val.text('');
            $wrap.addClass('d-none');
        }
    }

    function abrirModal(refTexto, multi, totalTexto, empresaId) {
        $('#deposito-cheque-ref').text(refTexto || '');
        $('#deposito_fecha').val(new Date().toISOString().slice(0, 10));
        $('#deposito_nro_boleta').val('');
        $('#deposito-modo-masivo').val(multi ? '1' : '0');
        setEmpresaDeposito(empresaId);
        limpiarCuentacajaDeposito(false);
        setTotalModal(totalTexto || '');
        $('#modalDepositoCheque').one('shown.bs.modal.chequeDepFocus', function () {
            $campoCuentacaja().find('.codigocuentacaja').trigger('focus');
        });
        $('#modalDepositoCheque').modal('show');
    }

    function abrirPdfDeposito(url, win) {
        if (!url) {
            if (win && !win.closed) {
                try { win.close(); } catch (e) {}
            }
            return;
        }
        if (win && !win.closed) {
            try {
                win.location = url;
                return;
            } catch (e) {}
        }
        window.open(url, '_blank', 'noopener');
    }

    $(document).on('change', '#cheque-select-all', function () {
        var on = $(this).is(':checked');
        $('.cheque-select-row').prop('checked', on);
        syncBotonMasivo();
    });

    $(document).on('change', '.cheque-select-row', syncBotonMasivo);

    $(document).on('click', '.btn-deposito-cheque', function (e) {
        e.preventDefault();
        chequeIdActual = parseInt($(this).data('cheque-id'), 10) || 0;
        chequeIdsMasivo = [];
        if (chequeIdActual <= 0) {
            return;
        }
        var monto = parseFloat($(this).attr('data-cheque-monto') || '0') || 0;
        var moneda = String($(this).attr('data-cheque-moneda') || '$').trim() || '$';
        var total = moneda + ' ' + formatMonto(monto);
        abrirModal($(this).data('cheque-ref') || ('#' + chequeIdActual), false, total, empresaIdDeSeleccion($(this)));
    });

    $(document).on('click', '#btn-deposito-masivo', function (e) {
        e.preventDefault();
        chequeIdsMasivo = idsSeleccionados();
        chequeIdActual = 0;
        if (!chequeIdsMasivo.length) {
            var hayChecks = $('.cheque-select-row').length > 0;
            if (!hayChecks) {
                alert('No hay cheques depositables en esta página.\nUsá la vista rápida «Para depositar».');
            } else {
                alert('Seleccioná uno o más cheques con el checkbox de la izquierda (o el de la cabecera).');
            }
            return;
        }
        var resumen = resumenSeleccion();
        abrirModal(chequeIdsMasivo.length + ' cheques seleccionados', true, resumen.texto, empresaIdDeSeleccion(null));
    });

    $(document).on('click', '#modalDepositoCheque .consultacuentacaja', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaCuentacaja();
    });

    $(document).on('keydown', '#modalDepositoCheque .codigocuentacaja', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            abrirModalConsultaCuentacaja();
            return;
        }
        if (e.which === 13 || e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            resolverCuentacajaPorCodigo(true);
        }
    });

    $(document).on('blur', '#modalDepositoCheque .codigocuentacaja', function () {
        if (modalConsultaCuentacajaAbierto()) {
            return;
        }
        var id = parseInt($campoCuentacaja().find('.cuentacaja_id').val() || '0', 10) || 0;
        if (id > 0) {
            return;
        }
        resolverCuentacajaPorCodigo(false);
    });

    $(document).on('input', '#modalDepositoCheque .codigocuentacaja', function () {
        $campoCuentacaja().find('.cuentacaja_id').val('');
        $campoCuentacaja().find('.descripcioncuentacaja').val('');
        $(this).removeAttr('data-cuentacaja-invalido');
    });

    $(document).on('click.chequeDepCcElige', '.eligeconsultacuentacaja', function (e) {
        if (!$('#modalDepositoCheque').hasClass('show')) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        var $tr = $(this).closest('tr');
        asignarCuentacajaDeposito({
            id: $tr.find('.cuentacaja_id').text().trim(),
            codigo: $tr.find('.codigo').text().trim(),
            nombre: $tr.find('.nombre').text().trim()
        });
        $('#consultacuentacajaModal').modal('hide');
        setTimeout(function () {
            $('#deposito_nro_boleta').trigger('focus');
        }, 0);
    });

    $(function () {
        syncBotonMasivo();
        $('#consultacuentacajaModal').on('hidden.bs.modal.chequeDepCc', function () {
            abriendoConsultaCc = false;
            if ($('#modalDepositoCheque').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });
    });

    $(document).on('click', '#deposito_cheque_confirmar', function (e) {
        e.preventDefault();
        var urls = window.chequeDepositoUrls || {};
        var cuentacajaId = parseInt($('#deposito_cuentacaja_id').val(), 10) || 0;
        var masivo = $('#deposito-modo-masivo').val() === '1';
        if (cuentacajaId <= 0) {
            avisarEnDeposito('Ingrese la cuenta de caja (código + Enter, o F1).');
            $campoCuentacaja().find('.codigocuentacaja').trigger('focus');
            return;
        }

        var payload = {
            _token: csrfToken(),
            cuentacaja_id: cuentacajaId,
            fecha: $('#deposito_fecha').val(),
            nro_boleta: $('#deposito_nro_boleta').val()
        };
        var url = '';
        if (masivo) {
            url = urls.depositarMasivo || '';
            payload.cheque_ids = chequeIdsMasivo;
        } else {
            url = urlConId(urls.depositar, chequeIdActual);
            if (!url || chequeIdActual <= 0) {
                avisar('URL de depósito no configurada.');
                return;
            }
        }
        if (!url) {
            avisar('URL de depósito masivo no configurada.');
            return;
        }

        var pdfWin = null;
        try {
            pdfWin = window.open('about:blank', 'cheque_deposito_pdf');
        } catch (err) {
            pdfWin = null;
        }

        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            data: JSON.stringify(payload)
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                if (pdfWin && !pdfWin.closed) {
                    try { pdfWin.close(); } catch (e2) {}
                }
                avisar((resp && resp.error) ? resp.error : 'No se pudo depositar.');
                return;
            }
            var pdfUrl = (resp.data && resp.data.url_comprobante_pdf) ? resp.data.url_comprobante_pdf : '';
            abrirPdfDeposito(pdfUrl, pdfWin);
            var msg = 'Cheque depositado. Se abrió el PDF para archivar.';
            if (masivo && resp.data) {
                msg = 'Depositados OK: ' + (resp.data.ok || 0) + ' — errores: ' + (resp.data.error || 0);
                if (pdfUrl) {
                    msg += '. Se abrió el PDF para archivar.';
                }
            } else if (resp.data && resp.data.anita_ok === false) {
                msg += ' (Anita no actualizado; revisar log).';
            }
            if (!pdfUrl) {
                msg = msg.replace(' Se abrió el PDF para archivar.', '');
            }
            $('#modalDepositoCheque').one('hidden.bs.modal', function () {
                setTimeout(function () {
                    alert(msg);
                    window.location.reload();
                }, 0);
            });
            $('#modalDepositoCheque').modal('hide');
        }).fail(function (xhr) {
            if (pdfWin && !pdfWin.closed) {
                try { pdfWin.close(); } catch (e3) {}
            }
            avisar((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al depositar.');
        });
    });
})(jQuery);
