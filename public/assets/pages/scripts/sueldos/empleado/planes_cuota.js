(function ($) {
    'use strict';

    var cargado = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-planes-cuota');
    }

    function panelUrl() {
        return host().data('url') || $('#planes-cuota-panel').data('url');
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

    function focusConcepto() {
        if (typeof window.focusSolapaEmpleado === 'function') {
            window.focusSolapaEmpleado('#tab-planes-cuota');
            return;
        }
        var $inp = $('#tab-planes-cuota .codigoconcepto_sueldos').filter(':visible').first();
        if ($inp.length) {
            setTimeout(function () { $inp.trigger('focus').trigger('select'); }, 60);
        }
    }

    function pintar(resp) {
        if (resp && resp.html) {
            var $panel = host().find('#planes-cuota-panel');
            if ($panel.length) {
                $panel.replaceWith(resp.html);
            } else {
                host().html(resp.html);
            }
        }
        aviso(resp && resp.mensaje ? resp.mensaje : null, 'ok');
        sincronizarTipoValor();
        focusConcepto();
    }

    function cargarPanel() {
        var url = host().data('url');
        if (!url) {
            return;
        }
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
            sincronizarTipoValor();
            focusConcepto();
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de préstamos/cuotas.</div>');
        });
    }

    function sincronizarTipoValor() {
        var tipo = $('#plan-cuota-tipo-valor').val();
        if (tipo === 'formula') {
            $('#plan-cuota-wrap-valor').addClass('d-none');
            $('#plan-cuota-wrap-formula').removeClass('d-none');
        } else {
            $('#plan-cuota-wrap-valor').removeClass('d-none');
            $('#plan-cuota-wrap-formula').addClass('d-none');
        }
    }

    function resetForm() {
        var $f = $('#form-plan-cuota');
        if (!$f.length) {
            return;
        }
        $f[0].reset();
        $f.find('[name="plan_id"]').val('');
        if (typeof window.limpiarConceptoSueldosEnCampo === 'function') {
            window.limpiarConceptoSueldosEnCampo($f.find('.tm-concepto-sueldos-campo'), false);
        } else {
            $f.find('[name="concepto_id"]').val('');
        }
        $('#plan-cuota-form-titulo').text('Nuevo plan de cuotas');
        $('#btn-plan-cuota-cancelar-edicion').addClass('d-none');
        $f.find('[name="corridas_afecta[]"]').prop('checked', false);
        $f.find('[name="corridas_afecta[]"][value="mensual"]').prop('checked', true);
        sincronizarTipoValor();
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-planes-cuota"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
            return;
        }
        focusConcepto();
    });

    $(document).on('change', '#plan-cuota-tipo-valor', sincronizarTipoValor);

    $(document).on('submit', '#form-plan-cuota', function (e) {
        e.preventDefault();
        var $f = $(this);
        var id = $f.find('[name="plan_id"]').val();
        var data = $f.serializeArray();
        data.push({ name: '_token', value: token() });

        var req;
        if (id) {
            data.push({ name: '_method', value: 'PUT' });
            req = $.post('/sueldos/plan-cuota/' + id, data);
        } else {
            req = $.post(panelUrl(), data);
        }
        req.done(function (resp) {
            resetForm();
            pintar(resp);
        }).fail(function (xhr) {
            var msg = 'No se pudo guardar el plan.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
            }
            aviso(msg, 'error');
        });
    });

    $(document).on('click', '.btn-plan-cuota-editar', function () {
        var $tr = $(this).closest('tr');
        var $f = $('#form-plan-cuota');
        $f.find('[name="plan_id"]').val($tr.data('id'));
        var $campoConcepto = $f.find('.tm-concepto-sueldos-campo');
        if (typeof window.aplicarConceptoSueldosEnCampo === 'function' && $tr.data('concepto')) {
            window.aplicarConceptoSueldosEnCampo($campoConcepto, {
                id: $tr.data('concepto'),
                codigo: $tr.data('concepto-codigo'),
                descripcion: $tr.attr('data-concepto-descripcion') || ''
            });
        } else {
            $f.find('[name="concepto_id"]').val($tr.data('concepto'));
        }
        $f.find('[name="descripcion"]').val($tr.data('descripcion'));
        $f.find('[name="tipo_valor"]').val($tr.data('tipo-valor'));
        $f.find('[name="cuota_valor"]').val($tr.data('cuota-valor'));
        $f.find('[name="cuota_formula"]').val($tr.data('cuota-formula'));
        $f.find('[name="importe_total"]').val($tr.data('importe-total'));
        $f.find('[name="cuotas_totales"]').val($tr.data('cuotas-totales'));

        var pini = String($tr.data('periodo-inicio') || '');
        if (pini.length === 6) {
            $f.find('[name="periodo_inicio_mes"]').val(pini.substr(0, 4) + '-' + pini.substr(4, 2));
        }
        $f.find('[name="observacion"]').val($tr.data('observacion'));

        var corridas = [];
        try { corridas = JSON.parse($tr.attr('data-corridas') || '[]'); } catch (err) { corridas = ['mensual']; }
        $f.find('[name="corridas_afecta[]"]').prop('checked', false);
        corridas.forEach(function (c) {
            $f.find('[name="corridas_afecta[]"][value="' + c + '"]').prop('checked', true);
        });

        $('#plan-cuota-form-titulo').text('Editar plan de cuotas');
        $('#btn-plan-cuota-cancelar-edicion').removeClass('d-none');
        sincronizarTipoValor();
        $('html, body').animate({ scrollTop: $f.offset().top - 120 }, 200);
        focusConcepto();
    });

    $(document).on('click', '#btn-plan-cuota-cancelar-edicion', function () {
        resetForm();
        focusConcepto();
    });

    $(document).on('click', '.btn-plan-cuota-estado', function () {
        var id = $(this).closest('tr').data('id');
        var accion = $(this).data('accion');
        if (accion === 'cancelar' && !confirm('¿Cancelar (detener) este plan? No se liquidarán más cuotas.')) {
            return;
        }
        $.ajax({
            url: '/sueldos/plan-cuota/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'PUT', solo_estado: accion }
        }).done(pintar).fail(function (xhr) {
            aviso((xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar.', 'error');
        });
    });

    $(document).on('click', '.btn-plan-cuota-borrar', function () {
        if (!confirm('¿Eliminar este plan de cuotas?')) {
            return;
        }
        var id = $(this).closest('tr').data('id');
        $.ajax({
            url: '/sueldos/plan-cuota/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar).fail(function (xhr) {
            aviso((xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar.', 'error');
        });
    });
})(jQuery);
