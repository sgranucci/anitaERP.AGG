(function () {
    'use strict';

    var cfg = window.CIERRES_TURNO_GASTRONOMIA || {};
    var apiUrl = cfg.urlApiComprobantes || '';
    var modalContext = { tipo: '', id: 0, page: 1 };

    if (!apiUrl || typeof window.GastronomiaTotalesTurnoRender === 'undefined') {
        return;
    }

    if (cfg.urlFacturaVerBase) {
        window.GASTRONOMIA_FACTURA_VER_BASE = cfg.urlFacturaVerBase;
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
        if (!cont || !window.GastronomiaTotalesTurnoRender) {
            return;
        }

        cont.innerHTML = window.GastronomiaTotalesTurnoRender.renderGrillaConciliacionHtml(grilla);
        var pag = grilla.paginacion;
        if (pag) {
            var pagHtml = window.GastronomiaTotalesTurnoRender.renderPaginacionGrillaHtml(pag, 'grilla-comprobantes-cierre');
            cont.querySelectorAll('.gastro-grilla-paginacion, .gastro-grilla-paginacion-footer').forEach(function (el) {
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
                window.GASTRONOMIA_FACTURA_VER_BASE = res.data.url_factura_ver_base;
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

    function urlAlcanceCierre(tipo, id, page, perPage) {
        var params = new URLSearchParams({
            tipo: String(tipo),
            id: String(id),
        });
        if (page) {
            params.set('page', String(page));
        }
        if (perPage) {
            params.set('per_page', String(perPage));
        }
        return '?' + params.toString();
    }

    function infoPaginacion(pag, etiquetaItem) {
        if (!pag || !pag.total) {
            return 'Sin movimientos.';
        }
        var desde = (pag.page - 1) * pag.per_page + 1;
        var hasta = Math.min(pag.page * pag.per_page, pag.total);
        return (
            'Mostrando ' + desde + '–' + hasta + ' de ' + pag.total + ' ' + etiquetaItem +
            ' · página ' + pag.page + ' de ' + (pag.total_pages || 1)
        );
    }

    function bindPaginacionBotones(containerId, onChange) {
        var cont = document.getElementById(containerId);
        if (!cont) {
            return;
        }
        cont.querySelectorAll('.js-grilla-pagina').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var p = parseInt(btn.getAttribute('data-page'), 10);
                if (p > 0) {
                    onChange(p);
                }
            });
        });
    }

    var canjesPremioCtx = { tipo: '', id: 0, page: 1, perPage: 40 };
    var canjesFidelidadCtx = { tipo: '', id: 0, page: 1, perPage: 40 };
    var ticketsTarjetaCtx = { tipo: '', id: 0, page: 1, perPage: 40 };

    function pintarCanjesPremio(canjes, pag) {
        var tbody = document.getElementById('ct-canjes-premio-body');
        var info = document.getElementById('ct-canjes-premio-info');
        var pagCont = document.getElementById('ct-canjes-premio-paginacion');
        if (!tbody) {
            return;
        }

        if (info) {
            info.textContent = infoPaginacion(pag, 'canje(s) de premio');
        }
        if (pagCont && window.GastronomiaTotalesTurnoRender) {
            pagCont.innerHTML = window.GastronomiaTotalesTurnoRender.renderPaginacionGrillaHtml(
                pag || {}, 'ct-canjes-premio-paginacion'
            );
            bindPaginacionBotones('ct-canjes-premio-paginacion', cargarCanjesPremioPagina);
        }

        if (!canjes || !canjes.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-muted small">' +
                (pag && pag.total > 0 ? 'Página vacía.' : 'Sin canjes de premio en este cierre.') +
                '</td></tr>';
            return;
        }

        tbody.innerHTML = canjes.map(function (c) {
            return (
                '<tr>' +
                '<td>' + esc(c.numerocupon) + '</td>' +
                '<td>' + esc(c.venta_codigo || c.venta_id || '') + '</td>' +
                '<td>' + esc(c.sku) + '</td>' +
                '<td>' + esc(c.articulo || '—') + '</td>' +
                '<td class="text-right">' + (parseFloat(c.cantidad) || 0) + '</td>' +
                '<td class="text-right">' + (c.puntos || 0) + '</td>' +
                '<td>' + esc(c.mozo || '—') + '</td>' +
                '<td>' + esc(c.numerodocumento || '—') + '</td>' +
                '<td>' + esc(c.fechacanje || '—') + '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function cargarCanjesPremioPagina(page) {
        var tbody = document.getElementById('ct-canjes-premio-body');
        var errEl = document.getElementById('ct-canjes-premio-error');
        var tit = document.getElementById('modal-canjes-premio-cierre-titulo');
        var sub = document.getElementById('modal-canjes-premio-cierre-subtitulo');
        if (!tbody || !cfg.urlApiCanjesPremio || canjesPremioCtx.id <= 0) {
            return;
        }

        canjesPremioCtx.page = page;
        tbody.innerHTML = '<tr><td colspan="9" class="text-muted small">Cargando…</td></tr>';
        if (errEl) {
            errEl.classList.add('d-none');
            errEl.textContent = '';
        }

        getJson(cfg.urlApiCanjesPremio + urlAlcanceCierre(
            canjesPremioCtx.tipo, canjesPremioCtx.id, page, canjesPremioCtx.perPage
        )).then(function (res) {
            if (!res.ok || !res.data.ok) {
                tbody.innerHTML = '';
                if (errEl) {
                    errEl.textContent = (res.data && res.data.error) || 'No se pudieron cargar los canjes.';
                    errEl.classList.remove('d-none');
                }
                return;
            }
            if (res.data.alcance) {
                if (tit && res.data.alcance.titulo) {
                    tit.textContent = res.data.alcance.titulo;
                }
                if (sub && res.data.alcance.subtitulo) {
                    sub.textContent = res.data.alcance.subtitulo;
                }
            }
            pintarCanjesPremio(res.data.canjes || [], res.data.paginacion || null);
        }).catch(function (err) {
            tbody.innerHTML = '';
            if (errEl) {
                errEl.textContent = err.message || 'Error de comunicación.';
                errEl.classList.remove('d-none');
            }
        });
    }

    function abrirModalCanjesPremio(tipo, id, referencia) {
        var modal = document.getElementById('modal-canjes-premio-cierre');
        var tit = document.getElementById('modal-canjes-premio-cierre-titulo');
        var sub = document.getElementById('modal-canjes-premio-cierre-subtitulo');
        if (!modal || !cfg.urlApiCanjesPremio) {
            return;
        }
        canjesPremioCtx.tipo = tipo;
        canjesPremioCtx.id = id;
        canjesPremioCtx.page = 1;

        if (tit) {
            tit.textContent = 'Canjes de premios — ' + (referencia || '');
        }
        if (sub) {
            sub.textContent = '';
        }
        if (typeof jQuery !== 'undefined') {
            jQuery('#modal-canjes-premio-cierre').modal('show');
        }
        cargarCanjesPremioPagina(1);
    }

    function pintarCanjesFidelidad(canjes, pag) {
        var tbody = document.getElementById('ct-canjes-fidelidad-body');
        var info = document.getElementById('ct-canjes-fidelidad-info');
        var pagCont = document.getElementById('ct-canjes-fidelidad-paginacion');
        if (!tbody) {
            return;
        }

        if (info) {
            info.textContent = infoPaginacion(pag, 'canje(s) de fidelidad');
        }
        if (pagCont && window.GastronomiaTotalesTurnoRender) {
            pagCont.innerHTML = window.GastronomiaTotalesTurnoRender.renderPaginacionGrillaHtml(
                pag || {}, 'ct-canjes-fidelidad-paginacion'
            );
            bindPaginacionBotones('ct-canjes-fidelidad-paginacion', cargarCanjesFidelidadPagina);
        }

        if (!canjes || !canjes.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-muted small">' +
                (pag && pag.total > 0 ? 'Página vacía.' : 'Sin canjes de fidelidad en este cierre.') +
                '</td></tr>';
            return;
        }

        tbody.innerHTML = canjes.map(function (c) {
            var titular = c.titular || ((c.apellido || '') + ' ' + (c.nombre || '')).trim();
            var categoria = (c.categoria_nombre || '') + (c.categoria_codigo ? ' [' + c.categoria_codigo + ']' : '');
            return (
                '<tr>' +
                '<td>' + esc(c.tarjeta) + '</td>' +
                '<td class="small">' + esc(c.trackdata) + '</td>' +
                '<td>' + esc(c.documento || '—') + '</td>' +
                '<td>' + esc(titular || '—') + '</td>' +
                '<td>' + esc(categoria || '—') + '</td>' +
                '<td>' + esc(c.sku) + '</td>' +
                '<td>' + esc(c.articulo || '—') + '</td>' +
                '<td>' + esc(c.venta_codigo || c.venta_id || '') + '</td>' +
                '<td>' + esc(c.fechacanje || '—') + '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function cargarCanjesFidelidadPagina(page) {
        var tbody = document.getElementById('ct-canjes-fidelidad-body');
        var errEl = document.getElementById('ct-canjes-fidelidad-error');
        var tit = document.getElementById('modal-canjes-fidelidad-cierre-titulo');
        var sub = document.getElementById('modal-canjes-fidelidad-cierre-subtitulo');
        if (!tbody || !cfg.urlApiCanjesFidelidad || canjesFidelidadCtx.id <= 0) {
            return;
        }

        canjesFidelidadCtx.page = page;
        tbody.innerHTML = '<tr><td colspan="9" class="text-muted small">Cargando…</td></tr>';
        if (errEl) {
            errEl.classList.add('d-none');
            errEl.textContent = '';
        }

        getJson(cfg.urlApiCanjesFidelidad + urlAlcanceCierre(
            canjesFidelidadCtx.tipo, canjesFidelidadCtx.id, page, canjesFidelidadCtx.perPage
        )).then(function (res) {
            if (!res.ok || !res.data.ok) {
                tbody.innerHTML = '';
                if (errEl) {
                    errEl.textContent = (res.data && res.data.error) || 'No se pudieron cargar los canjes de fidelidad.';
                    errEl.classList.remove('d-none');
                }
                return;
            }
            if (res.data.alcance) {
                if (tit && res.data.alcance.titulo) {
                    tit.textContent = res.data.alcance.titulo;
                }
                if (sub && res.data.alcance.subtitulo) {
                    sub.textContent = res.data.alcance.subtitulo;
                }
            }
            pintarCanjesFidelidad(res.data.canjes || [], res.data.paginacion || null);
        }).catch(function (err) {
            tbody.innerHTML = '';
            if (errEl) {
                errEl.textContent = err.message || 'Error de comunicación.';
                errEl.classList.remove('d-none');
            }
        });
    }

    function abrirModalCanjesFidelidad(tipo, id, referencia) {
        var modal = document.getElementById('modal-canjes-fidelidad-cierre');
        var tit = document.getElementById('modal-canjes-fidelidad-cierre-titulo');
        var sub = document.getElementById('modal-canjes-fidelidad-cierre-subtitulo');
        if (!modal || !cfg.urlApiCanjesFidelidad) {
            return;
        }
        canjesFidelidadCtx.tipo = tipo;
        canjesFidelidadCtx.id = id;
        canjesFidelidadCtx.page = 1;

        if (tit) {
            tit.textContent = 'Canjes de fidelidad — ' + (referencia || '');
        }
        if (sub) {
            sub.textContent = '';
        }
        if (typeof jQuery !== 'undefined') {
            jQuery('#modal-canjes-fidelidad-cierre').modal('show');
        }
        cargarCanjesFidelidadPagina(1);
    }

    function pintarTicketsTarjeta(tickets, pag) {
        var tbody = document.getElementById('ct-tickets-tarjeta-body');
        var info = document.getElementById('ct-tickets-tarjeta-info');
        var pagCont = document.getElementById('ct-tickets-tarjeta-paginacion');
        if (!tbody) {
            return;
        }

        if (info) {
            info.textContent = infoPaginacion(pag, 'ticket(s) tarjeta');
        }
        if (pagCont && window.GastronomiaTotalesTurnoRender) {
            pagCont.innerHTML = window.GastronomiaTotalesTurnoRender.renderPaginacionGrillaHtml(
                pag || {}, 'ct-tickets-tarjeta-paginacion'
            );
            bindPaginacionBotones('ct-tickets-tarjeta-paginacion', cargarTicketsTarjetaPagina);
        }

        if (!tickets || !tickets.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-muted small">' +
                (pag && pag.total > 0 ? 'Página vacía.' : 'Sin tickets tarjeta en este cierre.') +
                '</td></tr>';
            return;
        }

        tbody.innerHTML = tickets.map(function (t) {
            return (
                '<tr>' +
                '<td>' + esc(t.ticket_id) + '</td>' +
                '<td>' + esc(t.numeroticket) + '</td>' +
                '<td>' + esc(t.venta_codigo || t.venta_id || '') + '</td>' +
                '<td>' + esc(t.numerodocumento || '—') + '</td>' +
                '<td class="text-right">' + (parseFloat(t.montoticket) || 0).toFixed(2) + '</td>' +
                '<td>' + esc(t.fecha_emision || '—') + '</td>' +
                '<td>' + esc(t.created_at || '—') + '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function cargarTicketsTarjetaPagina(page) {
        var tbody = document.getElementById('ct-tickets-tarjeta-body');
        var errEl = document.getElementById('ct-tickets-tarjeta-error');
        var tit = document.getElementById('modal-tickets-tarjeta-cierre-titulo');
        var sub = document.getElementById('modal-tickets-tarjeta-cierre-subtitulo');
        if (!tbody || !cfg.urlApiTicketsTarjeta || ticketsTarjetaCtx.id <= 0) {
            return;
        }

        ticketsTarjetaCtx.page = page;
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted small">Cargando…</td></tr>';
        if (errEl) {
            errEl.classList.add('d-none');
            errEl.textContent = '';
        }

        getJson(cfg.urlApiTicketsTarjeta + urlAlcanceCierre(
            ticketsTarjetaCtx.tipo, ticketsTarjetaCtx.id, page, ticketsTarjetaCtx.perPage
        )).then(function (res) {
            if (!res.ok || !res.data.ok) {
                tbody.innerHTML = '';
                if (errEl) {
                    errEl.textContent = (res.data && res.data.error) || 'No se pudieron cargar los tickets.';
                    errEl.classList.remove('d-none');
                }
                return;
            }
            if (res.data.alcance) {
                if (tit && res.data.alcance.titulo) {
                    tit.textContent = res.data.alcance.titulo;
                }
                if (sub && res.data.alcance.subtitulo) {
                    sub.textContent = res.data.alcance.subtitulo;
                }
            }
            pintarTicketsTarjeta(res.data.tickets || [], res.data.paginacion || null);
        }).catch(function (err) {
            tbody.innerHTML = '';
            if (errEl) {
                errEl.textContent = err.message || 'Error de comunicación.';
                errEl.classList.remove('d-none');
            }
        });
    }

    function abrirModalTicketsTarjeta(tipo, id, referencia) {
        var modal = document.getElementById('modal-tickets-tarjeta-cierre');
        var tit = document.getElementById('modal-tickets-tarjeta-cierre-titulo');
        var sub = document.getElementById('modal-tickets-tarjeta-cierre-subtitulo');
        if (!modal || !cfg.urlApiTicketsTarjeta) {
            return;
        }
        ticketsTarjetaCtx.tipo = tipo;
        ticketsTarjetaCtx.id = id;
        ticketsTarjetaCtx.page = 1;

        if (tit) {
            tit.textContent = 'Tickets tarjeta — ' + (referencia || '');
        }
        if (sub) {
            sub.textContent = '';
        }
        if (typeof jQuery !== 'undefined') {
            jQuery('#modal-tickets-tarjeta-cierre').modal('show');
        }
        cargarTicketsTarjetaPagina(1);
    }

    document.querySelectorAll('.js-ver-canjes-premio-cierre').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tipo = btn.getAttribute('data-tipo') || '';
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (!tipo || id <= 0) {
                return;
            }
            abrirModalCanjesPremio(tipo, id, btn.getAttribute('data-referencia') || '');
        });
    });

    document.querySelectorAll('.js-ver-canjes-fidelidad-cierre').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tipo = btn.getAttribute('data-tipo') || '';
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (!tipo || id <= 0) {
                return;
            }
            abrirModalCanjesFidelidad(tipo, id, btn.getAttribute('data-referencia') || '');
        });
    });

    document.querySelectorAll('.js-ver-tickets-tarjeta-cierre').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tipo = btn.getAttribute('data-tipo') || '';
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (!tipo || id <= 0) {
                return;
            }
            abrirModalTicketsTarjeta(tipo, id, btn.getAttribute('data-referencia') || '');
        });
    });

    function initPaginaVerCierre(verCfg) {
        var tipo = verCfg.tipo || 'cierre';
        var id = parseInt(verCfg.id, 10) || 0;
        var referencia = verCfg.referencia || '';
        if (id <= 0) {
            return;
        }

        modalContext.tipo = tipo;
        modalContext.id = id;
        canjesPremioCtx.tipo = tipo;
        canjesPremioCtx.id = id;
        canjesFidelidadCtx.tipo = tipo;
        canjesFidelidadCtx.id = id;
        ticketsTarjetaCtx.tipo = tipo;
        ticketsTarjetaCtx.id = id;

        var cargado = {
            comprobantes: false,
            canjesPremio: false,
            canjesFidelidad: false,
            ticketsTarjeta: false,
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
            if (hash === '#tab-ver-canjes-premio' && !cargado.canjesPremio) {
                cargado.canjesPremio = true;
                cargarCanjesPremioPagina(1);
            }
            if (hash === '#tab-ver-canjes-fidelidad' && !cargado.canjesFidelidad) {
                cargado.canjesFidelidad = true;
                cargarCanjesFidelidadPagina(1);
            }
            if (hash === '#tab-ver-tickets-tarjeta' && !cargado.ticketsTarjeta) {
                cargado.ticketsTarjeta = true;
                cargarTicketsTarjetaPagina(1);
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
