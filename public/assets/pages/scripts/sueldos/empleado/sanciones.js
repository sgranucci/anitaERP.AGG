(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-sanciones');
    }

    function aviso(mensaje, tipo) {
        if (!mensaje) { return; }
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
        aviso(resp && resp.mensaje ? resp.mensaje : null, resp && resp.error ? 'error' : 'ok');
    }

    function cargarPanel() {
        var url = host().data('url');
        if (!url) { return; }
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
            if (typeof window.focusSolapaEmpleado === 'function') {
                window.focusSolapaEmpleado('#tab-sanciones');
            }
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de sanciones.</div>');
        });
    }

    function resetForm() {
        var $f = $('#form-sancion-empleado');
        if (!$f.length) { return; }
        $f[0].reset();
        $f.find('[name=sancion_id]').val('');
        $('#sancion-form-titulo').text('Nueva sanción');
        $('#btn-sancion-cancelar').addClass('d-none');
        if (typeof window.limpiarTipoSancionEnCampo === 'function') {
            window.limpiarTipoSancionEnCampo($('.tm-tipo-sancion-campo').first(), false);
        } else {
            $f.find('.tipo_sancion_id').val('');
            $f.find('.codigotipo_sancion, .nombretipo_sancion').val('');
        }
        $f.find('.motivo_sancion_id').val('');
        $f.find('.codigomotivo_sancion, .nombremotivo_sancion').val('');
    }

    function payloadForm() {
        var fd = new FormData($('#form-sancion-empleado')[0]);
        fd.append('_token', token());
        return fd;
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-sanciones"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('submit', '#form-sancion-empleado', function (e) {
        e.preventDefault();
        var id = $(this).find('[name=sancion_id]').val();
        var url = id
            ? carpetaBase + '/sueldos/sancion/' + id
            : host().data('url');
        var method = id ? 'POST' : 'POST';
        var fd = payloadForm();
        if (id) { fd.append('_method', 'PUT'); }
        $.ajax({ url: url, type: method, data: fd, processData: false, contentType: false })
            .done(pintar)
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.mensaje || xhr.responseJSON.message)) || 'No se pudo guardar.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join(' ');
                }
                aviso(msg, 'error');
            });
    });

    $(document).on('click', '#btn-sancion-cancelar', function () { resetForm(); });

    $(document).on('click', '.btn-editar-sancion', function () {
        var data = $(this).closest('tr').data('sancion') || {};
        var $f = $('#form-sancion-empleado');
        $f.find('[name=sancion_id]').val(data.id || '');
        $f.find('[name=fecha_hecho]').val(data.fecha_hecho || '');
        $f.find('[name=fecha_desde]').val(data.fecha_desde || '');
        $f.find('[name=fecha_hasta]').val(data.fecha_hasta || '');
        $f.find('[name=cant_dias]').val(data.cant_dias || 0);
        $f.find('[name=importe_perdida]').val(data.importe_perdida || 0);
        $f.find('[name=fecha_notificacion]').val(data.fecha_notificacion || '');
        $f.find('[name=fecha_recepcion]').val(data.fecha_recepcion || '');
        $f.find('[name=comentario]').val(data.comentario || '');
        $f.find('[name=descargo_texto]').val(data.descargo_texto || '');
        $f.find('[name=resolucion_texto]').val(data.resolucion_texto || '');
        $f.find('.tipo_sancion_id').val(data.tipo_sancion_id || '');
        $f.find('.codigotipo_sancion').val(data.tipo_codigo || '');
        $f.find('.nombretipo_sancion').val(data.tipo_nombre || '');
        $f.find('.motivo_sancion_id').val(data.motivo_sancion_id || '');
        $f.find('.codigomotivo_sancion').val(data.motivo_codigo || '');
        $f.find('.nombremotivo_sancion').val(data.motivo_nombre || '');
        $('#sancion-form-titulo').text('Editar sanción #' + data.id);
        $('#btn-sancion-cancelar').removeClass('d-none');
    });

    $(document).on('click', '.btn-transicion-sancion', function () {
        var id = $(this).data('id');
        var accion = $(this).data('accion');
        var extra = {};
        if (accion === 'descargo') {
            extra.descargo_texto = window.prompt('Texto del descargo:') || '';
            if (!extra.descargo_texto) { return; }
        }
        if (accion === 'anular' && !window.confirm('¿Anular esta sanción?')) { return; }
        $.post(carpetaBase + '/sueldos/sancion/' + id + '/transicion', $.extend({
            _token: token(),
            accion: accion
        }, extra)).done(pintar).fail(function () { aviso('No se pudo cambiar el estado', 'error'); });
    });

    $(document).on('click', '.btn-eliminar-sancion', function () {
        if (!window.confirm('¿Eliminar esta sanción?')) { return; }
        $.ajax({
            url: carpetaBase + '/sueldos/sancion/' + $(this).data('id'),
            type: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar);
    });
})(jQuery);
