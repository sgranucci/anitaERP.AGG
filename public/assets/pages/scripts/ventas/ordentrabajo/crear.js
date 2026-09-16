/**
 * Alta OT: marca + consulta artículo + combinaciones (incl. seleccionar todas).
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    window.otCombinacionesLista = window.otCombinacionesLista || [];

    function carpeta() {
        return typeof carpetaBase !== 'undefined' ? carpetaBase : '';
    }

    function limpiarCombinacion() {
        var $campo = $('#ot-combinacion-campo');
        $campo.find('.combinacion_id').val('');
        $campo.find('.codigocombinacion').val('');
        $campo.find('.descripcioncombinacion').val('');
        $campo.find('.ot-combinacion-todas').val('0');
        window.otCombinacionesLista = [];
    }

    function cargarCombinaciones(articuloId) {
        limpiarCombinacion();
        articuloId = parseInt(articuloId, 10) || 0;
        if (!articuloId) {
            return;
        }
        $.get(carpeta() + '/stock/leercombinaciones/' + articuloId, function (data) {
            var lista = [];
            if (Array.isArray(data)) {
                lista = data;
            } else if (data && typeof data === 'object') {
                lista = $.map(data, function (v) { return v; });
            }
            window.otCombinacionesLista = lista.map(function (c) {
                return {
                    id: parseInt(c.id, 10) || 0,
                    codigo: c.codigo || '',
                    nombre: c.nombre || ''
                };
            });
        });
    }

    function validarMarcaArticulo(dataArticulo) {
        var mventaId = String($('#mventa_id').val() || '');
        if (!mventaId) {
            return true;
        }
        var artMventa = String(dataArticulo.mventa_id != null ? dataArticulo.mventa_id : '');
        if (artMventa && artMventa !== mventaId) {
            window.setTimeout(function () {
                alert('El artículo no pertenece a la marca seleccionada.');
            }, 0);
            $('#ot-articulo-id').val('');
            $('#ot-codigoarticulo').val('');
            $('#ot-descripcionarticulo').val('');
            limpiarCombinacion();
            return false;
        }
        return true;
    }

    window.onArticuloSeleccionado = function (dataArticulo) {
        if (!dataArticulo || !dataArticulo.id) {
            return;
        }
        if (!validarMarcaArticulo(dataArticulo)) {
            return;
        }
        if (dataArticulo.mventa_id && !$('#mventa_id').val()) {
            $('#mventa_id').val(String(dataArticulo.mventa_id));
        }
        cargarCombinaciones(dataArticulo.id);
    };

    window.payloadExtraConsultaCombinacion = function () {
        return { lista: window.otCombinacionesLista || [] };
    };

    $(function () {
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        if (typeof activa_eventos_consultacombinacion === 'function') {
            activa_eventos_consultacombinacion();
        }

        $('#mventa_id').on('change', function () {
            $('#ot-articulo-id').val('');
            $('#ot-codigoarticulo').val('');
            $('#ot-descripcionarticulo').val('');
            limpiarCombinacion();
        });

        $(document).on('keydown', '#ot-codigoarticulo', function (e) {
            if (e.which !== 112 && e.key !== 'F1') {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.tm-articulo-campo').find('.consultaarticulo').trigger('click');
        });

        $('#form-general').on('submit', function (e) {
            var articuloId = parseInt($('#ot-articulo-id').val(), 10) || 0;
            if (!articuloId) {
                e.preventDefault();
                alert('Debe elegir un artículo.');
                $('#ot-codigoarticulo').trigger('focus');
                return false;
            }
            var todas = $('#ot-combinacion-todas').val() === '1';
            var combId = parseInt($('#ot-combinacion-id').val(), 10) || 0;
            if (!todas && !combId) {
                e.preventDefault();
                alert('Debe elegir una combinación o «Seleccionar todas».');
                $('#ot-codigocombinacion').trigger('focus');
                return false;
            }
            if (todas) {
                $('#ot-combinacion-id').val('');
            }
            return true;
        });

        var artIdInicial = parseInt($('#ot-articulo-id').val(), 10) || 0;
        if (artIdInicial) {
            cargarCombinaciones(artIdInicial);
        }
    });
})(window, window.jQuery);
