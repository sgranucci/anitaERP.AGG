/**
 * Modal compartido de combinaciones (F1 / lupa / Enter / Seleccionar todas).
 * La lista la provee window.otCombinacionesLista (o window.payloadExtraConsultaCombinacion).
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var ptrCombinacionCampo = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function listaBase() {
        if (typeof window.payloadExtraConsultaCombinacion === 'function') {
            var extra = window.payloadExtraConsultaCombinacion() || {};
            if (Array.isArray(extra.lista)) {
                return extra.lista;
            }
        }
        if (Array.isArray(window.otCombinacionesLista)) {
            return window.otCombinacionesLista;
        }
        return [];
    }

    function filtrar(consulta) {
        var lista = listaBase();
        var q = String(consulta || '').trim().toLowerCase();
        if (!q) {
            return lista;
        }
        return lista.filter(function (c) {
            return String(c.codigo || '').toLowerCase().indexOf(q) >= 0
                || String(c.nombre || '').toLowerCase().indexOf(q) >= 0
                || String(c.id) === q;
        });
    }

    function renderModal(consulta) {
        var $tb = $('#datoscombinacion');
        if (!$tb.length) {
            return;
        }
        var filas = filtrar(consulta);
        if (!filas.length) {
            $tb.html('<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>');
            return;
        }
        $tb.html(filas.map(function (c) {
            return '<tr data-id="' + c.id + '" data-codigo="' + escapeHtml(c.codigo || '') + '" data-nombre="' + escapeHtml(c.nombre || '') + '">'
                + '<td>' + c.id + '</td>'
                + '<td>' + escapeHtml(c.codigo || '') + '</td>'
                + '<td>' + escapeHtml(c.nombre || '') + '</td>'
                + '<td class="text-nowrap"><button type="button" class="btn btn-warning btn-sm eligeconsultacombinacion">Elegir</button></td>'
                + '</tr>';
        }).join(''));
    }

    function campoDesdeBoton($btn) {
        return $btn.closest('.tm-combinacion-campo');
    }

    function aplicarCombinacion($campo, id, codigo, nombre, todas) {
        if (!$campo || !$campo.length) {
            return;
        }
        $campo.find('.combinacion_id').val(todas ? '' : (id || ''));
        $campo.find('.codigocombinacion').val(todas ? '' : (codigo || ''));
        $campo.find('.descripcioncombinacion').val(todas ? 'TODAS LAS COMBINACIONES' : (nombre || ''));
        $campo.find('.ot-combinacion-todas').val(todas ? '1' : '0');
        $campo.trigger('combinacion:seleccionada', [{
            id: todas ? 0 : (parseInt(id, 10) || 0),
            codigo: codigo || '',
            nombre: nombre || '',
            todas: !!todas
        }]);
    }

    function modalAbierto() {
        return $('#consultacombinacionModal').hasClass('show');
    }

    window.activa_eventos_consultacombinacion = function () {
        $(document).off('click.consultaCombBtn', '.consultacombinacion').on('click.consultaCombBtn', '.consultacombinacion', function (e) {
            e.preventDefault();
            ptrCombinacionCampo = campoDesdeBoton($(this));
            $('#consultacombinacion').val('');
            renderModal('');
            $('#consultacombinacionModal').modal('show');
        });

        $('#consultacombinacionModal')
            .off('shown.bs.modal.consultaComb')
            .on('shown.bs.modal.consultaComb', function () {
                $('#consultacombinacion').trigger('focus');
            });

        $(document).off('input.consultaComb', '#consultacombinacion').on('input.consultaComb', '#consultacombinacion', function () {
            renderModal(String($(this).val() || '').trim());
        });

        $(document).off('keydown.consultaCombEnter', '#consultacombinacion').on('keydown.consultaCombEnter', '#consultacombinacion', function (e) {
            if (e.which !== 13) {
                return;
            }
            e.preventDefault();
            var $btn = $('#datoscombinacion .eligeconsultacombinacion').first();
            if ($btn.length) {
                $btn.trigger('click');
            }
        });

        $(document).off('click.eligeComb', '.eligeconsultacombinacion').on('click.eligeComb', '.eligeconsultacombinacion', function () {
            var $tr = $(this).closest('tr');
            var $campo = ptrCombinacionCampo && ptrCombinacionCampo.length
                ? ptrCombinacionCampo
                : $('.tm-combinacion-campo').first();
            aplicarCombinacion(
                $campo,
                $tr.data('id'),
                $tr.data('codigo'),
                $tr.data('nombre'),
                false
            );
            $('#consultacombinacionModal').modal('hide');
        });

        $(document).off('click.aceptaComb', '#aceptaconsultacombinacionModal').on('click.aceptaComb', '#aceptaconsultacombinacionModal', function () {
            var $btn = $('#datoscombinacion .eligeconsultacombinacion').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                $('#consultacombinacionModal').modal('hide');
            }
        });

        $(document).off('click.todasComb', '#seleccionartodascombinacionModal').on('click.todasComb', '#seleccionartodascombinacionModal', function () {
            var $campo = ptrCombinacionCampo && ptrCombinacionCampo.length
                ? ptrCombinacionCampo
                : $('.tm-combinacion-campo').first();
            if (!listaBase().length) {
                window.setTimeout(function () {
                    alert('Primero elija un artículo con combinaciones.');
                }, 0);
                return;
            }
            aplicarCombinacion($campo, '', '', '', true);
            $('#consultacombinacionModal').modal('hide');
        });

        $(document).off('keydown.consultaCombF1', '.codigocombinacion').on('keydown.consultaCombF1', '.codigocombinacion', function (e) {
            if (e.which !== 112) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.tm-combinacion-campo').find('.consultacombinacion').trigger('click');
        });

        $(document).off('keydown.consultaCombCodigoEnter', '.codigocombinacion').on('keydown.consultaCombCodigoEnter', '.codigocombinacion', function (e) {
            if (e.which !== 13) {
                return;
            }
            e.preventDefault();
            resolverCodigoCombinacion($(this).closest('.tm-combinacion-campo'));
        });

        $(document).off('blur.consultaCombCodigo', '.codigocombinacion').on('blur.consultaCombCodigo', '.codigocombinacion', function () {
            if (modalAbierto()) {
                return;
            }
            var $campo = $(this).closest('.tm-combinacion-campo');
            if ($campo.find('.ot-combinacion-todas').val() === '1') {
                return;
            }
            var codigo = String($(this).val() || '').trim();
            var idActual = String($campo.find('.combinacion_id').val() || '').trim();
            if (codigo === '' && idActual === '') {
                $campo.find('.descripcioncombinacion').val('');
                return;
            }
            resolverCodigoCombinacion($campo, true);
        });
    };

    function resolverCodigoCombinacion($campo, silencioso) {
        if (!$campo || !$campo.length) {
            return;
        }
        var codigo = String($campo.find('.codigocombinacion').val() || '').trim();
        if (codigo === '') {
            aplicarCombinacion($campo, '', '', '', false);
            return;
        }
        var lista = listaBase();
        var hallada = null;
        var codigoLower = codigo.toLowerCase();
        for (var i = 0; i < lista.length; i++) {
            if (String(lista[i].codigo || '').toLowerCase() === codigoLower
                || String(lista[i].id) === codigo) {
                hallada = lista[i];
                break;
            }
        }
        if (!hallada) {
            $campo.find('.combinacion_id').val('');
            $campo.find('.descripcioncombinacion').val('');
            $campo.find('.ot-combinacion-todas').val('0');
            if (!silencioso) {
                window.setTimeout(function () {
                    alert('Combinación no encontrada.');
                    $campo.find('.codigocombinacion').trigger('focus');
                }, 0);
            }
            return;
        }
        aplicarCombinacion($campo, hallada.id, hallada.codigo, hallada.nombre, false);
    }

    $(function () {
        if ($('#consultacombinacionModal').length) {
            window.activa_eventos_consultacombinacion();
        }
    });
})(window, window.jQuery);
