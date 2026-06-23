/**
 * Panel de filtros colapsable: etiqueta Mostrar/Ocultar (Bootstrap collapse nativo).
 *
 * Botón: data-toggle="collapse" data-target="#panel-id" data-listado-filtros-toggle
 */
(function ($) {
    'use strict';

    var DEFAULT_SHOW = 'Mostrar filtros';
    var DEFAULT_HIDE = 'Ocultar filtros';

    function labelShow($btn) {
        return $btn.attr('data-listado-filtros-label-show') || DEFAULT_SHOW;
    }

    function labelHide($btn) {
        return $btn.attr('data-listado-filtros-label-hide') || DEFAULT_HIDE;
    }

    function panelFromToggle($btn) {
        var target = $btn.attr('data-target');
        if (!target) {
            return $();
        }

        return $(target);
    }

    function panelEstaAbierto($panel) {
        return $panel.hasClass('show') || $panel.hasClass('in');
    }

    function actualizarEtiquetaToggle($btn, $panel) {
        var abierto = panelEstaAbierto($panel);
        $btn.attr('aria-expanded', abierto ? 'true' : 'false');
        $btn.find('.js-listado-filtros-toggle-text').text(abierto ? labelHide($btn) : labelShow($btn));
    }

    function initToggle($btn) {
        var $panel = panelFromToggle($btn);
        if (!$panel.length) {
            return;
        }

        if (!$btn.attr('data-toggle')) {
            $btn.attr('data-toggle', 'collapse');
        }
        if (!$btn.attr('aria-controls')) {
            $btn.attr('aria-controls', ($panel.attr('id') || '').replace('#', ''));
        }

        actualizarEtiquetaToggle($btn, $panel);

        $panel
            .off('shown.bs.collapse.listadoFiltros hidden.bs.collapse.listadoFiltros')
            .on('shown.bs.collapse.listadoFiltros hidden.bs.collapse.listadoFiltros', function () {
                actualizarEtiquetaToggle($btn, $panel);
            });
    }

    /**
     * Sincroniza dos inputs de texto (uno con name para el GET, el otro espejo en el panel).
     */
    function sincronizarInputsValor($principal, $espejo) {
        if (!$principal.length || !$espejo.length) {
            return;
        }

        $principal.on('input.listadoFiltrosValor', function () {
            if ($espejo.val() !== $principal.val()) {
                $espejo.val($principal.val());
            }
        });

        $espejo.on('input.listadoFiltrosValor', function () {
            if ($principal.val() !== $espejo.val()) {
                $principal.val($espejo.val());
            }
        });
    }

    /**
     * Búsqueda rápida solo con panel de filtros cerrado (lupa / Enter en caja superior).
     * Panel abierto: lupa y Enter en cabecera aplican como «Aplicar filtros»; Enter en el panel igual.
     */
    function initSubmitBusquedaRapida($form, options) {
        var opts = $.extend({
            hiddenRapida: '#filtro_busqueda_rapida',
            selectorRapidaBtn: '[data-busqueda-rapida]',
            selectorAplicarBtn: '[data-aplicar-filtros-panel]',
            selectorInputRapida: '#filtro_valor',
            selectorPanel: '[data-listado-filtros-panel]'
        }, options || {});

        function panelAbierto() {
            var $panel = $form.find(opts.selectorPanel).first();
            if (!$panel.length) {
                $panel = $(opts.selectorPanel).first();
            }

            return $panel.length > 0 && ($panel.hasClass('show') || $panel.hasClass('in'));
        }

        function debeUsarBusquedaRapida(submitter, activo) {
            if (submitter && $(submitter).is(opts.selectorAplicarBtn)) {
                return false;
            }

            if (panelAbierto()) {
                return false;
            }

            if (submitter && $(submitter).is(opts.selectorRapidaBtn)) {
                return true;
            }

            if (!submitter && $(activo).is(opts.selectorInputRapida)) {
                return true;
            }

            return false;
        }

        $form.on('submit.listadoFiltrosModo', function (e) {
            var $hidden = $(opts.hiddenRapida);
            if (!$hidden.length) {
                return;
            }

            var submitter = e.originalEvent && e.originalEvent.submitter;
            var activo = document.activeElement;

            $hidden.val(debeUsarBusquedaRapida(submitter, activo) ? '1' : '');
        });

        function enviarFormularioListado(submitter) {
            var $hidden = $(opts.hiddenRapida);
            if ($hidden.length) {
                $hidden.val(debeUsarBusquedaRapida(submitter, document.activeElement) ? '1' : '');
            }

            if (typeof $form[0].requestSubmit === 'function') {
                if (submitter) {
                    $form[0].requestSubmit(submitter);
                } else {
                    $form[0].requestSubmit();
                }
            } else {
                $form.trigger('submit');
            }
        }

        function inputsBusquedaRapida() {
            var formId = $form.attr('id');
            var $inputs = $(opts.selectorInputRapida);
            if (formId) {
                $inputs = $inputs.add($('input[form="' + formId + '"]').filter(function () {
                    var name = $(this).attr('name') || '';
                    return name === 'filtro_valor' || $(this).is(opts.selectorInputRapida);
                }));
            }

            return $inputs;
        }

        inputsBusquedaRapida().on('keydown.listadoFiltrosEnter', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();

            var $btnRapida = $('button[form="' + $form.attr('id') + '"][data-busqueda-rapida]').first();
            if (!$btnRapida.length) {
                $btnRapida = $form.find(opts.selectorRapidaBtn).first();
            }

            if (panelAbierto()) {
                var $btnAplicar = $form.find(opts.selectorAplicarBtn).first();
                enviarFormularioListado($btnAplicar.length ? $btnAplicar[0] : null);
            } else if ($btnRapida.length) {
                enviarFormularioListado($btnRapida[0]);
            } else {
                enviarFormularioListado(null);
            }
        });

        $form.find(opts.selectorInputRapida).add('#filtro_valor_panel').on('keydown.listadoFiltrosEnterPanel', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            if (!panelAbierto()) {
                return;
            }
            e.preventDefault();
            var $btnAplicar = $form.find(opts.selectorAplicarBtn).first();
            enviarFormularioListado($btnAplicar.length ? $btnAplicar[0] : null);
        });
    }

    window.ListadoFiltros = {
        init: function () {
            $('[data-listado-filtros-toggle]').each(function () {
                initToggle($(this));
            });
        },
        initSubmitBusquedaRapida: initSubmitBusquedaRapida,
        sincronizarValorPrincipal: function (selectorPrincipal, selectorEspejo) {
            sincronizarInputsValor($(selectorPrincipal), $(selectorEspejo));
        },
        operadoresModoTodos: function () {
            return {
                contiene: 'Contiene (en cualquier parte)',
                empieza: 'Empieza con',
                termina: 'Termina con',
                igual: 'Igual a',
                vacio: 'Vacío'
            };
        },
        rellenarSelectOperadores: function ($select, mapa, valorActual) {
            $select.empty();
            $.each(mapa, function (key, label) {
                var selected = key === valorActual ? ' selected' : '';
                $select.append('<option value="' + key + '"' + selected + '>' + label + '</option>');
            });
            if ($select.find('option[value="' + valorActual + '"]').length === 0) {
                $select.val($select.find('option:first').val());
            }
        }
    };

    $(function () {
        window.ListadoFiltros.init();
    });
})(jQuery);
