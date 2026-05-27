/* global $ */

(function () {
    'use strict';

    var $tabla = null;
    var $tbody = null;
    var $template = null;
    var areasPorEmpresa = {};

    $(function () {
        $tabla = $('#tabla-areas-comanda');
        if ($tabla.length === 0) {
            return;
        }
        $tbody = $('#tbody-areas-comanda');
        $template = $('#template-fila-area-comanda');

        try {
            var raw = $tabla.attr('data-areas-por-empresa') || '{}';
            areasPorEmpresa = JSON.parse(raw) || {};
        } catch (err) {
            areasPorEmpresa = {};
        }

        $tbody.find('tr.fila-area-comanda').each(function () {
            renderAreasParaFila($(this));
        });

        $('#js-agregar-fila-area').on('click', function (e) {
            e.preventDefault();
            agregarFila();
        });

        $tbody.on('click', '.js-eliminar-fila-area', function (e) {
            e.preventDefault();
            $(this).closest('tr.fila-area-comanda').remove();
            actualizarBadge();
            validarEstadoDuplicados();
        });

        $tbody.on('change', '.js-select-empresa-area', function () {
            var $fila = $(this).closest('tr.fila-area-comanda');
            $fila.find('.js-select-area-comanda').attr('data-selected', '');
            renderAreasParaFila($fila);
            validarEstadoDuplicados();
            actualizarBadge();
        });

        $tbody.on('change', '.js-select-area-comanda', function () {
            validarEstadoDuplicados();
            actualizarBadge();
        });

        $('form#form-general').on('submit', function (e) {
            if (! validarDuplicadosAlEnviar()) {
                e.preventDefault();
                $('#tab-areas-link').tab('show');
            }
        });

        actualizarBadge();
    });

    function agregarFila() {
        if (! $template || $template.length === 0) {
            return;
        }
        var html = $template.html();
        var $nuevaFila = $(html);
        $tbody.append($nuevaFila);
        renderAreasParaFila($nuevaFila);
    }

    function renderAreasParaFila($fila) {
        var $selectEmpresa = $fila.find('.js-select-empresa-area');
        var $selectArea = $fila.find('.js-select-area-comanda');

        var empresaId = String($selectEmpresa.val() || '');
        var seleccionPrevia = String($selectArea.attr('data-selected') || $selectArea.val() || '');

        $selectArea.empty().append('<option value="">-- Elija área --</option>');

        if (empresaId === '') {
            return;
        }

        var listaAreas = areasPorEmpresa[empresaId] || [];
        listaAreas.forEach(function (area) {
            var idStr = String(area.id);
            var label = (area.codigo ? area.codigo + ' - ' : '') + (area.nombre || '');
            var $opt = $('<option></option>').val(idStr).text(label);
            if (idStr === seleccionPrevia) {
                $opt.prop('selected', true);
            }
            $selectArea.append($opt);
        });
    }

    function actualizarBadge() {
        var $badge = $('#badge-cant-areas');
        if ($badge.length === 0) {
            return;
        }
        var cant = 0;
        $tbody.find('.js-select-area-comanda').each(function () {
            if (String($(this).val() || '') !== '') {
                cant += 1;
            }
        });
        $badge.text(cant);
    }

    function validarEstadoDuplicados() {
        var vistos = {};
        var duplicados = {};

        $tbody.find('tr.fila-area-comanda').each(function () {
            var val = String($(this).find('.js-select-area-comanda').val() || '');
            if (val === '') {
                return;
            }
            if (vistos[val]) {
                duplicados[val] = true;
            } else {
                vistos[val] = true;
            }
        });

        $tbody.find('tr.fila-area-comanda').each(function () {
            var $select = $(this).find('.js-select-area-comanda');
            var val = String($select.val() || '');
            if (val !== '' && duplicados[val]) {
                $select.addClass('is-invalid');
            } else {
                $select.removeClass('is-invalid');
            }
        });
    }

    function validarDuplicadosAlEnviar() {
        validarEstadoDuplicados();

        var $invalidos = $tbody.find('.js-select-area-comanda.is-invalid');
        if ($invalidos.length > 0) {
            alert('Hay áreas de comanda duplicadas. Cada área puede asignarse una sola vez a la subcategoría.');
            return false;
        }

        var pares = {};
        var pareIncompleto = false;
        $tbody.find('tr.fila-area-comanda').each(function () {
            var empresa = String($(this).find('.js-select-empresa-area').val() || '');
            var area = String($(this).find('.js-select-area-comanda').val() || '');

            if (empresa === '' && area === '') {
                return;
            }
            if (empresa === '' || area === '') {
                pareIncompleto = true;
                $(this).find('select').addClass('is-invalid');
                return;
            }

            var clave = empresa + '|' + area;
            if (pares[clave]) {
                $(this).find('.js-select-area-comanda').addClass('is-invalid');
            } else {
                pares[clave] = true;
            }
        });

        if (pareIncompleto) {
            alert('Complete la empresa y el área de comanda en todas las filas, o elimine las filas vacías.');
            return false;
        }

        return true;
    }
})();
