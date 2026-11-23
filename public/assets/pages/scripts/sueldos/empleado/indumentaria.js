(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-indumentaria');
    }

    function panel() {
        return $('#indumentaria-panel');
    }

    function aviso(mensaje, tipo) {
        if (!mensaje) {
            return;
        }
        var clase = tipo === 'error' ? 'alert-danger' : 'alert-success';
        var $box = $('<div class="alert ' + clase + ' alert-dismissible mt-2">' + mensaje +
            '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
        host().prepend($box);
        setTimeout(function () { $box.alert('close'); }, 5000);
    }

    function activarConsultasPanel() {
        if (typeof window.activa_eventos_consultaprenda === 'function') {
            window.activa_eventos_consultaprenda();
        }
        if (typeof window.activa_eventos_consultavarianteprenda === 'function') {
            window.activa_eventos_consultavarianteprenda();
        }
        if (typeof window.activa_eventos_consultatalle === 'function') {
            window.activa_eventos_consultatalle();
        }
    }

    function pintar(resp) {
        if (resp && resp.html) {
            host().html(resp.html);
            prepararTablasLineas();
            activarConsultasPanel();
        }
        aviso(resp && resp.mensaje ? resp.mensaje : null, resp && resp.mensaje_tipo === 'error' ? 'error' : 'ok');
    }

    function cargarPanel() {
        var url = host().data('url');
        if (!url) {
            return;
        }
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
            prepararTablasLineas();
            activarConsultasPanel();
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de indumentaria.</div>');
        });
    }

    function escapar(txt) {
        return $('<div>').text(txt == null ? '' : String(txt)).html();
    }

    function mostrarSaldo($tr) {
        var $campo = $tr.find('.tm-variante-prenda-campo');
        var $s = $tr.find('.linea-saldo');
        var vid = String($campo.find('.prenda_articulo_id').val() || '');
        var saldo = $campo.attr('data-saldo');
        var art = $campo.attr('data-articulo');
        if (!vid) {
            $s.text('');
            return;
        }
        if (art === '0' || art === '') {
            $s.html('<span class="text-danger">Variante sin SKU de stock</span>');
        } else if (saldo !== undefined && saldo !== '') {
            $s.html('Saldo disponible: <strong>' + escapar(saldo) + '</strong>');
        } else {
            $s.text('');
        }
    }

    function nuevaLinea(tablaSelector) {
        var tpl = document.getElementById('tpl-linea-indumentaria-modal');
        if (!tpl) {
            return null;
        }
        var $tr = $(tpl.content.querySelector('tr').cloneNode(true));
        $(tablaSelector + ' tbody').append($tr);
        return $tr;
    }

    function prepararTablasLineas() {
        ['#tabla-entrega-lineas', '#tabla-solicitud-lineas'].forEach(function (sel) {
            if (!$(sel).length) {
                return;
            }
            if ($(sel + ' tbody tr').length === 0) {
                nuevaLinea(sel);
            }
        });
    }

    function juntarLineas(tablaSelector) {
        var lineas = [];
        $(tablaSelector + ' tbody tr').each(function () {
            var vid = $(this).find('.prenda_articulo_id').val();
            var cant = parseFloat($(this).find('.linea-cantidad').val());
            if (vid && cant > 0) {
                lineas.push({ prenda_articulo_id: vid, cantidad: cant });
            }
        });
        return lineas;
    }

    function accionSolicitud($btn, url, mensajeConfirm) {
        if (mensajeConfirm && !confirm(mensajeConfirm)) {
            return;
        }
        $btn.prop('disabled', true);
        $.ajax({ url: url, type: 'POST', data: { _token: token() } })
            .done(pintar)
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo completar la acción.';
                aviso(msg, 'error');
            })
            .always(function () { $btn.prop('disabled', false); });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-indumentaria"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('click', '#btn-agregar-linea-entrega', function () {
        var $tr = nuevaLinea('#tabla-entrega-lineas');
        if ($tr) {
            $tr.find('.codigoprenda').trigger('focus');
        }
    });

    $(document).on('click', '#btn-agregar-linea-solicitud', function () {
        var $tr = nuevaLinea('#tabla-solicitud-lineas');
        if ($tr) {
            $tr.find('.codigoprenda').trigger('focus');
        }
    });

    $(document).on('click', '#indumentaria-panel .btn-quitar-linea', function () {
        var $tr = $(this).closest('tr');
        var $tbody = $tr.closest('tbody');
        if ($tbody.find('tr').length <= 1) {
            $tr.find('.prenda_id').val('');
            $tr.find('.codigoprenda').val('');
            $tr.find('.descripcionprenda').val('');
            if (typeof window.limpiarVariantePrendaCampo === 'function') {
                window.limpiarVariantePrendaCampo($tr.find('.tm-variante-prenda-campo'));
            }
            $tr.find('.linea-cantidad').val(1);
            $tr.find('.linea-saldo').text('');
            return;
        }
        $tr.remove();
    });

    $(document).on('prenda:seleccionada', '#indumentaria-panel .tm-prenda-campo', function (e, data) {
        var $tr = $(this).closest('tr');
        if (typeof window.limpiarVariantePrendaCampo === 'function') {
            window.limpiarVariantePrendaCampo($tr.find('.tm-variante-prenda-campo'));
        }
        $tr.find('.linea-saldo').text('');
        if (data && data.id) {
            $tr.find('.codigovarianteprenda').trigger('focus');
        }
    });

    $(document).on('variante:seleccionada', '#indumentaria-panel .tm-variante-prenda-campo', function () {
        mostrarSaldo($(this).closest('tr'));
    });

    $(document).on('submit', '#form-entrega-indumentaria', function (e) {
        e.preventDefault();
        var lineas = juntarLineas('#tabla-entrega-lineas');
        if (lineas.length === 0) {
            aviso('Agregue al menos una prenda con variante y cantidad.', 'error');
            return;
        }
        var $btn = $(this).find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: panel().data('url-entregar'),
            type: 'POST',
            data: { _token: token(), fecha: $('#entrega_fecha').val(), observacion: $('#entrega_obs').val(), lineas: lineas }
        }).done(pintar).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo registrar la entrega.';
            aviso(msg, 'error');
        }).always(function () { $btn.prop('disabled', false); });
    });

    $(document).on('submit', '#form-solicitud-indumentaria', function (e) {
        e.preventDefault();
        var lineas = juntarLineas('#tabla-solicitud-lineas');
        if (lineas.length === 0) {
            aviso('Agregue al menos una prenda con variante y cantidad.', 'error');
            return;
        }
        var $btn = $(this).find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: panel().data('url-solicitud'),
            type: 'POST',
            data: { _token: token(), fecha: $('#solicitud_fecha').val(), observacion: $('#solicitud_obs').val(), lineas: lineas }
        }).done(pintar).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo registrar la solicitud.';
            aviso(msg, 'error');
        }).always(function () { $btn.prop('disabled', false); });
    });

    $(document).on('click', '.btn-aprobar-solicitud', function () {
        accionSolicitud($(this), $(this).data('url'), '¿Aprobar esta solicitud?');
    });

    $(document).on('click', '.btn-rechazar-solicitud', function () {
        var motivo = prompt('Motivo del rechazo (opcional):', '');
        if (motivo === null) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({ url: $(this).data('url'), type: 'POST', data: { _token: token(), observacion: motivo } })
            .done(pintar)
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo rechazar.';
                aviso(msg, 'error');
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    $(document).on('click', '.btn-entregar-solicitud', function () {
        accionSolicitud($(this), $(this).data('url'), '¿Entregar esta solicitud? Se descuenta stock y genera asiento.');
    });

    $(document).on('click', '.btn-anular-solicitud', function () {
        accionSolicitud($(this), $(this).data('url'), '¿Anular esta solicitud?');
    });

    $(document).on('submit', '#form-talles-indumentaria', function (e) {
        e.preventDefault();
        var talles = {};
        $(this).find('.tm-talle-campo').each(function () {
            var pid = String($(this).data('prenda-id') || '');
            if (pid) {
                talles[pid] = $(this).find('.talle_id').val() || '';
            }
        });
        $.ajax({ url: panel().data('url-talles'), type: 'POST', data: { _token: token(), talles: talles } })
            .done(pintar)
            .fail(function () { aviso('No se pudo guardar el perfil de talles.', 'error'); });
    });

    $(document).on('click', '.btn-anular-entrega', function () {
        if (!confirm('¿Anular esta entrega? Se revierten stock y asiento contable.')) {
            return;
        }
        $.ajax({ url: $(this).data('url'), type: 'POST', data: { _token: token() } })
            .done(pintar)
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo anular.';
                aviso(msg, 'error');
            });
    });

    $(document).on('click', '.btn-tulegajo-entrega', function () {
        if (!confirm('¿Enviar el comprobante de esta entrega a TuLegajo?')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({ url: $btn.data('url'), type: 'POST', data: { _token: token() } })
            .done(pintar)
            .fail(function (xhr) {
                $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'No se pudo enviar a TuLegajo.';
                aviso(msg, 'error');
            });
    });
})(jQuery);
