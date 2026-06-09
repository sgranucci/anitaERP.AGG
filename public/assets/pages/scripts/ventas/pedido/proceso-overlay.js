(function ($) {
    'use strict';

    var overlayTimer = null;
    var onResultadoCerrar = null;

    function overlay(on) {
        var $ov = $('#pedido-procesando-overlay');
        if (!$ov.length) {
            return;
        }

        if (on) {
            $ov.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
        } else {
            $ov.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
        }
    }

    function mostrarSpinner(titulo, subtitulo) {
        $('#pedido-procesando-spinner').removeClass('d-none');
        $('#pedido-procesando-resultado').addClass('d-none');
        $('#pedido-procesando-titulo').text(titulo || 'Procesando…');
        $('#pedido-procesando-subtitulo').text(
            subtitulo || 'Por favor espere. No cierre ni recargue la página.',
        );
    }

    function escHtml(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function iconoResultado(tipo) {
        switch (tipo) {
            case 'ok':
                return 'fa-check-circle text-success';
            case 'parcial':
                return 'fa-exclamation-triangle text-warning';
            case 'error':
            default:
                return 'fa-times-circle text-danger';
        }
    }

    function mostrarPanelResultado(opciones) {
        var opts = opciones || {};
        var tipo = opts.tipo || 'error';

        $('#pedido-procesando-spinner').addClass('d-none');
        $('#pedido-procesando-resultado').removeClass('d-none');

        $('#pedido-procesando-resultado-icono')
            .attr('class', 'fa fa-2x mb-2 ' + iconoResultado(tipo));
        $('#pedido-procesando-resultado-titulo').text(opts.titulo || 'Resultado del proceso');
        $('#pedido-procesando-resultado-subtitulo').text(opts.subtitulo || '');

        var $facturas = $('#pedido-procesando-resultado-facturas').empty();
        (opts.facturas || []).forEach(function (item) {
            var codigo = item && item.codigo ? String(item.codigo) : '';
            if (!codigo) {
                return;
            }
            var ok = !(item && item.ok === false);
            var detalle = item && item.detalle ? String(item.detalle) : '';
            var clase = ok ? 'text-success' : 'text-danger';
            var icono = ok ? 'fa-check' : 'fa-times';
            var html =
                '<li class="' + clase + ' mb-1">' +
                '<i class="fa ' + icono + ' mr-1" aria-hidden="true"></i>' +
                '<strong>' + escHtml(codigo) + '</strong>';
            if (detalle) {
                html += '<div class="text-muted ml-3">' + escHtml(detalle) + '</div>';
            }
            html += '</li>';
            $facturas.append(html);
        });

        var $errores = $('#pedido-procesando-resultado-errores').empty();
        (opts.errores || []).forEach(function (err) {
            var texto = String(err || '').trim();
            if (!texto) {
                return;
            }
            $errores.append(
                '<li class="mb-1"><i class="fa fa-exclamation-circle mr-1" aria-hidden="true"></i>' +
                    escHtml(texto) +
                    '</li>',
            );
        });

        var btnLabel = opts.boton || (tipo === 'ok' ? 'Continuar' : 'Cerrar');
        $('#pedido-procesando-resultado-cerrar').text(btnLabel);

        onResultadoCerrar = typeof opts.onCerrar === 'function' ? opts.onCerrar : null;
    }

    window.PedidoProcesoOverlay = {
        detener: function () {
            if (overlayTimer) {
                clearInterval(overlayTimer);
                overlayTimer = null;
            }
            onResultadoCerrar = null;
            overlay(false);
        },

        iniciar: function (mensajes, tituloDefault) {
            this.detener();

            var msgs = mensajes && mensajes.length ? mensajes : ['Procesando…'];
            var idx = 0;

            overlay(true);
            mostrarSpinner(tituloDefault || msgs[0]);
            overlayTimer = setInterval(function () {
                idx = (idx + 1) % msgs.length;
                $('#pedido-procesando-titulo').text(msgs[idx]);
            }, 2200);
        },

        mostrarResultado: function (opciones) {
            if (overlayTimer) {
                clearInterval(overlayTimer);
                overlayTimer = null;
            }
            overlay(true);
            mostrarPanelResultado(opciones);
        },
    };

    $(document).on('click', '#pedido-procesando-resultado-cerrar', function () {
        var cb = onResultadoCerrar;
        onResultadoCerrar = null;
        overlay(false);
        if (typeof cb === 'function') {
            cb();
        }
    });
})(jQuery);
