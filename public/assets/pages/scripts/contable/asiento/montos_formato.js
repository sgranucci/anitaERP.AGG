(function () {
    'use strict';

    var SELECTORES_MONTO = '.debe, .haber, .cotizacion, .debeasiento, .haberasiento, .cotizacionasiento';

    function parseDecimal(str) {
        if (str == null || str === '') {
            return 0;
        }
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatearInput(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmt(parseDecimal(el.value));
    }

    function desformatearInput(el) {
        if (!el || el.value === '') {
            return;
        }
        var n = parseDecimal(el.value);
        el.value = n === 0 && String(el.value).trim() === '' ? '' : String(n);
    }

    function normalizarAntesDeEnviar(root) {
        var scope = root ? $(root) : $(document);
        scope.find(SELECTORES_MONTO).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '';
                return;
            }
            this.value = String(parseDecimal(this.value));
        });
    }

    function initEnContenedor(root) {
        var scope = root ? $(root) : $(document);
        scope.find(SELECTORES_MONTO).each(function () {
            formatearInput(this);
        });
    }

    function bindEventos() {
        if (bindEventos._listo) {
            return;
        }
        bindEventos._listo = true;

        $(document).on('focus', SELECTORES_MONTO, function () {
            desformatearInput(this);
        });

        $(document).on('blur', SELECTORES_MONTO, function () {
            formatearInput(this);
            $(document).trigger('asiento:monto-actualizado');
        });

        $(document).on('submit', 'form', function () {
            if ($(this).find(SELECTORES_MONTO).length) {
                normalizarAntesDeEnviar(this);
            }
        });
    }

    bindEventos();

    window.AsientoMontosFormato = {
        parseDecimal: parseDecimal,
        fmt: fmt,
        formatearInput: formatearInput,
        desformatearInput: desformatearInput,
        normalizarAntesDeEnviar: normalizarAntesDeEnviar,
        initEnContenedor: initEnContenedor,
        selectoresMonto: SELECTORES_MONTO
    };
})();
