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

    function esCeldaImporte(texto) {
        var t = String(texto || '').trim();
        if (t === '') {
            return true;
        }
        if (!/^[\s$()\-]*[\d.,]+[\s)]*$/.test(t)) {
            return false;
        }
        var digitos = t.replace(/\D/g, '');
        if (!/[.,]/.test(t) && digitos.length >= 5) {
            return false;
        }
        return true;
    }

    function parsearPegadoImportes(texto) {
        var crudo = String(texto || '').replace(/\u00a0/g, ' ').replace(/^\uFEFF/, '');
        if (!crudo.trim()) {
            return null;
        }
        var filas = crudo.split(/\r?\n/).map(function (linea) {
            var celdas = linea.split('\t').map(function (celda) {
                var limpia = celda.trim().replace(/^"(.*)"$/, '$1').trim();
                return limpia;
            });
            while (celdas.length && celdas[celdas.length - 1] === '') {
                celdas.pop();
            }
            return celdas;
        }).filter(function (celdas) {
            return celdas.length > 0;
        });
        if (!filas.length) {
            return null;
        }
        for (var i = 0; i < filas.length; i++) {
            if (filas[i].length > 2) {
                return null;
            }
            for (var j = 0; j < filas[i].length; j++) {
                if (!esCeldaImporte(filas[i][j])) {
                    return null;
                }
            }
        }
        return filas;
    }

    function claseMontoDe(el) {
        var clases = ['.debeasiento', '.haberasiento', '.cotizacionasiento', '.debe', '.haber', '.cotizacion', '.monto', '.js-monto-ar'];
        var $el = window.jQuery(el);
        for (var i = 0; i < clases.length; i++) {
            if ($el.is(clases[i])) {
                return clases[i];
            }
        }
        return null;
    }

    function escribirMonto(el, texto) {
        if (!el) {
            return;
        }
        var n = parseDecimal(texto);
        el.value = n === 0 ? '' : fmt(n);
    }

    function filasDeColumna($input) {
        var clase = claseMontoDe($input.get(0));
        var $tbody = $input.closest('tbody');
        if (!$tbody.length) {
            return { clase: clase, $filas: window.jQuery() };
        }
        return {
            clase: clase,
            $filas: $tbody.children('tr').filter(function () {
                return window.jQuery(this).find(clase).length > 0;
            })
        };
    }

    function agregarFilaDeMontos($input) {
        var $ = window.jQuery;
        var $tbody = $input.closest('tbody');
        var $scope = $input.closest('form');
        if (!$scope.length) {
            $scope = $(document);
        }
        var $btn = $scope.find('#agrega_renglon_cuenta, #agrega_renglon_asiento').filter(':visible').first();
        if (!$btn.length) {
            $btn = $('#agrega_renglon_cuenta, #agrega_renglon_asiento').filter(':visible').first();
        }
        if (!$btn.length || !$tbody.length) {
            return false;
        }
        var antes = $tbody.children('tr').length;
        $btn.trigger('click');
        return $tbody.children('tr').length > antes;
    }

    function pegarColumnaImportes(el, texto) {
        if (!jqueryDisponible() || !el) {
            return false;
        }
        var filas = parsearPegadoImportes(texto);
        if (!filas) {
            return false;
        }
        var $ = window.jQuery;
        var $inicio = $(el);
        if (!$inicio.is(SELECTORES_MONTO)) {
            var $trOrigen = $inicio.closest('tr');
            var $debeFila = $trOrigen.find('.debe, .debeasiento').first();
            if ($debeFila.length) {
                $inicio = $debeFila;
            } else {
                $inicio = $('#tbody-cuenta-table .debe, .debeasiento').first();
            }
        }
        if (!$inicio.length || !$inicio.is(SELECTORES_MONTO)) {
            return false;
        }

        var claseInicio = claseMontoDe($inicio.get(0));
        if (!claseInicio) {
            return false;
        }
        var dosColumnas = filas.some(function (fila) { return fila.length > 1; });
        var esAsientoExterno = claseInicio.indexOf('asiento') >= 0;
        var indiceInicio = filasDeColumna($inicio).$filas.index($inicio.closest('tr'));
        if (indiceInicio < 0) {
            indiceInicio = 0;
        }

        for (var i = 0; i < filas.length; i++) {
            var datos = filasDeColumna($inicio);
            var guard = 0;
            while (datos.$filas.length <= indiceInicio + i && guard < 40) {
                if (!agregarFilaDeMontos($inicio)) {
                    break;
                }
                datos = filasDeColumna($inicio);
                guard++;
            }
            if (datos.$filas.length <= indiceInicio + i) {
                break;
            }
            var $tr = datos.$filas.eq(indiceInicio + i);
            if (dosColumnas && (claseInicio === '.debe' || claseInicio === '.haber' || claseInicio === '.debeasiento' || claseInicio === '.haberasiento')) {
                escribirMonto($tr.find(esAsientoExterno ? '.debeasiento' : '.debe').get(0), filas[i][0]);
                escribirMonto($tr.find(esAsientoExterno ? '.haberasiento' : '.haber').get(0), filas[i][1] || '');
            } else {
                escribirMonto($tr.find(claseInicio).get(0), filas[i][0]);
            }
        }

        if (typeof window.sumaMonto === 'function') {
            window.sumaMonto();
        }
        $(document).trigger('asiento:monto-actualizado');
        return true;
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
            var texto = '';
            try {
                texto = clipboard.getData('text/plain') || clipboard.getData('text') || '';
            } catch (err) {
                texto = '';
            }
            if (!pegarColumnaImportes(this, texto)) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
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
        pegarColumnaImportes: pegarColumnaImportes,
        selectoresMonto: SELECTORES_MONTO
    };

    if (!bindEventos()) {
        document.addEventListener('DOMContentLoaded', bindEventos);
        window.addEventListener('load', bindEventos);
    }
})();
