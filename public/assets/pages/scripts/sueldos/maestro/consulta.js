/* global carpetaBase */
(function ($) {
    'use strict';

    var ptrCampo = $();
    var timerBusqueda = null;
    var modalAbriendose = false;
    var titulos = {
        categoria: 'Categorías',
        agrupamiento: 'Agrupamientos',
        lugartrabajo: 'Lugares de trabajo',
        vacacion: 'Vacaciones',
        art: 'ART'
    };

    function campoDesde($el) {
        var $c = $($el).closest('.tm-maestro-sueldos-campo');
        return $c.length ? $c : $();
    }

    function maestroDe($campo) {
        return String(($campo && $campo.length ? $campo.attr('data-maestro') : '') || '').trim();
    }

    function parsearHtml(respuesta) {
        if (respuesta && typeof respuesta === 'object') {
            return respuesta.data || '';
        }
        var resp = String(respuesta || '').trim();
        if (resp === '') {
            return '';
        }
        try {
            var parsed = JSON.parse(resp);
            return (parsed && parsed.data) ? parsed.data : '';
        } catch (e) {
            return resp.indexOf('<tr') >= 0 ? resp : '';
        }
    }

    function buscar(maestro, consulta) {
        if (!maestro) {
            return;
        }
        $.ajax({
            url: carpetaBase + '/sueldos/maestro/' + encodeURIComponent(maestro) + '/consulta',
            type: 'POST',
            dataType: 'HTML',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                    ($('input[name="_token"]').first().val() || '')
            },
            data: { consulta: consulta || '' }
        }).done(function (r) {
            $('#datosmaestro_sueldos').html(parsearHtml(r));
        });
    }

    function actualizarLink($campo, id) {
        var $link = $campo.find('.btn-link-editar-maestro-sueldos');
        if (!$link.length) {
            return;
        }
        var maestro = maestroDe($campo);
        var n = parseInt(id || '0', 10);
        if (n > 0 && maestro) {
            $link
                .attr('href', carpetaBase + '/sueldos/' + maestro + '/' + n + '/editar?origen=modal_consulta&vista=consulta')
                .removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function aplicar($campo, data) {
        if (!$campo.length || !data || !data.id) {
            return;
        }
        $campo.find('.maestro_sueldos_id').val(data.id);
        $campo.find('.codigomaestro_sueldos').val(data.codigo != null ? data.codigo : '').removeData('maestro-invalido');
        $campo.find('.nombremaestro_sueldos').val(data.nombre || '');
        if (data.origen_bases != null) {
            $campo.find('.maestro_sueldos_id').attr('data-origen-bases', data.origen_bases);
        }
        actualizarLink($campo, data.id);
        $campo.find('.maestro_sueldos_id').trigger('change.maestroSueldos', [data]);
    }

    function limpiar($campo, mantenerCodigo) {
        if (!$campo.length) {
            return;
        }
        $campo.find('.maestro_sueldos_id').val('').removeAttr('data-origen-bases');
        if (!mantenerCodigo) {
            $campo.find('.codigomaestro_sueldos').val('');
        }
        $campo.find('.nombremaestro_sueldos').val('');
        actualizarLink($campo, 0);
        $campo.find('.maestro_sueldos_id').trigger('change.maestroSueldos', [null]);
    }

    function enfocarSiguiente($campo) {
        var sig = String($campo.find('.codigomaestro_sueldos').attr('data-siguiente') || '').trim();
        if (!sig || !$(sig).length) {
            return;
        }
        setTimeout(function () {
            var $sig = $(sig);
            $sig.trigger('focus');
            if ($sig.is('input, textarea, select')) {
                try { $sig.trigger('select'); } catch (e) { /* ignore */ }
            }
        }, 40);
    }

    function resolverPorCodigo($campo, alertar, pasarSiguiente) {
        var maestro = maestroDe($campo);
        var codigo = String($campo.find('.codigomaestro_sueldos').val() || '').trim();
        if (!maestro) {
            return;
        }
        if (codigo === '') {
            limpiar($campo, true);
            if (pasarSiguiente) {
                enfocarSiguiente($campo);
            }
            return;
        }
        if ($('#consultamaestro_sueldosModal').hasClass('show') || modalAbriendose) {
            return;
        }
        $.get(carpetaBase + '/sueldos/maestro/' + encodeURIComponent(maestro) + '/leerporcodigo/' + encodeURIComponent(codigo))
            .done(function (data) {
                aplicar($campo, data);
                if (pasarSiguiente) {
                    enfocarSiguiente($campo);
                }
            })
            .fail(function () {
                $campo.find('.codigomaestro_sueldos').data('maestro-invalido', 1);
                limpiar($campo, true);
                if (alertar) {
                    window.setTimeout(function () {
                        window.alert((titulos[maestro] || 'Registro') + ' inexistente');
                    }, 0);
                }
            });
    }

    function abrirModal($campo) {
        ptrCampo = $campo && $campo.length ? $campo : $('.tm-maestro-sueldos-campo').first();
        var maestro = maestroDe(ptrCampo);
        if (!maestro) {
            return;
        }
        modalAbriendose = true;
        $('#consultamaestro_sueldosModal').attr('data-maestro', maestro);
        $('#consultamaestro_sueldosTitulo').text(titulos[maestro] || 'Consulta');
        $('#consultamaestro_sueldos').val('');
        buscar(maestro, '');
        $('#consultamaestro_sueldosModal').modal('show');
    }

    function activa() {
        $(document).off('.consultaMaestroSueldos');

        $(document).on('click.consultaMaestroSueldos', '.consultamaestro_sueldos', function (e) {
            e.preventDefault();
            abrirModal(campoDesde(this));
        });

        $('#consultamaestro_sueldosModal')
            .off('shown.bs.modal.consultaMaestroSueldos')
            .on('shown.bs.modal.consultaMaestroSueldos', function () {
                modalAbriendose = false;
                $('#consultamaestro_sueldos').trigger('focus');
            });

        $(document).on('input.consultaMaestroSueldos', '#consultamaestro_sueldos', function () {
            if (timerBusqueda) {
                window.clearTimeout(timerBusqueda);
            }
            var v = this.value;
            var maestro = String($('#consultamaestro_sueldosModal').attr('data-maestro') || '');
            timerBusqueda = window.setTimeout(function () { buscar(maestro, v); }, 250);
        });

        $(document).on('keydown.consultaMaestroSueldos', '#consultamaestro_sueldos', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var $btn = $('#datosmaestro_sueldos .eligeconsultamaestro_sueldos').first();
            if ($btn.length) {
                $btn.trigger('click');
            }
        });

        $(document).on('click.consultaMaestroSueldos', '.eligeconsultamaestro_sueldos', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var id = String($tr.find('.maestro_sueldos_id').text() || '').trim();
            var maestro = maestroDe(ptrCampo) || String($('#consultamaestro_sueldosModal').attr('data-maestro') || '');
            var basico = {
                id: id,
                codigo: $tr.find('.codigomaestro_sueldos_fila').text(),
                nombre: $tr.find('.nombremaestro_sueldos_fila').text()
            };
            $('#consultamaestro_sueldosModal').modal('hide');
            if (maestro && id) {
                $.get(carpetaBase + '/sueldos/maestro/' + encodeURIComponent(maestro) + '/leer/' + encodeURIComponent(id))
                    .done(function (data) {
                        aplicar(ptrCampo, data && data.id ? data : basico);
                        enfocarSiguiente(ptrCampo);
                    })
                    .fail(function () {
                        aplicar(ptrCampo, basico);
                        enfocarSiguiente(ptrCampo);
                    });
                return;
            }
            aplicar(ptrCampo, basico);
            enfocarSiguiente(ptrCampo);
        });

        $(document).on('keydown.consultaMaestroSueldos', '.codigomaestro_sueldos', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrirModal(campoDesde(this));
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverPorCodigo(campoDesde(this), true, true);
            }
        });

        $(document).on('blur.consultaMaestroSueldos', '.codigomaestro_sueldos', function () {
            resolverPorCodigo(campoDesde(this), false, false);
        });

        $(document).on('input.consultaMaestroSueldos', '.codigomaestro_sueldos', function () {
            $(this).removeData('maestro-invalido');
        });
    }

    window.activa_eventos_consulta_maestro_sueldos = activa;
    $(activa);
})(jQuery);
