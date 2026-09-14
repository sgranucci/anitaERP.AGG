/**
 * Acreditación de depósitos CHT (conciliación boleta).
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
        $('.conc-select-row:checked').each(function () {
            var id = parseInt($(this).val(), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function syncBoton() {
        var n = idsSeleccionados().length;
        var $btn = $('#btn-acreditar-masivo');
        if (!$btn.length) {
            return;
        }
        $btn.prop('disabled', n === 0);
        $btn.html('<i class="fa fa-check"></i> Acreditar sel.' + (n ? ' (' + n + ')' : ''));
    }

    function avisar(msg) {
        var $modal = $('#modalAcreditarCheque');
        if ($modal.hasClass('show')) {
            $modal.one('hidden.bs.modal', function () {
                setTimeout(function () { alert(msg); }, 0);
            });
            $modal.modal('hide');
        } else {
            setTimeout(function () { alert(msg); }, 0);
        }
    }

    function abrirModal(ref, multi) {
        $('#acreditar-cheque-ref').text(ref || '');
        $('#acreditar_fecha').val(new Date().toISOString().slice(0, 10));
        $('#acreditar-modo-masivo').val(multi ? '1' : '0');
        $('#modalAcreditarCheque').modal('show');
    }

    $(document).on('change', '#conc-select-all', function () {
        $('.conc-select-row').prop('checked', $(this).is(':checked'));
        syncBoton();
    });
    $(document).on('change', '.conc-select-row', syncBoton);

    $(document).on('click', '.btn-acreditar-cheque', function (e) {
        e.preventDefault();
        chequeIdActual = parseInt($(this).data('cheque-id'), 10) || 0;
        chequeIdsMasivo = [];
        if (chequeIdActual <= 0) {
            return;
        }
        abrirModal('#' + chequeIdActual, false);
    });

    $(document).on('click', '#btn-acreditar-masivo', function (e) {
        e.preventDefault();
        chequeIdsMasivo = idsSeleccionados();
        chequeIdActual = 0;
        if (!chequeIdsMasivo.length) {
            return;
        }
        abrirModal(chequeIdsMasivo.length + ' cheques', true);
    });

    $(document).on('click', '#acreditar_cheque_confirmar', function (e) {
        e.preventDefault();
        var urls = window.chequeAcreditarUrls || {};
        var masivo = $('#acreditar-modo-masivo').val() === '1';
        var payload = {
            _token: csrfToken(),
            fecha: $('#acreditar_fecha').val()
        };
        var url = '';
        if (masivo) {
            url = urls.acreditarMasivo || '';
            payload.cheque_ids = chequeIdsMasivo;
        } else {
            url = urlConId(urls.acreditar, chequeIdActual);
        }
        if (!url) {
            avisar('URL de acreditación no configurada.');
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
                avisar((resp && resp.error) ? resp.error : 'No se pudo acreditar.');
                return;
            }
            var msg = 'Acreditado.';
            if (masivo && resp.data) {
                msg = 'Acreditados OK: ' + (resp.data.ok || 0) + ' — errores: ' + (resp.data.error || 0);
            }
            $('#modalAcreditarCheque').one('hidden.bs.modal', function () {
                setTimeout(function () {
                    alert(msg);
                    window.location.reload();
                }, 0);
            });
            $('#modalAcreditarCheque').modal('hide');
        }).fail(function (xhr) {
            avisar((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al acreditar.');
        });
    });

    $(function () { syncBoton(); });
})(jQuery);
