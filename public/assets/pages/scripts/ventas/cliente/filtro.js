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

        var esVacio = operador === 'vacio';

        if (esVacio) {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }

        if (tipo === 'entero') {
            setPlaceholderValor('Número entero');
        } else {
            setPlaceholderValor('Texto (parecido desde 2 letras)');
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
        if (!$('#form-filtros-cliente').length) {
            return;
        }

        parseOperadores();

        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        function sincronizarValorAntesDeEnviar() {
            var $panel = $('#panel-filtros-cliente');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        }

        $('#form-filtros-cliente').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });

        $('#form-filtros-cliente').on('submit.listadoFiltrosSync', function () {
            sincronizarValorAntesDeEnviar();
        });

        LF.initSubmitBusquedaRapida($('#form-filtros-cliente'), {
            selectorPanel: '#panel-filtros-cliente'
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

        var busquedaVivaTimer = null;
        var busquedaVivaSeq = 0;
        var urlListado = $('#form-filtros-cliente').attr('action') || window.location.pathname;

        function panelFiltrosAbierto() {
            var $panel = $('#panel-filtros-cliente');
            return $panel.hasClass('show') || $panel.hasClass('in');
        }

        function longitudMinima(valor) {
            return /^\d+$/.test(valor) ? 1 : 2;
        }

        function paramsBusquedaViva(page) {
            var valor = String($valorPrincipal().val() || '').trim();
            var codigo = String($('#filtro_codigo').val() || '').trim();
            var params = {
                ajax: 1,
                filtro_valor: valor,
                filtro_busqueda_rapida: 1
            };
            if (codigo !== '') {
                params.filtro_codigo = codigo;
            }
            if (page && parseInt(page, 10) > 1) {
                params.page = parseInt(page, 10);
            }
            return params;
        }

        function debeDispararBusquedaViva() {
            var valor = String($valorPrincipal().val() || '').trim();
            var codigo = String($('#filtro_codigo').val() || '').trim();
            if (codigo !== '') {
                return true;
            }
            if (valor === '') {
                return true;
            }
            return valor.length >= longitudMinima(valor);
        }

        function actualizarAvisoFiltros(data) {
            var tiene = !!data.tiene_criterios;
            $valorPrincipal().toggleClass('listado-filtros-input-activo', tiene);
            $('#filtro_codigo').toggleClass(
                'listado-filtros-input-activo',
                String($('#filtro_codigo').val() || '').trim() !== ''
            );
            $('#btn-toggle-filtros-cliente').toggleClass('listado-filtros-toggle-activo', tiene);

            var $aviso = $('.card-tools .listado-filtros-aviso-activos');
            if (typeof data.aviso === 'string') {
                if ($aviso.length) {
                    $aviso.replaceWith(data.aviso);
                } else if (data.aviso !== '') {
                    $('.card-tools [data-busqueda-rapida="1"]').first().after(data.aviso);
                }
            }
        }

        function actualizarNuevoHref(filtrosQuery) {
            var $nuevo = $('.card-tools a').has('.fa-plus-circle');
            if (!$nuevo.length) {
                return;
            }
            var base = String($nuevo.attr('href') || '').split('?')[0];
            var qs = $.param(filtrosQuery || {});
            $nuevo.attr('href', qs ? base + '?' + qs : base);
        }

        function actualizarHistorial(filtrosQuery, page) {
            var params = $.extend({}, filtrosQuery || {});
            if (page && parseInt(page, 10) > 1) {
                params.page = parseInt(page, 10);
            }
            var qs = $.param(params);
            var next = urlListado + (qs ? '?' + qs : '');
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', next);
            }
        }

        function aplicarRespuestaListado(data, page) {
            if (!data || typeof data.html !== 'string') {
                return;
            }
            $('#cliente-listado-filas').html(data.html);
            if (typeof data.paginacion === 'string') {
                var $pag = $('#cliente-listado-paginacion');
                if ($pag.length) {
                    $pag.replaceWith(data.paginacion);
                } else {
                    $('.card.card-info').closest('.row').after(data.paginacion);
                }
            }
            if (typeof data.export === 'string') {
                $('#cliente-export-toolbar').html(data.export);
            }
            actualizarAvisoFiltros(data);
            actualizarNuevoHref(data.filtros_query || {});
            actualizarHistorial(data.filtros_query || {}, page);
        }

        function buscarListadoVivo(page) {
            if (!debeDispararBusquedaViva()) {
                return;
            }
            var seq = ++busquedaVivaSeq;
            var params = paramsBusquedaViva(page);
            $('#tabla-paginada').css('opacity', 0.55);

            $.ajax({
                url: urlListado,
                type: 'GET',
                dataType: 'json',
                data: params
            })
                .done(function (data) {
                    if (seq !== busquedaVivaSeq) {
                        return;
                    }
                    aplicarRespuestaListado(data, page);
                })
                .fail(function () {
                    if (seq !== busquedaVivaSeq) {
                        return;
                    }
                })
                .always(function () {
                    if (seq === busquedaVivaSeq) {
                        $('#tabla-paginada').css('opacity', 1);
                    }
                });
        }

        function programarBusquedaViva() {
            if (panelFiltrosAbierto()) {
                return;
            }
            $valorPanel().val($valorPrincipal().val());
            clearTimeout(busquedaVivaTimer);
            busquedaVivaTimer = setTimeout(function () {
                buscarListadoVivo(1);
            }, 280);
        }

        $valorPrincipal().on('input.clienteListadoVivo', function () {
            programarBusquedaViva();
        });

        $('#filtro_codigo').on('input.clienteListadoVivo', function () {
            programarBusquedaViva();
        });

        $('#filtro_codigo').on('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            clearTimeout(busquedaVivaTimer);
            if (panelFiltrosAbierto()) {
                var $form = $('#form-filtros-cliente');
                if ($form.length && $form[0] && typeof $form[0].requestSubmit === 'function') {
                    $form[0].requestSubmit();
                } else if ($form.length) {
                    $form.trigger('submit');
                }
                return;
            }
            buscarListadoVivo(1);
        });

        $(document).on('click', '#cliente-listado-paginacion a', function (e) {
            var href = $(this).attr('href');
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            var page = 1;
            try {
                var url = new URL(href, window.location.origin);
                page = parseInt(url.searchParams.get('page') || '1', 10) || 1;
            } catch (err) {
                page = 1;
            }
            buscarListadoVivo(page);
        });
    });
})(jQuery);
