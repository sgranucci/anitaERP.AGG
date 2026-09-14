/**
 * Depósito CHT individual y masivo.
 */
(function ($) {
    'use strict';

    var chequeIdActual = 0;
    var chequeIdsMasivo = [];

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function urlConId(plantilla, id) {
        return String(plantilla || '').replace(':id', String(id)).replace('{id}', String(id));
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

    function syncBotonMasivo() {
        var n = idsSeleccionados().length;
        var $btn = $('#btn-deposito-masivo');
        if (!$btn.length) {
            return;
        }
        // No usar disabled nativo: no dispara click y parece "roto".
        $btn.toggleClass('disabled', n === 0);
        $btn.attr('aria-disabled', n === 0 ? 'true' : 'false');
        $btn.css('opacity', n === 0 ? '0.55' : '1');
        $btn.attr('title', n
            ? ('Depositar ' + n + ' cheque(s) seleccionado(s)')
            : 'Seleccioná cheques con el checkbox y luego depositá');
        $btn.html('<i class="fa fa-university"></i> Depositar sel.' + (n ? ' (' + n + ')' : ''));
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

    function abrirModal(refTexto, multi) {
        $('#deposito-cheque-ref').text(refTexto || '');
        $('#deposito_fecha').val(new Date().toISOString().slice(0, 10));
        $('#deposito_nro_boleta').val('');
        $('#deposito-modo-masivo').val(multi ? '1' : '0');
        $('#modalDepositoCheque').modal('show');
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
        abrirModal($(this).data('cheque-ref') || ('#' + chequeIdActual), false);
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
        abrirModal(chequeIdsMasivo.length + ' cheques seleccionados', true);
    });

    $(document).on('click', '#deposito_cheque_confirmar', function (e) {
        e.preventDefault();
        var urls = window.chequeDepositoUrls || {};
        var cuentacajaId = parseInt($('#deposito_cuentacaja_id').val(), 10) || 0;
        var masivo = $('#deposito-modo-masivo').val() === '1';
        if (cuentacajaId <= 0) {
            avisar('Seleccione la cuenta de caja.');
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

        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            data: JSON.stringify(payload)
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                avisar((resp && resp.error) ? resp.error : 'No se pudo depositar.');
                return;
            }
            var msg = 'Cheque depositado.';
            if (masivo && resp.data) {
                msg = 'Depositados OK: ' + (resp.data.ok || 0) + ' — errores: ' + (resp.data.error || 0);
            } else if (resp.data && resp.data.anita_ok === false) {
                msg += ' (Anita no actualizado; revisar log).';
            }
            $('#modalDepositoCheque').one('hidden.bs.modal', function () {
                setTimeout(function () {
                    alert(msg);
                    window.location.reload();
                }, 0);
            });
            $('#modalDepositoCheque').modal('hide');
        }).fail(function (xhr) {
            avisar((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al depositar.');
        });
    });

    $(function () { syncBotonMasivo(); });
})(jQuery);
