/**
 * Caución CHT individual y masiva.
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

    function idsSeleccionadosCaucion() {
        var ids = [];
        $('.cheque-select-row:checked').each(function () {
            var id = parseInt($(this).val(), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function syncBotonCaucion() {
        var n = idsSeleccionadosCaucion().length;
        var $btn = $('#btn-caucion-masivo');
        if (!$btn.length) {
            return;
        }
        $btn.prop('disabled', n === 0);
        $btn.html('<i class="fa fa-lock"></i> Caucionar sel.' + (n ? ' (' + n + ')' : ''));
    }

    function avisar(msg) {
        var $modal = $('#modalCaucionCheque');
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
        $('#caucion-cheque-ref').text(ref || '');
        $('#caucion_nro').val('');
        $('#caucion_fecha').val(new Date().toISOString().slice(0, 10));
        $('#caucion-modo-masivo').val(multi ? '1' : '0');
        $('#modalCaucionCheque').modal('show');
    }

    $(document).on('change', '#cheque-select-all, .cheque-select-row', syncBotonCaucion);

    $(document).on('click', '.btn-caucion-cheque', function (e) {
        e.preventDefault();
        chequeIdActual = parseInt($(this).data('cheque-id'), 10) || 0;
        chequeIdsMasivo = [];
        if (chequeIdActual <= 0) {
            return;
        }
        abrirModal($(this).data('cheque-ref') || ('#' + chequeIdActual), false);
    });

    $(document).on('click', '#btn-caucion-masivo', function (e) {
        e.preventDefault();
        chequeIdsMasivo = idsSeleccionadosCaucion();
        chequeIdActual = 0;
        if (!chequeIdsMasivo.length) {
            return;
        }
        abrirModal(chequeIdsMasivo.length + ' cheques seleccionados', true);
    });

    $(document).on('click', '.btn-liberar-caucion-cheque', function (e) {
        e.preventDefault();
        var id = parseInt($(this).data('cheque-id'), 10) || 0;
        var urls = window.chequeCaucionUrls || {};
        if (id <= 0 || !urls.liberar) {
            return;
        }
        if (!window.confirm('¿Liberar caución del cheque #' + id + '?')) {
            return;
        }
        $.ajax({
            url: urlConId(urls.liberar, id),
            method: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            data: { _token: csrfToken() }
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                avisar((resp && resp.error) ? resp.error : 'No se pudo liberar.');
                return;
            }
            setTimeout(function () {
                alert('Caución liberada.');
                window.location.reload();
            }, 0);
        }).fail(function (xhr) {
            avisar((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al liberar.');
        });
    });

    $(document).on('click', '#caucion_cheque_confirmar', function (e) {
        e.preventDefault();
        var urls = window.chequeCaucionUrls || {};
        var nro = $.trim($('#caucion_nro').val() || '');
        var masivo = $('#caucion-modo-masivo').val() === '1';
        if (!nro || nro === '0') {
            avisar('Indique el número de caución.');
            return;
        }
        var payload = {
            _token: csrfToken(),
            nro_caucion: nro,
            fecha: $('#caucion_fecha').val()
        };
        var url = '';
        if (masivo) {
            url = urls.caucionarMasivo || '';
            payload.cheque_ids = chequeIdsMasivo;
        } else {
            url = urlConId(urls.caucionar, chequeIdActual);
        }
        if (!url) {
            avisar('URL de caución no configurada.');
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
                avisar((resp && resp.error) ? resp.error : 'No se pudo caucionar.');
                return;
            }
            var msg = 'Cheque caucionado.';
            if (masivo && resp.data) {
                msg = 'Caucionados OK: ' + (resp.data.ok || 0) + ' — errores: ' + (resp.data.error || 0);
            } else if (resp.data && resp.data.anita_ok === false) {
                msg += ' (Anita no actualizado; revisar log).';
            }
            $('#modalCaucionCheque').one('hidden.bs.modal', function () {
                setTimeout(function () {
                    alert(msg);
                    window.location.reload();
                }, 0);
            });
            $('#modalCaucionCheque').modal('hide');
        }).fail(function (xhr) {
            avisar((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al caucionar.');
        });
    });

    $(function () { syncBotonCaucion(); });
})(jQuery);
