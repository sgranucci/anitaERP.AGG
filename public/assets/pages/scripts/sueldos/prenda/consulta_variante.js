(function (window, $) {
    'use strict';

    var modalAbriendo = false;
    var buscaTimer = null;
    var prendaIdActual = 0;

    function buscar_datos_variante(consulta) {
        if (prendaIdActual <= 0) {
            $('#datosvarianteprenda').html('<tr><td colspan="6" class="text-muted">Elija primero una prenda</td></tr>');
            return;
        }
        $('#datosvarianteprenda').html('<tr><td colspan="6" class="text-muted">Buscando…</td></tr>');
        $.ajax({
            url: carpetaBase + '/sueldos/indumentaria/prenda/' + encodeURIComponent(prendaIdActual) + '/consultavariante',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' }
        }).done(function (respuesta) {
            $('#datosvarianteprenda').html((respuesta && respuesta.data) ? respuesta.data
                : '<tr><td colspan="6" class="text-muted">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datosvarianteprenda').html('<tr><td colspan="6" class="text-danger">Error al consultar variantes</td></tr>');
        });
    }

    function elegirPrimeraVariante() {
        var $btn = $('#datosvarianteprenda .elige-variante-prenda').first();
        if ($btn.length) {
            $btn.trigger('click');
            return true;
        }
        return false;
    }

    function aplicarVarianteEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data) {
            return;
        }
        $campo.find('.prenda_articulo_id').val(data.id || '');
        $campo.find('.codigovarianteprenda').val(data.sku || data.codigo || '').removeAttr('data-variante-invalido');
        $campo.find('.descripcionvarianteprenda').val(data.nombre || '');
        $campo.attr('data-saldo', data.saldo != null ? data.saldo : '');
        $campo.attr('data-articulo', data.articulo_id != null ? data.articulo_id : '');
        $campo.trigger('variante:seleccionada', [data]);
    }

    function limpiarVariante($campo) {
        if (!$campo || !$campo.length) {
            return;
        }
        $campo.find('.prenda_articulo_id').val('');
        $campo.find('.codigovarianteprenda').val('');
        $campo.find('.descripcionvarianteprenda').val('');
        $campo.removeAttr('data-saldo').removeAttr('data-articulo');
        $campo.trigger('variante:seleccionada', [{ id: '', sku: '', nombre: '' }]);
    }

    function resolverVarianteCodigo($campo, avisar) {
        var prendaId = parseInt(String($campo.closest('tr').find('.prenda_id').val() || '0'), 10);
        var codigo = String($campo.find('.codigovarianteprenda').val() || '').trim();
        if (prendaId <= 0) {
            limpiarVariante($campo);
            return;
        }
        if (codigo === '') {
            limpiarVariante($campo);
            return;
        }
        if ($('#consultavarianteprendaModal').hasClass('show') || modalAbriendo) {
            return;
        }
        $.get(carpetaBase + '/sueldos/indumentaria/resolvervariante', {
            prenda_id: prendaId,
            codigo: codigo
        }).done(function (r) {
            if (r && r.ok) {
                aplicarVarianteEnCampo($campo, r);
                return;
            }
            $campo.find('.prenda_articulo_id').val('');
            $campo.find('.descripcionvarianteprenda').val('');
            $campo.find('.codigovarianteprenda').attr('data-variante-invalido', '1');
            $campo.trigger('variante:seleccionada', [{ id: '', sku: codigo, nombre: '' }]);
            if (avisar) {
                setTimeout(function () {
                    if (typeof window.liberarPantallaModalesBloqueados === 'function') {
                        window.liberarPantallaModalesBloqueados();
                    }
                    alert(r && r.error ? r.error : 'Variante no encontrada');
                    $campo.find('.codigovarianteprenda').trigger('focus');
                }, 0);
            }
        });
    }

    window.activa_eventos_consultavarianteprenda = function () {
        $(document)
            .off('click.consultaVariantePrenda', '.consultavarianteprenda')
            .on('click.consultaVariantePrenda', '.consultavarianteprenda', function (e) {
                e.preventDefault();
                var $campo = $(this).closest('.tm-variante-prenda-campo');
                var prendaId = parseInt(String($campo.closest('tr').find('.prenda_id').val() || '0'), 10);
                if (prendaId <= 0) {
                    alert('Elija primero la prenda.');
                    $campo.closest('tr').find('.codigoprenda').trigger('focus');
                    return;
                }
                modalAbriendo = true;
                window.ptrvarianteprenda_campo = $campo;
                prendaIdActual = prendaId;
                var ctx = String($campo.closest('tr').find('.descripcionprenda').val() || '').trim();
                var cod = String($campo.closest('tr').find('.codigoprenda').val() || '').trim();
                $('#consultavarianteprenda-contexto').text(
                    (cod ? ('Prenda ' + cod + (ctx ? ' — ' + ctx : '')) : (ctx || 'Prenda #' + prendaId))
                );
                $('#consultavarianteprenda').val('');
                $('#consultavarianteprendaModal').modal('show');
            });

        $('#consultavarianteprendaModal')
            .off('shown.bs.modal.consultaVariantePrenda')
            .on('shown.bs.modal.consultaVariantePrenda', function () {
                modalAbriendo = false;
                $('#consultavarianteprenda').trigger('focus');
                buscar_datos_variante('');
            })
            .off('hidden.bs.modal.consultaVariantePrenda')
            .on('hidden.bs.modal.consultaVariantePrenda', function () {
                modalAbriendo = false;
            });

        $(document)
            .off('keyup.consultaVarianteBuscar', '#consultavarianteprenda')
            .on('keyup.consultaVarianteBuscar', '#consultavarianteprenda', function (e) {
                if (e.which === 13) {
                    return;
                }
                clearTimeout(buscaTimer);
                var v = String($(this).val() || '').trim();
                buscaTimer = setTimeout(function () { buscar_datos_variante(v); }, 250);
            })
            .off('keydown.consultaVarianteEnter', '#consultavarianteprenda')
            .on('keydown.consultaVarianteEnter', '#consultavarianteprenda', function (e) {
                if (e.which !== 13 && e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                if (!elegirPrimeraVariante()) {
                    buscar_datos_variante(String($(this).val() || '').trim());
                }
            })
            .off('click.eligeVariantePrenda', '.elige-variante-prenda')
            .on('click.eligeVariantePrenda', '.elige-variante-prenda', function () {
                var $tr = $(this).closest('tr');
                var data = {
                    id: $tr.data('id'),
                    sku: $tr.data('sku'),
                    codigo: $tr.data('sku'),
                    color: $tr.data('color'),
                    talle: $tr.data('talle'),
                    articulo_id: $tr.data('articulo'),
                    saldo: $tr.data('saldo'),
                    nombre: $tr.data('nombre')
                };
                if (window.ptrvarianteprenda_campo) {
                    aplicarVarianteEnCampo(window.ptrvarianteprenda_campo, data);
                }
                $('#consultavarianteprendaModal').modal('hide');
            })
            .off('click.aceptaVariantePrenda', '#aceptaconsultavarianteprendaModal')
            .on('click.aceptaVariantePrenda', '#aceptaconsultavarianteprendaModal', function () {
                if (!elegirPrimeraVariante()) {
                    $('#consultavarianteprendaModal').modal('hide');
                }
            })
            .off('keydown.varianteF1', '.codigovarianteprenda')
            .on('keydown.varianteF1', '.codigovarianteprenda', function (e) {
                if (e.key !== 'F1' && e.keyCode !== 112) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-variante-prenda-campo').find('.consultavarianteprenda').trigger('click');
            })
            .off('keydown.varianteEnter', '.codigovarianteprenda')
            .on('keydown.varianteEnter', '.codigovarianteprenda', function (e) {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                resolverVarianteCodigo($(this).closest('.tm-variante-prenda-campo'), false);
            })
            .off('blur.varianteCodigo', '.codigovarianteprenda')
            .on('blur.varianteCodigo', '.codigovarianteprenda', function () {
                if (modalAbriendo || $('#consultavarianteprendaModal').hasClass('show')) {
                    return;
                }
                resolverVarianteCodigo($(this).closest('.tm-variante-prenda-campo'), false);
            })
            .off('input.varianteLimpia', '.codigovarianteprenda')
            .on('input.varianteLimpia', '.codigovarianteprenda', function () {
                $(this).removeAttr('data-variante-invalido');
            });
    };

    window.aplicarVarianteEnCampo = aplicarVarianteEnCampo;
    window.limpiarVariantePrendaCampo = limpiarVariante;

    $(function () {
        if (typeof window.activa_eventos_consultavarianteprenda === 'function') {
            window.activa_eventos_consultavarianteprenda();
        }
    });
})(window, window.jQuery);
