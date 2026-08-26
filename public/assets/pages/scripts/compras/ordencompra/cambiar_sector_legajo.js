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

    function aplicarGate($form, gate, modo) {
        var $bloque = $form.find('.js-oc-bloque-factura-legajo');
        var $ok = $bloque.find('.js-oc-gate-ok');
        var $err = $bloque.find('.js-oc-gate-errores');
        var $com = $bloque.find('.js-oc-gate-com-ok');
        var $upload = $bloque.find('.js-oc-pdf-upload');

        $ok.addClass('d-none');
        $err.addClass('d-none').empty();

        if (!gate) {
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
                $com.text(gate.tiene_com
                    ? ' Hay recepción COM asociada.'
                    : ' Falta recepción COM confirmada asociada a la factura (obligatoria).');
            } else {
                $com.text(gate.exige_flujo_empresa
                    ? ' Contrato vigente: el circuito no exige COM.'
                    : ' Esta empresa no exige COM (configuración de Cuentas a pagar).');
            }
        }

        if (!ok && errores && errores.length) {
            $err.removeClass('d-none').text(errores.join(' '));
        }

        if (gate.requiere_pdf) {
            $upload.removeClass('d-none');
            $upload.find('input[type=file]').prop('required', true);
        } else {
            $upload.find('input[type=file]').prop('required', false);
        }
    }

    function consultarGate($form, ordencompraId, done) {
        if (!ordencompraId) {
            done(null);
            return;
        }
        $.getJSON(carpeta() + '/compras/ordencompra/' + ordencompraId + '/gate-cuentas-a-pagar')
            .done(function (gate) { done(gate); })
            .fail(function () { done(null); });
    }

    function toggleBloque($form, sectorId, opciones) {
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
                aplicarGate($form, gate, (forzar || esGastro) && !esCxp ? 'paquete' : 'cxp');
            });
        } else {
            $bloque.addClass('d-none');
            $bloque.find('input[type=file]').prop('required', false).val('');
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
            $form.on('change', 'select[name="sector_legajocompra_id"]', function () {
                toggleBloque($form, $(this).val(), opciones);
            });
            toggleBloque($form, $form.find('select[name="sector_legajocompra_id"]').val(), opciones);
        },
        setOrdencompraId: function ($form, id) {
            $form.data('ordencompra-id', id);
            toggleBloque($form, $form.find('select[name="sector_legajocompra_id"]').val());
        }
    };
})(jQuery);
