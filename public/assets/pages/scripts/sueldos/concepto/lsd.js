(function () {
    'use strict';

    var sel = document.getElementById('concepto_afip');
    var libre = document.getElementById('concepto_afip_libre');

    function tipoDesdeCodigo(codigo) {
        var n = parseInt(String(codigo || '').replace(/\D+/g, ''), 10) || 0;
        if (n >= 110000 && n <= 499999) {
            return 'remunerativo';
        }
        if (n >= 510000 && n <= 799999) {
            return 'no_remunerativo';
        }
        if (n >= 810000 && n <= 829999) {
            return 'descuento';
        }
        return '';
    }

    function aplicarDefaults(tipo) {
        var flags = document.querySelectorAll('[data-lsd-flag]');
        var valor = tipo === 'remunerativo';
        flags.forEach(function (el) {
            el.checked = valor;
        });
    }

    if (sel) {
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) {
                return;
            }
            if (libre) {
                libre.value = '';
            }
            aplicarDefaults(opt.getAttribute('data-tipo') || tipoDesdeCodigo(opt.value));
        });
    }

    if (libre) {
        libre.addEventListener('blur', function () {
            var tipo = tipoDesdeCodigo(libre.value);
            if (tipo) {
                aplicarDefaults(tipo);
            }
        });
    }
})();
