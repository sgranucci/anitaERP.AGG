(function ($) {
    'use strict';

    function carpeta() {
        return (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
    }

    function sectorCxpId($select) {
        var found = null;
        $select.find('option').each(function () {
            var t = String($(this).text() || '').trim().toUpperCase();
            if (t === 'CUENTAS A PAGAR') {
                found = String($(this).val());
                return false;
            }
        });
        return found;
    }

    function sectorGastronomiaId($form) {
        var fromData = parseInt($form.attr('data-sector-gastronomia-id') || '0', 10);
        if (fromData > 0) {
            return String(fromData);
        }
        var found = null;
        $form.find('select[name="sector_legajocompra_id"] option').each(function () {
            var t = String($(this).text() || '').trim().toUpperCase();
            if (t === 'GASTRONOMIA') {
                found = String($(this).val());
                return false;
            }
        });
        return found;
    }

    function opcionesDe($form, override) {
        return $.extend({}, $form.data('oc-sector-opciones') || {}, override || {});
    }

    function aplicarGate($form, gate, modo) {
        var $bloque = $form.find('.js-oc-bloque-factura-legajo');
        var $ok = $bloque.find('.js-oc-gate-ok');
        var $err = $bloque.find('.js-oc-gate-errores');
        var $com = $bloque.find('.js-oc-gate-com-ok');
        var $upload = $bloque.find('.js-oc-pdf-upload');
        var $submit = $form.find('button[type=submit], input[type=submit]');

        $ok.addClass('d-none');
        $err.addClass('d-none').empty();

        if (!gate) {
            $submit.prop('disabled', false);
            return;
        }

        var errores = modo === 'paquete'
            ? (gate.paquete_errores || gate.errores || [])
            : (gate.errores || []);
        var ok = modo === 'paquete'
            ? (typeof gate.paquete_ok === 'boolean' ? gate.paquete_ok : !!gate.ok)
            : !!gate.ok;

        if (gate.tiene_factura) {
            $ok.removeClass('d-none');
            if (gate.exige_com !== false) {
                if (gate.tiene_com_asignada) {
                    $com.text(' Hay recepción COM asignada a la factura.');
                } else if (gate.tiene_com) {
                    $com.text(' Hay COM disponible pero aún no está asignada a todas las facturas que la exigen.');
                } else {
                    $com.text(' Falta recepción COM confirmada asociada a la factura (obligatoria).');
                }
            } else {
                if (gate.es_anticipada) {
                    $com.text(' OC anticipada: no exige COM (la recepción puede llegar después).');
                } else {
                    $com.text(gate.exige_flujo_empresa
                        ? ' Contrato vigente: el circuito no exige COM.'
                        : ' Esta empresa no exige COM (configuración de Cuentas a pagar).');
                }
            }
        }

        if (!ok && errores && errores.length) {
            $err.removeClass('d-none').html(errores.map(function (e) {
                return $('<div/>').text(e).html();
            }).join('<br>'));
        }

        if (gate.requiere_pdf) {
            $upload.removeClass('d-none');
            $upload.find('input[type=file]').prop('required', true);
        } else {
            $upload.find('input[type=file]').prop('required', false);
        }

        $submit.prop('disabled', !ok);
        $form.data('oc-gate-ok', !!ok);
        $form.data('oc-gate-errores', errores || []);
    }

    function consultarGate($form, ordencompraId, done) {
        if (!ordencompraId) {
            done(null);
            return;
        }
        var opts = opcionesDe($form);
        var qs = (opts.forzarCxp || opts.preflight) ? '?preflight=1' : '';
        $.getJSON(carpeta() + '/compras/ordencompra/' + ordencompraId + '/gate-cuentas-a-pagar' + qs)
            .done(function (gate) { done(gate); })
            .fail(function () { done(null); });
    }

    function modoGate(opciones, esCxp, esGastro) {
        var forzar = opciones && opciones.forzarPaquete;
        var forzarCxp = opciones && opciones.forzarCxp;
        if (forzarCxp || esCxp) {
            return 'cxp';
        }
        if ((forzar || esGastro) && !esCxp) {
            return 'paquete';
        }

        return 'cxp';
    }

    function toggleBloque($form, sectorId, opciones) {
        opciones = opcionesDe($form, opciones);
        var $select = $form.find('select[name="sector_legajocompra_id"]');
        var cxp = sectorCxpId($select);
        var gastro = sectorGastronomiaId($form);
        var $bloque = $form.find('.js-oc-bloque-factura-legajo');
        var forzar = opciones && opciones.forzarPaquete;
        var forzarCxp = opciones && opciones.forzarCxp;
        var esCxp = forzarCxp || (cxp && String(sectorId) === String(cxp));
        var esGastro = gastro && String(sectorId) === String(gastro);
        if (forzar || esCxp || esGastro) {
            $bloque.removeClass('d-none');
            var ocId = $form.data('ordencompra-id') || $form.find('input[name="ordencompra_id"]').val();
            consultarGate($form, ocId, function (gate) {
                aplicarGate($form, gate, modoGate(opciones, esCxp, esGastro));
            });
        } else {
            $bloque.addClass('d-none');
            $bloque.find('input[type=file]').prop('required', false).val('');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', false);
        }
    }

    window.OcCambiarSectorLegajo = {
        initForm: function ($form, opciones) {
            if (!$form || !$form.length) {
                return;
            }
            if ($form.attr('enctype') !== 'multipart/form-data') {
                $form.attr('enctype', 'multipart/form-data');
            }
            $form.data('oc-sector-opciones', opciones || {});
            if (!$form.data('oc-sector-inited')) {
                $form.data('oc-sector-inited', true);
                $form.on('change', 'select[name="sector_legajocompra_id"]', function () {
                    toggleBloque($form, $(this).val(), opcionesDe($form));
                });
                $form.on('submit', function (e) {
                    var opts = opcionesDe($form);
                    if (!(opts.forzarPaquete || opts.forzarCxp)) {
                        return;
                    }
                    if ($form.data('oc-gate-ok') === false) {
                        e.preventDefault();
                        var errs = $form.data('oc-gate-errores') || [];
                        alert(errs.length ? errs.join('\n') : 'El legajo no cumple los requisitos para continuar.');
                    }
                });
            }
            toggleBloque($form, $form.find('select[name="sector_legajocompra_id"]').val(), opcionesDe($form));
        },
        setOrdencompraId: function ($form, id, opciones) {
            $form.data('ordencompra-id', id);
            if (opciones) {
                $form.data('oc-sector-opciones', opciones);
            }
            toggleBloque($form, $form.find('select[name="sector_legajocompra_id"]').val(), opcionesDe($form));
        },
        consultarGate: function (ordencompraId, done) {
            consultarGate($('<form/>'), ordencompraId, done);
        }
    };
})(jQuery);
