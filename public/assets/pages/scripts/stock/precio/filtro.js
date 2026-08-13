(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var CAMPO_LISTA = 'listaprecio';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;
    var submitListaPendiente = null;

    function $valorPrincipal() {
        return $('#filtro_valor');
    }

    function $valorPanel() {
        return $('#filtro_valor_panel');
    }

    function $selectLista() {
        return $('#filtro_valor_listaprecio');
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

    function campoActivo() {
        if ($('#filtro_modo').val() !== MODO_CAMPO) {
            return '';
        }

        return String($('#filtro_campo').val() || '');
    }

    function tipoCampoActivo() {
        var modo = $('#filtro_modo').val();
        if (modo !== MODO_CAMPO) {
            return 'texto';
        }
        var $opt = $('#filtro_campo option:selected');

        return String($opt.attr('data-type') || 'texto');
    }

    function esCampoListaPrecio() {
        return campoActivo() === CAMPO_LISTA;
    }

    function setPlaceholderValor(texto) {
        $valorPrincipal().attr('type', 'text').attr('placeholder', texto);
        $valorPanel().attr('type', 'text').attr('placeholder', texto);
    }

    function sincronizarValorListaAntesDeEnviar() {
        if (!esCampoListaPrecio()) {
            return;
        }

        var listaId = String($selectLista().val() || '');
        $valorPrincipal().val(listaId);
        $valorPanel().val('');
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        var operador = $('#filtro_operador').val();
        var tipo = tipoCampoActivo();
        var esLista = esCampoListaPrecio();
        var esFecha = tipo === 'fecha' && !esLista;

        if (modo === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
        }

        var esVacio = operador === 'vacio';

        if (esVacio && !esLista) {
            $valorPrincipal().val('');
            $valorPanel().val('');
            $selectLista().val('');
            $('.filtro-valor-hasta-wrap').hide();
        } else if (esLista) {
            $('.filtro-listaprecio-wrap').show();
            $('.filtro-valor-texto-wrap').hide();
            $('.filtro-valor-hasta-wrap').hide();
            $selectLista().prop('disabled', false);
            $valorPanel().prop('disabled', true);
            setPlaceholderValor('Seleccione la lista en el panel');
        } else {
            $('.filtro-listaprecio-wrap').hide();
            $('.filtro-valor-texto-wrap').show();
            $selectLista().prop('disabled', true);
            $valorPanel().prop('disabled', false);

            if (esFecha && operador === 'entre') {
                $('.filtro-valor-hasta-wrap').show();
            } else {
                $('.filtro-valor-hasta-wrap').hide();
            }

            if (esFecha) {
                setPlaceholderValor('dd/mm/aaaa');
            } else if (tipo === 'entero') {
                setPlaceholderValor('Número entero');
            } else if (tipo === 'decimal') {
                setPlaceholderValor('Importe (ej. 1500 o 1500,50)');
            } else {
                setPlaceholderValor('Texto (tolera errores de tipeo)');
            }
        }
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa;
        var $op = $('#filtro_operador');

        if (modo === MODO_CAMPO) {
            var campo = $('#filtro_campo').val();
            mapa = operadoresPorCampo[campo] || LF.operadoresModoTodos();
        } else {
            mapa = LF.operadoresModoTodos();
        }

        LF.rellenarSelectOperadores($op, mapa, valorActual);
        actualizarVisibilidad();
    }

    function enviarFiltroListaToolbar() {
        var $form = $('#form-filtros-precio');
        if (!$form.length) {
            return;
        }
        if (submitListaPendiente) {
            clearTimeout(submitListaPendiente);
        }
        submitListaPendiente = setTimeout(function () {
            submitListaPendiente = null;
            $form.trigger('submit');
        }, 50);
    }

    $(function () {
        if (!$('#form-filtros-precio').length) {
            return;
        }

        if (typeof activa_eventos_consultalistaprecio === 'function') {
            activa_eventos_consultalistaprecio();
        }

        window.onListaprecioSeleccionado = function () {
            enviarFiltroListaToolbar();
        };

        parseOperadores();

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            if (esCampoListaPrecio()) {
                sincronizarValorListaAntesDeEnviar();
                return;
            }

            var $panel = $('#panel-filtros-precio');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-precio').on('click', '[data-aplicar-filtros-panel]', function () {
            sincronizarValorAntesDeEnviar();
        });

        $('#form-filtros-precio').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        $selectLista().on('change', function () {
            if (esCampoListaPrecio()) {
                sincronizarValorListaAntesDeEnviar();
            }
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-precio'), {
            selectorPanel: '#panel-filtros-precio'
        });

        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        $('#filtro_operador').on('change', function () {
            if ($(this).val() === 'vacio') {
                $valorPrincipal().val('');
                $valorPanel().val('');
                $selectLista().val('');
            }
            actualizarVisibilidad();
        });

        actualizarOperadores(true);
    });
})(jQuery);
