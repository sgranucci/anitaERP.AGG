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

    function tipoCampoActivo() {
        var modo = $('#filtro_modo').val();
        if (modo !== MODO_CAMPO) {
            return 'texto';
        }
        var $opt = $('#filtro_campo option:selected');
        return $opt.data('type') || 'texto';
    }

    function setPlaceholderValor(texto) {
        $valorPrincipal().attr('placeholder', texto);
        $valorPanel().attr('placeholder', texto);
    }

    function actualizarVisibilidad() {
        var modo = $('#filtro_modo').val();
        var operador = $('#filtro_operador').val();
        var tipo = tipoCampoActivo();

        if (modo === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
        }

        var esFecha = tipo === 'fecha';
        var esVacio = operador === 'vacio';

        if (esVacio) {
            $valorPrincipal().val('');
            $valorPanel().val('');
            $('.filtro-valor-hasta-wrap').hide();
        } else if (esFecha && operador === 'entre') {
            $('.filtro-valor-hasta-wrap').show();
        } else {
            $('.filtro-valor-hasta-wrap').hide();
        }

        if (esFecha) {
            setPlaceholderValor('dd/mm/aaaa');
        } else if (tipo === 'entero') {
            setPlaceholderValor('Número entero');
        } else if (tipo === 'estado') {
            setPlaceholderValor('Pendiente, Facturado o Suspendido');
        } else {
            setPlaceholderValor('Texto (tolera errores de tipeo desde 6 caracteres)');
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

    $(function () {
        if (!$('#form-filtros-remito').length) {
            return;
        }

        parseOperadores();

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-remito');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-remito').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-remito').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-remito'), {
            selectorPanel: '#panel-filtros-remito'
        });

        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });

        $('#filtro_operador').on('change', function () {
            if ($(this).val() === 'vacio') {
                $valorPrincipal().val('');
                $valorPanel().val('');
            }
            actualizarVisibilidad();
        });

        actualizarOperadores(true);

        $('#form-traer-remito').on('submit', function (e) {
            var $num = $('#numero_remito');
            var valor = $.trim($num.val() || '');
            $num.val(valor);
            if (!valor) {
                e.preventDefault();
                $num.focus();
                if (window.toastr) {
                    toastr.warning('Ingrese el número o código del remito.', '', { timeOut: 5000, closeButton: true });
                } else {
                    alert('Ingrese el número o código del remito.');
                }
                return false;
            }
        });

        // Fecha entrega: al elegir "desde", si "hasta" está vacío → hoy
        $('#fecha_entrega_desde').on('change', function () {
            var $hasta = $('#fecha_entrega_hasta');
            if (!$hasta.val()) {
                var hoy = $(this).data('fecha-hoy') || new Date().toISOString().slice(0, 10);
                $hasta.val(hoy);
            }
        });
    });
})(jQuery);
