/**
 * Validación de impuestos ARCA en segundo plano (no bloquea la UI).
 * Al finalizar con problemas, abre #arca-impuestos-validacion-modal.
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
            habilitado: String($el.data('arca-validar-impuestos') || '0') === '1',
            validarUrl: String($el.data('arca-validar-url') || '').trim(),
            constanciaUrl: String($el.data('arca-constancia-url') || '').trim(),
            riId: parseInt($el.data('condicioniva-ri-id') || '1', 10),
            monoId: parseInt($el.data('condicioniva-monotributo-id') || '4', 10),
            proveedorId: parseInt($el.data('proveedor-id') || '0', 10),
            suspenderEnAbm: String($el.data('suspender-en-abm') || '0') === '1',
        };
    }

    function soloDigitos(v) {
        return String(v || '').replace(/\D+/g, '');
    }

    function condicionivaRequiereValidacion(condicionivaId, cfg) {
        var id = parseInt(condicionivaId || '0', 10);
        return id === cfg.riId || id === cfg.monoId;
    }

    function claveResultado(validacion, mensaje) {
        var det = (validacion && validacion.detalles) ? validacion.detalles.join('|') : '';
        return String((validacion && validacion.mensaje) || mensaje || '') + '::' + det;
    }

    function mostrarModalValidacion(validacion, mensajeFallback) {
        if (!validacion || !validacion.aplica || validacion.ok) {
            return;
        }

        var mensaje = validacion.mensaje || mensajeFallback || 'Problemas en ARCA con los impuestos del contribuyente.';
        var clave = claveResultado(validacion, mensaje);
        if (clave === ultimaClaveMostrada) {
            return;
        }
        ultimaClaveMostrada = clave;

        var $modal = $('#arca-impuestos-validacion-modal');
        if (!$modal.length) {
            alert(mensaje);
            return;
        }

        $('#arca-imp-validacion-mensaje').text(mensaje);
        var $det = $('#arca-imp-validacion-detalles').empty();
        var detalles = validacion.detalles || [];
        if (detalles.length) {
            detalles.forEach(function (t) {
                $det.append($('<li>').text(t));
            });
            $det.show();
        } else {
            $det.hide();
        }
        $('#arca-imp-validacion-nota').show();
        $modal.modal('show');
    }

    function aplicarSuspensionProveedorAbm(json) {
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
    }

    function resolverEndpointYBody(opts, cfg) {
        var condicionivaId = parseInt(opts.condicionivaId || '0', 10);
        var cuit = soloDigitos(opts.cuit || '');
        var proveedorId = parseInt(opts.proveedorId || cfg.proveedorId || '0', 10);

        if (cfg.validarUrl && proveedorId > 0) {
            return {
                url: cfg.validarUrl,
                body: {
                    condicioniva_id: condicionivaId > 0 ? condicionivaId : undefined,
                    proveedor_id: proveedorId,
                },
            };
        }

        if (cfg.constanciaUrl && cuit.length === 11) {
            return {
                url: cfg.constanciaUrl,
                body: {
                    cuit: cuit,
                    condicioniva_id: condicionivaId > 0 ? condicionivaId : undefined,
                },
            };
        }

        return null;
    }

    /**
     * @param {object} opts
     * @param {jQuery} [opts.$config] — #proveedor-arca-validacion-config o #cp-proveedor-arca-config
     * @param {string} [opts.url] — override URL
     * @param {number} [opts.proveedorId]
     * @param {number} [opts.condicionivaId]
     * @param {string} [opts.cuit]
     * @param {boolean} [opts.suspenderUi]
     * @param {function} [opts.onResult]
     */
    window.ArcaPadronValidacionAsync = {
        encolar: function (opts) {
            opts = opts || {};
            var $cfgEl = opts.$config || $('#proveedor-arca-validacion-config, #cp-proveedor-arca-config').first();
            var cfg = cfgDesde($cfgEl);
            if (!cfg || !cfg.habilitado) {
                return;
            }

            var condicionivaId = opts.condicionivaId;
            if (condicionivaId === undefined || condicionivaId === null) {
                var $civa = $('#condicioniva_id');
                condicionivaId = $civa.length ? parseInt($civa.val() || '0', 10) : 0;
            }

            if (!condicionivaRequiereValidacion(condicionivaId, cfg)) {
                var proveedorIdEncolado = parseInt(opts.proveedorId || cfg.proveedorId || '0', 10);
                if (!(cfg.validarUrl && proveedorIdEncolado > 0)) {
                    return;
                }
            }

            var cuit = opts.cuit;
            if (cuit === undefined) {
                var $cuit = $('#nroinscripcion');
                cuit = $cuit.length ? $cuit.val() : '';
            }
            if (soloDigitos(cuit).length !== 11 && !(cfg.validarUrl && (opts.proveedorId || cfg.proveedorId))) {
                return;
            }

            var resolved = resolverEndpointYBody({
                condicionivaId: condicionivaId,
                cuit: cuit,
                proveedorId: opts.proveedorId,
            }, cfg);

            var url = String(opts.url || (resolved && resolved.url) || '').trim();
            if (!url) {
                return;
            }

            if (xhrActivo) {
                xhrActivo.abort();
            }

            var body = resolved ? resolved.body : {};
            if (opts.body) {
                body = $.extend({}, body, opts.body);
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
                data: JSON.stringify(body),
            });

            xhrActivo.always(function () {
                xhrActivo = null;
            });

            xhrActivo.done(function (json) {
                if (json && json.skipped) {
                    return;
                }
                if (json && json.validacion) {
                    mostrarModalValidacion(json.validacion, json.message);
                } else if (json && !json.ok && json.message) {
                    mostrarModalValidacion({
                        aplica: true,
                        ok: false,
                        mensaje: json.message,
                        detalles: [],
                    });
                }

                var suspender = opts.suspenderUi !== false && cfg.suspenderEnAbm;
                if (suspender && json && json.suspendido) {
                    aplicarSuspensionProveedorAbm(json);
                }

                if (typeof opts.onResult === 'function') {
                    opts.onResult(json);
                }
            });

            xhrActivo.fail(function (xhr) {
                var json = xhr.responseJSON || {};
                if (json.validacion) {
                    mostrarModalValidacion(json.validacion, json.message);
                } else if (json.message) {
                    mostrarModalValidacion({
                        aplica: true,
                        ok: false,
                        mensaje: json.message,
                        detalles: [],
                    });
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
