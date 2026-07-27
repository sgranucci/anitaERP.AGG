(function ($) {
    'use strict';

    function baseUrl() {
        var $root = $('#ai-agente-evento-hitl');
        if ($root.length) {
            return String($root.data('hitl-visto-url') || '').replace(/\/$/, '');
        }
        return '/configuracion/ai-agente-eventos';
    }

    function csrf() {
        return window.AI_AGENTE_EVENTO_CSRF
            || $('meta[name="csrf-token"]').attr('content')
            || '';
    }

    function toast(msg, ok) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: ok ? 'success' : 'error',
                title: msg,
                showConfirmButton: false,
                timer: 2500
            });
            return;
        }
        window.alert(msg);
    }

    function postAccion($btn, accion) {
        var $tr = $btn.closest('tr');
        var id = $tr.data('evento-id');
        if (!id) {
            return;
        }
        $btn.prop('disabled', true);
        $.ajax({
            url: baseUrl() + '/' + id + '/' + accion,
            method: 'POST',
            data: { _token: csrf() },
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                toast((resp && resp.message) || 'No se pudo actualizar.', false);
                $btn.prop('disabled', false);
                return;
            }
            var estado = (resp.evento && resp.evento.estado) || accion;
            $tr.attr('data-estado', estado);
            $tr.find('.js-estado-evento').html('<span class="badge badge-info">' + estado + '</span>');
            if (estado === 'descartado' || estado === 'resuelto') {
                $tr.find('.js-acciones-evento').html('<span class="text-muted small">Cerrado</span>');
            } else if (estado === 'visto') {
                $tr.find('.js-hitl-visto').remove();
            }
            toast('Evento actualizado: ' + estado, true);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error de red.';
            toast(msg, false);
            $btn.prop('disabled', false);
        });
    }

    $(document).on('click', '.js-hitl-visto', function () {
        postAccion($(this), 'visto');
    });
    $(document).on('click', '.js-hitl-descartar', function () {
        postAccion($(this), 'descartar');
    });
    $(document).on('click', '.js-hitl-resolver', function () {
        postAccion($(this), 'resolver');
    });
})(jQuery);
