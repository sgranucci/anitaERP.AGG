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
            $form.find('.js-oc-nc-imputacion').addClass('d-none').empty();
            $form.data('oc-nc-incompleta', false);
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
        renderNcImputacion($form, gate, modo);
    }

    function asegurarConsultaCuenta() {
        if (typeof window.resolverPorCodigoCuentaContable === 'function' || window.ocNcConsultaCuentaCargando) {
            return;
        }
        window.ocNcConsultaCuentaCargando = true;
        var base = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
        $.getScript(base + '/assets/pages/scripts/contable/cuentacontable/consulta.js').always(function () {
            $(document).off('keydown.noEnterSubmitCuentacontable', 'input');
        });
    }

    function escHtml(valor) {
        return $('<div/>').text(valor == null ? '' : String(valor)).html();
    }

    function modoPideNc(modo) {
        return modo === 'cxp' || modo === 'paquete';
    }

    function renderNcImputacion($form, gate, modo) {
        var $box = $form.find('.js-oc-nc-imputacion');
        if (!$box.length) {
            return;
        }
        var notas = (gate && gate.notas_credito) || [];
        if (!modoPideNc(modo) || !notas.length) {
            $box.addClass('d-none').empty();
            $form.data('oc-nc-incompleta', false);
            $form.closest('.modal-dialog').removeClass('modal-lg');
            return;
        }

        $form.closest('.modal-dialog').addClass('modal-lg');
        asegurarConsultaCuenta();
        var formKey = String($form.attr('id') || 'ocnc').replace(/\W/g, '');
        var html = '<div class="alert alert-light border py-2 mb-2">'
            + '<strong>Notas de crédito y débito.</strong> El neto se imputa a las cuentas de los artículos de la OC, '
            + 'salvo que indiques otra cuenta (por ejemplo diferencia de cambio). Elegí una opción para cada NC.'
            + '</div>';
        notas.forEach(function (nc, idx) {
            var modoNc = String(nc.modo || '');
            var empresaId = parseInt(nc.empresa_id || '0', 10) || 0;
            var cuentaId = parseInt(nc.cuentacontable_id || '0', 10) || 0;
            var precargaId = parseInt(nc.precarga_id || '0', 10) || 0;
            var anitaId = parseInt(nc.anita_scan_documento_id || '0', 10) || 0;
            html += '<table class="w-100 mb-2"><tr class="js-oc-nc-item" data-idx="' + idx + '"><td class="border rounded p-2">';
            html += '<div class="font-weight-bold mb-1">' + escHtml(nc.etiqueta || 'Nota de crédito') + '</div>';
            html += '<input type="hidden" name="nc_imputacion[' + idx + '][precarga_id]" value="' + (precargaId > 0 ? precargaId : '') + '">';
            html += '<input type="hidden" name="nc_imputacion[' + idx + '][anita_scan_documento_id]" value="' + (anitaId > 0 ? anitaId : '') + '">';
            html += '<input type="hidden" class="empresa" value="' + empresaId + '">';
            html += '<div class="custom-control custom-radio">'
                + '<input type="radio" class="custom-control-input js-oc-nc-modo" id="' + formKey + '_nc_oc_' + idx + '" name="nc_imputacion[' + idx + '][modo]" value="oc"'
                + (modoNc === 'oc' ? ' checked' : '') + '>'
                + '<label class="custom-control-label" for="' + formKey + '_nc_oc_' + idx + '">Cuenta de la OC</label></div>';
            html += '<div class="custom-control custom-radio mb-1">'
                + '<input type="radio" class="custom-control-input js-oc-nc-modo" id="' + formKey + '_nc_cta_' + idx + '" name="nc_imputacion[' + idx + '][modo]" value="cuenta"'
                + (modoNc === 'cuenta' ? ' checked' : '') + '>'
                + '<label class="custom-control-label" for="' + formKey + '_nc_cta_' + idx + '">Otra cuenta contable</label></div>';
            html += '<div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center js-oc-nc-cuenta' + (modoNc === 'cuenta' ? '' : ' d-none') + '" style="gap:4px;">'
                + '<input type="hidden" class="cuentacontable_id" name="nc_imputacion[' + idx + '][cuentacontable_id]" value="' + (cuentaId > 0 ? cuentaId : '') + '">'
                + '<button type="button" title="Consulta cuenta" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0"><i class="fa fa-search text-primary"></i></button>'
                + '<input type="text" class="codigocuentacontable form-control form-control-sm" style="width:6rem;flex-shrink:0;" value="' + escHtml(nc.cuenta_codigo || '') + '" placeholder="Cód." autocomplete="off">'
                + '<input type="text" class="nombrecuentacontable form-control form-control-sm" readonly value="' + escHtml(nc.cuenta_nombre || '') + '" placeholder="Descripción">'
                + '</div></td></tr></table>';
        });
        $box.removeClass('d-none').html(html);
        actualizarNcImputacion($form);
    }

    function actualizarNcImputacion($form) {
        var $box = $form.find('.js-oc-nc-imputacion');
        if (!$box.length || $box.hasClass('d-none')) {
            $form.data('oc-nc-incompleta', false);
            return;
        }
        var incompleta = false;
        $box.find('.js-oc-nc-item').each(function () {
            var $item = $(this);
            var modo = String($item.find('.js-oc-nc-modo:checked').val() || '');
            var $cuenta = $item.find('.js-oc-nc-cuenta');
            if (modo === 'cuenta') {
                $cuenta.removeClass('d-none');
                var id = parseInt($cuenta.find('.cuentacontable_id').val() || '0', 10) || 0;
                if (id <= 0) {
                    incompleta = true;
                }
            } else {
                $cuenta.addClass('d-none');
                if (modo !== 'oc') {
                    incompleta = true;
                }
            }
        });
        $form.data('oc-nc-incompleta', incompleta);
        $form.data('oc-nc-mensaje', 'Elegí la cuenta del neto de cada nota de crédito o débito: la de la OC u otra cuenta contable.');
        var gateOk = $form.data('oc-gate-ok') !== false;
        $form.find('button[type=submit], input[type=submit]').prop('disabled', !gateOk || incompleta);
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
            $form.find('.js-oc-nc-imputacion').addClass('d-none').empty();
            $form.data('oc-nc-incompleta', false);
            $form.closest('.modal-dialog').removeClass('modal-lg');
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
                    if ($form.data('oc-nc-incompleta')) {
                        e.preventDefault();
                        alert($form.data('oc-nc-mensaje') || 'Elegí la cuenta del neto de cada nota de crédito o débito.');
                        return;
                    }
                    if (!(opts.forzarPaquete || opts.forzarCxp)) {
                        return;
                    }
                    if ($form.data('oc-gate-ok') === false) {
                        e.preventDefault();
                        var errs = $form.data('oc-gate-errores') || [];
                        alert(errs.length ? errs.join('\n') : 'El legajo no cumple los requisitos para continuar.');
                    }
                });
                $form.on('change', '.js-oc-nc-modo, .js-oc-nc-imputacion .cuentacontable_id', function () {
                    actualizarNcImputacion($form);
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

    $(document).on('shown.bs.modal', '#consultacuentaModal', function () {
        if (!$('.js-oc-nc-imputacion:visible').length) {
            return;
        }
        var $modal = $(this);
        $modal.css('z-index', 2000);
        $('.modal-backdrop').last().css('z-index', 1990);
    });
})(jQuery);
