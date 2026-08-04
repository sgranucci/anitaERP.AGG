/**
 * Validación WSAPOC (facturas apócrifas) en segundo plano — proveedores y clientes.
 */
(function ($) {
    'use strict';

    var xhrActivo = null;
    var ultimaClaveMostrada = '';

    function cfgDesde($el) {
        if (!$el || !$el.length) {
            return null;
        }
        return {
            habilitado: String($el.data('arca-apoc-habilitado') || '0') === '1',
            validarUrl: String($el.data('arca-apoc-validar-url') || '').trim(),
            proveedorId: parseInt($el.data('proveedor-id') || '0', 10),
            clienteId: parseInt($el.data('cliente-id') || '0', 10),
            suspenderEnAbm: String($el.data('suspender-en-abm') || '0') === '1',
            esCliente: $el.is('#cliente-arca-apoc-config'),
        };
    }

    function claveResultado(validacion, mensaje) {
        var det = (validacion && validacion.detalles) ? validacion.detalles.join('|') : '';
        return String((validacion && validacion.mensaje) || mensaje || '') + '::' + det;
    }

    function mostrarModalApoc(validacion, mensajeFallback, esCliente) {
        if (!validacion) {
            return;
        }

        // Solo el hallazgo real de apócrifo abre el modal rojo. Fallas del WS son aviso suave.
        if (validacion.error_servicio && !validacion.es_apocrifo) {
            mostrarAvisoServicio(validacion.mensaje || mensajeFallback);
            return;
        }

        if (validacion.ok || !validacion.es_apocrifo) {
            return;
        }

        var fallback = esCliente
            ? 'El cliente figura en la base de facturas apócrifas de ARCA.'
            : 'El proveedor figura en la base de facturas apócrifas de ARCA.';
        var mensaje = validacion.mensaje || mensajeFallback || fallback;
        var clave = claveResultado(validacion, mensaje);
        if (clave === ultimaClaveMostrada) {
            return;
        }
        ultimaClaveMostrada = clave;

        var $modal = $('#arca-apoc-validacion-modal');
        if (!$modal.length) {
            if (typeof toastr !== 'undefined') {
                toastr.warning(mensaje);
            } else {
                alert(mensaje);
            }
            return;
        }

        $('#arca-apoc-validacion-titulo').text(esCliente ? 'Facturas apócrifas — Cliente' : 'Facturas apócrifas — Proveedor');
        $('#arca-apoc-validacion-mensaje').text(mensaje);
        var $det = $('#arca-apoc-validacion-detalles').empty();
        var detalles = validacion.detalles || [];
        if (detalles.length) {
            detalles.forEach(function (t) {
                $det.append($('<li>').text(t));
            });
            $det.show();
        } else {
            $det.hide();
        }
        $modal.modal('show');
    }

    function mostrarAvisoServicio(mensaje) {
        var texto = mensaje
            || 'Consulta de facturas apócrifas no disponible por el momento (ARCA). Puede continuar con normalidad.';
        var clave = 'aviso-servicio::' + texto;
        if (clave === ultimaClaveMostrada) {
            return;
        }
        ultimaClaveMostrada = clave;

        if (typeof toastr !== 'undefined') {
            toastr.options = toastr.options || {};
            toastr.info(texto, 'APOC', { timeOut: 4500, extendedTimeOut: 2000, closeButton: true });
            return;
        }

        // Fallback mínimo si no hay toastr
        var selector = '#cliente-apoc-estado-badge, #proveedor-apoc-estado-badge';
        var $badge = $(selector).first();
        if ($badge.length) {
            $badge.removeClass('badge-success badge-danger').addClass('badge-secondary')
                .text('APOC no consultado')
                .attr('title', texto)
                .show();
        }
    }

    function actualizarBadgeApoc(facturasApocrifas, consultaAt, esCliente) {
        var selector = esCliente ? '#cliente-apoc-estado-badge' : '#proveedor-apoc-estado-badge';
        var $badge = $(selector);
        if (!$badge.length) {
            return;
        }

        if (facturasApocrifas) {
            $badge.removeClass('badge-success badge-secondary').addClass('badge-danger')
                .text('Facturas apócrifas (ARCA)');
            $badge.show();
        } else if (consultaAt) {
            $badge.removeClass('badge-danger badge-secondary').addClass('badge-success')
                .text('Sin registro APOC (' + consultaAt + ')');
            $badge.show();
        } else {
            $badge.hide();
        }
    }

    function aplicarSuspensionProveedorAbm(json, cfg) {
        if (!json || !json.suspendido) {
            return;
        }
        var $estado = $('#estado');
        if ($estado.length) {
            $estado.val('Suspendido');
        }
        var $boton = $('#botonestado');
        if ($boton.length) {
            $boton.html("<i class='fa fa-bell'></i>&nbsp;Estado Suspendido");
        }
        var tipoId = parseInt(json.tiposuspension_id || (json.validacion && json.validacion.tiposuspension_id) || '0', 10);
        if (tipoId > 0) {
            $('#tiposuspension_id').val(tipoId);
            if (typeof window.muestraTipoSuspension === 'function') {
                window.muestraTipoSuspension();
            }
        }
        if (cfg && cfg.suspenderEnAbm) {
            actualizarBadgeApoc(true, null, false);
        }
    }

    function aplicarSuspensionClienteAbm(json, cfg) {
        if (!json || !json.suspendido) {
            return;
        }
        var $estado = $('#estado');
        if ($estado.length) {
            $estado.val('1');
        }
        var $boton = $('#botonestado');
        if ($boton.length) {
            $boton.html("<i class='fa fa-bell'></i>&nbsp;Estado Suspendido");
        }
        var tipoId = parseInt(json.tiposuspension_id || (json.validacion && json.validacion.tiposuspension_id) || '0', 10);
        if (tipoId > 0) {
            $('#tiposuspension_id').val(tipoId);
            if (typeof window.muestraTipoSuspension === 'function') {
                window.muestraTipoSuspension();
            }
        }
        if (cfg && cfg.suspenderEnAbm) {
            actualizarBadgeApoc(true, null, true);
        }
    }

    window.ArcaApocValidacionAsync = {
        encolar: function (opts) {
            opts = opts || {};
            var $cfgEl = opts.$config || $('#proveedor-arca-apoc-config, #cp-proveedor-arca-apoc-config, #cliente-arca-apoc-config').first();
            var cfg = cfgDesde($cfgEl);
            if (!cfg || !cfg.habilitado) {
                return;
            }

            var entityId = parseInt(
                opts.proveedorId || opts.clienteId || cfg.proveedorId || cfg.clienteId || '0',
                10
            );
            var url = String(opts.url || cfg.validarUrl || '').trim();
            if (!url || entityId <= 0) {
                return;
            }

            url = url.replace('__ID__', String(entityId));

            if (xhrActivo) {
                xhrActivo.abort();
            }

            xhrActivo = $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                data: JSON.stringify({}),
            });

            xhrActivo.always(function () {
                xhrActivo = null;
            });

            xhrActivo.done(function (json) {
                if (json && json.skipped) {
                    return;
                }

                if (json && json.validacion) {
                    mostrarModalApoc(json.validacion, json.message, cfg.esCliente);
                } else if (json && !json.ok && json.message) {
                    mostrarModalApoc({
                        ok: false,
                        mensaje: json.message,
                        detalles: [],
                    }, null, cfg.esCliente);
                }

                if (json && typeof json.facturas_apocrifas !== 'undefined') {
                    actualizarBadgeApoc(!!json.facturas_apocrifas, null, cfg.esCliente);
                } else if (json && json.validacion && typeof json.validacion.es_apocrifo !== 'undefined') {
                    actualizarBadgeApoc(!!json.validacion.es_apocrifo, null, cfg.esCliente);
                }

                var suspender = opts.suspenderUi !== false && cfg.suspenderEnAbm;
                if (suspender && json && json.suspendido) {
                    if (cfg.esCliente) {
                        aplicarSuspensionClienteAbm(json, cfg);
                    } else {
                        aplicarSuspensionProveedorAbm(json, cfg);
                    }
                }

                if (typeof opts.onResult === 'function') {
                    opts.onResult(json);
                }
            });

            xhrActivo.fail(function (xhr) {
                var json = xhr.responseJSON || {};
                if (json.validacion) {
                    mostrarModalApoc(json.validacion, json.message, cfg.esCliente);
                } else if (json.message) {
                    mostrarModalApoc({
                        ok: false,
                        mensaje: json.message,
                        detalles: [],
                    }, null, cfg.esCliente);
                }

                var suspender = opts.suspenderUi !== false && cfg.suspenderEnAbm;
                if (suspender && json && json.suspendido) {
                    if (cfg.esCliente) {
                        aplicarSuspensionClienteAbm(json, cfg);
                    } else {
                        aplicarSuspensionProveedorAbm(json, cfg);
                    }
                }

                if (typeof opts.onResult === 'function') {
                    opts.onResult(json);
                }
            });
        },

        limpiarUltimoModal: function () {
            ultimaClaveMostrada = '';
        },
    };
})(jQuery);
