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

    function aplicarGate($form, gate) {
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

        if (gate.tiene_factura) {
            $ok.removeClass('d-none');
            if (gate.exige_com) {
                $com.text(gate.tiene_com
                    ? ' Hay recepción COM disponible.'
                    : ' Falta recepción COM confirmada con provisión.');
            } else {
                $com.text(' (OC de gasto/servicio: no exige COM).');
            }
        }

        if (!gate.ok && gate.errores && gate.errores.length) {
            $err.removeClass('d-none').text(gate.errores.join(' '));
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

    function toggleBloque($form, sectorId) {
        var cxp = sectorCxpId($form.find('select[name="sector_legajocompra_id"]'));
        var $bloque = $form.find('.js-oc-bloque-factura-legajo');
        if (cxp && String(sectorId) === String(cxp)) {
            $bloque.removeClass('d-none');
            var ocId = $form.data('ordencompra-id') || $form.find('input[name="ordencompra_id"]').val();
            consultarGate($form, ocId, function (gate) {
                aplicarGate($form, gate);
            });
        } else {
            $bloque.addClass('d-none');
            $bloque.find('input[type=file]').prop('required', false).val('');
        }
    }

    window.OcCambiarSectorLegajo = {
        initForm: function ($form) {
            if (!$form || !$form.length) {
                return;
            }
            if ($form.attr('enctype') !== 'multipart/form-data') {
                $form.attr('enctype', 'multipart/form-data');
            }
            $form.on('change', 'select[name="sector_legajocompra_id"]', function () {
                toggleBloque($form, $(this).val());
            });
            toggleBloque($form, $form.find('select[name="sector_legajocompra_id"]').val());
        },
        setOrdencompraId: function ($form, id) {
            $form.data('ordencompra-id', id);
            toggleBloque($form, $form.find('select[name="sector_legajocompra_id"]').val());
        }
    };
})(jQuery);
