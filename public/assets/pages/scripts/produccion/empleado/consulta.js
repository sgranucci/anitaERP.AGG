/* global carpetaBase */
(function () {
    'use strict';

    var ptrCampoEmpleado = $();
    var timerBusqueda = null;
    var modalAbriendo = false;

    function esF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function modalAbierto() {
        var $m = $('#consultaempleadoModal');
        return $m.length && $m.hasClass('show');
    }

    function campoDesde($el) {
        var $campo = $($el).closest('.tm-empleado-campo');
        return $campo.length ? $campo : $();
    }

    function apuntar($campo) {
        ptrCampoEmpleado = $campo && $campo.length ? $campo : $();
    }

    function actualizarLink($campo, id) {
        if (!$campo || !$campo.length) {
            return;
        }
        var $link = $campo.find('.btn-link-editar-empleado');
        if (!$link.length) {
            return;
        }
        id = parseInt(id || '0', 10);
        if (id > 0) {
            $link
                .attr('href', carpetaBase + '/produccion/empleado/' + id + '/editar?origen=modal_consulta&vista=consulta')
                .removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function limpiar($campo, mantenerCodigo) {
        if (!$campo || !$campo.length) {
            return;
        }
        $campo.find('.empleado_id').first().val('');
        if (!mantenerCodigo) {
            $campo.find('.codigoempleado').first().val('');
        }
        $campo.find('.nombreempleado').first().val('');
        actualizarLink($campo, 0);
    }

    function aplicar($campo, data) {
        if (!$campo || !$campo.length || !data || !data.id) {
            return;
        }
        $campo.find('.empleado_id').first().val(data.id);
        $campo.find('.codigoempleado').first().val(data.id);
        $campo.find('.nombreempleado').first().val(data.nombre || '');
        actualizarLink($campo, data.id);
    }

    function avanzarSiguiente($campo) {
        var next = $campo.attr('data-next-focus');
        if (next) {
            $(next).trigger('focus');
        }
    }

    function leerPorId(id, $campo, avisar) {
        $campo = $campo && $campo.length ? $campo : ptrCampoEmpleado;
        if (!$campo.length) {
            return;
        }
        id = parseInt(id || '0', 10);
        if (!(id > 0)) {
            limpiar($campo, false);
            return;
        }
        limpiar($campo, true);
        $.get(carpetaBase + '/produccion/leerempleado/' + id)
            .done(function (data) {
                if (data && data.id) {
                    aplicar($campo, data);
                    return;
                }
                limpiar($campo, false);
                if (avisar) {
                    setTimeout(function () {
                        alert('No se encontró el empleado indicado.');
                    }, 0);
                    $campo.find('.codigoempleado').first().trigger('focus');
                }
            })
            .fail(function () {
                limpiar($campo, false);
                if (avisar) {
                    setTimeout(function () {
                        alert('No se pudo cargar el empleado.');
                    }, 0);
                }
            });
    }

    function buscar(consulta) {
        $.ajax({
            url: carpetaBase + '/produccion/empleado/consultaempleado',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') ||
                    ($('input[name="_token"]').first().val() || '')
            },
            data: { consulta: consulta || '' }
        })
            .done(function (resp) {
                $('#datosempleado').html((resp && resp.data) ? resp.data : '');
            })
            .fail(function () {
                $('#datosempleado').html('<tr><td colspan="3">Error al consultar empleados.</td></tr>');
            });
    }

    function abrirModal($campo) {
        modalAbriendo = true;
        apuntar($campo && $campo.length ? $campo : $('.tm-empleado-campo').first());
        $('#consultaempleado').val('');
        buscar('');
        $('#consultaempleadoModal').modal('show');
    }

    window.activa_eventos_consultaempleado = function () {
        $(document)
            .off('click.consultaEmpleadoProd', '.consultaempleado')
            .on('click.consultaEmpleadoProd', '.consultaempleado', function (e) {
                e.preventDefault();
                abrirModal(campoDesde(this));
            });

        $(document)
            .off('keydown.consultaEmpleadoProdF1', '.codigoempleado')
            .on('keydown.consultaEmpleadoProdF1', '.codigoempleado', function (e) {
                if (!esF1(e) || this.readOnly || this.disabled || modalAbierto()) {
                    return;
                }
                e.preventDefault();
                abrirModal(campoDesde(this));
            });

        $(document)
            .off('keydown.consultaEmpleadoProdEnter', '.codigoempleado')
            .on('keydown.consultaEmpleadoProdEnter', '.codigoempleado', function (e) {
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
            .off('input.consultaEmpleadoProdCodigo', '.codigoempleado')
            .on('input.consultaEmpleadoProdCodigo', '.codigoempleado', function () {
                var $campo = campoDesde(this);
                if (String($(this).val() || '').trim() === '') {
                    limpiar($campo, false);
                }
            });

        $(document)
            .off('click.eligeConsultaEmpleadoProd', '.eligeconsultaempleado')
            .on('click.eligeConsultaEmpleadoProd', '.eligeconsultaempleado', function (e) {
                e.preventDefault();
                var $tr = $(this).closest('tr');
                var data = {
                    id: $.trim($tr.find('td.id').first().text()),
                    nombre: $.trim($tr.find('td.nombre').first().text())
                };
                aplicar(ptrCampoEmpleado, data);
                $('#consultaempleadoModal').modal('hide');
                avanzarSiguiente(ptrCampoEmpleado);
            });

        $('#consultaempleadoModal')
            .off('shown.bs.modal.consultaEmpleadoProd hidden.bs.modal.consultaEmpleadoProd')
            .on('shown.bs.modal.consultaEmpleadoProd', function () {
                modalAbriendo = false;
                $('#consultaempleado').trigger('focus');
            })
            .on('hidden.bs.modal.consultaEmpleadoProd', function () {
                modalAbriendo = false;
            });

        $('#consultaempleado')
            .off('keyup.consultaEmpleadoProd keydown.consultaEmpleadoProd')
            .on('keydown.consultaEmpleadoProd', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    var $primera = $('#datosempleado .eligeconsultaempleado').first();
                    if ($primera.length) {
                        $primera.trigger('click');
                    }
                }
            })
            .on('keyup.consultaEmpleadoProd', function () {
                var valor = $(this).val() || '';
                if (timerBusqueda) {
                    clearTimeout(timerBusqueda);
                }
                timerBusqueda = setTimeout(function () {
                    buscar(valor);
                }, 250);
            });
    };

    $(document).on('blur.consultaEmpleadoProd', '.codigoempleado', function () {
        if (modalAbriendo || modalAbierto()) {
            return;
        }
        var $campo = campoDesde(this);
        var codigo = String($(this).val() || '').trim();
        var idActual = String($campo.find('.empleado_id').val() || '').trim();
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
