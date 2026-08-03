(function ($) {
    'use strict';

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-elegibilidad-concepto');
    }

    function pintar(resp) {
        if (resp && resp.html) {
            host().html(resp.html);
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
            host().html('<div class="alert alert-danger">No se pudieron cargar las reglas.</div>');
        });
    }

    $(function () {
        if (host().length) {
            cargar();
        }
    });

    $(document).on('change', '#eleg_operador', function () {
        var op = $(this).val();
        var sinValor = op === 'vacio' || op === 'no_vacio';
        $('#eleg_valor').prop('disabled', sinValor).prop('required', !sinValor);
        if (sinValor) {
            $('#eleg_valor').val('');
        }
    });

    $(document).on('submit', '#form-concepto-elegibilidad', function (e) {
        e.preventDefault();
        var concepto = $('#elegibilidad-concepto-panel').data('concepto');
        var data = $(this).serializeArray();
        data.push({ name: '_token', value: token() });
        $.post('/sueldos/concepto/' + concepto + '/elegibilidad', data).done(pintar)
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error al guardar regla';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                alert(msg);
            });
    });

    $(document).on('click', '.btn-del-elegibilidad', function () {
        if (!confirm('¿Eliminar esta regla?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '/sueldos/concepto-elegibilidad/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar);
    });
})(jQuery);
