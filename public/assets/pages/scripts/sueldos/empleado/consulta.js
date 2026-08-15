/* global carpetaBase */
(function ($) {
    'use strict';

    var $campoActivo = $();
    var filasActuales = [];
    var abriendoModal = false;

    function modalAbierto() {
        var $modal = $('#consultaempleado_sueldosModal');
        return abriendoModal || ($modal.length > 0 && $modal.hasClass('show'));
    }

    function endpoint(nombre) {
        var urls = window.empleadoSueldosConsultaUrls || {};
        if (urls[nombre]) {
            return urls[nombre];
        }
        var base = (typeof carpetaBase !== 'undefined' ? carpetaBase : '').replace(/\/$/, '');
        return base + '/sueldos/empleado-consulta/' + nombre;
    }

    function empresaId($campo) {
        return parseInt($campo.closest('form').find('[name="empresa_id"]').first().val() || '0', 10) || 0;
    }

    function textoSeguro(valor) {
        return $('<div>').text(valor == null ? '' : String(valor)).html();
    }

    function limpiar($campo, mantenerLegajo) {
        $campo.find('.empleado_sueldos_id').val('');
        if (!mantenerLegajo) {
            $campo.find('.codigoempleado_sueldos').val('');
        }
        $campo.find('.nombreempleado_sueldos').val('');
        $campo.find('.btn-link-editar-empleado-sueldos').attr('href', '#').addClass('d-none');
    }

    function aplicar($campo, fila) {
        if (!$campo.length || !fila || !fila.id) {
            return;
        }
        $campo.find('.empleado_sueldos_id').val(fila.id);
        $campo.find('.codigoempleado_sueldos').val(fila.legajo);
        $campo.find('.nombreempleado_sueldos').val(fila.nombre || '');
        var $link = $campo.find('.btn-link-editar-empleado-sueldos');
        if (fila.consultar_url) {
            $link.attr('href', fila.consultar_url).removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function avanzar($campo) {
        var selector = String($campo.data('next-focus') || '');
        if (selector) {
            var $destino = $(selector).first();
            if ($destino.length) {
                $destino.trigger('focus');
                return;
            }
        }

        var $form = $campo.closest('form');
        var $focusables = $form.find('input:not([type="hidden"]):not([readonly]):not(:disabled), select:not(:disabled), button:not(:disabled)');
        var indice = $focusables.index($campo.find('.codigoempleado_sueldos'));
        if (indice >= 0 && indice + 1 < $focusables.length) {
            $focusables.eq(indice + 1).trigger('focus');
        }
    }

    function cerrarModal() {
        abriendoModal = false;
        var $modal = $('#consultaempleado_sueldosModal');
        if ($modal.length && ($modal.hasClass('show') || $modal.hasClass('in'))) {
            $modal.modal('hide');
        }
        if (typeof window.liberarPantallaModalesBloqueados === 'function') {
            window.liberarPantallaModalesBloqueados();
        }
    }

    /** El aviso va después de cerrar el modal: un alert sobre la transición deja la pantalla bloqueada. */
    function avisarNoEncontrado($input, legajo, mensaje, avisar) {
        $input.data('empleadoLegajoInvalido', legajo);
        if (!avisar) {
            return;
        }
        cerrarModal();
        setTimeout(function () {
            alert(mensaje);
            $input.trigger('focus');
            if ($input.get(0) && typeof $input.get(0).select === 'function') {
                $input.get(0).select();
            }
        }, 0);
    }

    function resolver($input, opciones) {
        opciones = opciones || {};
        var avanzarAlFinal = !!opciones.avanzarFoco;
        var avisar = opciones.avisar !== false;
        var $campo = $input.closest('.tm-empleado-sueldos-campo');
        var legajo = String($input.val() || '').trim();
        if (!legajo) {
            $input.removeData('empleadoLegajoInvalido');
            limpiar($campo, false);
            if (avanzarAlFinal) {
                avanzar($campo);
            }
            return;
        }
        if ($input.data('empleadoResolviendo')) {
            return;
        }
        // Sin reintento explícito no se repite el aviso por el mismo legajo errado.
        if (!avisar && $input.data('empleadoLegajoInvalido') === legajo) {
            return;
        }
        $input.removeData('empleadoLegajoInvalido');

        $input.data('empleadoResolviendo', true);
        limpiar($campo, true);
        $.getJSON(endpoint('resolver'), {
            legajo: legajo,
            empresa_id: empresaId($campo)
        }).done(function (respuesta) {
            if (respuesta && respuesta.ok) {
                aplicar($campo, respuesta);
                if (avanzarAlFinal) {
                    avanzar($campo);
                }
                return;
            }
            avisarNoEncontrado(
                $input,
                legajo,
                (respuesta && respuesta.mensaje) || 'No se encontró el empleado.',
                avisar
            );
        }).fail(function (xhr) {
            var mensaje = xhr.responseJSON && xhr.responseJSON.mensaje
                ? xhr.responseJSON.mensaje
                : 'No se encontró el empleado indicado.';
            avisarNoEncontrado($input, legajo, mensaje, avisar);
        }).always(function () {
            $input.removeData('empleadoResolviendo');
        });
    }

    function renderFilas(filas) {
        filasActuales = Array.isArray(filas) ? filas : [];
        var html = '';
        filasActuales.forEach(function (fila, indice) {
            html += '<tr>';
            html += '<td>' + textoSeguro(fila.legajo) + '</td>';
            html += '<td>' + textoSeguro(fila.nombre) + '</td>';
            html += '<td>' + textoSeguro(fila.documento) + '</td>';
            html += '<td>' + textoSeguro(fila.cuil) + '</td>';
            html += '<td class="text-nowrap"><button type="button" class="btn btn-warning btn-sm elegir-empleado-sueldos" data-indice="'
                + indice + '">Elegir</button>';
            if (fila.consultar_url) {
                html += ' <a class="btn btn-info btn-sm" href="' + textoSeguro(fila.consultar_url)
                    + '" target="_blank" rel="noopener">Consultar</a>';
            }
            html += '</td></tr>';
        });
        if (!html) {
            html = '<tr><td colspan="5" class="text-center text-muted">No se encontraron empleados.</td></tr>';
        }
        $('#datosempleado_sueldos').html(html);
    }

    function buscar(texto) {
        if (!$campoActivo.length) {
            return;
        }
        $.getJSON(endpoint('buscar'), {
            consulta: texto || '',
            empresa_id: empresaId($campoActivo)
        }).done(function (respuesta) {
            renderFilas(respuesta && respuesta.data ? respuesta.data : []);
        }).fail(function () {
            renderFilas([]);
        });
    }

    function abrir($campo) {
        if (!$campo.length) {
            return;
        }
        if (empresaId($campo) <= 0) {
            cerrarModal();
            setTimeout(function () {
                alert('Primero seleccione la empresa.');
                $campo.find('.codigoempleado_sueldos').trigger('focus');
            }, 0);
            return;
        }
        $campoActivo = $campo;
        abriendoModal = true;
        $('#consultaempleado_sueldos').val('');
        buscar('');
        $('#consultaempleado_sueldosModal').modal('show');
    }

    $(document)
        .on('click', '.consultaempleado_sueldos', function (e) {
            e.preventDefault();
            abrir($(this).closest('.tm-empleado-sueldos-campo'));
        })
        .on('keydown', '.codigoempleado_sueldos', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrir($(this).closest('.tm-empleado-sueldos-campo'));
                return;
            }
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                resolver($(this), { avanzarFoco: true, avisar: true });
            }
        })
        .on('input', '.codigoempleado_sueldos', function () {
            $(this).removeData('empleadoLegajoInvalido');
        })
        .on('blur', '.codigoempleado_sueldos', function () {
            if (modalAbierto()) {
                return;
            }
            var $campo = $(this).closest('.tm-empleado-sueldos-campo');
            if (String($(this).val() || '').trim() && !$campo.find('.empleado_sueldos_id').val()) {
                resolver($(this), { avanzarFoco: false, avisar: false });
            }
        })
        .on('keydown', '#consultaempleado_sueldos', function (e) {
            if (e.key !== 'Enter' && e.keyCode !== 13) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            $('#datosempleado_sueldos .elegir-empleado-sueldos').first().trigger('click');
        })
        .on('keyup', '#consultaempleado_sueldos', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                return;
            }
            buscar(String($(this).val() || '').trim());
        })
        .on('click', '.elegir-empleado-sueldos', function () {
            var fila = filasActuales[parseInt($(this).data('indice'), 10)];
            if (!fila) {
                return;
            }
            aplicar($campoActivo, fila);
            $('#consultaempleado_sueldosModal').modal('hide');
            avanzar($campoActivo);
        })
        .on('change', 'form [name="empresa_id"]', function () {
            $(this).closest('form').find('.tm-empleado-sueldos-campo').each(function () {
                limpiar($(this), false);
            });
        });

    $('#consultaempleado_sueldosModal')
        .on('shown.bs.modal', function () {
            abriendoModal = false;
            $('#consultaempleado_sueldos').trigger('focus');
        })
        .on('hidden.bs.modal', function () {
            abriendoModal = false;
        });

    /*
     * El legajo llega desde el filtro o lo restaura el navegador al recargar,
     * pero el ID y el nombre no: se completan resolviendo en silencio.
     */
    function completarCamposConLegajo() {
        $('.tm-empleado-sueldos-campo').each(function () {
            var $campo = $(this);
            var $codigo = $campo.find('.codigoempleado_sueldos');
            var legajo = String($codigo.val() || '').trim();
            if (!legajo) {
                return;
            }
            var idCargado = String($campo.find('.empleado_sueldos_id').val() || '').trim();
            var nombre = String($campo.find('.nombreempleado_sueldos').val() || '').trim();
            if (idCargado && nombre) {
                return;
            }
            resolver($codigo, { avanzarFoco: false, avisar: false });
        });
    }

    $(function () {
        completarCamposConLegajo();
    });

    // El navegador restaura los inputs después de ready en recargas (Ctrl+R).
    $(window).on('load', function () {
        setTimeout(completarCamposConLegajo, 0);
    });
})(jQuery);
