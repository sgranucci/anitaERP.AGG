(function ($) {
    'use strict';

    var MODO_CAMPO = 'campo';
    var operadoresPorCampo = {};
    var LF = window.ListadoFiltros;
    var overlay = document.getElementById('tipotransaccion-compra-overlay');
    var exportAbort = null;

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
        return $('#filtro_campo option:selected').data('type') || 'texto';
    }

    function actualizarVisibilidad() {
        if ($('#filtro_modo').val() === MODO_CAMPO) {
            $('.filtro-campo-wrap').show();
        } else {
            $('.filtro-campo-wrap').hide();
        }
        if ($('#filtro_operador').val() === 'vacio') {
            $valorPrincipal().val('');
            $valorPanel().val('');
        }
        var placeholder = tipoCampoActivo() === 'entero'
            ? 'Número'
            : 'Texto. «no retiene» filtra las marcas.';
        $valorPrincipal().attr('placeholder', placeholder);
        $valorPanel().attr('placeholder', placeholder);
    }

    function actualizarOperadores(mantenerSeleccion) {
        var modo = $('#filtro_modo').val();
        var valorActual = mantenerSeleccion ? $('#filtro_operador').val() : null;
        var mapa = modo === MODO_CAMPO
            ? (operadoresPorCampo[$('#filtro_campo').val()] || LF.operadoresModoTodos())
            : LF.operadoresModoTodos();
        LF.rellenarSelectOperadores($('#filtro_operador'), mapa, valorActual);
        actualizarVisibilidad();
    }

    function mostrarOverlay(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('tipotransaccion-compra-titulo');
        var s = document.getElementById('tipotransaccion-compra-subtitulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivo(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return fallback;
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function descargar(href) {
        var lower = String(href).toLowerCase();
        var formato = 'archivo';
        var fallback = 'tipos_comprobante_compras';
        if (lower.indexOf('/excel') !== -1) {
            formato = 'Excel';
            fallback += '.xlsx';
        } else if (lower.indexOf('/pdf') !== -1) {
            formato = 'PDF';
            fallback += '.pdf';
        } else if (lower.indexOf('/csv') !== -1) {
            formato = 'CSV';
            fallback += '.csv';
        }
        mostrarOverlay('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');
        if (exportAbort) {
            try { exportAbort.abort(); } catch (e) {}
        }
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        exportAbort = controller;
        fetch(href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller ? controller.signal : undefined
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('No se pudo exportar');
            }
            return res.blob().then(function (blob) {
                return { blob: blob, filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback) };
            });
        }).then(function (pack) {
            var url = window.URL.createObjectURL(pack.blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = pack.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1500);
            ocultarOverlay();
        }).catch(function (err) {
            ocultarOverlay();
            if (err && err.name === 'AbortError') {
                return;
            }
            alert((err && err.message) ? err.message : 'No se pudo exportar');
        });
    }

    $(function () {
        if (typeof window.initListadoQbeGrupos === 'function') {
            window.initListadoQbeGrupos();
        }
        if (typeof window.initListadoOrden === 'function') {
            window.initListadoOrden();
        }
        if (!$('#form-filtros-tipotransaccion-compra').length || !LF) {
            return;
        }

        parseOperadores();
        LF.sincronizarValorPrincipal('#filtro_valor', '#filtro_valor_panel');

        $('#form-filtros-tipotransaccion-compra').on('click', '[data-aplicar-filtros-panel]', function () {
            $valorPrincipal().val($valorPanel().val());
        });
        $('#form-filtros-tipotransaccion-compra').on('submit.listadoFiltrosSync', function () {
            var $panel = $('#panel-filtros-tipotransaccion-compra');
            var panelAbierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (panelAbierto) {
                $valorPrincipal().val($valorPanel().val());
            } else {
                $valorPanel().val($valorPrincipal().val());
            }
        });
        LF.initSubmitBusquedaRapida($('#form-filtros-tipotransaccion-compra'), {
            selectorPanel: '#panel-filtros-tipotransaccion-compra'
        });
        $('#filtro_modo, #filtro_campo').on('change', function () {
            actualizarOperadores(false);
        });
        $('#filtro_operador').on('change', actualizarVisibilidad);
        actualizarOperadores(true);

        $(document).on('click', 'a[href*="lista-tipotransaccion-compra"]', function (event) {
            event.preventDefault();
            descargar(this.href);
        });
        window.addEventListener('pageshow', ocultarOverlay);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && exportAbort) {
                try { exportAbort.abort(); } catch (e) {}
                ocultarOverlay();
            }
        });
    });
})(jQuery);
