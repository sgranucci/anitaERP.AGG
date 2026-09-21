/**
 * Espejo de valor panel/toolbar para filtros del legajo marketplace.
 * listado-filtros.js maneja el toggle genérico.
 */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-filtros-cdm');
        if (!form) return;
        form.addEventListener('submit', function () {
            var panel = document.getElementById('filtro_valor_panel');
            var top = document.getElementById('filtro_valor');
            if (panel && top && panel.offsetParent !== null) {
                top.value = panel.value;
            }
        });
    });
})();
