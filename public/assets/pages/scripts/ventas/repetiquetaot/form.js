/**
 * Etiquetas de OT: emisión desde la orden o con los datos de la etiqueta.
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

    function esManual() {
        return $('#modo').val() === 'manual';
    }

    function tipoPideTalle() {
        return $('#tipoetiqueta').val() !== 'CUIT';
    }

    function limpiarCombinacion() {
        var $campo = $('#eti-combinacion-campo');
        $campo.find('.combinacion_id').val('');
        $campo.find('.codigocombinacion').val('');
        $campo.find('.descripcioncombinacion').val('');
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

    function aplicarModo(ponerFoco) {
        var manual = esManual();
        $('#bloque-etiqueta-manual').toggleClass('d-none', !manual);
        $('#bloque-etiqueta-ot').toggleClass('d-none', manual);
        $('#ordenestrabajo').prop('required', !manual);
        $('#origen').prop('required', !manual);
        var pideTalle = manual && tipoPideTalle();
        $('#eti-talle-campo').toggleClass('d-none', !pideTalle);
        $('#eti-nro-referencia').toggleClass('d-none', !manual || tipoPideTalle());
        if (!ponerFoco) {
            return;
        }
        if (manual) {
            $('#eti-codigoarticulo').trigger('focus');
        } else {
            $('#ordenestrabajo').trigger('focus');
        }
    }

    window.onArticuloSeleccionado = function (dataArticulo) {
        if (!dataArticulo || !dataArticulo.id) {
            return;
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
        if (typeof activa_eventos_consultatalle === 'function') {
            activa_eventos_consultatalle();
        }

        $('#seleccionartodascombinacionModal').addClass('d-none');

        $('#modo').on('change', function () { aplicarModo(true); });
        $('#tipoetiqueta').on('change', function () { aplicarModo(false); });
        aplicarModo(true);

        var artIdInicial = parseInt($('#eti-articulo-id').val(), 10) || 0;
        if (artIdInicial) {
            cargarCombinaciones(artIdInicial);
        }

        $(document).on('keydown', '#eti-codigoarticulo', function (e) {
            if (e.which !== 112 && e.key !== 'F1') {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.tm-articulo-campo').find('.consultaarticulo').trigger('click');
        });

        $('#form-general').on('submit', function (e) {
            if (!esManual()) {
                if (!$.trim($('#ordenestrabajo').val())) {
                    e.preventDefault();
                    alert('Indique los números de orden de trabajo.');
                    $('#ordenestrabajo').trigger('focus');
                    return false;
                }
                if (!$('#origen').val()) {
                    e.preventDefault();
                    alert('Elija el origen de los datos.');
                    $('#origen').trigger('focus');
                    return false;
                }
                return true;
            }

            var articuloId = parseInt($('#eti-articulo-id').val(), 10) || 0;
            if (!articuloId) {
                e.preventDefault();
                alert('Elija el artículo.');
                $('#eti-codigoarticulo').trigger('focus');
                return false;
            }
            var combId = parseInt($('#eti-combinacion-id').val(), 10) || 0;
            if (!combId) {
                e.preventDefault();
                alert('Elija la combinación.');
                $('#eti-codigocombinacion').trigger('focus');
                return false;
            }
            if (tipoPideTalle() && !(parseInt($('#eti-talle-id').val(), 10) || 0)) {
                e.preventDefault();
                alert('Elija el talle.');
                $('#eti-codigotalle').trigger('focus');
                return false;
            }
            var cantidad = parseInt($('#cantidad').val(), 10) || 0;
            if (cantidad < 1 || cantidad > 500) {
                e.preventDefault();
                alert('La cantidad debe estar entre 1 y 500.');
                $('#cantidad').trigger('focus');
                return false;
            }
            return true;
        });
    });
})(window, window.jQuery);
