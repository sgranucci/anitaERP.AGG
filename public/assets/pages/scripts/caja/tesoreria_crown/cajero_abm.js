(function () {
    'use strict';

    function aplicarCampos(renglon) {
        var select = renglon.querySelector('.tc-externo-sistema');
        var codigo = renglon.querySelector('.tc-externo-codigo');
        var mozo = renglon.querySelector('.tc-externo-mozo');
        if (!select || !codigo || !mozo) {
            return;
        }
        var sistema = (select.value || '').toUpperCase();
        var usaMozo = sistema === 'GASTRONOMIA';
        var usaCodigo = sistema === 'PXSOL' || sistema === 'GST';
        codigo.disabled = !usaCodigo;
        mozo.disabled = !usaMozo;
        if (!usaCodigo) {
            codigo.value = '';
        }
        if (!usaMozo) {
            mozo.value = '';
        }
    }

    function reindexar() {
        var filas = document.querySelectorAll('#tbody-cajero-externos .tc-externo-renglon');
        filas.forEach(function (fila, indice) {
            fila.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/externos\[(?:\d+|__IDX__)\]/, 'externos[' + indice + ']');
            });
        });
    }

    function agregar() {
        var tpl = document.getElementById('tpl-cajero-externo');
        var tbody = document.getElementById('tbody-cajero-externos');
        if (!tpl || !tbody) {
            return;
        }
        var html = tpl.innerHTML.replace(/__IDX__/g, String(tbody.children.length));
        var wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        var fila = wrap.firstElementChild;
        if (!fila) {
            return;
        }
        tbody.appendChild(fila);
        aplicarCampos(fila);
        reindexar();
    }

    function quitar(boton) {
        var tbody = document.getElementById('tbody-cajero-externos');
        var fila = boton.closest('.tc-externo-renglon');
        if (!tbody || !fila) {
            return;
        }
        if (tbody.querySelectorAll('.tc-externo-renglon').length <= 1) {
            fila.querySelectorAll('input, select').forEach(function (el) {
                if (el.tagName === 'SELECT') {
                    el.value = '';
                } else {
                    el.value = '';
                }
            });
            aplicarCampos(fila);
            return;
        }
        fila.remove();
        reindexar();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#tbody-cajero-externos .tc-externo-renglon').forEach(aplicarCampos);
        var btn = document.getElementById('btn-agregar-externo');
        if (btn) {
            btn.addEventListener('click', agregar);
        }
        var tbody = document.getElementById('tbody-cajero-externos');
        if (tbody) {
            tbody.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('tc-externo-sistema')) {
                    aplicarCampos(e.target.closest('.tc-externo-renglon'));
                }
            });
            tbody.addEventListener('click', function (e) {
                var btnQuitar = e.target.closest('.tc-externo-quitar');
                if (btnQuitar) {
                    quitar(btnQuitar);
                }
            });
        }
    });
})();
