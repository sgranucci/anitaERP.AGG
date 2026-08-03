(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-set-conceptos');
    }

    function pintar(resp) {
        if (resp && resp.html) {
            var $p = host().find('#set-conceptos-panel');
            if ($p.length) {
                $p.replaceWith(resp.html);
            } else {
                host().html(resp.html);
            }
        }
        if (resp && resp.mensaje) {
            var $box = $('<div class="alert alert-success alert-dismissible py-1 px-2 small">' + resp.mensaje +
                '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            host().prepend($box);
            setTimeout(function () { $box.alert('close'); }, 3000);
        }
    }

    function cargar() {
        var url = host().data('url');
        if (!url) return;
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el set de conceptos.</div>');
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-bases"]', function () {
        if (!cargado) {
            cargado = true;
            cargar();
        }
    });

    $(document).on('submit', '#form-empleado-agregar-grupo', function (e) {
        e.preventDefault();
        var emp = $('#set-conceptos-panel').data('empleado');
        var data = $(this).serializeArray();
        data.push({ name: '_token', value: token() });
        $.post('/sueldos/empleado/' + emp + '/grupos-concepto', data).done(pintar)
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ||
                    (xhr.responseJSON && xhr.responseJSON.message) || 'Error al agregar grupo';
                if (xhr.responseJSON && xhr.responseJSON.html) {
                    pintar(xhr.responseJSON);
                }
                alert(msg);
            });
    });

    $(document).on('click', '.btn-del-grupo-concepto', function () {
        if (!confirm('¿Quitar este grupo del legajo?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '/sueldos/empleado-grupo-concepto/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar);
    });

    $(document).on('submit', '#form-empleado-concepto-explicito', function (e) {
        e.preventDefault();
        var emp = $('#set-conceptos-panel').data('empleado');
        var data = $(this).serializeArray();
        data.push({ name: '_token', value: token() });
        $.post('/sueldos/empleado/' + emp + '/concepto-explicito', data).done(function (resp) {
            pintar(resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error al guardar';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
            }
            alert(msg);
        });
    });

    $(document).on('click', '.btn-del-explicito', function () {
        if (!confirm('¿Quitar esta asignación?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '/sueldos/empleado-concepto/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar);
    });
})(jQuery);
