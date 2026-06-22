(function ($) {
    'use strict';

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
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

    document.addEventListener('keydown', function (e) {
        if (!esTeclaF1(e)) {
            return;
        }

        var form = document.getElementById('formgeneral');
        if (!form) {
            return;
        }

        var target = e.target;
        if (!target || !form.contains(target)) {
            return;
        }

        if (target.classList.contains('codigoarticulo')) {
            var trArt = target.closest('#tabla-items-movimientostock tr');
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

        if (target.classList.contains('codigodeposito') && !target.readOnly) {
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
        }
    }, true);
}(jQuery));
