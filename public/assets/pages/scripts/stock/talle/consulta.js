(function (window, $) {
    'use strict';

    var modalAbriendo = false;
    var buscaTimer = null;

    function idsFiltroTalle() {
        if (typeof window.payloadExtraConsultaTalle === 'function') {
            var extra = window.payloadExtraConsultaTalle() || {};
            if (Array.isArray(extra.ids)) {
                return extra.ids;
            }
        }
        return [];
    }

    function buscar_datos_talle(consulta) {
        $('#datostalle').html('<tr><td colspan="4" class="text-muted">Buscando…</td></tr>');
        $.ajax({
            url: carpetaBase + '/stock/talle/consultatalle',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                consulta: consulta || '',
                ids: idsFiltroTalle()
            }
        }).done(function (respuesta) {
            $('#datostalle').html((respuesta && respuesta.data) ? respuesta.data
                : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>');
        }).fail(function () {
            $('#datostalle').html('<tr><td colspan="4" class="text-danger">Error al consultar talles</td></tr>');
        });
    }

    function elegirPrimeraTalle() {
        var $btn = $('#datostalle .elige-talle').first();
        if ($btn.length) {
            $btn.trigger('click');
            return true;
        }
        return false;
    }

    function aplicarTalleEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data) {
            return;
        }
        $campo.find('.talle_id').val(data.id || '');
        $campo.find('.codigotalle').val(data.codigo || '').removeAttr('data-talle-invalido');
        $campo.find('.descripciontalle').val(data.nombre || '');
        $campo.trigger('talle:seleccionado', [data]);
    }

    function resolverTalleCodigo($campo, avisar) {
        var codigo = String($campo.find('.codigotalle').val() || '').trim();
        if (codigo === '') {
            $campo.find('.talle_id').val('');
            $campo.find('.descripciontalle').val('');
            return;
        }
        if ($('#consultatalleModal').hasClass('show') || modalAbriendo) {
            return;
        }
        $.get(carpetaBase + '/stock/talle/resolvertalles', {
            codigo: codigo,
            ids: idsFiltroTalle()
        }).done(function (r) {
            if (r && r.ok) {
                aplicarTalleEnCampo($campo, r);
                return;
            }
            $campo.find('.talle_id').val('');
            $campo.find('.descripciontalle').val('');
            $campo.find('.codigotalle').attr('data-talle-invalido', '1');
            if (avisar) {
                setTimeout(function () {
                    alert(r && r.error ? r.error : 'Talle no encontrado');
                    $campo.find('.codigotalle').trigger('focus');
                }, 0);
            }
        });
    }

    window.activa_eventos_consultatalle = function () {
        $(document)
            .off('click.consultaTalle', '.consultatalle')
            .on('click.consultaTalle', '.consultatalle', function (e) {
                e.preventDefault();
                modalAbriendo = true;
                window.ptrtalle_campo = $(this).closest('.tm-talle-campo');
                $('#consultatalle').val('');
                $('#consultatalleModal').modal('show');
            });

        $('#consultatalleModal')
            .off('shown.bs.modal.consultaTalle')
            .on('shown.bs.modal.consultaTalle', function () {
                modalAbriendo = false;
                $('#consultatalle').trigger('focus');
                buscar_datos_talle('');
            })
            .off('hidden.bs.modal.consultaTalle')
            .on('hidden.bs.modal.consultaTalle', function () {
                modalAbriendo = false;
            });

        $(document)
            .off('keyup.consultaTalleBuscar', '#consultatalle')
            .on('keyup.consultaTalleBuscar', '#consultatalle', function (e) {
                if (e.which === 13) {
                    return;
                }
                clearTimeout(buscaTimer);
                var v = String($(this).val() || '').trim();
                buscaTimer = setTimeout(function () { buscar_datos_talle(v); }, 250);
            })
            .off('keydown.consultaTalleEnter', '#consultatalle')
            .on('keydown.consultaTalleEnter', '#consultatalle', function (e) {
                if (e.which !== 13 && e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                if (!elegirPrimeraTalle()) {
                    buscar_datos_talle(String($(this).val() || '').trim());
                }
            })
            .off('click.eligeTalle', '.elige-talle')
            .on('click.eligeTalle', '.elige-talle', function () {
                var $tr = $(this).closest('tr');
                var data = {
                    id: $tr.data('id'),
                    codigo: $tr.data('codigo'),
                    nombre: $tr.data('nombre')
                };
                if (window.ptrtalle_campo) {
                    aplicarTalleEnCampo(window.ptrtalle_campo, data);
                }
                $('#consultatalleModal').modal('hide');
            })
            .off('click.aceptaTalle', '#aceptaconsultatalleModal')
            .on('click.aceptaTalle', '#aceptaconsultatalleModal', function () {
                if (!elegirPrimeraTalle()) {
                    $('#consultatalleModal').modal('hide');
                }
            })
            .off('keydown.talleF1', '.codigotalle')
            .on('keydown.talleF1', '.codigotalle', function (e) {
                if (e.key !== 'F1' && e.keyCode !== 112) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-talle-campo').find('.consultatalle').trigger('click');
            })
            .off('keydown.talleEnter', '.codigotalle')
            .on('keydown.talleEnter', '.codigotalle', function (e) {
                if (e.key !== 'Enter') {
                    return;
                }
                // POS Facturación Local: Enter lo maneja pos.js (capture) con navegación entre campos
                if ($(this).closest('#fl-modal-var').length) {
                    return;
                }
                e.preventDefault();
                resolverTalleCodigo($(this).closest('.tm-talle-campo'), true);
            })
            .off('blur.talleCodigo', '.codigotalle')
            .on('blur.talleCodigo', '.codigotalle', function () {
                if ($(this).closest('#fl-modal-var').length) {
                    return;
                }
                if (modalAbriendo || $('#consultatalleModal').hasClass('show')) {
                    return;
                }
                resolverTalleCodigo($(this).closest('.tm-talle-campo'), false);
            })
            .off('input.talleLimpia', '.codigotalle')
            .on('input.talleLimpia', '.codigotalle', function () {
                $(this).removeAttr('data-talle-invalido');
            });
    };

    $(function () {
        if (typeof window.activa_eventos_consultatalle === 'function') {
            window.activa_eventos_consultatalle();
        }
    });
})(window, window.jQuery);
