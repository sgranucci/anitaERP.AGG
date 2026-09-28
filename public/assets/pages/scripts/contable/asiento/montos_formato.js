(function () {
    'use strict';

    var SELECTORES_MONTO = '.debe, .haber, .cotizacion, .debeasiento, .haberasiento, .cotizacionasiento, .monto, .js-monto-ar';

    function parseDecimal(str) {
        if (str == null || str === '') {
            return 0;
        }
        var t = String(str).trim().replace(/\s/g, '').replace(/[^\d,.\-]/g, '');
        if (t === '' || t === '-') {
            return 0;
        }
        var negativo = false;
        if (t.charAt(0) === '-') {
            negativo = true;
            t = t.substring(1);
        }
        var tieneComa = t.indexOf(',') >= 0;
        var tienePunto = t.indexOf('.') >= 0;
        if (tieneComa && tienePunto) {
            if (t.lastIndexOf(',') > t.lastIndexOf('.')) {
                t = t.replace(/\./g, '').replace(',', '.');
            } else {
                t = t.replace(/,/g, '');
            }
        } else if (tieneComa) {
            if (/^\d{1,3}(,\d{3})+$/.test(t)) {
                t = t.replace(/,/g, '');
            } else {
                t = t.replace(/,/g, '.');
            }
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        if (negativo) {
            t = '-' + t;
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

    function esInputNumeroNativo(el) {
        return el && String(el.type || '').toLowerCase() === 'number';
    }

    function formatearInput(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        var n = parseDecimal(el.value);
        // type=number no acepta miles/coma (1.600.432,00): el browser vacía el campo.
        if (esInputNumeroNativo(el)) {
            el.value = String(n);
            return;
        }
        el.value = fmt(n);
    }

    function desformatearInput(el) {
        if (!el || el.value === '') {
            return;
        }
        var n = parseDecimal(el.value);
        // Si está en cero (0 / 0,00), vaciar al foco para facilitar la carga.
        el.value = n === 0 ? '' : String(n);
    }

    function jqueryDisponible() {
        return typeof window.jQuery !== 'undefined';
    }

    function normalizarAntesDeEnviar(root) {
        if (!jqueryDisponible()) {
            return;
        }
        var scope = root ? window.jQuery(root) : window.jQuery(document);
        scope.find(SELECTORES_MONTO).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '';
                return;
            }
            this.value = String(parseDecimal(this.value));
        });
    }

    function initEnContenedor(root) {
        if (!jqueryDisponible()) {
            return;
        }
        var scope = root ? window.jQuery(root) : window.jQuery(document);
        scope.find(SELECTORES_MONTO).each(function () {
            formatearInput(this);
        });
    }

    function bindEventos() {
        if (bindEventos._listo) {
            return true;
        }
        if (!jqueryDisponible()) {
            return false;
        }
        var $ = window.jQuery;
        bindEventos._listo = true;

        $(document).on('focus', SELECTORES_MONTO, function () {
            desformatearInput(this);
        });

        $(document).on('blur', SELECTORES_MONTO, function () {
            formatearInput(this);
            $(document).trigger('asiento:monto-actualizado');
        });

        $(document).on('paste', SELECTORES_MONTO, function (e) {
            var clipboard = e.originalEvent && e.originalEvent.clipboardData
                ? e.originalEvent.clipboardData
                : window.clipboardData;
            if (!clipboard) {
                return;
            }
            var texto = String(clipboard.getData('text') || '').replace(/\u00a0/g, ' ').trim();
            if (!texto) {
                return;
            }
            var lineas = texto.split(/\r?\n/).map(function (linea) {
                return linea.trim();
            }).filter(function (linea) {
                return linea !== '';
            });
            if (lineas.length !== 1 || lineas[0].indexOf('\t') >= 0) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var n = parseDecimal(lineas[0]);
            this.value = n === 0 ? '' : fmt(n);
            $(document).trigger('asiento:monto-actualizado');
        });

        $(document).on('submit', 'form', function () {
            if ($(this).find(SELECTORES_MONTO).length) {
                normalizarAntesDeEnviar(this);
            }
        });

        return true;
    }

    window.AsientoMontosFormato = {
        parseDecimal: parseDecimal,
        fmt: fmt,
        formatearInput: formatearInput,
        desformatearInput: desformatearInput,
        normalizarAntesDeEnviar: normalizarAntesDeEnviar,
        initEnContenedor: initEnContenedor,
        bindEventos: bindEventos,
        selectoresMonto: SELECTORES_MONTO
    };

    if (!bindEventos()) {
        document.addEventListener('DOMContentLoaded', bindEventos);
        window.addEventListener('load', bindEventos);
    }
})();
