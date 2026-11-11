/**
 * Alta de corrida: al cambiar empresa, sugiere el próximo código Anita
 * salvo que el usuario ya lo haya editado a mano.
 */
(function ($) {
    'use strict';

    var $numero = $('#numero');
    var $empresa = $('#empresa_id');
    if (! $numero.length || ! $empresa.length || $numero.prop('required')) {
        return;
    }

    var urlProximo = $numero.data('url-proximo') || '';
    var editadoManual = false;
    var valorInicial = String($numero.val() || '');

    $numero.on('input', function () {
        var actual = String($numero.val() || '');
        editadoManual = actual !== '' && actual !== valorInicial;
    });

    function sugerir() {
        if (editadoManual || ! urlProximo) {
            return;
        }
        var empresaId = parseInt($empresa.val(), 10) || 0;
        if (empresaId <= 0) {
            return;
        }
        $.getJSON(urlProximo, { empresa_id: empresaId })
            .done(function (resp) {
                if (editadoManual) {
                    return;
                }
                if (resp && resp.numero) {
                    $numero.val(resp.numero);
                    valorInicial = String(resp.numero);
                }
            });
    }

    $empresa.on('change', sugerir);
})(jQuery);
