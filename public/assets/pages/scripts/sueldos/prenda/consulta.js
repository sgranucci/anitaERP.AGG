(function (window, $) {
    'use strict';

    var modalAbriendo = false;
    var buscaTimer = null;

    function buscar_datos_prenda(consulta) {
        $('#datosprenda').html('<tr><td colspan="4" class="text-muted">Buscando…</td></tr>');
        $.ajax({
            url: carpetaBase + '/sueldos/prenda/consultaprenda',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' }
        }).done(function (respuesta) {
            $('#datosprenda').html((respuesta && respuesta.data) ? respuesta.data
                : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datosprenda').html('<tr><td colspan="4" class="text-danger">Error al consultar prendas</td></tr>');
        });
    }

    function elegirPrimeraPrenda() {
        var $btn = $('#datosprenda .elige-prenda').first();
        if ($btn.length) {
            $btn.trigger('click');
            return true;
        }
        return false;
    }

    function aplicarPrendaEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data) {
            return;
        }
        $campo.find('.prenda_id').val(data.id || '');
        $campo.find('.codigoprenda').val(data.codigo || '').removeAttr('data-prenda-invalido');
        $campo.find('.descripcionprenda').val(data.nombre || '');
        $campo.trigger('prenda:seleccionada', [data]);
    }

    function resolverPrendaCodigo($campo, avisar) {
        var codigo = String($campo.find('.codigoprenda').val() || '').trim();
        if (codigo === '') {
            $campo.find('.prenda_id').val('');
            $campo.find('.descripcionprenda').val('');
            $campo.trigger('prenda:seleccionada', [{ id: '', codigo: '', nombre: '' }]);
            return;
        }
        if ($('#consultaprendaModal').hasClass('show') || modalAbriendo) {
            return;
        }
        $.get(carpetaBase + '/sueldos/prenda/resolverprenda', { codigo: codigo }).done(function (r) {
            if (r && r.ok) {
                aplicarPrendaEnCampo($campo, r);
                return;
            }
            $campo.find('.prenda_id').val('');
            $campo.find('.descripcionprenda').val('');
            $campo.find('.codigoprenda').attr('data-prenda-invalido', '1');
            $campo.trigger('prenda:seleccionada', [{ id: '', codigo: codigo, nombre: '' }]);
            if (avisar) {
                setTimeout(function () {
                    if (typeof window.liberarPantallaModalesBloqueados === 'function') {
                        window.liberarPantallaModalesBloqueados();
                    }
                    alert(r && r.error ? r.error : 'Prenda no encontrada');
                    $campo.find('.codigoprenda').trigger('focus');
                }, 0);
            }
        });
    }

    window.activa_eventos_consultaprenda = function () {
        $(document)
            .off('click.consultaPrenda', '.consultaprenda')
            .on('click.consultaPrenda', '.consultaprenda', function (e) {
                e.preventDefault();
                modalAbriendo = true;
                window.ptrprenda_campo = $(this).closest('.tm-prenda-campo');
                $('#consultaprenda').val('');
                $('#consultaprendaModal').modal('show');
            });

        $('#consultaprendaModal')
            .off('shown.bs.modal.consultaPrenda')
            .on('shown.bs.modal.consultaPrenda', function () {
                modalAbriendo = false;
                $('#consultaprenda').trigger('focus');
                buscar_datos_prenda('');
            })
            .off('hidden.bs.modal.consultaPrenda')
            .on('hidden.bs.modal.consultaPrenda', function () {
                modalAbriendo = false;
            });

        $(document)
            .off('keyup.consultaPrendaBuscar', '#consultaprenda')
            .on('keyup.consultaPrendaBuscar', '#consultaprenda', function (e) {
                if (e.which === 13) {
                    return;
                }
                clearTimeout(buscaTimer);
                var v = String($(this).val() || '').trim();
                buscaTimer = setTimeout(function () { buscar_datos_prenda(v); }, 250);
            })
            .off('keydown.consultaPrendaEnter', '#consultaprenda')
            .on('keydown.consultaPrendaEnter', '#consultaprenda', function (e) {
                if (e.which !== 13 && e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                if (!elegirPrimeraPrenda()) {
                    buscar_datos_prenda(String($(this).val() || '').trim());
                }
            })
            .off('click.eligePrenda', '.elige-prenda')
            .on('click.eligePrenda', '.elige-prenda', function () {
                var $tr = $(this).closest('tr');
                var data = {
                    id: $tr.data('id'),
                    codigo: $tr.data('codigo'),
                    nombre: $tr.data('nombre')
                };
                if (window.ptrprenda_campo) {
                    aplicarPrendaEnCampo(window.ptrprenda_campo, data);
                }
                $('#consultaprendaModal').modal('hide');
            })
            .off('click.aceptaPrenda', '#aceptaconsultaprendaModal')
            .on('click.aceptaPrenda', '#aceptaconsultaprendaModal', function () {
                if (!elegirPrimeraPrenda()) {
                    $('#consultaprendaModal').modal('hide');
                }
            })
            .off('keydown.prendaF1', '.codigoprenda')
            .on('keydown.prendaF1', '.codigoprenda', function (e) {
                if (e.key !== 'F1' && e.keyCode !== 112) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-prenda-campo').find('.consultaprenda').trigger('click');
            })
            .off('keydown.prendaEnter', '.codigoprenda')
            .on('keydown.prendaEnter', '.codigoprenda', function (e) {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                // Enter avanza: blur resuelve en silencio (no alert)
                var $campo = $(this).closest('.tm-prenda-campo');
                resolverPrendaCodigo($campo, false);
            })
            .off('blur.prendaCodigo', '.codigoprenda')
            .on('blur.prendaCodigo', '.codigoprenda', function () {
                if (modalAbriendo || $('#consultaprendaModal').hasClass('show')) {
                    return;
                }
                resolverPrendaCodigo($(this).closest('.tm-prenda-campo'), false);
            })
            .off('input.prendaLimpia', '.codigoprenda')
            .on('input.prendaLimpia', '.codigoprenda', function () {
                $(this).removeAttr('data-prenda-invalido');
            });
    };

    window.aplicarPrendaEnCampo = aplicarPrendaEnCampo;

    $(function () {
        if (typeof window.activa_eventos_consultaprenda === 'function') {
            window.activa_eventos_consultaprenda();
        }
    });
})(window, window.jQuery);
