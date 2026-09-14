(function (window, $) {
    'use strict';

    var modalAbriendo = false;
    var buscaTimer = null;

    function idsFiltroColor() {
        if (typeof window.payloadExtraConsultaColor === 'function') {
            var extra = window.payloadExtraConsultaColor() || {};
            if (Array.isArray(extra.ids)) {
                return extra.ids;
            }
        }
        return [];
    }

    function buscar_datos_color(consulta) {
        $('#datoscolor').html('<tr><td colspan="4" class="text-muted">Buscando…</td></tr>');
        $.ajax({
            url: carpetaBase + '/stock/color/consultacolor',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                consulta: consulta || '',
                ids: idsFiltroColor()
            }
        }).done(function (respuesta) {
            $('#datoscolor').html((respuesta && respuesta.data) ? respuesta.data
                : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datoscolor').html('<tr><td colspan="4" class="text-danger">Error al consultar colores</td></tr>');
        });
    }

    function elegirPrimeraColor() {
        var $btn = $('#datoscolor .elige-color').first();
        if ($btn.length) {
            $btn.trigger('click');
            return true;
        }
        return false;
    }

    function aplicarColorEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data) {
            return;
        }
        $campo.find('.color_id').val(data.id || '');
        $campo.find('.codigocolor').val(data.codigo || '').removeAttr('data-color-invalido');
        $campo.find('.descripcioncolor').val(data.nombre || '');
        $campo.trigger('color:seleccionado', [data]);
    }

    function resolverColorCodigo($campo, avisar) {
        var codigo = String($campo.find('.codigocolor').val() || '').trim();
        if (codigo === '') {
            $campo.find('.color_id').val('');
            $campo.find('.descripcioncolor').val('');
            return;
        }
        if ($('#consultacolorModal').hasClass('show') || modalAbriendo) {
            return;
        }
        $.get(carpetaBase + '/stock/color/resolvercolor', {
            codigo: codigo,
            ids: idsFiltroColor()
        }).done(function (r) {
            if (r && r.ok) {
                aplicarColorEnCampo($campo, r);
                return;
            }
            $campo.find('.color_id').val('');
            $campo.find('.descripcioncolor').val('');
            $campo.find('.codigocolor').attr('data-color-invalido', '1');
            if (avisar) {
                setTimeout(function () {
                    alert(r && r.error ? r.error : 'Color no encontrado');
                    $campo.find('.codigocolor').trigger('focus');
                }, 0);
            }
        });
    }

    window.activa_eventos_consultacolor = function () {
        $(document)
            .off('click.consultaColor', '.consultacolor')
            .on('click.consultaColor', '.consultacolor', function (e) {
                e.preventDefault();
                modalAbriendo = true;
                window.ptrcolor_campo = $(this).closest('.tm-color-campo');
                $('#consultacolor').val('');
                $('#consultacolorModal').modal('show');
            });

        $('#consultacolorModal')
            .off('shown.bs.modal.consultaColor')
            .on('shown.bs.modal.consultaColor', function () {
                modalAbriendo = false;
                $('#consultacolor').trigger('focus');
                buscar_datos_color('');
            })
            .off('hidden.bs.modal.consultaColor')
            .on('hidden.bs.modal.consultaColor', function () {
                modalAbriendo = false;
            });

        $(document)
            .off('keyup.consultaColorBuscar', '#consultacolor')
            .on('keyup.consultaColorBuscar', '#consultacolor', function (e) {
                if (e.which === 13) {
                    return;
                }
                clearTimeout(buscaTimer);
                var v = String($(this).val() || '').trim();
                buscaTimer = setTimeout(function () { buscar_datos_color(v); }, 250);
            })
            .off('keydown.consultaColorEnter', '#consultacolor')
            .on('keydown.consultaColorEnter', '#consultacolor', function (e) {
                if (e.which !== 13 && e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                if (!elegirPrimeraColor()) {
                    buscar_datos_color(String($(this).val() || '').trim());
                }
            })
            .off('click.eligeColor', '.elige-color')
            .on('click.eligeColor', '.elige-color', function () {
                var $tr = $(this).closest('tr');
                var data = {
                    id: $tr.data('id'),
                    codigo: $tr.data('codigo'),
                    nombre: $tr.data('nombre')
                };
                if (window.ptrcolor_campo) {
                    aplicarColorEnCampo(window.ptrcolor_campo, data);
                }
                $('#consultacolorModal').modal('hide');
            })
            .off('click.aceptaColor', '#aceptaconsultacolorModal')
            .on('click.aceptaColor', '#aceptaconsultacolorModal', function () {
                if (!elegirPrimeraColor()) {
                    $('#consultacolorModal').modal('hide');
                }
            })
            .off('keydown.colorF1', '.codigocolor')
            .on('keydown.colorF1', '.codigocolor', function (e) {
                if (e.key !== 'F1' && e.keyCode !== 112) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-color-campo').find('.consultacolor').trigger('click');
            })
            .off('keydown.colorEnter', '.codigocolor')
            .on('keydown.colorEnter', '.codigocolor', function (e) {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                resolverColorCodigo($(this).closest('.tm-color-campo'), true);
            })
            .off('blur.colorCodigo', '.codigocolor')
            .on('blur.colorCodigo', '.codigocolor', function () {
                if (modalAbriendo || $('#consultacolorModal').hasClass('show')) {
                    return;
                }
                resolverColorCodigo($(this).closest('.tm-color-campo'), false);
            })
            .off('input.colorLimpia', '.codigocolor')
            .on('input.colorLimpia', '.codigocolor', function () {
                $(this).removeAttr('data-color-invalido');
            });
    };

    $(function () {
        if (typeof window.activa_eventos_consultacolor === 'function') {
            window.activa_eventos_consultacolor();
        }
    });
})(window, window.jQuery);
