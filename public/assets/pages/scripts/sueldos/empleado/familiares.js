(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-familiares');
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
            var $panel = host().find('#familiares-panel');
            if ($panel.length) {
                $panel.replaceWith(resp.html);
            } else {
                host().html(resp.html);
            }
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
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de familiares.</div>');
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-familiares"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('submit', '#form-familiar-nuevo', function (e) {
        e.preventDefault();
        var url = host().data('url');
        if (!url) {
            return;
        }
        var data = $(this).serializeArray();
        data.push({ name: '_token', value: token() });
        $.post(url, data).done(pintar).fail(function (xhr) {
            var msg = 'No se pudo guardar el familiar.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
            }
            aviso(msg, 'error');
        });
    });

    $(document).on('click', '.btn-familiar-borrar', function () {
        if (!confirm('¿Eliminar este familiar?')) {
            return;
        }
        var id = $(this).closest('tr').data('id');
        $.ajax({
            url: '/sueldos/familiar/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar).fail(function () {
            aviso('No se pudo eliminar.', 'error');
        });
    });

    $(document).on('click', '.btn-familiar-toggle', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        var activo = Number($tr.data('activo')) === 1 ? 0 : 1;
        $.ajax({
            url: '/sueldos/familiar/' + id,
            method: 'POST',
            data: {
                _token: token(),
                _method: 'PUT',
                solo_activo: 1,
                activo: activo
            }
        }).done(pintar).fail(function () {
            aviso('No se pudo actualizar.', 'error');
        });
    });
})(jQuery);
