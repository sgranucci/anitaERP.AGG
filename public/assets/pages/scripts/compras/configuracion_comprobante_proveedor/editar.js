(function ($) {
    'use strict';

    function reindexTolerancias() {
        $('#tolerancia-cp-table tbody tr.item-tolerancia-cp').each(function (idx) {
            $(this).find('[name^="tolerancias["]').each(function () {
                var name = $(this).attr('name');
                if (!name) {
                    return;
                }
                $(this).attr('name', name.replace(/tolerancias\[\d+]/, 'tolerancias[' + idx + ']'));
            });
        });
    }

    function etiquetaFlujo(flujo) {
        if (flujo === 'estricto') {
            return 'Escenario activo: Estándar con recepción (MM)';
        }
        return 'Escenario activo: Flexible (sin circuito COM fijo)';
    }

    function etiquetaContab(modo) {
        if (modo === 'provision') {
            return 'Contabilidad activa: Provisión automática (GR valuado)';
        }
        return 'Contabilidad activa: Sin provisión (GR no valuado)';
    }

    function sincronizarNodoComFi(activa) {
        var $nodo = $('[data-com-fi-nodo]');
        if (!$nodo.length) {
            return;
        }
        if (String(activa) === '1') {
            $nodo.find('.js-com-fi-code').text('GR+FI');
            $nodo.find('.js-com-fi-label').text('COM + provisión');
            $nodo.find('.js-com-fi-hint').text('Obligatoria · FAR');
        } else {
            $nodo.find('.js-com-fi-code').text('GR');
            $nodo.find('.js-com-fi-label').text('Recepción COM');
            $nodo.find('.js-com-fi-hint').text('Obligatoria · sin FI');
        }
    }

    function seleccionarFlujo($card) {
        var $wrap = $('#cp-flujo-proceso');
        if (!$wrap.length || !$card.length) {
            return;
        }
        var flujo = $card.data('flujo');
        var exige = String($card.data('exige')) === '1' ? '1' : '0';

        $wrap.find('.cp-flujo-card').removeClass('is-selected').attr('aria-pressed', 'false');
        $card.addClass('is-selected').attr('aria-pressed', 'true');
        $('#exige_flujo_oc_com_fac').val(exige);
        $('#cp-flujo-etiqueta-activa').text(etiquetaFlujo(flujo));
    }

    function seleccionarContab($card) {
        var $wrap = $('#cp-com-contabilidad');
        if (!$wrap.length || !$card.length) {
            return;
        }
        var modo = $card.data('contab');
        var activa = String($card.data('activa')) === '1' ? '1' : '0';

        $wrap.find('.cp-flujo-card').removeClass('is-selected').attr('aria-pressed', 'false');
        $card.addClass('is-selected').attr('aria-pressed', 'true');
        $('#com_genera_contabilidad').val(activa);
        $('#cp-contab-etiqueta-activa').text(etiquetaContab(modo));
        sincronizarNodoComFi(activa);
    }

    function initSelectorFlujo() {
        var $wrap = $('#cp-flujo-proceso');
        if (!$wrap.length) {
            return;
        }

        var inicial = $wrap.data('flujo-inicial') === 'estricto' ? 'estricto' : 'flexible';
        var $activa = $wrap.find('.cp-flujo-card[data-flujo="' + inicial + '"]').first();
        if (!$activa.length) {
            $activa = $wrap.find('.cp-flujo-card').first();
        }
        seleccionarFlujo($activa);

        $wrap.on('click', '.cp-flujo-card', function (e) {
            e.preventDefault();
            seleccionarFlujo($(this));
        });

        $wrap.on('keydown', '.cp-flujo-card', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                seleccionarFlujo($(this));
            }
        });
    }

    function initSelectorContab() {
        var $wrap = $('#cp-com-contabilidad');
        if (!$wrap.length) {
            return;
        }

        var inicial = $wrap.data('contab-inicial') === 'provision' ? 'provision' : 'factura';
        var $activa = $wrap.find('.cp-flujo-card[data-contab="' + inicial + '"]').first();
        if (!$activa.length) {
            $activa = $wrap.find('.cp-flujo-card').first();
        }
        seleccionarContab($activa);

        $wrap.on('click', '.cp-flujo-card', function (e) {
            e.preventDefault();
            seleccionarContab($(this));
        });

        $wrap.on('keydown', '.cp-flujo-card', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                seleccionarContab($(this));
            }
        });
    }

    function syncToleranciaPrecioUi() {
        var on = $('#controla_precio_unitario').is(':checked');
        $('#tolerancia_precio_pct').prop('readonly', !on);
        if (!on) {
            // No forzar a 0: se conserva el valor configurado por si reactivan el control.
        }
    }

    $(function () {
        $('#empresa_id').on('change', function () {
            var id = $(this).val();
            if (id) {
                window.location = (window.carpetaBase || '') + '/compras/configuracion-comprobante-proveedor?empresa_id=' + id;
            }
        });

        initSelectorFlujo();
        initSelectorContab();
        syncToleranciaPrecioUi();
        $(document).on('change', '#controla_sku_vs_com, #controla_precio_unitario', syncToleranciaPrecioUi);

        $('#btn-agregar-tolerancia-cp').on('click', function () {
            var tpl = document.getElementById('template-tolerancia-cp');
            if (!tpl) {
                return;
            }
            var idx = $('#tolerancia-cp-table tbody tr.item-tolerancia-cp').length;
            var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
            $('#tolerancia-cp-table tbody').append(html);
        });

        $(document).on('click', '.js-quitar-tolerancia-cp', function () {
            $(this).closest('tr').remove();
            reindexTolerancias();
        });
    });
})(jQuery);
