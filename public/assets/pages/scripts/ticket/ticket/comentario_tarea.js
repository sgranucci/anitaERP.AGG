$(function () {
    var urlBase = $('#url_guarda_comentario_tarea').val();
    var urlReabrir = $('#url_reabrir_ticket').val();
    var csrfToken = $('#csrf_token').val();
    var $overlay = $('#ticket-comentario-enviando-overlay');
    var $form = $('#form-general');

    function hayComentariosSinEnviar() {
        var pendiente = false;
        $('.comentario-tarea-texto').each(function () {
            if ($.trim($(this).val()) !== '') {
                pendiente = true;
                return false;
            }
        });
        return pendiente;
    }

    function enfocarPrimerComentarioPendiente() {
        $('.comentario-tarea-texto').each(function () {
            if ($.trim($(this).val()) === '') {
                return;
            }
            var $textarea = $(this);
            if ($textarea.is('#comentario-reabrir-ticket')) {
                $textarea.focus();
                return false;
            }
            var $panel = $textarea.closest('.collapse');
            if ($panel.length && ! $panel.hasClass('show')) {
                $panel.collapse('show');
            }
            $textarea.focus();
            return false;
        });
    }

    function confirmarComentariosPendientesOAbortar() {
        if (! hayComentariosSinEnviar()) {
            return true;
        }
        var ok = window.confirm(
            'Hay comentarios escritos sin enviar. Si actualiza ahora se van a perder.\n\n¿Desea actualizar de todos modos?'
        );
        if (! ok) {
            enfocarPrimerComentarioPendiente();
            return false;
        }
        return true;
    }

    function vincularAvisoComentarioEnSubmit() {
        if (! $form.length) {
            return;
        }

        $form.off('submit.ticketComentarioPendiente')
            .on('submit.ticketComentarioPendiente', function (event) {
                if ($form.data('ticket-omitir-aviso-comentario')) {
                    $form.removeData('ticket-omitir-aviso-comentario');
                    return;
                }
                if (! hayComentariosSinEnviar()) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                if (! confirmarComentariosPendientesOAbortar()) {
                    return false;
                }
                $form.data('ticket-omitir-aviso-comentario', true);
                $form.trigger('submit');
                return false;
            });

        var eventos = $._data($form[0], 'events');
        if (eventos && eventos.submit && eventos.submit.length > 1) {
            eventos.submit.unshift(eventos.submit.pop());
        }
    }

    $(document).off('click.ticketUsuarioSubmit', '.botonsubmit')
        .on('click.ticketUsuarioSubmit', '.botonsubmit', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();

            if (! confirmarComentariosPendientesOAbortar()) {
                return false;
            }

            var form = document.getElementById('form-general');
            if (! form) {
                alert('No se encontró el formulario para guardar.');
                return false;
            }

            if (typeof validarCamposObligatoriosFormulario === 'function') {
                var resultado = validarCamposObligatoriosFormulario(form);
                if (! resultado.valido) {
                    if (typeof mostrarSolapaDelPrimerCampoInvalido === 'function') {
                        mostrarSolapaDelPrimerCampoInvalido(resultado.primerInvalido);
                    }
                    if (typeof notificarCamposObligatoriosPendientes === 'function') {
                        notificarCamposObligatoriosPendientes(resultado.primerInvalido, resultado.cantidadInvalidos);
                    }
                    if (typeof enfocarCampoInvalido === 'function') {
                        enfocarCampoInvalido(resultado.primerInvalido);
                    }
                    return false;
                }
            }

            HTMLFormElement.prototype.submit.call(form);
        });

    vincularAvisoComentarioEnSubmit();

    function mostrarBannerEnviando(titulo, subtitulo) {
        if (titulo) {
            $('#ticket-comentario-enviando-titulo').text(titulo);
        }
        if (subtitulo) {
            $('#ticket-comentario-enviando-subtitulo').text(subtitulo);
        }
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

    function appendComentarioEnLista(ticketTareaId, comentario) {
        var $lista = $('.lista-comentarios-usuario[data-ticket-tarea-id="' + ticketTareaId + '"]');
        if (! $lista.length) {
            return;
        }
        $lista.find('.sin-comentarios').remove();

        var html = '<div class="comentario-usuario-item border-bottom pb-1 mb-1">' +
            '<strong>' + $('<div>').text(comentario.usuario || '').html() + '</strong>' +
            '<span class="text-muted"> — ' + (comentario.fecha || '') + '</span>' +
            '<div class="comentario-usuario-texto">' + $('<div>').text(comentario.comentario || '').html() + '</div>' +
            '</div>';
        $lista.append(html);

        var $toggleBtn = $('button[data-target="#comentarios-tarea-' + ticketTareaId + '"]');
        var $badge = $toggleBtn.find('.badge');
        if ($badge.length) {
            $badge.text(parseInt($badge.text(), 10) + 1);
        } else if ($toggleBtn.length) {
            $toggleBtn.append(' <span class="badge badge-light">1</span>');
        }
    }

    if (urlBase) {
        $(document).on('click', '.btn-enviar-comentario-tarea', function () {
            var $btn = $(this);
            var ticketTareaId = $btn.data('ticket-tarea-id');
            var $panel = $btn.closest('.collapse');
            var $textarea = $panel.find('.comentario-tarea-texto').not('#comentario-reabrir-ticket');
            var comentario = $.trim($textarea.val());

            if (!comentario) {
                alert('Ingrese un comentario.');
                $textarea.focus();
                return;
            }

            $btn.prop('disabled', true);
            mostrarBannerEnviando(
                'Enviando comentario y notificando al técnico…',
                'Por favor espere. Se está guardando el comentario y enviando el correo al técnico asignado.'
            );

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

                    appendComentarioEnLista(ticketTareaId, resp.comentario);
                    $textarea.val('');
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
    }

    if (urlReabrir) {
        $(document).on('click', '#btn-reabrir-ticket', function () {
            var $btn = $(this);
            var $textarea = $('#comentario-reabrir-ticket');
            var comentario = $.trim($textarea.val());

            if (!comentario) {
                alert('Escriba qué necesita para solicitar más ayuda.');
                $textarea.focus();
                return;
            }

            if (! window.confirm(
                'Se reabrirá el ticket a Pendiente y se avisará al área técnica.\n\n¿Confirma?'
            )) {
                return;
            }

            $btn.prop('disabled', true);
            mostrarBannerEnviando(
                'Reabriendo ticket y notificando al técnico…',
                'Por favor espere. Se está guardando su pedido y enviando el correo al área técnica.'
            );

            $.ajax({
                url: urlReabrir,
                method: 'POST',
                data: {
                    _token: csrfToken,
                    comentario: comentario
                },
                success: function (resp) {
                    if (resp.mensaje !== 'ok' || !resp.comentario) {
                        alert(resp.error || 'No se pudo solicitar más ayuda.');
                        return;
                    }

                    $('#estado_ticket').val(resp.estado_ticket || 'Pendiente');
                    appendComentarioEnLista(resp.ticket_tarea_id, resp.comentario);
                    $textarea.val('');
                    $('#bloque-reabrir-ticket').slideUp(200, function () {
                        $(this).remove();
                    });
                    alert('Pedido enviado. El ticket volvió a Pendiente y el área técnica fue notificada.');
                },
                error: function (xhr) {
                    var msg = 'No se pudo solicitar más ayuda.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.comentario) {
                        msg = xhr.responseJSON.errors.comentario[0];
                    }
                    alert(msg);
                },
                complete: function () {
                    ocultarBannerEnviando();
                    $btn.prop('disabled', false);
                }
            });
        });
    }
});
