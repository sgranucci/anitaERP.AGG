(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-novedades');
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
            var $panel = host().find('#novedades-empleado-panel');
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
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de novedades.</div>');
        });
    }

    function resetForm() {
        var $f = $('#form-novedad-empleado');
        if (!$f.length) {
            return;
        }
        $f[0].reset();
        $f.find('[name="novedad_id"]').val('');
        if (typeof window.limpiarConceptoSueldosEnCampo === 'function') {
            window.limpiarConceptoSueldosEnCampo($f.find('.tm-concepto-sueldos-campo'), false);
        } else {
            $f.find('[name="concepto_id"]').val('');
        }
        $('#novedad-empleado-form-titulo').text('Nueva novedad');
        $('#btn-novedad-empleado-cancelar').addClass('d-none');
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-novedades"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('submit', '#form-novedad-empleado', function (e) {
        e.preventDefault();
        var $f = $(this);
        var id = $f.find('[name="novedad_id"]').val();
        var data = $f.serializeArray();
        data.push({ name: '_token', value: token() });
        var emp = $('#novedades-empleado-panel').data('empleado');
        var req;
        if (id) {
            data.push({ name: '_method', value: 'PUT' });
            req = $.post('/sueldos/novedad-empleado/' + id, data);
        } else {
            req = $.post('/sueldos/empleado/' + emp + '/novedades', data);
        }
        req.done(function (resp) {
            pintar(resp);
            resetForm();
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar la novedad.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
            }
            aviso(msg, 'error');
        });
    });

    $(document).on('click', '#btn-novedad-empleado-cancelar', function () {
        resetForm();
    });

    $(document).on('click', '.btn-novedad-empleado-editar', function () {
        var $b = $(this);
        var $f = $('#form-novedad-empleado');
        $f.find('[name="novedad_id"]').val($b.data('id'));
        $f.find('[name="liquidacion_id"]').val($b.data('liquidacion-id') || '');
        $f.find('[name="valor1"]').val($b.data('valor1'));
        $f.find('[name="valor2"]').val($b.data('valor2'));
        $f.find('[name="estado"]').val($b.data('estado'));
        $f.find('[name="fecha_vto"]').val($b.data('fecha-vto') || '');
        $f.find('[name="fecha_desde"]').val($b.data('fecha-desde') || '');
        $f.find('[name="fecha_hasta"]').val($b.data('fecha-hasta') || '');
        $f.find('[name="observacion"]').val($b.data('observacion') || '');
        var $campo = $f.find('.tm-concepto-sueldos-campo');
        $campo.find('.concepto_sueldos_id').val($b.data('concepto-id'));
        $campo.find('.codigoconcepto_sueldos').val($b.data('concepto-codigo'));
        $campo.find('.descripcionconcepto_sueldos').val($b.data('concepto-desc'));
        $('#novedad-empleado-form-titulo').text('Editar novedad #' + $b.data('id'));
        $('#btn-novedad-empleado-cancelar').removeClass('d-none');
    });

    $(document).on('click', '.btn-novedad-empleado-borrar', function () {
        if (!confirm('¿Eliminar esta novedad?')) {
            return;
        }
        var id = $(this).data('id');
        $.ajax({
            url: '/sueldos/novedad-empleado/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(function (resp) {
            pintar(resp);
        }).fail(function () {
            aviso('No se pudo eliminar.', 'error');
        });
    });
})(jQuery);
