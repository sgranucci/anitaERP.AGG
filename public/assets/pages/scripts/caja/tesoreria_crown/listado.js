(function ($) {
    'use strict';

    var formularios = [
        {
            form: '#form-filtros-tesoreria-crown-valor',
            panel: '#panel-filtros-tesoreria-crown-valor',
            principal: '#filtro_valor',
            panelValor: '#filtro_valor_panel',
            rapida: '#filtro_busqueda_rapida'
        },
        {
            form: '#form-filtros-tesoreria-crown-deposito',
            panel: '#panel-filtros-tesoreria-crown-deposito',
            principal: '#filtro_valor',
            panelValor: '#filtro_valor_panel',
            rapida: '#filtro_busqueda_rapida_deposito'
        },
        {
            form: '#form-filtros-tesoreria-crown-cotizacion',
            panel: '#panel-filtros-tesoreria-crown-cotizacion',
            principal: '#filtro_valor',
            panelValor: '#filtro_valor_panel',
            rapida: '#filtro_busqueda_rapida'
        },
        {
            form: '#form-filtros-tesoreria-crown-jornada',
            panel: '#panel-filtros-tesoreria-crown-jornada',
            principal: '#filtro_valor',
            panelValor: '#filtro_valor_panel',
            rapida: '#filtro_busqueda_rapida_jornada'
        }
    ];

    function enganchar(cfg) {
        var $form = $(cfg.form);
        if (!$form.length || !window.ListadoFiltros) {
            return;
        }

        var $principal = $(cfg.principal);
        var $panelValor = $(cfg.panelValor);

        window.ListadoFiltros.sincronizarValorPrincipal(cfg.principal, cfg.panelValor);

        function sincronizar() {
            var $panel = $(cfg.panel);
            var abierto = $panel.hasClass('show') || $panel.hasClass('in');
            if (abierto) {
                $principal.val($panelValor.val());
            } else {
                $panelValor.val($principal.val());
            }
        }

        $form.on('click', '[data-aplicar-filtros-panel]', function () {
            $principal.val($panelValor.val());
        });
        $form.on('submit.listadoFiltrosSync', function () {
            sincronizar();
        });

        window.ListadoFiltros.initSubmitBusquedaRapida($form, {
            selectorPanel: cfg.panel,
            hiddenRapida: cfg.rapida
        });
    }

    $(function () {
        formularios.forEach(enganchar);
    });
})(jQuery);

(function () {
    var overlay = document.getElementById('tesoreria-crown-overlay');
    if (!overlay) {
        return;
    }

    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        var t = document.getElementById('tesoreria-crown-overlay-titulo');
        var s = document.getElementById('tesoreria-crown-overlay-subtitulo');
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

    function ocultar() {
        if (exportAbort) {
            exportAbort.abort();
            exportAbort = null;
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

    function descargar(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    document.querySelectorAll('.js-export-tesoreria-crown a').forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            e.preventDefault();
            var href = enlace.href;
            var formato = 'archivo';
            var fallback = 'listado_tesoreria';
            var lower = href.toLowerCase();
            if (lower.indexOf('/listar/pdf') !== -1) {
                formato = 'PDF';
                fallback += '.pdf';
            } else if (lower.indexOf('/listar/excel') !== -1) {
                formato = 'Excel';
                fallback += '.xlsx';
            } else if (lower.indexOf('/listar/csv') !== -1) {
                formato = 'CSV';
                fallback += '.csv';
            }
            mostrar('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');
            if (exportAbort) {
                exportAbort.abort();
            }
            exportAbort = new AbortController();
            fetch(href, {
                credentials: 'same-origin',
                signal: exportAbort.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('No se pudo exportar (' + res.status + ')');
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback)
                    };
                });
            }).then(function (pack) {
                descargar(pack.blob, pack.filename);
                ocultar();
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    ocultar();
                    return;
                }
                ocultar();
                window.alert((err && err.message) || 'No se pudo exportar');
            });
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
})();
