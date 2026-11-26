(function ($) {
    'use strict';

    var cargado = false;
    var seccionesAbiertas = {};

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-ausencias');
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

    function recordarSecciones() {
        host().find('#ausencias-panel .collapse[id]').each(function () {
            seccionesAbiertas[this.id] = $(this).hasClass('show');
        });
    }

    // El panel se re-renderiza entero por AJAX: sin esto las secciones vuelven al estado inicial.
    function restaurarSecciones() {
        Object.keys(seccionesAbiertas).forEach(function (id) {
            var $seccion = host().find('#' + id);
            if (!$seccion.length) {
                return;
            }
            var abierta = seccionesAbiertas[id];
            $seccion.toggleClass('show', abierta);
            host().find('[data-target="#' + id + '"]')
                .toggleClass('collapsed', !abierta)
                .attr('aria-expanded', abierta ? 'true' : 'false');
        });
    }

    function pintar(resp) {
        if (resp && resp.html) {
            var $panel = host().find('#ausencias-panel');
            if ($panel.length) {
                recordarSecciones();
                $panel.replaceWith(resp.html);
                restaurarSecciones();
            } else {
                host().html(resp.html);
            }
            toggleModalidadVacaciones();
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
            toggleModalidadVacaciones();
            if (typeof window.focusSolapaEmpleado === 'function') {
                window.focusSolapaEmpleado('#tab-ausencias');
            }
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar el panel de ausencias.</div>');
        });
    }

    function payloadForm() {
        return {
            _token: token(),
            tipo_ausencia_id: $('#ausencia_tipo').val(),
            anio_imputacion: $('#ausencia_anio').val(),
            fecha_desde: $('#ausencia_desde').val(),
            fecha_hasta: $('#ausencia_hasta').val(),
            dias: $('#ausencia_dias').val(),
            tipo_dias: $('#ausencia_tipo_dias').val(),
            modalidad_vacaciones: $('#ausencia_modalidad_wrap').is(':visible')
                ? $('#ausencia_modalidad').val()
                : '',
            estado: $('#ausencia_estado').val(),
            observacion: $('#ausencia_obs').val()
        };
    }

    function toggleModalidadVacaciones() {
        var esVac = String($('#ausencia_tipo option:selected').data('vacaciones') || '0') === '1';
        $('#ausencia_modalidad_wrap').toggle(esVac);
        if (!esVac) {
            $('#ausencia_modalidad').val('1');
        }
    }

    function resetForm() {
        $('#ausencia_id').val('');
        $('#form-ausencia').removeData('url-update');
        $('#ausencia_desde').val('');
        $('#ausencia_hasta').val('');
        $('#ausencia_dias').val('');
        $('#ausencia_anio').val('');
        $('#ausencia_obs').val('');
        $('#ausencia_estado').val('tomada');
        $('#ausencia_modalidad').val('1');
        $('#ausencia-form-titulo').text('Registrar ausencia');
        $('#btn-cancelar-ausencia').addClass('d-none');
        $('#ausencia_tipo').trigger('change');
    }

    function validarForm() {
        if (!$('#ausencia_tipo').val()) {
            aviso('Seleccioná el tipo de ausencia.', 'error');
            return false;
        }
        if (!$('#ausencia_desde').val() || !$('#ausencia_hasta').val()) {
            aviso('Completá las fechas desde y hasta.', 'error');
            return false;
        }
        return true;
    }

    function guardarAusencia() {
        if (!validarForm()) {
            return;
        }
        var id = $('#ausencia_id').val();
        var urlUpdate = $('#form-ausencia').data('url-update');
        var esEdicion = id && urlUpdate;
        var url = esEdicion ? urlUpdate : $('#form-ausencia').data('url-crear');
        if (!url) {
            aviso('No hay URL de guardado configurada.', 'error');
            return;
        }
        var data = payloadForm();
        if (esEdicion) {
            data._method = 'PUT';
        }
        var $btn = $('#btn-guardar-ausencia').prop('disabled', true);
        $.ajax({ url: url, type: 'POST', data: data })
            .done(function (resp) {
                pintar(resp);
            })
            .fail(function (xhr) {
                var msg = 'No se pudo guardar la ausencia.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(function (a) { return a[0]; }).join(' ');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                aviso(msg, 'error');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    // Días corridos/hábiles aproximados en cliente (el servidor recalcula el valor final).
    function autoDias() {
        var d = $('#ausencia_desde').val();
        var h = $('#ausencia_hasta').val();
        if (!d || !h) {
            return;
        }
        var desde = new Date(d + 'T00:00:00');
        var hasta = new Date(h + 'T00:00:00');
        if (hasta < desde) {
            return;
        }
        if ($('#ausencia_tipo_dias').val() === 'habiles') {
            var dias = 0;
            var cur = new Date(desde);
            while (cur <= hasta) {
                var wd = cur.getDay();
                if (wd !== 0 && wd !== 6) {
                    dias++;
                }
                cur.setDate(cur.getDate() + 1);
            }
            $('#ausencia_dias').val(dias);
        } else {
            var ms = hasta - desde;
            $('#ausencia_dias').val(Math.round(ms / 86400000) + 1);
        }
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-ausencias"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('click', '#btn-devengar-ausencias', function () {
        var url = $('#ausencias-panel').data('url-devengar');
        $.ajax({ url: url, type: 'POST', data: { _token: token() } })
            .done(pintar)
            .fail(function () { aviso('No se pudo recalcular.', 'error'); });
    });

    $(document).on('change', '#ausencia_tipo', function () {
        var td = $(this).find('option:selected').data('tipo-dias');
        if (td) {
            $('#ausencia_tipo_dias').val(td);
        }
        toggleModalidadVacaciones();
        autoDias();
    });

    $(document).on('change', '#ausencia_desde, #ausencia_hasta, #ausencia_tipo_dias', autoDias);

    // Guardar solo la ausencia (nunca el legajo).
    $(document).on('click', '#btn-guardar-ausencia', function (e) {
        e.preventDefault();
        e.stopPropagation();
        guardarAusencia();
    });

    $(document).on('click', '.btn-editar-ausencia', function () {
        var a = $(this).data('ausencia');
        if (!a) {
            return;
        }
        $('#ausencia_id').val(a.id);
        $('#form-ausencia').data('url-update', a.url);
        $('#ausencia_tipo').val(a.tipo_ausencia_id);
        $('#ausencia_anio').val(a.anio_imputacion || '');
        $('#ausencia_desde').val(a.fecha_desde || '');
        $('#ausencia_hasta').val(a.fecha_hasta || '');
        $('#ausencia_dias').val(a.dias || '');
        $('#ausencia_tipo_dias').val(a.tipo_dias || 'corridos');
        $('#ausencia_modalidad').val(a.modalidad_vacaciones || '1');
        $('#ausencia_estado').val(a.estado || 'tomada');
        $('#ausencia_obs').val(a.observacion || '');
        $('#ausencia_tipo').trigger('change');
        $('#ausencia-form-titulo').text('Editar ausencia #' + a.id);
        $('#btn-cancelar-ausencia').removeClass('d-none');

        var enfocarForm = function () {
            var $form = $('#form-ausencia');
            if ($form.length) {
                $('html, body').animate({ scrollTop: $form.offset().top - 120 }, 250);
            }
        };
        var $seccionForm = $('#ausencias-seccion-form');
        if ($seccionForm.length && !$seccionForm.hasClass('show')) {
            $seccionForm.one('shown.bs.collapse', enfocarForm).collapse('show');
        } else {
            enfocarForm();
        }
    });

    $(document).on('click', '#btn-cancelar-ausencia', function () {
        resetForm();
    });

    // Plus/Adelanto: filtro de las vacaciones que todavía no lo tienen y asignación a varias de una vez.
    function aplicarFiltroSinModalidad() {
        var solo = $('#ausencias-solo-sin-modalidad').is(':checked');
        $('#tabla-ausencias-empleado tbody tr[data-ausencia-id]').each(function () {
            var oculta = solo && String($(this).data('sin-modalidad')) !== '1';
            $(this).toggleClass('d-none', oculta);
            if (oculta) {
                $(this).find('.ausencia-check-modalidad').prop('checked', false);
            }
        });
    }

    $(document).on('change', '#ausencias-solo-sin-modalidad', aplicarFiltroSinModalidad);

    $(document).on('change', '#ausencias-check-todas', function () {
        var marcado = $(this).is(':checked');
        $('#tabla-ausencias-empleado tbody tr[data-ausencia-id]:not(.d-none) .ausencia-check-modalidad')
            .prop('checked', marcado);
    });

    $(document).on('click', '#btn-modalidad-masiva', function () {
        var ids = $('.ausencia-check-modalidad:checked').map(function () { return this.value; }).get();
        if (!ids.length) {
            aviso('Tildá al menos una vacación.', 'error');
            return;
        }
        var $sel = $('#ausencias-modalidad-masiva');
        var etiqueta = $sel.find('option:selected').text();
        if (!confirm('¿Marcar ' + ids.length + ' vacación(es) como "' + etiqueta + '"?')) {
            return;
        }
        var recordarFiltro = $('#ausencias-solo-sin-modalidad').is(':checked');
        $.ajax({
            url: $('#ausencias-modalidad-bar').data('url-modalidad'),
            type: 'POST',
            data: { _token: token(), ids: ids, modalidad_vacaciones: $sel.val() }
        }).done(function (resp) {
            pintar(resp);
            if (recordarFiltro) {
                $('#ausencias-solo-sin-modalidad').prop('checked', true);
                aplicarFiltroSinModalidad();
            }
        }).fail(function (xhr) {
            var msg = 'No se pudo asignar Plus/Adelanto.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            aviso(msg, 'error');
        });
    });

    $(document).on('click', '.btn-eliminar-ausencia', function () {
        if (!confirm('¿Eliminar esta ausencia? Se recalcularán los saldos.')) {
            return;
        }
        var url = $(this).data('url');
        $.ajax({ url: url, type: 'POST', data: { _token: token(), _method: 'DELETE' } })
            .done(pintar)
            .fail(function () { aviso('No se pudo eliminar.', 'error'); });
    });
})(jQuery);
