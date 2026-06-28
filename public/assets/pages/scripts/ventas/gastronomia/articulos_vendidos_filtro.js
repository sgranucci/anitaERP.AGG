(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;

    function $valorPrincipal() {
        return $('#filtro_valor');
    }

    function $valorPanel() {
        return $('#filtro_valor_panel');
    }

    function $empresa() {
        return $('#empresa_id');
    }

    function $fechaJornada() {
        return $('#fecha_jornada');
    }

    function parseOperadores() {
        var $sel = $('#filtro_operador');
        if (!$sel.length) {
            return;
        }
        try {
            operadoresPorCampo = JSON.parse($sel.attr('data-operadores') || '{}');
        } catch (e) {
            operadoresPorCampo = {};
        }
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        if (modo === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
        }

        if ($('#filtro_operador').val() === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa;
        var $op = $('#filtro_operador');

        if (modo === MODO_CAMPO) {
            mapa = operadoresPorCampo[$('#filtro_campo').val()] || (LF ? LF.operadoresModoTodos() : {});
        } else {
            mapa = LF ? LF.operadoresModoTodos() : {};
        }

        if (LF && LF.rellenarSelectOperadores) {
            LF.rellenarSelectOperadores($op, mapa, valorActual);
        }
        actualizarVisibilidad();
    }

    function sincronizarValorAntesDeEnviar() {
        var $panel = $('#panel-filtros-articulos-vendidos');
        var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
        if (panelAbierto) {
            $valorPrincipal().val($valorPanel().val());
        } else {
            $valorPanel().val($valorPrincipal().val());
        }
    }

    function puedeEnviarConsulta() {
        var empresaVal = ($empresa().val() || '').trim();
        var fechaVal = ($fechaJornada().val() || '').trim();
        return empresaVal !== '' && fechaVal !== '';
    }

    function enviarConsultaSiCompleto(origenAuto) {
        if (!puedeEnviarConsulta()) {
            return;
        }
        if (origenAuto && window.ArticulosVendidosProcesando && window.ArticulosVendidosProcesando.mostrar) {
            window.ArticulosVendidosProcesando.mostrar();
        }
        $('#form-filtros-articulos-vendidos').trigger('submit');
    }

    $(function () {
        if (!$('#form-filtros-articulos-vendidos').length) {
            return;
        }

        parseOperadores();

        if (LF && LF.sincronizarValorPrincipal) {
            LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');
        }

        $('#jornada_historial').on('change', function () {
            var v = ($(this).val() || '').trim();
            if (v !== '') {
                $fechaJornada().val(v);
            }
        });

        $empresa().on('change', function () {
            enviarConsultaSiCompleto(true);
        });

        $fechaJornada().on('change', function () {
            enviarConsultaSiCompleto(true);
        });

        $('#form-filtros-articulos-vendidos').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-articulos-vendidos').on('submit.listadoFiltrosSync', function (event) {
            sincronizarValorAntesDeEnviar();

            if ($(this).find('input[name="consultar"]').length === 0) {
                $(this).append('<input type="hidden" name="consultar" value="1">');
            }

            if (!puedeEnviarConsulta()) {
                event.preventDefault();
                if (window.toastr) {
                    toastr.warning('Seleccione empresa y jornada para consultar.');
                } else {
                    window.alert('Seleccione empresa y jornada para consultar.');
                }
                if (($empresa().val() || '').trim() === '') {
                    $empresa().focus();
                } else {
                    $fechaJornada().focus();
                }
            }
        });

        if (LF && LF.initSubmitBusquedaRapida) {
            LF.initSubmitBusquedaRapida($('#form-filtros-articulos-vendidos'), {
                selectorPanel: '#panel-filtros-articulos-vendidos',
            });
        }

        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });
        $('#filtro_operador').on('change', actualizarVisibilidad);

        actualizarOperadores(true);
    });
}(jQuery));
