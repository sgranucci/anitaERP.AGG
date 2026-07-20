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

    function pintar(resp) {
        if (resp && resp.html) {
            host().html(resp.html);
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
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de indumentaria.</div>');
        });
    }

    function nuevaLinea(tablaSelector) {
        var prendasHtml = $('#tpl-prendas').html();
        var $tr = $('<tr>' +
            '<td><select class="form-control form-control-sm linea-prenda">' + prendasHtml + '</select></td>' +
            '<td><select class="form-control form-control-sm linea-variante" disabled><option value="">— elija prenda —</option></select>' +
            '<div class="linea-saldo small text-muted"></div></td>' +
            '<td><input type="number" min="0.01" step="0.01" class="form-control form-control-sm linea-cantidad" value="1"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-danger btn-quitar-linea"><i class="fa fa-trash"></i></button></td>' +
            '</tr>');
        $(tablaSelector + ' tbody').append($tr);
    }

    function juntarLineas(tablaSelector) {
        var lineas = [];
        $(tablaSelector + ' tbody tr').each(function () {
            var vid = $(this).find('.linea-variante').val();
            var cant = $(this).find('.linea-cantidad').val();
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

    function cargarVariantes($tr, prendaId) {
        var base = panel().data('url-variantes');
        var $var = $tr.find('.linea-variante');
        var $saldo = $tr.find('.linea-saldo');
        $var.prop('disabled', true).html('<option value="">Cargando…</option>');
        $saldo.text('');
        if (!prendaId) {
            $var.html('<option value="">— elija prenda —</option>');
            return;
        }
        $.get(base + '/' + prendaId + '/variantes').done(function (resp) {
            var opts = '<option value="">— color / talle —</option>';
            (resp.variantes || []).forEach(function (v) {
                var saldoTxt = v.saldo === null ? '' : ' [saldo ' + v.saldo + ']';
                var etq = (v.color || '') + ' ' + (v.talle || '') + ' · ' + (v.sku || 's/SKU') + saldoTxt;
                opts += '<option value="' + v.id + '" data-saldo="' + (v.saldo === null ? '' : v.saldo) + '" data-art="' + v.articulo_id + '">' + etq + '</option>';
            });
            $var.html(opts).prop('disabled', false);
        }).fail(function () {
            $var.html('<option value="">Error al cargar</option>');
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-indumentaria"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('click', '#btn-agregar-linea-entrega', function () {
        nuevaLinea('#tabla-entrega-lineas');
    });

    $(document).on('click', '#btn-agregar-linea-solicitud', function () {
        nuevaLinea('#tabla-solicitud-lineas');
    });

    $(document).on('click', '.btn-quitar-linea', function () {
        $(this).closest('tr').remove();
    });

    $(document).on('change', '.linea-prenda', function () {
        cargarVariantes($(this).closest('tr'), $(this).val());
    });

    $(document).on('change', '.linea-variante', function () {
        var saldo = $(this).find('option:selected').data('saldo');
        var art = $(this).find('option:selected').data('art');
        var $s = $(this).closest('tr').find('.linea-saldo');
        if (!art) {
            $s.html('<span class="text-danger">Variante sin SKU</span>');
        } else if (saldo !== '' && saldo !== undefined) {
            $s.html('Saldo disponible: <strong>' + saldo + '</strong>');
        } else {
            $s.text('');
        }
    });

    $(document).on('submit', '#form-entrega-indumentaria', function (e) {
        e.preventDefault();
        var lineas = juntarLineas('#tabla-entrega-lineas');
        if (lineas.length === 0) {
            aviso('Agregue al menos una prenda con cantidad.', 'error');
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
            aviso('Agregue al menos una prenda con cantidad.', 'error');
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
        $(this).find('select[name^="talles["]').each(function () {
            var m = $(this).attr('name').match(/talles\[(\d+)\]/);
            if (m) {
                talles[m[1]] = $(this).val() || '';
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
