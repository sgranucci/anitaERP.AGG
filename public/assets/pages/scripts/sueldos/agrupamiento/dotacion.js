(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-dotacion');
    }

    function aviso(mensaje, tipo) {
        if (!mensaje) {
            return;
        }
        var clase = tipo === 'error' ? 'alert-danger' : 'alert-success';
        var $box = $('<div class="alert ' + clase + ' alert-dismissible mt-2">' + mensaje +
            '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
        host().prepend($box);
        setTimeout(function () { $box.alert('close'); }, 4000);
    }

    function pintar(resp) {
        if (resp && resp.html) {
            host().html(resp.html);
        }
        aviso(resp && resp.mensaje ? resp.mensaje : null, 'ok');
    }

    function cargarPanel() {
        var url = host().data('url');
        if (!url) {
            return;
        }
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar la dotación.</div>');
        });
    }

    function resetForm() {
        $('#dotacion_id').val('');
        $('#dotacion_prenda').val('');
        $('#dotacion_color').val('');
        $('#dotacion_limite').val('1');
        $('#dotacion_orden').val('0');
        $('#dotacion-form-titulo').text('Agregar prenda a la dotación');
        $('#btn-cancelar-dotacion').addClass('d-none');
    }

    function payloadForm() {
        return {
            _token: token(),
            sexo: $('#dotacion_sexo').val(),
            prenda_id: $('#dotacion_prenda').val(),
            color_id: $('#dotacion_color').val(),
            limite_anual: $('#dotacion_limite').val(),
            orden: $('#dotacion_orden').val()
        };
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-dotacion"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('submit', '#form-dotacion', function (e) {
        e.preventDefault();
        var id = $('#dotacion_id').val();
        var data = payloadForm();
        var url;
        if (id) {
            url = $('#dotacion-panel').data('url-update-base') + '/' + id;
            data._method = 'PUT';
        } else {
            url = $('#dotacion-panel').data('url-crear');
        }
        $.ajax({ url: url, type: 'POST', data: data })
            .done(function (resp) { pintar(resp); resetForm(); })
            .fail(function (xhr) {
                var msg = 'No se pudo guardar la dotación.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(function (a) { return a[0]; }).join(' ');
                }
                aviso(msg, 'error');
            });
    });

    $(document).on('click', '.btn-editar-dotacion', function () {
        var d = $(this).data('dotacion');
        if (!d) {
            return;
        }
        $('#dotacion_id').val(d.id);
        $('#dotacion_sexo').val(String(d.sexo));
        $('#dotacion_prenda').val(String(d.prenda_id));
        $('#dotacion_color').val(d.color_id ? String(d.color_id) : '');
        $('#dotacion_limite').val(d.limite_anual);
        $('#dotacion_orden').val(d.orden || 0);
        $('#dotacion-form-titulo').text('Editando dotación #' + d.id);
        $('#btn-cancelar-dotacion').removeClass('d-none');
        $('html, body').animate({ scrollTop: $('#form-dotacion').offset().top - 120 }, 250);
    });

    $(document).on('click', '#btn-cancelar-dotacion', function () {
        resetForm();
    });

    $(document).on('click', '.btn-eliminar-dotacion', function () {
        if (!confirm('¿Quitar esta prenda de la dotación?')) {
            return;
        }
        var url = $(this).data('url');
        $.ajax({ url: url, type: 'POST', data: { _token: token(), _method: 'DELETE' } })
            .done(pintar)
            .fail(function () { aviso('No se pudo eliminar.', 'error'); });
    });
})(jQuery);
