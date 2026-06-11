(function () {
    'use strict';

    var cfg = window.CIERRES_TURNO_ESTACIONAMIENTO || {};
    var apiUrl = cfg.urlApiComprobantes || '';
    var modalContext = { tipo: '', id: 0, page: 1 };

    if (!apiUrl || typeof window.EstacionamientoTotalesTurnoRender === 'undefined') {
        return;
    }

    if (cfg.urlFacturaVerBase) {
        window.ESTACIONAMIENTO_FACTURA_VER_BASE = cfg.urlFacturaVerBase;
    }

    function esc(s) {
        if (s == null) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getJson(url) {
        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, status: r.status, data: j };
            });
        });
    }

    function urlComprobantes(page) {
        var solo = document.getElementById('filtro-solo-diferencias-cierre');
        var params = new URLSearchParams({
            tipo: modalContext.tipo,
            id: String(modalContext.id),
            page: String(page),
            solo_diferencias: solo && solo.checked ? '1' : '0',
        });
        return apiUrl + '?' + params.toString();
    }

    function pintarGrilla(grilla) {
        var cont = document.getElementById('grilla-comprobantes-cierre');
        if (!cont || !window.EstacionamientoTotalesTurnoRender) {
            return;
        }

        cont.innerHTML = window.EstacionamientoTotalesTurnoRender.renderGrillaConciliacionHtml(grilla);
        var pag = grilla.paginacion;
        if (pag) {
            var pagHtml = window.EstacionamientoTotalesTurnoRender.renderPaginacionGrillaHtml(pag, 'grilla-comprobantes-cierre');
            cont.querySelectorAll('.est-grilla-paginacion, .est-grilla-paginacion-footer').forEach(function (el) {
                el.innerHTML = pagHtml;
                el.setAttribute('data-grilla-container', 'grilla-comprobantes-cierre');
            });
        }

        cont.querySelectorAll('.js-grilla-pagina').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var p = parseInt(btn.getAttribute('data-page'), 10);
                if (p > 0) {
                    cargarPagina(p);
                }
            });
        });

        cont.querySelectorAll('.js-ver-factura-detalle').forEach(function (lnk) {
            lnk.addEventListener('click', function (e) {
                e.preventDefault();
                var vid = parseInt(lnk.getAttribute('data-venta-id'), 10);
                var base = cfg.urlFacturaVerBase || '';
                if (vid > 0 && base) {
                    var url = base.replace(/\/$/, '') + '/' + vid + '/ver';
                    if (window.ModoConsulta) {
                        url = window.ModoConsulta.url(url);
                    }
                    window.open(url, '_blank', 'noopener');
                }
            });
        });
    }

    function cargarPagina(page) {
        var cont = document.getElementById('grilla-comprobantes-cierre');
        if (cont) {
            cont.innerHTML = '<p class="text-muted p-3 mb-0"><i class="fa fa-spinner fa-spin"></i> Cargando comprobantes…</p>';
        }

        return getJson(urlComprobantes(page)).then(function (res) {
            if (!cont) {
                return;
            }
            if (!res.ok || !res.data.ok) {
                var err = (res.data && res.data.error) || 'No se pudieron cargar los comprobantes.';
                cont.innerHTML = '<div class="alert alert-danger m-2 mb-0 small">' + esc(err) + '</div>';
                return;
            }

            if (res.data.url_factura_ver_base) {
                window.ESTACIONAMIENTO_FACTURA_VER_BASE = res.data.url_factura_ver_base;
                cfg.urlFacturaVerBase = res.data.url_factura_ver_base;
            }

            if (res.data.alcance) {
                var tit = document.getElementById('modal-comprobantes-cierre-titulo');
                var sub = document.getElementById('modal-comprobantes-cierre-subtitulo');
                if (tit && res.data.alcance.titulo) {
                    tit.textContent = res.data.alcance.titulo;
                }
                if (sub && res.data.alcance.subtitulo) {
                    sub.textContent = res.data.alcance.subtitulo;
                }
            }

            modalContext.page = page;
            pintarGrilla(res.data.grilla || {});
        }).catch(function (err) {
            if (cont) {
                cont.innerHTML = '<div class="alert alert-danger m-2 mb-0 small">' + esc(err.message || 'Error de red') + '</div>';
            }
        });
    }

    function abrirModal(tipo, id, referencia) {
        modalContext.tipo = tipo;
        modalContext.id = id;
        modalContext.page = 1;

        var tit = document.getElementById('modal-comprobantes-cierre-titulo');
        if (tit) {
            tit.textContent = 'Comprobantes — ' + (referencia || '');
        }
        var sub = document.getElementById('modal-comprobantes-cierre-subtitulo');
        if (sub) {
            sub.textContent = '';
        }

        var chk = document.getElementById('filtro-solo-diferencias-cierre');
        if (chk) {
            chk.checked = false;
        }

        if (typeof jQuery !== 'undefined') {
            jQuery('#modal-comprobantes-cierre').modal('show');
        }

        cargarPagina(1);
    }

    document.querySelectorAll('.js-ver-comprobantes-cierre').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tipo = btn.getAttribute('data-tipo') || '';
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (!tipo || id <= 0) {
                return;
            }
            abrirModal(tipo, id, btn.getAttribute('data-referencia') || '');
        });
    });

    var filtroDif = document.getElementById('filtro-solo-diferencias-cierre');
    if (filtroDif) {
        filtroDif.addEventListener('change', function () {
            if (modalContext.id > 0) {
                cargarPagina(1);
            }
        });
    }

    function initPaginaVerCierre(verCfg) {
        var tipo = verCfg.tipo || 'cierre';
        var id = parseInt(verCfg.id, 10) || 0;
        var referencia = verCfg.referencia || '';
        if (id <= 0) {
            return;
        }

        modalContext.tipo = tipo;
        modalContext.id = id;

        var cargado = {
            comprobantes: false,
        };

        function alMostrarSolapa(hash) {
            if (hash === '#tab-ver-comprobantes' && !cargado.comprobantes) {
                cargado.comprobantes = true;
                var cont = document.getElementById('grilla-comprobantes-cierre');
                if (cont) {
                    cont.innerHTML = '<p class="text-muted p-3 mb-0"><i class="fa fa-spinner fa-spin"></i> Cargando comprobantes…</p>';
                }
                cargarPagina(1);
            }
        }

        if (typeof jQuery !== 'undefined') {
            jQuery('#cierre-turno-ver-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                alMostrarSolapa(jQuery(e.target).attr('href') || '');
            });
        }

        var subComp = document.getElementById('modal-comprobantes-cierre-subtitulo');
        if (subComp && referencia) {
            subComp.textContent = referencia;
        }
    }

    if (window.CIERRE_TURNO_VER && parseInt(window.CIERRE_TURNO_VER.id, 10) > 0) {
        initPaginaVerCierre(window.CIERRE_TURNO_VER);
    }
})();
