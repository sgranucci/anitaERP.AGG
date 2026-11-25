(function ($) {
    'use strict';

    var cargado = false;
    var avanzarTrasConcepto = false;
    var conceptoEnterVacio = false;
    var avanzarTrasLiquidacion = false;
    var liquidacionEnterVacia = false;

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-novedades');
    }

    function panelUrl() {
        return host().data('url') || $('#novedades-empleado-panel').data('url') || '';
    }

    function replicarUrl() {
        return $('#novedades-empleado-panel').data('url-replicar') || '';
    }

    function idsNovedadesSeleccionadas() {
        var ids = [];
        host().find('.novedad-empleado-check:checked').each(function () {
            var id = parseInt($(this).val() || '0', 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function carpeta() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase)
            ? String(carpetaBase).replace(/\/$/, '')
            : '';
    }

    function urlNovedad(id) {
        return carpeta() + '/sueldos/novedad-empleado/' + id;
    }

    function esEnter(e) {
        return e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13;
    }

    function modalConsultaAbierto() {
        return $('#consultaconcepto_sueldosModal').hasClass('show')
            || $('#consultaliquidacion_sueldosModal').hasClass('show');
    }

    function esCampoConsulta(el) {
        return el.classList.contains('codigoconcepto_sueldos')
            || el.classList.contains('numeroliquidacion_sueldos');
    }

    function formNovedad() {
        return $('#form-novedad-empleado');
    }

    function camposEditables($form) {
        return $form.find('input, select, textarea').filter(function () {
            var $el = $(this);
            if (!$el.is(':visible') || $el.is(':disabled') || $el.prop('readonly')) {
                return false;
            }
            var type = String($el.attr('type') || '').toLowerCase();
            return type !== 'hidden' && type !== 'submit' && type !== 'button' && type !== 'reset' && type !== 'file';
        });
    }

    function focusSiguiente(desde) {
        var $desde = $(desde);
        var $form = $desde.closest('#form-novedad-empleado');
        if (!$form.length) {
            return;
        }
        var $lista = camposEditables($form);
        var indice = $lista.index($desde);
        if (indice < 0) {
            return;
        }

        setTimeout(function () {
            if (indice >= $lista.length - 1) {
                var $boton = $form.find('button[type="submit"]').filter(':visible').first();
                if ($boton.length) {
                    $boton.trigger('focus');
                }
                return;
            }
            var $siguiente = $lista.eq(indice + 1);
            $siguiente.trigger('focus');
            if ($siguiente.is('input, textarea')) {
                var nodo = $siguiente.get(0);
                if (nodo && typeof nodo.select === 'function') {
                    nodo.select();
                }
            }
        }, 0);
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
        var url = panelUrl();
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
        var $f = formNovedad();
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
        if (typeof window.limpiarLiquidacionSueldos === 'function') {
            window.limpiarLiquidacionSueldos($f.find('.tm-liquidacion-sueldos-campo'), false);
        } else {
            $f.find('[name="liquidacion_id"]').val('');
        }
        $('#novedad-empleado-form-titulo').text('Nueva novedad');
        $('#btn-novedad-empleado-cancelar').addClass('d-none');
        avanzarTrasConcepto = false;
        conceptoEnterVacio = false;
        avanzarTrasLiquidacion = false;
        liquidacionEnterVacia = false;
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-novedades"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    // Marca Enter en campos consulta para avanzar al resolver (capture, antes de consulta.js).
    window.addEventListener('keydown', function (e) {
        if (!esEnter(e) || !e.target || !e.target.closest) {
            return;
        }
        if (!e.target.closest('#form-novedad-empleado')) {
            return;
        }
        if (e.target.classList.contains('codigoconcepto_sueldos')) {
            avanzarTrasConcepto = true;
            conceptoEnterVacio = String(e.target.value || '').trim() === '';
        }
        if (e.target.classList.contains('numeroliquidacion_sueldos')) {
            avanzarTrasLiquidacion = true;
            liquidacionEnterVacia = String(e.target.value || '').trim() === '';
        }
    }, true);

    $(document).on('keydown.novedadEmpleadoEnter', '#form-novedad-empleado input, #form-novedad-empleado select, #form-novedad-empleado textarea', function (e) {
        if (!esEnter(e) || modalConsultaAbierto()) {
            return;
        }
        if (esCampoConsulta(this) || this.readOnly || this.disabled) {
            return;
        }
        var type = String(this.type || '').toLowerCase();
        if (type === 'submit' || type === 'button' || type === 'hidden') {
            return;
        }
        e.preventDefault();
        if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
            if (typeof this.reportValidity === 'function') {
                this.reportValidity();
            }
            return;
        }
        focusSiguiente(this);
    });

    $(document).on('change.conceptoSueldos', '#form-novedad-empleado .concepto_sueldos_id', function () {
        if (!avanzarTrasConcepto) {
            return;
        }
        var id = String($(this).val() || '').trim();
        var $codigo = formNovedad().find('.codigoconcepto_sueldos');
        var codigo = String($codigo.val() || '').trim();
        if (id) {
            avanzarTrasConcepto = false;
            conceptoEnterVacio = false;
            focusSiguiente($codigo.get(0));
            return;
        }
        if (conceptoEnterVacio && codigo === '') {
            avanzarTrasConcepto = false;
            conceptoEnterVacio = false;
            aviso('Indicá el concepto (código + Enter o lupa).', 'error');
            $codigo.trigger('focus');
            return;
        }
        if (codigo === '') {
            avanzarTrasConcepto = false;
        }
    });

    $(document).on('change.liquidacionSueldos', '#form-novedad-empleado .liquidacion_sueldos_id', function () {
        if (!avanzarTrasLiquidacion) {
            return;
        }
        var id = String($(this).val() || '').trim();
        var $numero = formNovedad().find('.numeroliquidacion_sueldos');
        var vacia = liquidacionEnterVacia;
        avanzarTrasLiquidacion = false;
        liquidacionEnterVacia = false;
        if (id || vacia) {
            focusSiguiente($numero.get(0));
        }
        // Número inválido: consulta.js ya avisó; no avanzar.
    });

    $(document).on('click', '#form-novedad-empleado .eligeconsultaconcepto_sueldos, .eligeconsultaconcepto_sueldos', function () {
        if (!formNovedad().length) {
            return;
        }
        // Solo si el ptr del modal apunta al campo de este form.
        avanzarTrasConcepto = true;
        conceptoEnterVacio = false;
    });

    $(document).on('click', '.eligeconsultaliquidacion_sueldos', function () {
        if (!formNovedad().length) {
            return;
        }
        avanzarTrasLiquidacion = true;
        liquidacionEnterVacia = false;
    });

    $(document).on('submit', '#form-novedad-empleado', function (e) {
        e.preventDefault();
        var $f = $(this);
        var conceptoId = parseInt($f.find('[name="concepto_id"]').val() || '0', 10);
        if (!conceptoId) {
            aviso('Seleccione un concepto (código + Enter o lupa).', 'error');
            $f.find('.codigoconcepto_sueldos').trigger('focus');
            return;
        }
        var id = $f.find('[name="novedad_id"]').val();
        var data = $f.serializeArray();
        data.push({ name: '_token', value: token() });
        var req;
        if (id) {
            data.push({ name: '_method', value: 'PUT' });
            req = $.post(urlNovedad(id), data);
        } else {
            var urlAlta = panelUrl();
            if (!urlAlta) {
                aviso('No se encontró la URL del panel de novedades.', 'error');
                return;
            }
            req = $.post(urlAlta, data);
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
        var $f = formNovedad();
        if (!$f.length) {
            return;
        }
        $f.find('[name="novedad_id"]').val($b.data('id'));
        $f.find('[name="valor1"]').val($b.data('valor1'));
        $f.find('[name="valor2"]').val($b.data('valor2'));
        $f.find('[name="estado"]').val($b.data('estado'));
        $f.find('[name="fecha_vto"]').val($b.data('fecha-vto') || '');
        $f.find('[name="fecha_desde"]').val($b.data('fecha-desde') || '');
        $f.find('[name="fecha_hasta"]').val($b.data('fecha-hasta') || '');
        $f.find('[name="observacion"]').val($b.data('observacion') || '');

        var $campoConcepto = $f.find('.tm-concepto-sueldos-campo');
        if (typeof window.aplicarConceptoSueldosEnCampo === 'function') {
            window.aplicarConceptoSueldosEnCampo($campoConcepto, {
                id: $b.data('concepto-id'),
                codigo: $b.data('concepto-codigo'),
                descripcion: $b.data('concepto-desc')
            });
        } else {
            $campoConcepto.find('.concepto_sueldos_id').val($b.data('concepto-id'));
            $campoConcepto.find('.codigoconcepto_sueldos').val($b.data('concepto-codigo'));
            $campoConcepto.find('.nombreconcepto_sueldos').val($b.data('concepto-desc'));
        }

        var $campoLiq = $f.find('.tm-liquidacion-sueldos-campo');
        var liqId = parseInt($b.data('liquidacion-id') || '0', 10);
        if (liqId > 0 && typeof window.aplicarLiquidacionSueldos === 'function') {
            window.aplicarLiquidacionSueldos($campoLiq, {
                id: liqId,
                numero: $b.data('liquidacion-numero'),
                descripcion: $b.attr('data-liquidacion-desc') || ''
            });
        } else if (typeof window.limpiarLiquidacionSueldos === 'function') {
            window.limpiarLiquidacionSueldos($campoLiq, false);
        } else {
            $f.find('[name="liquidacion_id"]').val('');
        }

        $('#novedad-empleado-form-titulo').text('Editar novedad #' + $b.data('id'));
        $('#btn-novedad-empleado-cancelar').removeClass('d-none');
        $f.find('.codigoconcepto_sueldos').trigger('focus');
    });

    $(document).on('click', '.btn-novedad-empleado-borrar', function () {
        if (!confirm('¿Eliminar esta novedad?')) {
            return;
        }
        var id = $(this).data('id');
        $.ajax({
            url: urlNovedad(id),
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(function (resp) {
            pintar(resp);
        }).fail(function () {
            aviso('No se pudo eliminar.', 'error');
        });
    });

    $(document).on('change', '#novedad-empleado-check-todas', function () {
        var checked = $(this).prop('checked');
        host().find('.novedad-empleado-check').prop('checked', checked);
    });

    $(document).on('change', '.novedad-empleado-check', function () {
        var $todas = host().find('#novedad-empleado-check-todas');
        if (!$todas.length) {
            return;
        }
        var total = host().find('.novedad-empleado-check').length;
        var marcadas = host().find('.novedad-empleado-check:checked').length;
        $todas.prop('checked', total > 0 && marcadas === total);
    });

    $(document).on('click', '#btn-replicar-novedades-multiempresa', function () {
        var url = replicarUrl();
        if (!url) {
            aviso('No se encontró la URL de réplica multiempresa.', 'error');
            return;
        }
        var ids = idsNovedadesSeleccionadas();
        var empresas = String($('#novedades-multiempresa-destino').data('empresas') || '').trim();
        var destino = empresas !== '' ? empresas : 'las otras empresas donde el legajo está activo';
        var alcance = ids.length > 0
            ? ('las ' + ids.length + ' novedad(es) seleccionada(s)')
            : 'todas las novedades no anuladas';
        if (!confirm('¿Replicar ' + alcance + ' a ' + destino + '?')) {
            return;
        }

        var $btn = $(this).prop('disabled', true);
        var data = { _token: token() };
        ids.forEach(function (id, i) {
            data['novedad_ids[' + i + ']'] = id;
        });

        $.ajax({
            url: url,
            method: 'POST',
            data: data
        }).done(function (resp) {
            pintar(resp);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudieron replicar las novedades.';
            aviso(msg, 'error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
})(jQuery);
