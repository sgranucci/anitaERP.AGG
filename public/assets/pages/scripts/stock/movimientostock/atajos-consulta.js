(function ($) {
    'use strict';

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function esTeclaF2(e) {
        return e.key === 'F2' || e.code === 'F2' || e.keyCode === 113;
    }

    function modalAbierto(selector) {
        var $m = $(selector);
        return $m.length && $m.hasClass('show');
    }

    function abrirConsultaArticuloFila($tr) {
        var $btn = $tr.find('.consultaarticulo').first();
        if ($btn.length) {
            $btn.trigger('click');
        }
    }

    function abrirConsultaDepositoCampo($ctx) {
        var $btn = $ctx.find('.consultadeposito').first();
        if ($btn.length) {
            $btn.trigger('click');
        }
    }

    var FORM_IDS = ['formgeneral', 'form-recuento'];
    var TABLA_ARTICULO_SELECTORS = ['#tabla-items-movimientostock', '#tabla-recuento-items'];

    function formContenedor(target) {
        if (!target) {
            return null;
        }
        for (var i = 0; i < FORM_IDS.length; i++) {
            var form = document.getElementById(FORM_IDS[i]);
            if (form && form.contains(target)) {
                return form;
            }
        }
        return null;
    }

    function filaArticuloDesdeTarget(target) {
        for (var i = 0; i < TABLA_ARTICULO_SELECTORS.length; i++) {
            var tr = target.closest(TABLA_ARTICULO_SELECTORS[i] + ' tr');
            if (tr) {
                return tr;
            }
        }
        return null;
    }

    document.addEventListener('keydown', function (e) {
        if (!esTeclaF1(e) && !esTeclaF2(e)) {
            return;
        }

        var target = e.target;
        if (!formContenedor(target)) {
            return;
        }

        if (esTeclaF1(e) && target.classList.contains('codigoarticulo')) {
            var trArt = filaArticuloDesdeTarget(target);
            if (!trArt) {
                return;
            }
            if (modalAbierto('#consultaarticuloModal')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaArticuloFila($(trArt));
            return;
        }

        if (esTeclaF1(e) && target.classList.contains('codigodeposito') && !target.readOnly) {
            var ctxDep = target.closest('.tm-deposito-campo');
            if (!ctxDep) {
                return;
            }
            if (modalAbierto('#consultadepositoModal')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaDepositoCampo($(ctxDep));
            return;
        }

        if (esTeclaF2(e) && target.classList.contains('abreviaturatipotransaccionstock') && !target.readOnly) {
            var ctxTipo = target.closest('.tm-tipotransaccion-stock-campo');
            if (!ctxTipo) {
                return;
            }
            if (modalAbierto('#consultatipotransaccionstockModal')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(ctxTipo).find('.consultatipotransaccionstock').first();
            if ($btn.length) {
                $btn.trigger('click');
            }
        }
    }, true);
}(jQuery));
