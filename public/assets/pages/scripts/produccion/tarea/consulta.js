/* global carpetaBase */
(function () {
    'use strict';

    var ptrCampoTarea = $();
    var timerBusqueda = null;
    var modalAbriendo = false;

    function esF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function modalAbierto() {
        var $m = $('#consultatareaModal');
        return $m.length && $m.hasClass('show');
    }

    function campoDesde($el) {
        var $campo = $($el).closest('.tm-tarea-campo');
        return $campo.length ? $campo : $();
    }

    function apuntar($campo) {
        ptrCampoTarea = $campo && $campo.length ? $campo : $();
    }

    function actualizarLink($campo, id) {
        if (!$campo || !$campo.length) {
            return;
        }
        var $link = $campo.find('.btn-link-editar-tarea');
        if (!$link.length) {
            return;
        }
        id = parseInt(id || '0', 10);
        if (id > 0) {
            $link
                .attr('href', carpetaBase + '/produccion/tarea/' + id + '/editar?origen=modal_consulta&vista=consulta')
                .removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function limpiar($campo, mantenerCodigo) {
        if (!$campo || !$campo.length) {
            return;
        }
        $campo.find('.tarea_id').first().val('');
        if (!mantenerCodigo) {
            $campo.find('.codigotarea').first().val('');
        }
        $campo.find('.nombretarea').first().val('');
        actualizarLink($campo, 0);
    }

    function aplicar($campo, data) {
        if (!$campo || !$campo.length || !data || !data.id) {
            return;
        }
        $campo.find('.tarea_id').first().val(data.id);
        $campo.find('.codigotarea').first().val(data.id);
        $campo.find('.nombretarea').first().val(data.nombre || '');
        actualizarLink($campo, data.id);
    }

    function avanzarSiguiente($campo) {
        var next = $campo.attr('data-next-focus');
        if (next) {
            $(next).trigger('focus');
        }
    }

    function leerPorId(id, $campo, avisar) {
        $campo = $campo && $campo.length ? $campo : ptrCampoTarea;
        if (!$campo.length) {
            return;
        }
        id = parseInt(id || '0', 10);
        if (!(id > 0)) {
            limpiar($campo, false);
            return;
        }
        limpiar($campo, true);
        $.get(carpetaBase + '/produccion/leertarea/' + id)
            .done(function (data) {
                if (data && data.id) {
                    aplicar($campo, data);
                    return;
                }
                limpiar($campo, false);
                if (avisar) {
                    setTimeout(function () {
                        alert('No se encontró la tarea indicada.');
                    }, 0);
                    $campo.find('.codigotarea').first().trigger('focus');
                }
            })
            .fail(function () {
                limpiar($campo, false);
                if (avisar) {
                    setTimeout(function () {
                        alert('No se pudo cargar la tarea.');
                    }, 0);
                }
            });
    }

    function buscar(consulta) {
        $.ajax({
            url: carpetaBase + '/produccion/tarea/consultatarea',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                    ($('input[name="_token"]').first().val() || '')
            },
            data: { consulta: consulta || '' }
        })
            .done(function (resp) {
                $('#datostarea').html((resp && resp.data) ? resp.data : '');
            })
            .fail(function () {
                $('#datostarea').html('<tr><td colspan="3">Error al consultar tareas.</td></tr>');
            });
    }

    function abrirModal($campo) {
        modalAbriendo = true;
        apuntar($campo && $campo.length ? $campo : $('.tm-tarea-campo').first());
        $('#consultatarea').val('');
        buscar('');
        $('#consultatareaModal').modal('show');
    }

    window.activa_eventos_consultatarea = function () {
        $(document)
            .off('click.consultaTarea', '.consultatarea')
            .on('click.consultaTarea', '.consultatarea', function (e) {
                e.preventDefault();
                abrirModal(campoDesde(this));
            });

        $(document)
            .off('keydown.consultaTareaF1', '.codigotarea')
            .on('keydown.consultaTareaF1', '.codigotarea', function (e) {
                if (!esF1(e) || this.readOnly || this.disabled || modalAbierto()) {
                    return;
                }
                e.preventDefault();
                abrirModal(campoDesde(this));
            });

        $(document)
            .off('keydown.consultaTareaEnter', '.codigotarea')
            .on('keydown.consultaTareaEnter', '.codigotarea', function (e) {
                if (e.key !== 'Enter' && e.keyCode !== 13) {
                    return;
                }
                e.preventDefault();
                var $campo = campoDesde(this);
                apuntar($campo);
                var codigo = String($(this).val() || '').trim();
                if (codigo === '') {
                    limpiar($campo, false);
                    avanzarSiguiente($campo);
                    return;
                }
                leerPorId(codigo, $campo, true);
                avanzarSiguiente($campo);
            });

        $(document)
            .off('input.consultaTareaCodigo', '.codigotarea')
            .on('input.consultaTareaCodigo', '.codigotarea', function () {
                var $campo = campoDesde(this);
                if (String($(this).val() || '').trim() === '') {
                    limpiar($campo, false);
                }
            });

        $(document)
            .off('click.eligeConsultaTarea', '.eligeconsultatarea')
            .on('click.eligeConsultaTarea', '.eligeconsultatarea', function (e) {
                e.preventDefault();
                var $tr = $(this).closest('tr');
                var data = {
                    id: $.trim($tr.find('td.id').first().text()),
                    nombre: $.trim($tr.find('td.nombre').first().text())
                };
                aplicar(ptrCampoTarea, data);
                $('#consultatareaModal').modal('hide');
                avanzarSiguiente(ptrCampoTarea);
            });

        $('#consultatareaModal')
            .off('shown.bs.modal.consultaTarea hidden.bs.modal.consultaTarea')
            .on('shown.bs.modal.consultaTarea', function () {
                modalAbriendo = false;
                $('#consultatarea').trigger('focus');
            })
            .on('hidden.bs.modal.consultaTarea', function () {
                modalAbriendo = false;
            });

        $('#consultatarea')
            .off('keyup.consultaTarea keydown.consultaTarea')
            .on('keydown.consultaTarea', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    var $primera = $('#datostarea .eligeconsultatarea').first();
                    if ($primera.length) {
                        $primera.trigger('click');
                    }
                }
            })
            .on('keyup.consultaTarea', function () {
                var valor = $(this).val() || '';
                if (timerBusqueda) {
                    clearTimeout(timerBusqueda);
                }
                timerBusqueda = setTimeout(function () {
                    buscar(valor);
                }, 250);
            });
    };

    // blur no alerta si el modal se está abriendo
    $(document).on('blur.consultaTarea', '.codigotarea', function () {
        if (modalAbriendo || modalAbierto()) {
            return;
        }
        var $campo = campoDesde(this);
        var codigo = String($(this).val() || '').trim();
        var idActual = String($campo.find('.tarea_id').val() || '').trim();
        if (codigo === '') {
            if (idActual !== '') {
                limpiar($campo, false);
            }
            return;
        }
        if (codigo === idActual) {
            return;
        }
        leerPorId(codigo, $campo, false);
    });
})();
