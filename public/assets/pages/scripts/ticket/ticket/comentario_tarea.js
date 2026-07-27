$(function () {
    var urlBase = $('#url_guarda_comentario_tarea').val();
    var csrfToken = $('#csrf_token').val();
    var $overlay = $('#ticket-comentario-enviando-overlay');

    if (!urlBase) {
        return;
    }

    function mostrarBannerEnviando() {
        $overlay
            .removeClass('d-none')
            .css('display', 'flex')
            .attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function ocultarBannerEnviando() {
        $overlay
            .addClass('d-none')
            .css('display', '')
            .attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    }

    $(document).on('click', '.btn-enviar-comentario-tarea', function () {
        var $btn = $(this);
        var ticketId = $btn.data('ticket-id');
        var ticketTareaId = $btn.data('ticket-tarea-id');
        var $panel = $btn.closest('.collapse');
        var $textarea = $panel.find('.comentario-tarea-texto');
        var comentario = $.trim($textarea.val());

        if (!comentario) {
            alert('Ingrese un comentario.');
            $textarea.focus();
            return;
        }

        $btn.prop('disabled', true);
        mostrarBannerEnviando();

        $.ajax({
            url: urlBase + '/' + ticketTareaId + '/comentario',
            method: 'POST',
            data: {
                _token: csrfToken,
                comentario: comentario
            },
            success: function (resp) {
                if (resp.mensaje !== 'ok' || !resp.comentario) {
                    alert(resp.error || 'No se pudo enviar el comentario.');
                    return;
                }

                var $lista = $panel.find('.lista-comentarios-usuario[data-ticket-tarea-id="' + ticketTareaId + '"]');
                $lista.find('.sin-comentarios').remove();

                var html = '<div class="comentario-usuario-item border-bottom pb-1 mb-1">' +
                    '<strong>' + (resp.comentario.usuario || '') + '</strong>' +
                    '<span class="text-muted"> — ' + (resp.comentario.fecha || '') + '</span>' +
                    '<div class="comentario-usuario-texto">' + $('<div>').text(resp.comentario.comentario).html() + '</div>' +
                    '</div>';
                $lista.append(html);

                $textarea.val('');

                var $toggleBtn = $('button[data-target="#comentarios-tarea-' + ticketTareaId + '"]');
                var $badge = $toggleBtn.find('.badge');
                if ($badge.length) {
                    $badge.text(parseInt($badge.text(), 10) + 1);
                } else {
                    $toggleBtn.append(' <span class="badge badge-light">1</span>');
                }

                alert('Comentario enviado. El técnico fue notificado por correo.');
            },
            error: function (xhr) {
                var msg = 'No se pudo enviar el comentario.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                alert(msg);
            },
            complete: function () {
                ocultarBannerEnviando();
                $btn.prop('disabled', false);
            }
        });
    });
});
