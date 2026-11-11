(function ($) {
    'use strict';

    var cfg = window.GastroCrownPos || {};
    var estado = {
        cuentaId: null,
        cuenta: null,
        lineaSeleccionadaId: null,
        lineaDestinoId: null,
        busy: false,
        areas: [],
        planos: [],
        mesasPorId: {},
        cuentasAbiertas: [],
        planoAbierto: null,
        mediosCaja: [],
        mediosPorId: {},
        cuentacajaEfectivo: null,
        cuentacajaEfectivoError: null,
        pollKdsTimer: null,
        mozo: null,
        opc: {
            grupos: [],
            seleccion: {},
            paso: 0,
            contexto: null
        }
    };

    function pintarMozoSesion(mozo) {
        estado.mozo = mozo || null;
        var badge = $('#gc-mozo-badge');
        var btnIn = $('#gc-btn-mozo-login');
        var btnOut = $('#gc-btn-mozo-logout');
        if (!estado.mozo) {
            badge.addClass('d-none').text('');
            btnIn.removeClass('d-none');
            btnOut.addClass('d-none');
            return;
        }
        badge
            .removeClass('d-none')
            .text((estado.mozo.codigo || '') + ' ' + (estado.mozo.nombre || '') + ' · ' + (estado.mozo.aplicacion_label || ''));
        btnIn.addClass('d-none');
        btnOut.removeClass('d-none');
    }

    function exigirMozoSesion() {
        if (estado.mozo && estado.mozo.id) {
            return true;
        }
        toast('Ingrese el mozo (código y clave) para operar.', 'warning');
        $('#gc-modal-mozo').modal('show');
        setTimeout(function () { $('#gc-mozo-codigo').trigger('focus'); }, 200);
        return false;
    }

    function confirmarLoginMozo() {
        var codigo = String($('#gc-mozo-codigo').val() || '').trim();
        var clave = String($('#gc-mozo-clave').val() || '');
        if (!codigo || !clave) {
            toast('Complete código y clave.', 'warning');
            return;
        }
        post(cfg.urls.mozoLogin, { codigo: codigo, clave: clave })
            .done(function (res) {
                if (!res || !res.ok) {
                    toast((res && res.mensaje) || 'No se pudo ingresar', 'error');
                    return;
                }
                pintarMozoSesion(res.mozo);
                $('#gc-modal-mozo').modal('hide');
                $('#gc-form-mozo')[0].reset();
                toast('Mozo en sesión: ' + (res.mozo.nombre || ''), 'success');
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo ingresar', 'error');
            });
    }

    function badgeEstadoKds(l) {
        if (!l || l.es_separador || l.prepara === false) {
            return '';
        }
        var est = l.kds_estado || (l.fired || l.fired_postre ? 'fired' : null);
        if (!est) {
            return '';
        }
        var label = l.kds_estado_label || ({
            fired: 'En KDS',
            preparing: 'Preparando',
            ready: 'Listo',
            delivered: 'Entregado',
            void: 'Anulada'
        }[est] || est);
        var cls = ({
            fired: 'badge-primary',
            preparing: 'badge-warning',
            ready: 'badge-success',
            delivered: 'badge-dark',
            void: 'badge-secondary'
        }[est] || 'badge-dark');
        return ' <span class="badge ' + cls + '" title="Estado en cocina">' + $('<div>').text(label).html() + '</span>';
    }

    function programarPollKds() {
        if (estado.pollKdsTimer) {
            window.clearInterval(estado.pollKdsTimer);
            estado.pollKdsTimer = null;
        }
        if (!estado.cuentaId) {
            return;
        }
        var tieneMarcha = (estado.cuenta && estado.cuenta.lineas || []).some(function (l) {
            return l && (l.fired || l.fired_postre) && l.kds_estado !== 'delivered' && l.kds_estado !== 'void';
        });
        if (!tieneMarcha) {
            return;
        }
        var segundos = Math.max(5, parseInt(cfg.kdsRefrescoSegundos, 10) || 5);
        estado.pollKdsTimer = window.setInterval(function () {
            if (estado.busy || !estado.cuentaId) {
                return;
            }
            recargarCuenta();
        }, segundos * 1000);
    }

    function toast(msg, tipo) {
        if (window.toastr) {
            toastr[tipo || 'info'](msg);
            return;
        }
        window.alert(msg);
    }

    function money(n) {
        return '$ ' + (Number(n) || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function post(url, data) {
        return $.ajax({
            url: url,
            method: 'POST',
            data: data || {},
            headers: { 'X-CSRF-TOKEN': csrf() }
        });
    }

    function urlCuenta(base, id, suffix) {
        return base + '/' + id + (suffix ? '/' + suffix : '');
    }

    function requiereCuenta() {
        if (!estado.cuentaId) {
            toast('Seleccione o abra una mesa/cuenta.', 'warning');
            return false;
        }
        return true;
    }

    /** Normaliza IDs de línea (JSON a veces trae string). */
    function idLineaNum(v) {
        var n = parseInt(v, 10);
        return n > 0 ? n : null;
    }

    function lineaPorId(id) {
        id = idLineaNum(id);
        if (!id || !estado.cuenta) {
            return null;
        }
        return (estado.cuenta.lineas || []).find(function (l) {
            return idLineaNum(l.id) === id;
        }) || null;
    }

    /**
     * Línea activa: clic en fila, tr.seleccionada en DOM, o única marcada.
     * @returns {object|null}
     */
    function resolverLineaActiva() {
        var id = idLineaNum(estado.lineaSeleccionadaId);
        var linea = id ? lineaPorId(id) : null;
        if (linea && !linea.es_separador) {
            return linea;
        }
        var domId = idLineaNum($('#gc-tabla-lineas tr.seleccionada').first().attr('data-linea-id'));
        linea = domId ? lineaPorId(domId) : null;
        if (linea && !linea.es_separador) {
            estado.lineaSeleccionadaId = domId;
            return linea;
        }
        var marcadas = (estado.cuenta && estado.cuenta.lineas || []).filter(function (l) {
            return l.marcado && !l.es_separador;
        });
        if (marcadas.length === 1) {
            estado.lineaSeleccionadaId = idLineaNum(marcadas[0].id);
            return marcadas[0];
        }
        return null;
    }

    function aplicarCuenta(cuenta) {
        estado.cuenta = cuenta || null;
        estado.cuentaId = cuenta ? cuenta.id : null;
        pintarEstado();
        pintarLineas();
        programarPollKds();
        $('.gc-mesa-btn').removeClass('activa');
        if (cuenta) {
            $('.gc-mesa-btn[data-cuenta-id="' + cuenta.id + '"]').addClass('activa');
            if (cuenta.mesa_id) {
                $('.gc-mesa-btn[data-mesa-id="' + cuenta.mesa_id + '"]').addClass('activa');
            }
        }
        pintarPlanos();
        actualizarBotonesCierreCuenta();
        $('#gc-codigo-articulo').trigger('focus');
        $('.gc-pos-topbar').addClass('gc-pulse');
        setTimeout(function () { $('.gc-pos-topbar').removeClass('gc-pulse'); }, 450);
    }

    function pintarEstado() {
        var $el = $('#gc-estado-cuenta');
        if (!estado.cuenta) {
            $el.removeClass('alert-success alert-warning').addClass('alert-secondary')
                .text(cfg.usaMesas
                    ? 'Tocá una mesa libre para abrir su cuenta, o + para una cuenta sin mesa.'
                    : 'Tocá + para abrir una cuenta, o elegí una de la lista.');
            pintarTituloDetalle();
            return;
        }
        var c = estado.cuenta;
        var txt = 'Cuenta #' + c.id;
        if (c.mesa) {
            txt += ' — ' + c.mesa;
        }
        if (c.cliente) {
            txt += ' · Cliente: ' + c.cliente;
        }
        if (c.cubiertos) {
            txt += ' · Cubiertos: ' + c.cubiertos;
        }
        if (c.mozo) {
            txt += ' · Mozo: ' + c.mozo;
        }
        if (c.habitacion) {
            txt += ' · Hab: ' + c.habitacion;
        }
        if (c.tarjeta_gst) {
            txt += ' · GST: ' + c.tarjeta_gst;
        }
        if (c.porcentaje_descuento) {
            txt += ' · Dto ' + c.porcentaje_descuento + '%';
        }
        if (c.observacion) {
            txt += ' · Obs: ' + c.observacion;
        }
        $el.removeClass('alert-secondary alert-warning').addClass('alert-success').text(txt);
        pintarTituloDetalle();
    }

    function pintarTituloDetalle() {
        var $t = $('#gc-detalle-titulo');
        if (!$t.length) {
            return;
        }
        if (!estado.cuenta) {
            $t.text('Detalle');
            return;
        }
        var c = estado.cuenta;
        var txt = 'Cuenta #' + c.id;
        if (c.mesa) {
            txt += ' — ' + c.mesa;
        }
        $t.text(txt);
    }

    function textoCantidad(n) {
        var x = Number(n);
        if (!isFinite(x)) {
            return '';
        }
        if (Math.abs(x - Math.round(x)) < 0.0001) {
            return String(Math.round(x));
        }
        return String(x);
    }

    function sufijoVisible(sku) {
        var pref = String(cfg.skuPrefijo || '').toUpperCase();
        var n = parseInt(cfg.skuDigitos, 10) || 0;
        sku = String(sku || '').toUpperCase();
        var suf = sku;
        if (n > 0 && pref && sku.indexOf(pref) === 0) {
            suf = sku.substring(pref.length);
        }
        // Solo dígitos significativos en pantalla (V00001 → 1); al marchar se rellena.
        if (n > 0 && /^\d+$/.test(suf)) {
            suf = String(parseInt(suf, 10));
            if (suf === 'NaN') {
                suf = sku;
            }
        }
        return suf;
    }

    function pintarTotales() {
        if (!estado.cuenta) {
            $('#gc-subtotal-cuenta').hide().text('');
            $('#gc-total-cuenta').text(money(0));
            return;
        }
        var c = estado.cuenta;
        if (c.descuento && c.descuento > 0) {
            $('#gc-subtotal-cuenta').show().text(
                'Subt. ' + money(c.subtotal) + ' − Dto ' + money(c.descuento)
            );
        } else {
            $('#gc-subtotal-cuenta').hide().text('');
        }
        $('#gc-total-cuenta').text(money(c.total || 0));
    }

    function pintarLineas() {
        var $tb = $('#gc-tabla-lineas tbody');
        var lineas = (estado.cuenta && estado.cuenta.lineas) ? estado.cuenta.lineas : [];
        if (!lineas.length) {
            $tb.html('<tr><td colspan="6" class="text-muted text-center py-3">Sin ítems</td></tr>');
            pintarTotales();
            return;
        }
        var html = '';
        lineas.forEach(function (l) {
            if (l.es_separador) {
                html += '<tr class="gc-separador" data-linea-id="' + l.id + '">'
                    + '<td colspan="6" class="text-center text-muted small py-1">— separador —</td></tr>';
                return;
            }
            var cls = [];
            if (l.marcado) {
                cls.push('marcada');
            }
            if (l.fired) {
                cls.push('fired');
            }
            if (idLineaNum(estado.lineaSeleccionadaId) === idLineaNum(l.id)) {
                cls.push('seleccionada');
            }
            var cantTxt = textoCantidad(l.cantidad);
            var qtyHtml = l.fired
                ? cantTxt
                : '<button type="button" class="gc-qty-btn" data-dir="-1" title="Menos">−</button>'
                    + '<span class="gc-qty-n">' + cantTxt + '</span>'
                    + '<button type="button" class="gc-qty-btn" data-dir="1" title="Más">+</button>';
            var opcPartes = [];
            (l.opcionales_detalle || []).forEach(function (d) {
                var t = (d && (d.descripcion || d.sku)) ? String(d.descripcion || d.sku) : '';
                if (t) {
                    opcPartes.push($('<div>').text(t).html());
                }
            });
            var skuTxt = $('<div>').text(l.sku || '').html();
            var obsTxt = l.observacion ? $('<div>').text(l.observacion).html() : '';
            html += '<tr class="' + cls.join(' ') + '" data-linea-id="' + l.id + '">'
                + '<td class="text-center"><input type="checkbox" class="gc-marca-linea" title="Marcar ítem"'
                + (l.marcado ? ' checked' : '') + '></td>'
                + '<td class="text-nowrap">' + qtyHtml + '</td>'
                + '<td class="text-nowrap">'
                + (skuTxt ? '<span class="gc-linea-sku" title="Código artículo">' + skuTxt + '</span>' : '—')
                + '</td>'
                + '<td><span class="gc-linea-desc">' + $('<div>').text(l.descripcion || '').html() + '</span>'
                + (opcPartes.length ? '<br><small class="text-muted">+ ' + opcPartes.join(' · ') + '</small>' : '')
                + (obsTxt ? '<span class="gc-linea-obs"><i class="fa fa-sticky-note"></i> ' + obsTxt + '</span>' : '')
                + (l.area ? ' <span class="badge badge-info" title="KDS destino">' + $('<div>').text(l.area_codigo || l.area).html() + '</span>' : '')
                + (l.prepara === false ? ' <span class="badge badge-secondary">no prepara</span>' : '')
                + badgeEstadoKds(l)
                + '</td>'
                + '<td class="text-right text-nowrap">' + money(l.importe) + '</td>'
                + '<td class="text-center text-nowrap gc-linea-acciones">'
                + '<button type="button" class="btn btn-xs btn-link gc-obs-linea' + (l.observacion ? ' has-obs' : '') + '"'
                + ' title="Observación para comanda"><i class="fa fa-sticky-note"></i></button>'
                + '<button type="button" class="btn btn-xs btn-link gc-destino-linea" title="Elegir KDS / prepara"><i class="fa fa-utensils"></i></button>'
                + '<button type="button" class="btn btn-xs btn-link text-danger gc-void-linea" title="Anular ítem"><i class="fa fa-times"></i></button>'
                + '</td>'
                + '</tr>';
        });
        $tb.html(html);
        pintarTotales();
    }

    function recargarCuenta() {
        if (!estado.cuentaId) {
            return $.Deferred().resolve().promise();
        }
        return $.getJSON(urlCuenta(cfg.urls.cuentaVer, estado.cuentaId))
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                }
            });
    }

    /* ---------------- Planos de mesas (visor Konva) ---------------- */

    function usaPlanos() {
        return !!(cfg.usaMesas && cfg.planos && cfg.planos.length && window.GastroCrownPlanoViewer);
    }

    function idCanvasPlano(planoId) {
        return 'gc-plano-canvas-' + planoId;
    }

    function destruirVisores() {
        if (!window.GastroCrownPlanoViewer) {
            return;
        }
        estado.planos.forEach(function (p) {
            window.GastroCrownPlanoViewer.destruir(idCanvasPlano(p.id));
        });
    }

    function cargarPlanos() {
        var $panel = $('#gc-panel-mesas');
        if (!estado.planos.length) {
            $panel.html('<div class="text-muted small p-2">Cargando planos…</div>');
        }
        $.getJSON(cfg.urls.planosEstado)
            .done(function (resp) {
                if (!resp || !resp.ok) {
                    $panel.html('<div class="text-danger small p-2">Error al cargar los planos</div>');
                    return;
                }
                destruirVisores();
                estado.planos = resp.planos || [];
                estado.mesasPorId = {};
                (resp.mesas || []).forEach(function (m) {
                    estado.mesasPorId[String(m.id)] = m;
                });
                renderPlanos();
                cargarCuentasAbiertas();
            })
            .fail(function () {
                $panel.html('<div class="text-danger small p-2">No se pudo conectar</div>');
            });
    }

    function mesasDelPlano(plano) {
        return ((plano.layout && plano.layout.nodes) ? plano.layout.nodes : []).map(function (n) {
            return estado.mesasPorId[String(n.mesa_id)] || null;
        });
    }

    function renderPlanos() {
        var $panel = $('#gc-panel-mesas');
        if (!estado.planos.length) {
            $panel.html('<div class="text-muted small p-2">La PC no tiene planos activos asignados.</div>');
            return;
        }
        if (estado.planoAbierto === null) {
            estado.planoAbierto = estado.planos[0].id;
        }

        var leyenda = '<div class="gc-plano-leyenda">'
            + '<span class="gc-lg-libre"><i class="fa fa-square"></i>Libre</span>'
            + '<span class="gc-lg-ocupada"><i class="fa fa-square"></i>Ocupada</span>'
            + '<span class="gc-lg-activa"><i class="fa fa-square"></i>En pantalla</span>'
            + '</div>';

        var html = '<div class="gc-planos-accordion" id="gc-planos-accordion">';
        estado.planos.forEach(function (p) {
            var abierto = String(estado.planoAbierto) === String(p.id);
            var mesas = mesasDelPlano(p);
            var total = mesas.length;
            var ocupadas = mesas.filter(function (m) { return m && m.ocupada; }).length;

            html += '<div class="card">'
                + '<div class="card-header gc-plano-header">'
                + '<button type="button" class="gc-plano-toggle" data-toggle="collapse"'
                + ' data-target="#gc-plano-collapse-' + p.id + '" data-plano-id="' + p.id + '"'
                + ' aria-expanded="' + (abierto ? 'true' : 'false') + '">'
                + '<span><i class="fa fa-chevron-right gc-plano-chevron"></i> '
                + $('<div>').text(p.nombre || ('Plano ' + p.id)).html() + '</span>'
                + '<span class="gc-plano-badges">'
                + '<span class="badge badge-danger" title="Ocupadas">' + ocupadas + '</span>'
                + '<span class="badge badge-success" title="Libres">' + (total - ocupadas) + '</span>'
                + '</span>'
                + '</button>'
                + '</div>'
                + '<div class="collapse' + (abierto ? ' show' : '') + '" id="gc-plano-collapse-' + p.id + '"'
                + ' data-plano-id="' + p.id + '" data-parent="#gc-planos-accordion">'
                + (total
                    ? '<div class="gc-plano-canvas" id="' + idCanvasPlano(p.id) + '"></div>' + leyenda
                    : '<div class="gc-plano-vacio">Este plano todavía no tiene mesas ubicadas.</div>')
                + '</div>'
                + '</div>';
        });
        html += '</div>';
        $panel.html(html);

        estado.planos.forEach(function (p) {
            if (String(estado.planoAbierto) === String(p.id)) {
                montarVisor(p);
            }
        });
    }

    function planoPorId(planoId) {
        var encontrado = null;
        estado.planos.forEach(function (p) {
            if (String(p.id) === String(planoId)) {
                encontrado = p;
            }
        });
        return encontrado;
    }

    function montarVisor(plano) {
        if (!window.GastroCrownPlanoViewer || !plano) {
            return;
        }
        var contenedor = idCanvasPlano(plano.id);
        if (!document.getElementById(contenedor)) {
            return;
        }
        window.GastroCrownPlanoViewer.montar(contenedor, plano, {
            onMesaClick: seleccionarMesaDePlano
        });
        pintarPlanos();
    }

    function pintarPlanos() {
        if (!window.GastroCrownPlanoViewer || !estado.planos.length) {
            return;
        }
        var mesaActiva = (estado.cuenta && estado.cuenta.mesa_id) ? estado.cuenta.mesa_id : null;
        estado.planos.forEach(function (p) {
            var contenedor = idCanvasPlano(p.id);
            if (window.GastroCrownPlanoViewer.existe(contenedor)) {
                window.GastroCrownPlanoViewer.pintar(contenedor, estado.mesasPorId, mesaActiva);
            }
        });
    }

    function seleccionarMesaDePlano(mesaId) {
        var mesa = estado.mesasPorId[String(mesaId)];
        if (mesa && mesa.cuenta_abierta_id) {
            cargarCuentaPorId(mesa.cuenta_abierta_id);
            return;
        }
        abrirMesa(mesaId);
    }

    function cargarCuentaPorId(cuentaId) {
        estado.busy = true;
        $.getJSON(urlCuenta(cfg.urls.cuentaVer, cuentaId))
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                }
            })
            .fail(function () { toast('No se pudo cargar la cuenta', 'error'); })
            .always(function () { estado.busy = false; });
    }

    function cargarMesasOCuentas() {
        if (usaPlanos()) {
            cargarPlanos();
            return;
        }
        var $panel = $('#gc-panel-mesas');
        $panel.html('<div class="text-muted small p-2">Cargando…</div>');
        var url = cfg.usaMesas ? cfg.urls.mesas : cfg.urls.cuentas;
        $.getJSON(url)
            .done(function (resp) {
                if (!resp.ok) {
                    $panel.html('<div class="text-danger small p-2">Error al cargar</div>');
                    return;
                }
                if (cfg.usaMesas) {
                    renderMesas(resp.mesas || []);
                } else {
                    renderCuentas(resp.cuentas || []);
                }
                cargarCuentasAbiertas();
            })
            .fail(function () {
                $panel.html('<div class="text-danger small p-2">No se pudo conectar</div>');
            });
    }

    function renderMesas(mesas) {
        var $panel = $('#gc-panel-mesas');
        if (!mesas.length) {
            $panel.html('<div class="text-muted small p-2">No hay mesas para este filtro.</div>');
            return;
        }
        var html = '';
        mesas.forEach(function (m) {
            var codigo = (m.codigo || '').toString();
            var nombre = (m.nombre || '').toString();
            var corto = codigo || nombre;
            var title = (codigo ? codigo + ' — ' : '') + nombre;
            html += '<button type="button" class="btn btn-sm btn-outline-secondary gc-mesa-btn'
                + (m.ocupada ? ' ocupada' : '')
                + (estado.cuentaId && m.cuenta_abierta_id === estado.cuentaId ? ' activa' : '')
                + '" data-mesa-id="' + m.id + '" data-cuenta-id="' + (m.cuenta_abierta_id || '') + '"'
                + ' title="' + $('<div>').text(title).html() + '">'
                + '<strong>' + $('<div>').text(corto).html() + '</strong>'
                + (m.ocupada
                    ? ' <span class="badge badge-warning">#' + m.cuenta_abierta_id + '</span>'
                    : ' <span class="badge badge-success">libre</span>')
                + '</button>';
        });
        $panel.html(html);
    }

    function etiquetaCuentaAbierta(c) {
        if (c.mesa) {
            return String(c.mesa);
        }
        var obs = (c.observacion || '').toString().trim();
        if (obs) {
            return obs.length > 22 ? obs.slice(0, 22) + '…' : obs;
        }
        return 'Barra';
    }

    function cargarCuentasAbiertas() {
        var $box = $('#gc-panel-cuentas');
        if (!$box.length || !cfg.urls.cuentas) {
            return;
        }
        $.getJSON(cfg.urls.cuentas)
            .done(function (resp) {
                estado.cuentasAbiertas = (resp && resp.ok) ? (resp.cuentas || []) : [];
                pintarCuentasAbiertasLista(estado.cuentasAbiertas);
            })
            .fail(function () {
                $box.html('<div class="text-danger small">No se pudieron cargar las cuentas</div>');
                $('#gc-cuentas-contador').text('0');
                actualizarBotonesCierreCuenta();
            });
    }

    function pintarCuentasAbiertasLista(cuentas) {
        var $box = $('#gc-panel-cuentas');
        if (!$box.length) {
            return;
        }
        var list = cuentas || [];
        $('#gc-cuentas-contador').text(String(list.length));
        if (!list.length) {
            $box.html('<div class="text-muted small">Ninguna abierta</div>');
            actualizarBotonesCierreCuenta();
            return;
        }
        var html = '';
        list.forEach(function (c) {
            var sinMesa = !c.mesa_id && !c.mesa;
            var label = etiquetaCuentaAbierta(c);
            html += '<button type="button" class="btn btn-sm '
                + (sinMesa ? 'btn-outline-primary' : 'btn-outline-secondary')
                + ' gc-mesa-btn'
                + (estado.cuentaId === c.id ? ' activa' : '')
                + '" data-cuenta-id="' + c.id + '"'
                + ' title="Cuenta #' + c.id + (sinMesa ? ' · sin mesa' : '') + '">'
                + '<strong>#' + c.id + '</strong> '
                + (sinMesa ? '<i class="fa fa-glass-martini-alt"></i> ' : '')
                + $('<div>').text(label).html()
                + ' <span class="text-muted">' + money(c.total || 0) + '</span>'
                + '</button>';
        });
        $box.html(html);
        actualizarBotonesCierreCuenta();
    }

    function actualizarBotonesCierreCuenta() {
        var $btn = $('#gc-btn-cerrar-cuenta');
        if (!$btn.length) {
            return;
        }
        if (estado.cuentaId) {
            $btn.removeClass('d-none');
        } else {
            $btn.addClass('d-none');
        }
        var n = (estado.cuentasAbiertas || []).length;
        $('#gc-btn-cerrar-todas').prop('disabled', n === 0);
    }

    function cerrarCuentaActivaSinFacturar() {
        if (!estado.cuentaId) {
            toast('Seleccione una cuenta abierta.', 'warning');
            return;
        }
        var c = (estado.cuentasAbiertas || []).find(function (x) { return x.id === estado.cuentaId; })
            || estado.cuenta;
        var tieneItems = c && c.lineas && c.lineas.some(function (l) { return !l.es_separador; });
        var msg = tieneItems
            ? '¿Cerrar cuenta #' + estado.cuentaId + ' sin facturar? Los consumos no se facturarán.'
            : '¿Cerrar cuenta #' + estado.cuentaId + ' (vacía)?';
        if (!window.confirm(msg)) {
            return;
        }
        post(urlCuenta(cfg.urls.cerrarCuenta || cfg.urls.anular, estado.cuentaId, 'cerrar'))
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo cerrar', 'error');
                    return;
                }
                toast(resp.mensaje || 'Cuenta cerrada', 'success');
                aplicarCuenta(null);
                cargarMesasOCuentas();
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo cerrar', 'error');
            });
    }

    function cerrarTodasCuentasSinFacturar() {
        var n = (estado.cuentasAbiertas || []).length;
        if (!n) {
            toast('No hay cuentas abiertas.', 'warning');
            return;
        }
        if (!window.confirm('¿Cerrar TODAS las cuentas abiertas (' + n + ') sin facturar?')) {
            return;
        }
        if (!cfg.urls.cerrarTodas) {
            toast('Ruta de cierre masivo no configurada.', 'error');
            return;
        }
        post(cfg.urls.cerrarTodas, {})
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo cerrar', 'error');
                    return;
                }
                toast(resp.mensaje || ((resp.cerradas || 0) + ' cuentas cerradas'), 'success');
                aplicarCuenta(null);
                cargarMesasOCuentas();
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo cerrar', 'error');
            });
    }

    function renderCuentas(cuentas) {
        var $panel = $('#gc-panel-mesas');
        if (!cuentas.length) {
            $panel.html('<div class="text-muted small p-2">Sin cuentas en el listado lateral. Use + Barra.</div>');
            return;
        }
        var html = '';
        cuentas.forEach(function (c) {
            html += '<button type="button" class="btn btn-sm btn-outline-secondary gc-mesa-btn'
                + (estado.cuentaId === c.id ? ' activa' : '')
                + '" data-cuenta-id="' + c.id + '">'
                + '<strong>#' + c.id + '</strong> '
                + $('<div>').text(c.mesa || 'Cuenta').html()
                + ' · ' + money(c.total)
                + '</button>';
        });
        $panel.html(html);
    }

    function abrirMesa(mesaId) {
        if (!exigirMozoSesion()) {
            return;
        }
        if (estado.busy) {
            return;
        }
        var cubiertos = 0;
        if (cfg.pideCubiertos) {
            var n = window.prompt('Cubiertos:', '2');
            if (n === null) {
                return;
            }
            cubiertos = parseInt(n, 10) || 0;
        }
        estado.busy = true;
        post(cfg.urls.abrirMesa, {
            mesa_id: mesaId,
            cubiertos: cubiertos,
            mozo_gastronomia_id: estado.mozo ? estado.mozo.id : null
        })
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo abrir', 'error');
                    return;
                }
                aplicarCuenta(resp.cuenta);
                cargarMesasOCuentas();
                var mesaTxt = resp.cuenta && resp.cuenta.mesa ? ' — ' + resp.cuenta.mesa : '';
                toast('Cuenta #' + resp.cuenta.id + mesaTxt, 'success');
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al abrir mesa', 'error');
            })
            .always(function () { estado.busy = false; });
    }

    function abrirCuentaLibre() {
        if (!exigirMozoSesion()) {
            return;
        }
        if (estado.busy) {
            return;
        }
        estado.busy = true;
        post(cfg.urls.abrirCuenta, {
            mozo_gastronomia_id: estado.mozo ? estado.mozo.id : null
        })
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo abrir', 'error');
                    return;
                }
                aplicarCuenta(resp.cuenta);
                toast('Cuenta de barra #' + resp.cuenta.id + ' (sin mesa)', 'success');
                cargarMesasOCuentas();
                $('#gc-codigo-articulo').trigger('focus');
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al abrir cuenta', 'error');
            })
            .always(function () { estado.busy = false; });
    }

    function setDescCarga(texto, sinPrecio) {
        var t = (texto || '').toString().trim();
        $('#gc-desc-articulo').text(t).toggleClass('gc-sin-precio', !!sinPrecio);
        $('#gc-desc-articulo-hidden').val(t);
    }

    function setPrecioCarga(articulo) {
        var $p = $('#gc-precio-articulo');
        $p.removeClass('gc-precio-cero gc-precio-faltante');
        if (!articulo) {
            $p.text('—');
            return;
        }
        if (articulo.tiene_precio_vigente === false) {
            $p.text('Sin precio').addClass('gc-precio-faltante');
            return;
        }
        var n = Number(articulo.precio);
        if (!isFinite(n)) {
            $p.text('—');
            return;
        }
        if (Math.abs(n) < 0.00001) {
            $p.text('Sin cargo').addClass('gc-precio-cero');
            return;
        }
        $p.text('$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    }

    function cargarAreasKds() {
        if (!cfg.urls.areasKds) {
            return;
        }
        $.getJSON(cfg.urls.areasKds)
            .done(function (resp) {
                estado.areas = resp.areas || [];
                var $sel = $('#gc-area-kds');
                $sel.find('option:not(:first)').remove();
                estado.areas.forEach(function (a) {
                    var label = (a.codigo ? a.codigo + ' — ' : '') + (a.nombre || '');
                    $sel.append($('<option>').val(a.id).text(label));
                });
            });
    }

    function agregarLinea(payloadPrevio, opcionales) {
        if (!requiereCuenta() || estado.busy) {
            return;
        }
        var data = payloadPrevio;
        if (!data) {
            var sku = $.trim($('#gc-codigo-articulo').val());
            if (!sku) {
                return;
            }
            var areaId = $('#gc-area-kds').val();
            data = {
                sku: sku,
                cantidad: parseFloat($('#gc-cantidad').val()) || 1,
                prepara: $('#gc-prepara').is(':checked') ? 1 : 0
            };
            if (areaId) {
                data.area_comanda_gastronomia_id = parseInt(areaId, 10);
            }
        }
        var envio = $.extend({}, data);
        if (opcionales && Object.keys(opcionales).length) {
            envio.opcionales = opcionales;
        }
        estado.busy = true;
        post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea'), envio)
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se agregó', 'error');
                    return;
                }
                $('#gc-codigo-articulo').val('');
                setDescCarga('');
                setPrecioCarga(null);
                $('#gc-cantidad').val(1);
                aplicarCuenta(resp.cuenta);
            })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                if (r.requiere_opcionales && (r.grupos || []).length) {
                    abrirModalOpcionales(r.grupos, r.articulo, { tipo: 'agregar', payload: data }, null);
                    return;
                }
                toast(r.mensaje || 'Artículo no encontrado', 'error');
                $('#gc-codigo-articulo').trigger('select');
            })
            .always(function () { estado.busy = false; });
    }

    // ---------------------------------------------------------------- opcionales

    function valorOpcion(op) {
        if (op.formula_hija_id) {
            return 'f:' + op.formula_hija_id;
        }
        return String(op.articulo_id || '');
    }

    function abrirModalOpcionales(grupos, articulo, contexto, preseleccion) {
        estado.opc.grupos = grupos || [];
        estado.opc.seleccion = {};
        estado.opc.paso = 0;
        estado.opc.contexto = contexto || null;

        if (preseleccion) {
            Object.keys(preseleccion).forEach(function (orden) {
                var v = preseleccion[orden];
                if (v !== null && v !== '' && typeof v !== 'object') {
                    estado.opc.seleccion[String(orden)] = String(v);
                }
            });
        }

        var info = '';
        if (articulo) {
            info = [articulo.sku || '', articulo.descripcion || ''].filter(function (t) { return !!t; }).join(' · ');
        }
        $('#gc-opc-info').text(info);
        renderOpcionales();
        $('#gc-modal-opcionales').modal('show');
    }

    function renderOpcionales() {
        var grupos = estado.opc.grupos;
        var total = grupos.length;
        var html = '';
        grupos.forEach(function (g, i) {
            var orden = String(g.orden);
            var elegido = estado.opc.seleccion[orden] || '';
            html += '<div class="gc-opc-grupo' + (i === estado.opc.paso ? ' activo' : '') + '"'
                + ' data-orden="' + $('<div>').text(orden).html() + '" data-paso="' + i + '">'
                + '<div class="gc-opc-grupo-titulo">'
                + '<span class="gc-opc-pill">Paso ' + (i + 1) + '/' + total + '</span>'
                + '<span>Elegí una opción</span>'
                + '<small class="text-muted">· Orden ' + $('<div>').text(orden).html() + '</small>'
                + '</div><div class="gc-opc-grilla">';
            (g.opciones || []).forEach(function (o, idx) {
                var valor = valorOpcion(o);
                html += '<div class="gc-opc-tarjeta' + (valor && valor === elegido ? ' seleccionada' : '') + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-orden="' + $('<div>').text(orden).html() + '"'
                    + ' data-valor="' + $('<div>').text(valor).html() + '">'
                    + (idx < 9 ? '<span class="gc-opc-atajo">' + (idx + 1) + '</span>' : '')
                    + (valor && valor === elegido ? '<i class="fa fa-check gc-opc-check"></i>' : '')
                    + '<div class="gc-opc-sku">' + $('<div>').text(o.sku || '').html() + '</div>'
                    + '<div class="gc-opc-desc">' + $('<div>').text(o.descripcion || '').html() + '</div>'
                    + '</div>';
            });
            html += '</div></div>';
        });
        $('#gc-opc-body').html(html);

        var pasos = '';
        grupos.forEach(function (g, i) {
            var orden = String(g.orden);
            var cls = 'gc-opc-paso';
            if (i === estado.opc.paso) {
                cls += ' actual';
            } else if (estado.opc.seleccion[orden]) {
                cls += ' completado';
            }
            pasos += '<span class="' + cls + '" data-paso="' + i + '">'
                + '<span class="gc-opc-paso-num">' + (i + 1) + '</span> Orden '
                + $('<div>').text(orden).html() + '</span>';
        });
        $('#gc-opc-pasos').html(pasos);

        $('#gc-opc-atras').prop('disabled', estado.opc.paso <= 0);
        $('#gc-opc-confirmar').html(
            estado.opc.paso >= total - 1
                ? 'Aceptar <i class="fa fa-check"></i>'
                : 'Siguiente <i class="fa fa-arrow-right"></i>'
        );
        $('#gc-opc-body .gc-opc-grupo.activo .gc-opc-tarjeta').first().trigger('focus');
    }

    function irPasoOpcionales(paso) {
        var total = estado.opc.grupos.length;
        estado.opc.paso = Math.max(0, Math.min(total - 1, paso));
        renderOpcionales();
    }

    function ordenesFaltantesOpcionales() {
        var faltan = [];
        estado.opc.grupos.forEach(function (g, i) {
            if (!estado.opc.seleccion[String(g.orden)]) {
                faltan.push(i);
            }
        });
        return faltan;
    }

    function avanzarOConfirmarOpcionales() {
        var total = estado.opc.grupos.length;
        var actual = estado.opc.grupos[estado.opc.paso];
        if (actual && !estado.opc.seleccion[String(actual.orden)]) {
            $('#gc-opc-body .gc-opc-grupo.activo').addClass('faltante');
            setTimeout(function () { $('#gc-opc-body .gc-opc-grupo').removeClass('faltante'); }, 500);
            return;
        }
        if (estado.opc.paso < total - 1) {
            irPasoOpcionales(estado.opc.paso + 1);
            return;
        }
        var faltan = ordenesFaltantesOpcionales();
        if (faltan.length) {
            toast('Faltan opcionales por elegir.', 'warning');
            irPasoOpcionales(faltan[0]);
            return;
        }
        confirmarOpcionales();
    }

    function confirmarOpcionales() {
        var ctx = estado.opc.contexto || {};
        var seleccion = $.extend({}, estado.opc.seleccion);
        $('#gc-modal-opcionales').modal('hide');

        if (ctx.tipo === 'agregar') {
            agregarLinea(ctx.payload, seleccion);
            return;
        }
        if (ctx.tipo === 'linea' && ctx.lineaId && estado.cuentaId) {
            post(
                urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + ctx.lineaId + '/opcionales'),
                { opcionales: seleccion }
            )
                .done(function (resp) {
                    if (resp.ok) {
                        aplicarCuenta(resp.cuenta);
                        toast('Opcionales guardados', 'success');
                    }
                })
                .fail(function (xhr) {
                    toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudieron guardar los opcionales', 'error');
                });
        }
    }

    function opcionalesLineaSeleccionada() {
        if (!requiereCuenta()) {
            return;
        }
        var linea = resolverLineaActiva();
        if (!linea || linea.es_separador || !linea.articulo_id) {
            toast('Seleccione un ítem con artículo (clic en la fila).', 'warning');
            return;
        }
        if (linea.fired) {
            toast('Ítem ya comandado: no se pueden cambiar los opcionales.', 'warning');
            return;
        }
        $.getJSON(cfg.urls.opcionalesArticulo + '/' + linea.articulo_id)
            .done(function (resp) {
                if (!resp.ok || !(resp.grupos || []).length) {
                    toast('El artículo no tiene opcionales de receta.', 'info');
                    return;
                }
                abrirModalOpcionales(
                    resp.grupos,
                    resp.articulo,
                    { tipo: 'linea', lineaId: linea.id },
                    linea.opcionales || null
                );
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudieron leer los opcionales', 'error');
            });
    }

    function abrirReceta() {
        var linea = resolverLineaActiva();
        var articuloId = linea && linea.articulo_id ? parseInt(linea.articulo_id, 10) : 0;
        if (articuloId > 0) {
            abrirRecetaPorArticulo(articuloId);
            return;
        }
        var sku = $.trim($('#gc-codigo-articulo').val());
        if (!sku) {
            toast('Seleccione un ítem o escriba un código para ver la receta.', 'warning');
            return;
        }
        $.getJSON(cfg.urls.articulo, { sku: sku })
            .done(function (resp) {
                if (!resp.ok || !resp.articulo) {
                    toast((resp && resp.mensaje) || 'Artículo no encontrado', 'warning');
                    return;
                }
                if (resp.articulo.url_formula) {
                    window.open(resp.articulo.url_formula, '_blank', 'noopener');
                    return;
                }
                abrirRecetaPorArticulo(resp.articulo.id);
            })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                toast(r.mensaje || r.error || 'Artículo no encontrado', 'error');
            });
    }

    function abrirRecetaPorArticulo(articuloId) {
        $.getJSON(cfg.urls.articuloBase + '/' + articuloId + '/receta')
            .done(function (resp) {
                if (!resp.ok || !resp.url) {
                    toast(resp.mensaje || 'El artículo no tiene receta asociada.', 'warning');
                    return;
                }
                window.open(resp.url, '_blank', 'noopener');
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'El artículo no tiene receta asociada.', 'warning');
            });
    }

    function pintarChipsDestinoKds(areaIdSel) {
        var sel = parseInt(areaIdSel, 10) || 0;
        var $box = $('#gc-destino-areas').empty();
        var chips = [{ id: 0, codigo: 'Auto', nombre: 'según categoría' }].concat(estado.areas || []);
        chips.forEach(function (a) {
            var id = parseInt(a.id, 10) || 0;
            var $btn = $('<button type="button" class="gc-kds-chip"></button>')
                .attr('data-area-id', id)
                .toggleClass('activo', id === sel);
            if (id === 0) {
                $btn.html('<span class="gc-kds-chip-cod">Auto</span> categoría');
            } else {
                $btn.html(
                    '<span class="gc-kds-chip-cod">' + $('<div>').text(a.codigo || id).html() + '</span>'
                    + $('<div>').text(a.nombre || '').html()
                );
            }
            $box.append($btn);
        });
        $('#gc-destino-area-id').val(sel);
    }

    function editarDestinoLinea(lineaId) {
        if (!requiereCuenta() || !lineaId) {
            return;
        }
        var id = idLineaNum(lineaId);
        var linea = id ? lineaPorId(id) : null;
        if (!linea || linea.es_separador) {
            return;
        }
        if (linea.fired) {
            toast('Ítem ya comandado: no se cambia el KDS.', 'warning');
            return;
        }
        estado.lineaDestinoId = id;
        estado.lineaSeleccionadaId = id;
        var titulo = [linea.sku, linea.descripcion].filter(Boolean).join(' — ');
        $('#gc-destino-articulo').text(titulo || ('Ítem #' + id));
        pintarChipsDestinoKds(linea.area_id || 0);
        $('#gc-destino-prepara').prop('checked', linea.prepara !== false).trigger('change');
        $('#gc-modal-destino-kds').modal('show');
    }

    function guardarDestinoLinea() {
        var lineaId = estado.lineaDestinoId;
        if (!requiereCuenta() || !lineaId) {
            return;
        }
        var areaId = parseInt($('#gc-destino-area-id').val(), 10) || 0;
        var prepara = $('#gc-destino-prepara').is(':checked') ? 1 : 0;
        post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + lineaId + '/destino'), {
            area_comanda_gastronomia_id: areaId,
            prepara: prepara
        })
            .done(function (resp) {
                if (resp.ok) {
                    $('#gc-modal-destino-kds').modal('hide');
                    aplicarCuenta(resp.cuenta);
                    toast('Destino KDS actualizado', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error', 'error');
            });
    }

    function marcha(tipo, soloMarcados) {
        if (!requiereCuenta() || estado.busy) {
            return;
        }
        if (cfg.politicaComanda === 'sin_comanda') {
            toast('Este punto de venta no emite comanda.', 'warning');
            return;
        }
        estado.busy = true;
        post(urlCuenta(cfg.urls.marcha, estado.cuentaId, 'marcha'), {
            tipo: tipo || 'normal',
            solo_marcados: soloMarcados ? 1 : 0
        })
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'Sin ítems para marcha', 'warning');
                    return;
                }
                var n = (resp.comandas || []).length;
                toast('Marcha enviada (' + n + ' ticket/s)', 'success');
                aplicarCuenta(resp.cuenta);
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error en marcha', 'error');
            })
            .always(function () { estado.busy = false; });
    }

    function voidLineaSeleccionada() {
        if (!requiereCuenta()) {
            return;
        }
        var linea = resolverLineaActiva();
        var id = linea ? idLineaNum(linea.id) : null;
        if (!id) {
            toast('Seleccione o marque un ítem para borrar.', 'warning');
            return;
        }
        if (!window.confirm('¿Anular el ítem seleccionado?')) {
            return;
        }
        post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + id + '/void'))
            .done(function (resp) {
                if (resp.ok) {
                    estado.lineaSeleccionadaId = null;
                    aplicarCuenta(resp.cuenta);
                    toast('Ítem anulado', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo anular', 'error');
            });
    }

    function abrirObsItem(lineaId) {
        if (!requiereCuenta()) {
            return;
        }
        var id = idLineaNum(lineaId) || idLineaNum(estado.lineaSeleccionadaId);
        var linea = id ? lineaPorId(id) : resolverLineaActiva();
        id = linea ? idLineaNum(linea.id) : null;
        if (!id || !linea) {
            toast('Seleccione un ítem (clic en la fila o el ícono de nota).', 'warning');
            return;
        }
        if (linea.es_separador) {
            toast('Ese renglón no admite observación.', 'warning');
            return;
        }
        estado.lineaSeleccionadaId = id;
        $('#gc-obs-linea-id').val(id);
        var titulo = [linea.sku, linea.descripcion].filter(Boolean).join(' — ');
        $('#gc-obs-articulo').text(titulo || ('Ítem #' + id));
        $('#gc-obs-texto').val(linea.observacion || '');
        $('#gc-modal-obs-item')
            .one('shown.bs.modal', function () {
                var el = document.getElementById('gc-obs-texto');
                if (!el) {
                    return;
                }
                el.focus();
                el.select();
            })
            .modal('show');
    }

    function guardarObsItem(texto) {
        var id = parseInt($('#gc-obs-linea-id').val(), 10) || estado.lineaSeleccionadaId;
        if (!requiereCuenta() || !id) {
            return;
        }
        post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + id + '/observacion'), {
            observacion: texto == null ? '' : String(texto)
        })
            .done(function (resp) {
                if (resp.ok) {
                    $('#gc-modal-obs-item').modal('hide');
                    aplicarCuenta(resp.cuenta);
                    toast(String(texto || '').trim() ? 'Observación guardada' : 'Observación quitada', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error', 'error');
            });
    }

    function obsItem() {
        abrirObsItem(estado.lineaSeleccionadaId);
    }

    function obsMesa() {
        if (!requiereCuenta()) {
            return;
        }
        var obs = window.prompt('Observación de mesa:', (estado.cuenta && estado.cuenta.observacion) || '');
        if (obs === null) {
            return;
        }
        post(urlCuenta(cfg.urls.obsMesa, estado.cuentaId, 'obs-mesa'), { observacion: obs })
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error', 'error');
            });
    }

    function separador() {
        if (!requiereCuenta()) {
            return;
        }
        post(urlCuenta(cfg.urls.separador, estado.cuentaId, 'separador'))
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error', 'error');
            });
    }

    function anularMesa() {
        if (!requiereCuenta()) {
            return;
        }
        if (!window.confirm('¿Anular la cuenta #' + estado.cuentaId + '? Esta acción no se puede deshacer.')) {
            return;
        }
        post(urlCuenta(cfg.urls.anular, estado.cuentaId, 'anular'))
            .done(function (resp) {
                if (resp.ok) {
                    toast('Cuenta anulada', 'success');
                    aplicarCuenta(null);
                    cargarMesasOCuentas();
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al anular', 'error');
            });
    }

    function consultarArticulo() {
        var $btn = $('#gc-campo-articulo .consultaarticulo');
        if (!$btn.length) {
            return;
        }
        $('#gc-campo-articulo .codigoarticulo').val($.trim($('#gc-codigo-articulo').val()));
        $btn.trigger('click');
    }

    function descuentoMesa() {
        if (!requiereCuenta()) {
            return;
        }
        var actual = (estado.cuenta && estado.cuenta.porcentaje_descuento) || 0;
        var pct = window.prompt('Descuento de mesa (%):', String(actual));
        if (pct === null) {
            return;
        }
        post(urlCuenta(cfg.urls.descuento, estado.cuentaId, 'descuento'), {
            porcentaje: parseFloat(pct) || 0
        })
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                    toast('Descuento aplicado', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error en descuento', 'error');
            });
    }

    function asignarCliente() {
        if (!requiereCuenta()) {
            return;
        }
        var cod = window.prompt('Código de cliente (vacío = quitar):', '');
        if (cod === null) {
            return;
        }
        post(urlCuenta(cfg.urls.cliente, estado.cuentaId, 'cliente'), { codigo: cod })
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                    toast(resp.cuenta.cliente ? ('Cliente: ' + resp.cuenta.cliente) : 'Cliente quitado', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Cliente no encontrado', 'error');
            });
    }

    function transferirMesa() {
        if (!requiereCuenta()) {
            return;
        }
        if (!cfg.usaMesas) {
            toast('Transferir solo aplica en modo con mesas.', 'warning');
            return;
        }
        var mesaId = window.prompt('ID de mesa destino:', '');
        if (mesaId === null || !$.trim(mesaId)) {
            return;
        }
        post(urlCuenta(cfg.urls.transferir, estado.cuentaId, 'transferir'), {
            mesa_id: parseInt(mesaId, 10)
        })
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                    cargarMesasOCuentas();
                    toast('Mesa transferida', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo transferir', 'error');
            });
    }

    function abrirCobranza() {
        if (!requiereCuenta() || estado.busy) {
            return;
        }
        if (cfg.soloMarcha) {
            toast('Este puesto es solo marcha.', 'warning');
            return;
        }
        var c = estado.cuenta;
        $('#gc-cobro-cuenta').text('Cuenta #' + c.id + (c.mesa ? ' — ' + c.mesa : ''));
        $('#gc-cobro-total').text(money(c.total || 0));
        limpiarGrillaCobranza();
        cargarMediosRapidos().always(function () {
            agregarRenglonCobranza(true);
            actualizarResumenCobranza();
            $('#gc-modal-cobrar').modal('show');
        });
    }

    /** F5 Anita: efectivo directo, sin modal ni confirmación. */
    function cobrarEfectivoDirecto() {
        cobrar('efectivo');
    }

    function htmlIconoMedio(cuenta) {
        if (!cuenta) {
            return '<i class="fa fa-cash-register text-secondary"></i>';
        }
        var icono = cuenta.icono || 'fa fa-cash-register';
        var color = cuenta.icono_color || 'text-secondary';
        if (String(icono).indexOf('gastro-icon-') === 0) {
            return '<span class="' + icono + '" aria-hidden="true"></span>';
        }
        return '<i class="' + icono + ' ' + color + '" aria-hidden="true"></i>';
    }

    function cargarMediosRapidos() {
        if (!cfg.urls.cuentasCaja) {
            return $.Deferred().resolve().promise();
        }
        return $.getJSON(cfg.urls.cuentasCaja)
            .done(function (resp) {
                estado.mediosCaja = resp.cuentas_caja || [];
                estado.mediosPorId = {};
                estado.cuentacajaEfectivo = resp.cuentacaja_efectivo || null;
                estado.cuentacajaEfectivoError = resp.cuentacaja_efectivo_error || null;
                estado.mediosCaja.forEach(function (c) {
                    if (c && c.id) {
                        estado.mediosPorId[String(c.id)] = c;
                    }
                });
                renderMediosRapidos();
            })
            .fail(function () {
                toast('No se pudieron cargar los medios de cobro', 'warning');
            });
    }

    function renderMediosRapidos() {
        var $wrap = $('#gc-medios-rapidos');
        if (!$wrap.length) {
            return;
        }
        $wrap.empty();
        (estado.mediosCaja || []).forEach(function (cuenta) {
            var $btn = $('<button type="button" class="gc-medio-chip"/>')
                .attr('title', (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || ''))
                .attr('data-cuentacaja-id', cuenta.id)
                .html(htmlIconoMedio(cuenta) + '<span>' + (cuenta.etiqueta_boton || cuenta.codigo || 'Medio') + '</span>');
            $btn.on('click', function () {
                seleccionarMedioRapido(cuenta);
            });
            $wrap.append($btn);
        });
    }

    function limpiarGrillaCobranza() {
        $('#gc-tbody-cuenta-table').empty();
        actualizarResumenCobranza();
    }

    function filaCobranzaDesdeTemplate() {
        var tpl = document.getElementById('gc-template-renglon-cuenta');
        if (!tpl || !tpl.content) {
            return null;
        }
        return tpl.content.firstElementChild.cloneNode(true);
    }

    function agregarRenglonCobranza(enfocar) {
        var tr = filaCobranzaDesdeTemplate();
        if (!tr) {
            return null;
        }
        $('#gc-tbody-cuenta-table').append(tr);
        wireFilaCobranza(tr);
        if (enfocar !== false) {
            $(tr).find('.codigo').trigger('focus');
        }
        return tr;
    }

    function wireFilaCobranza(tr) {
        var $tr = $(tr);
        $tr.find('.gc-eliminar-cuenta').on('click', function () {
            $tr.remove();
            if (!$('#gc-tbody-cuenta-table tr').length) {
                agregarRenglonCobranza(false);
            }
            actualizarResumenCobranza();
        });
        $tr.find('.monto').on('input change', actualizarResumenCobranza);
        $tr.find('.codigo').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverCodigoCuentacaja($tr);
            }
        });
        $tr.find('.codigo').on('blur', function () {
            resolverCodigoCuentacaja($tr);
        });
        if (typeof activa_eventos_consultacuentacaja === 'function') {
            activa_eventos_consultacuentacaja();
        }
    }

    function asignarCuentaEnFila(tr, cuenta) {
        if (!tr || !cuenta) {
            return;
        }
        var $tr = $(tr);
        $tr.find('.cuentacaja_id').val(cuenta.id);
        $tr.find('.codigo').val(cuenta.codigo || '');
        $tr.find('.nombre').val(cuenta.nombre || '');
        $tr.find('.moneda_id').val(cuenta.moneda_id || 1);
        $tr.find('.moneda-label').text(cuenta.moneda_abreviatura || cuenta.moneda_id || '—');
        var $btn = $tr.find('.consultacuentacaja');
        $btn.find('i, .gastro-icon-mercadopago').remove();
        $btn.prepend(htmlIconoMedio(cuenta));
        var pendiente = saldoPendienteCobranza();
        if (!$tr.find('.monto').val() && pendiente > 0) {
            $tr.find('.monto').val(pendiente.toFixed(2));
        }
        actualizarResumenCobranza();
    }

    function seleccionarMedioRapido(cuenta) {
        var $vacia = $('#gc-tbody-cuenta-table tr').filter(function () {
            return !$(this).find('.cuentacaja_id').val();
        }).first();
        var tr = $vacia.length ? $vacia.get(0) : agregarRenglonCobranza(false);
        asignarCuentaEnFila(tr, cuenta);
        $(tr).find('.monto').trigger('focus').select();
    }

    function resolverCodigoCuentacaja($tr) {
        var codigo = $.trim($tr.find('.codigo').val() || '');
        if (!codigo) {
            return;
        }
        var hallada = (estado.mediosCaja || []).find(function (c) {
            return String(c.codigo) === codigo || String(c.codigo).replace(/^0+/, '') === codigo.replace(/^0+/, '');
        });
        if (hallada) {
            asignarCuentaEnFila($tr.get(0), hallada);
        }
    }

    function recogerMediosPago() {
        var medios = [];
        $('#gc-tbody-cuenta-table tr').each(function () {
            var $tr = $(this);
            var id = parseInt($tr.find('.cuentacaja_id').val() || '0', 10);
            var mon = parseInt($tr.find('.moneda_id').val() || '0', 10);
            var monto = parseFloat($tr.find('.monto').val() || '0');
            if (id > 0 && mon > 0 && monto > 0) {
                medios.push({
                    cuentacaja_id: id,
                    moneda_id: mon,
                    monto: Math.round(monto * 100) / 100
                });
            }
        });
        return medios;
    }

    function totalCobranzaGrilla() {
        return recogerMediosPago().reduce(function (s, m) { return s + m.monto; }, 0);
    }

    function saldoPendienteCobranza() {
        var total = estado.cuenta ? Number(estado.cuenta.total || 0) : 0;
        return Math.round((total - totalCobranzaGrilla()) * 100) / 100;
    }

    function actualizarResumenCobranza() {
        var total = estado.cuenta ? Number(estado.cuenta.total || 0) : 0;
        var cobrado = totalCobranzaGrilla();
        var dif = Math.round((total - cobrado) * 100) / 100;
        var cls = Math.abs(dif) <= 0.02 ? 'ok' : 'warn';
        $('#gc-totales-cobranza').attr('data-estado', cls).html(
            'Cobrado ' + money(cobrado) + ' · Diff ' + money(dif)
        );
    }

    function cobrarConMediosGrilla() {
        var medios = recogerMediosPago();
        if (!medios.length) {
            toast('Indique al menos un medio de cobro.', 'warning');
            return;
        }
        var total = estado.cuenta ? Number(estado.cuenta.total || 0) : 0;
        if (total > 0 && Math.abs(totalCobranzaGrilla() - total) > 0.02) {
            toast('El total de la grilla debe coincidir con el total a facturar.', 'warning');
            return;
        }
        $('#gc-modal-cobrar').modal('hide');
        cobrar('medios', { medios_pago: medios });
    }

    function abrirF8() {
        if (!requiereCuenta() || estado.busy) {
            return;
        }
        if (cfg.soloMarcha) {
            toast('Este puesto es solo marcha.', 'warning');
            return;
        }
        $('#gc-f8-tipo').val('invitados');
        $('.gc-f8-tipo').removeClass('active');
        $('.gc-f8-tipo[data-tipo="invitados"]').addClass('active');
        $('#gc-f8-bloque-codigo').hide();
        $('#gc-f8-autorizante-id, #gc-f8-autorizante-codigo, #gc-f8-autorizante-nombre').val('');
        $('#gc-f8-ccosto-id, #gc-f8-ccosto-codigo, #gc-f8-ccosto-nombre').val('');
        $('#gc-f8-cliente-id, #gc-f8-cliente-codigo, #gc-f8-cliente-nombre').val('');
        $('#gc-f8-descuento-id, #gc-f8-descuento-codigo, #gc-f8-descuento-nombre').val('');
        $('#gc-f8-dto-valor').text('');
        $('#gc-modal-f8').modal('show');
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }
        if (typeof activa_eventos_consultacliente === 'function') {
            activa_eventos_consultacliente();
        }
        if (typeof activa_eventos_consultadescuento === 'function') {
            activa_eventos_consultadescuento();
        }
        // Enter en CC: pasar al cliente interno dentro del F8.
        window.afterCentrocostoEnterOk = function (data, inputCodigo) {
            if (!data || !data.id) {
                return;
            }
            var $modal = $(inputCodigo).closest('#gc-modal-f8');
            if (!$modal.length) {
                return;
            }
            $modal.find('#gc-f8-cliente-codigo').trigger('focus').select();
        };
    }

    function confirmarF8() {
        var tipo = $('#gc-f8-tipo').val() || 'invitados';
        var data = {
            tipo: 'efectivo',
            aplicar_descuento_autorizante: 1,
            tipo_descuento: tipo,
            autorizante_usuario_id: parseInt($('#gc-f8-autorizante-id').val() || '0', 10),
            centrocosto_id: parseInt($('#gc-f8-ccosto-id').val() || '0', 10),
            cliente_interno_descuento_id: parseInt($('#gc-f8-cliente-id').val() || '0', 10)
        };
        if (!data.autorizante_usuario_id) {
            toast('Indique el autorizante.', 'warning');
            return;
        }
        if (!data.centrocosto_id) {
            toast('Indique el centro de costo.', 'warning');
            return;
        }
        if (!data.cliente_interno_descuento_id) {
            toast('Indique el cliente interno.', 'warning');
            return;
        }
        if (tipo === 'descuento') {
            data.descuento_gastronomia_id = parseInt($('#gc-f8-descuento-id').val() || '0', 10) || null;
            data.descuento_codigo = $.trim($('#gc-f8-descuento-codigo').val() || '');
            if (!data.descuento_gastronomia_id && !data.descuento_codigo) {
                toast('Indique el código de descuento.', 'warning');
                return;
            }
        }
        $('#gc-modal-f8').modal('hide');
        cobrar('efectivo', data);
    }

    function cobrar(tipo, extra) {
        if (!requiereCuenta() || estado.busy) {
            return;
        }
        if (cfg.soloMarcha) {
            toast('Este puesto es solo marcha.', 'warning');
            return;
        }
        var total = estado.cuenta ? estado.cuenta.total : 0;
        var data = $.extend({ tipo: tipo }, extra || {});

        if (tipo === 'hotel') {
            if (!cfg.habilitaHotel) {
                toast('Hotel no habilitado en este PC.', 'warning');
                return;
            }
            if (!data.habitacion) {
                var hab = window.prompt('Habitación:', (estado.cuenta && estado.cuenta.habitacion) || '');
                if (hab === null || !$.trim(hab)) {
                    return;
                }
                data.habitacion = $.trim(hab);
                var res = window.prompt('Reserva (opcional):', (estado.cuenta && estado.cuenta.reserva_hotel) || '');
                if (res === null) {
                    return;
                }
                if ($.trim(res)) {
                    data.reserva_hotel = $.trim(res);
                }
            }
        } else if (tipo === 'interna') {
            if (cfg.habilitaGst && data.sin_gst == null && !data.tarjeta_gst) {
                var gst = window.prompt(
                    'Tarjeta GST / track (vacío = interna sin canje):',
                    (estado.cuenta && estado.cuenta.tarjeta_gst) || ''
                );
                if (gst === null) {
                    return;
                }
                if ($.trim(gst)) {
                    data.tarjeta_gst = $.trim(gst);
                } else {
                    data.sin_gst = 1;
                }
            } else if (data.sin_gst == null && !data.tarjeta_gst) {
                data.sin_gst = 1;
            }
        }

        estado.busy = true;
        post(urlCuenta(cfg.urls.cobrar, estado.cuentaId, 'cobrar'), data)
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'Error al cobrar', 'error');
                    return;
                }
                toast(resp.mensaje || ('Cobrado: ' + money((resp.totales && resp.totales.total) || total)), 'success');
                if (resp.warn) {
                    toast(resp.warn, 'warning');
                }
                aplicarCuenta(null);
                cargarMesasOCuentas();
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al cobrar', 'error');
            })
            .always(function () { estado.busy = false; });
    }

    function cambiarListaPrecio() {
        if (!requiereCuenta()) {
            return;
        }
        var actual = (estado.cuenta && estado.cuenta.listaprecio_id) || cfg.listaprecioId || '';
        var id = window.prompt('ID de lista de precio:', String(actual));
        if (id === null || !$.trim(id)) {
            return;
        }
        post(urlCuenta(cfg.urls.listaPrecio, estado.cuentaId, 'lista-precio'), {
            listaprecio_id: parseInt(id, 10)
        })
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                    toast('Lista de precio #' + id + ' (nuevos ítems)', 'success');
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error', 'error');
            });
    }

    function ejecutarHerramienta(action) {
        switch (action) {
            case 'marcha_postres':
                marcha('postres', false);
                break;
            case 'buscar_articulo':
                consultarArticulo();
                break;
            case 'cliente':
                asignarCliente();
                break;
            case 'obs_mesa':
                obsMesa();
                break;
            case 'separador':
                separador();
                break;
            case 'transferir':
                transferirMesa();
                break;
            case 'opcionales':
                opcionalesLineaSeleccionada();
                break;
            case 'receta':
                abrirReceta();
                break;
            case 'lista_precio':
                cambiarListaPrecio();
                break;
            case 'datos_mesa':
                obsMesa();
                break;
            case 'cobrar':
                // Misma grilla que F7 (solo si la piden; F5 es efectivo directo).
                abrirCobranza();
                break;
            case 'anular_mesa':
                anularMesa();
                break;
            default:
                toast('Herramienta no implementada: ' + action, 'warning');
        }
    }

    function onHotkey(e) {
        if ($(e.target).is('textarea')) {
            return;
        }
        // Ctrl/Cmd/Alt+Fx → dejar al navegador (p. ej. Ctrl+F5 / Ctrl+Shift+R recargar).
        if (e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        if ($('#gc-modal-opcionales').hasClass('show')
            || $('#consultaarticuloModal').hasClass('show')
            || $('#gc-modal-cobrar').hasClass('show')
            || $('#gc-modal-f8').hasClass('show')) {
            return;
        }
        var key = e.key;
        if (key === 'F1') {
            e.preventDefault();
            $('#gc-btn-f1').trigger('click');
            return;
        }
        if (key === 'F2') {
            e.preventDefault();
            $('#gc-btn-f2').trigger('click');
            return;
        }
        if (key === 'F5') {
            e.preventDefault();
            $('#gc-btn-f5').trigger('click');
            return;
        }
        if (key === 'F6') {
            e.preventDefault();
            $('#gc-btn-f6').trigger('click');
            return;
        }
        if (key === 'F7') {
            e.preventDefault();
            $('#gc-btn-f7').trigger('click');
            return;
        }
        if (key === 'F8') {
            e.preventDefault();
            $('#gc-btn-f8').trigger('click');
            return;
        }
        if (key === 'F9') {
            e.preventDefault();
            $('#gc-btn-f9').trigger('click');
            return;
        }
        if (key === 'F10') {
            e.preventDefault();
            $('#gc-btn-f10').trigger('click');
            return;
        }
        if (key === 'F11') {
            e.preventDefault();
            $('#gc-btn-f11').trigger('click');
            return;
        }
        if (key === 'F12') {
            e.preventDefault();
            $('#gc-btn-f12').trigger('click');
            return;
        }
        // Esc en el código: suelta el foco para que + abra otra cuenta Barra.
        if (key === 'Escape' && $(e.target).is('#gc-codigo-articulo')) {
            e.preventDefault();
            $(e.target).blur();
            $('#gc-btn-nueva-cuenta').trigger('focus');
            return;
        }
        // + fuera del código de artículo: siempre abre cuenta Barra (aunque ya haya una activa).
        if (key === '+' || key === 'Add' || e.code === 'NumpadAdd') {
            if ($(e.target).is('#gc-codigo-articulo')) {
                return; // cantidad / barra lo maneja el keydown del campo
            }
            if ($(e.target).is('input, select, textarea')) {
                return;
            }
            e.preventDefault();
            abrirCuentaLibre();
        }
    }

    $(function () {
        if (!$('#gc-pos-root').length) {
            return;
        }

        $(document).on('keydown.gcPos', onHotkey);

        if (typeof window.activa_eventos_consultaarticulo === 'function') {
            window.activa_eventos_consultaarticulo();
        }
        if (typeof window.activa_eventos_consultausuario === 'function') {
            window.activa_eventos_consultausuario();
        }
        if (typeof window.activa_eventos_consultacliente === 'function') {
            window.activa_eventos_consultacliente();
        }
        if (typeof window.activa_eventos_consultacentrocosto === 'function') {
            window.activa_eventos_consultacentrocosto();
        }
        if (typeof window.activa_eventos_consultadescuento === 'function') {
            window.activa_eventos_consultadescuento();
        }

        // Consultas anidadas sobre F8 / cobranza: subir z-index (mismo patrón AGG).
        ['#consultausuarioModal', '#consultaclienteModal', '#consultacentrocostoModal', '#consultadescuentoModal', '#consultaarticuloModal', '#consultacuentacajaModal'].forEach(function (sel) {
            var $m = $(sel);
            if (!$m.length) {
                return;
            }
            $m.on('show.bs.modal.gcStack', function () {
                if (!$m.parent().is('body')) {
                    $m.appendTo('body');
                }
            });
            $m.on('shown.bs.modal.gcStack', function () {
                var hayPadre = $('#gc-modal-f8').hasClass('show') || $('#gc-modal-cobrar').hasClass('show');
                if (!hayPadre) {
                    return;
                }
                var z = 2060;
                $m.css('z-index', z).addClass('gc-modal-sobre-f8');
                $('.modal-backdrop').last().css('z-index', z - 10);
            });
            $m.on('hidden.bs.modal.gcStack', function () {
                $m.css('z-index', '').removeClass('gc-modal-sobre-f8');
                if ($('#gc-modal-f8').hasClass('show') || $('#gc-modal-cobrar').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });
        });

        $('#gc-modal-f8').on('shown.bs.modal', function () {
            $('#gc-f8-autorizante-codigo').trigger('focus');
        });

        $('#gc-campo-articulo .consultaarticulo').on('click', function () {
            $('#gc-campo-articulo .codigoarticulo').val($.trim($('#gc-codigo-articulo').val()));
        });

        $(document).on('click', '#consultaarticuloModal .eligeconsultaarticulo', function () {
            var $fila = $(this).closest('tr');
            var sku = $.trim($fila.find('.sku').text());
            var nombre = $.trim($fila.find('.descripcion').text());
            if (sku) {
                $('#gc-codigo-articulo').val(sufijoVisible(sku));
            }
            if (nombre) {
                setDescCarga(nombre);
            }
        });

        var alElegirArticuloPrevio = window.onArticuloSeleccionado;
        window.onArticuloSeleccionado = function (data, ctx) {
            if (typeof alElegirArticuloPrevio === 'function') {
                alElegirArticuloPrevio(data, ctx);
            }
            if (!$('#gc-campo-articulo').length || !data || !data.sku) {
                return;
            }
            $('#gc-codigo-articulo').val(sufijoVisible(data.sku));
            if (data.descripcion) {
                setDescCarga(data.descripcion);
            }
            $('#gc-codigo-articulo').trigger('blur');
            $('#gc-codigo-articulo').trigger('focus');
        };

        $('#gc-btn-refresh-mesas').on('click', cargarMesasOCuentas);
        $('#gc-btn-nueva-cuenta').on('click', abrirCuentaLibre);
        $('#gc-btn-cerrar-cuenta').on('click', cerrarCuentaActivaSinFacturar);
        $('#gc-btn-cerrar-todas').on('click', cerrarTodasCuentasSinFacturar);
        $('#gc-btn-mozo-login').on('click', function () {
            $('#gc-modal-mozo').modal('show');
            setTimeout(function () { $('#gc-mozo-codigo').trigger('focus'); }, 200);
        });
        $('#gc-btn-mozo-logout').on('click', function () {
            post(cfg.urls.mozoLogout, {})
                .done(function () {
                    pintarMozoSesion(null);
                    toast('Mozo desconectado', 'info');
                });
        });
        $('#gc-mozo-confirmar').on('click', confirmarLoginMozo);
        $('#gc-form-mozo').on('submit', function (e) {
            e.preventDefault();
            confirmarLoginMozo();
        });
        if (cfg.urls.config) {
            $.getJSON(cfg.urls.config)
                .done(function (resp) {
                    if (resp && resp.ok) {
                        pintarMozoSesion(resp.mozo || null);
                    }
                });
        }

        $('#gc-panel-mesas, #gc-panel-cuentas').on('click', '.gc-mesa-btn', function () {
            var $b = $(this);
            var cuentaId = $b.data('cuenta-id') || null;
            var mesaId = $b.data('mesa-id') || null;
            if (cuentaId) {
                cargarCuentaPorId(cuentaId);
                return;
            }
            if (mesaId) {
                abrirMesa(mesaId);
            }
        });

        $('#gc-panel-mesas').on('shown.bs.collapse', '.collapse[data-plano-id]', function () {
            var planoId = $(this).data('plano-id');
            estado.planoAbierto = planoId;
            montarVisor(planoPorId(planoId));
        });

        $('#gc-panel-mesas').on('hidden.bs.collapse', '.collapse[data-plano-id]', function () {
            var planoId = $(this).data('plano-id');
            if (window.GastroCrownPlanoViewer) {
                window.GastroCrownPlanoViewer.destruir(idCanvasPlano(planoId));
            }
            if (String(estado.planoAbierto) === String(planoId)) {
                estado.planoAbierto = null;
            }
        });

        $(window).on('resize.gcPlanos', function () {
            if (!window.GastroCrownPlanoViewer) {
                return;
            }
            estado.planos.forEach(function (p) {
                var contenedor = idCanvasPlano(p.id);
                if (window.GastroCrownPlanoViewer.existe(contenedor)) {
                    window.GastroCrownPlanoViewer.ajustar(contenedor);
                }
            });
        });

        $('#gc-codigo-articulo').on('keydown', function (e) {
            if (e.key === '+' || e.key === 'Add' || e.code === 'NumpadAdd') {
                e.preventDefault();
                // Sin cuenta: + abre barra. Con cuenta: + fija cantidad.
                if (!estado.cuentaId) {
                    abrirCuentaLibre();
                    return;
                }
                var n = window.prompt('Cantidad a agregar:', $('#gc-cantidad').val() || '1');
                if (n === null) {
                    return;
                }
                var q = parseFloat(String(n).replace(',', '.'));
                if (!(q >= 0.001)) {
                    toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
                    return;
                }
                $('#gc-cantidad').val(q);
                // Si ya hay código, tickea ya. Si no, queda la cantidad para el próximo Enter.
                if ($.trim($(this).val())) {
                    agregarLinea();
                } else {
                    toast('Cantidad ' + textoCantidad(q) + ' — ingresá el código y Enter', 'info');
                }
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                // Respeta la cantidad fijada con + (no forzar 1).
                agregarLinea();
            }
        });

        $('#gc-tabla-lineas').on('click', 'tr[data-linea-id]', function (e) {
            if ($(e.target).closest('.gc-marca-linea, .gc-void-linea, .gc-destino-linea, .gc-obs-linea, .gc-qty-btn').length) {
                return;
            }
            estado.lineaSeleccionadaId = idLineaNum($(this).attr('data-linea-id'));
            pintarLineas();
        });

        $('#gc-tabla-lineas').on('click', '.gc-qty-btn', function (e) {
            e.stopPropagation();
            var id = idLineaNum($(this).closest('tr').attr('data-linea-id')) || 0;
            var dir = parseInt($(this).data('dir'), 10) || 0;
            var linea = lineaPorId(id);
            if (!linea || !estado.cuentaId) {
                return;
            }
            var next = (Number(linea.cantidad) || 0) + dir;
            if (!(next >= 0.001)) {
                toast('La cantidad no puede ser menor al mínimo permitido.', 'warning');
                return;
            }
            post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + id + '/cantidad'), { cantidad: next })
                .done(function (resp) {
                    if (resp.ok) {
                        aplicarCuenta(resp.cuenta);
                    }
                })
                .fail(function (xhr) {
                    toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo cambiar la cantidad', 'error');
                });
        });

        $('#gc-tabla-lineas').on('click', '.gc-destino-linea', function (e) {
            e.stopPropagation();
            editarDestinoLinea(idLineaNum($(this).closest('tr').attr('data-linea-id')));
        });

        $('#gc-tabla-lineas').on('click', '.gc-obs-linea', function (e) {
            e.stopPropagation();
            abrirObsItem(idLineaNum($(this).closest('tr').attr('data-linea-id')));
        });

        $('#gc-destino-areas').on('click', '.gc-kds-chip', function () {
            var id = parseInt($(this).attr('data-area-id'), 10) || 0;
            $('#gc-destino-area-id').val(id);
            $('#gc-destino-areas .gc-kds-chip').removeClass('activo');
            $(this).addClass('activo');
        });

        $('#gc-destino-prepara').on('change', function () {
            $('#gc-destino-prepara-label').text($(this).is(':checked') ? 'Sí' : 'No');
        });

        $('#gc-destino-guardar').on('click', guardarDestinoLinea);

        $('#gc-obs-guardar').on('click', function () {
            guardarObsItem($('#gc-obs-texto').val());
        });

        $('#gc-obs-limpiar').on('click', function () {
            $('#gc-obs-texto').val('');
            guardarObsItem('');
        });

        $('#gc-obs-texto').on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                guardarObsItem($(this).val());
            }
        });

        $('#gc-prepara').on('change', function () {
            $('#gc-prepara-label').text($(this).is(':checked') ? 'Sí' : 'No');
        });

        $('#gc-codigo-articulo').on('blur', function () {
            var sku = $.trim($(this).val());
            if (!sku || !cfg.urls.articulo) {
                return;
            }
            var params = { sku: sku };
            if (estado.cuentaId) {
                params.cuenta_id = estado.cuentaId;
            }
            $.getJSON(cfg.urls.articulo, params)
                .done(function (resp) {
                    if (!resp.ok || !resp.articulo) {
                        setDescCarga('');
                        setPrecioCarga(null);
                        if (resp && resp.mensaje) {
                            toast(resp.mensaje, 'warning');
                        }
                        return;
                    }
                    var a = resp.articulo;
                    setDescCarga(a.descripcion, a.tiene_precio_vigente === false);
                    setPrecioCarga(a);
                    if (typeof a.prepara === 'boolean') {
                        $('#gc-prepara').prop('checked', a.prepara).trigger('change');
                    }
                    if (a.area_comanda_gastronomia_id) {
                        $('#gc-area-kds').val(String(a.area_comanda_gastronomia_id));
                    } else {
                        $('#gc-area-kds').val('');
                    }
                })
                .fail(function (xhr) {
                    var r = xhr.responseJSON || {};
                    setDescCarga('');
                    setPrecioCarga(null);
                    toast(r.mensaje || r.error || 'Artículo no encontrado', 'error');
                });
        });

        $('#gc-tabla-lineas').on('change', '.gc-marca-linea', function () {
            var id = idLineaNum($(this).closest('tr').attr('data-linea-id'));
            if (!id || !estado.cuentaId) {
                return;
            }
            estado.lineaSeleccionadaId = id;
            post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + id + '/marcar'), {
                marcado: $(this).is(':checked') ? 1 : 0
            }).done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                }
            });
        });

        $('#gc-tabla-lineas').on('click', '.gc-void-linea', function (e) {
            e.stopPropagation();
            estado.lineaSeleccionadaId = idLineaNum($(this).closest('tr').attr('data-linea-id'));
            voidLineaSeleccionada();
        });

        $('#gc-btn-f1').on('click', consultarArticulo);
        $('#gc-btn-f2').on('click', function () { cobrar('hotel'); });
        $('#gc-btn-f5').on('click', cobrarEfectivoDirecto);
        $('#gc-btn-f7').on('click', abrirCobranza);
        $('#gc-modal-cobrar').on('click', '[data-cobro]', function () {
            var tipo = $(this).data('cobro');
            $('#gc-modal-cobrar').modal('hide');
            cobrar(tipo);
        });
        $('#gc-agrega-renglon-cuenta').on('click', function () { agregarRenglonCobranza(true); });
        $('#gc-cobrar-medios').on('click', cobrarConMediosGrilla);
        $('#gc-btn-f6').on('click', voidLineaSeleccionada);
        $('#gc-btn-f8').on('click', abrirF8);
        $('#gc-btn-f9').on('click', function () { cobrar('factura_sola'); });
        $('#gc-btn-f10').on('click', descuentoMesa);
        $('#gc-btn-f11').on('click', obsItem);
        $('#gc-btn-f12').on('click', function () { marcha('normal', false); });

        $('.gc-f8-tipo').on('click', function () {
            var tipo = $(this).data('tipo');
            $('.gc-f8-tipo').removeClass('active');
            $(this).addClass('active');
            $('#gc-f8-tipo').val(tipo);
            $('#gc-f8-bloque-codigo').toggle(tipo === 'descuento');
        });
        $('#gc-f8-confirmar').on('click', confirmarF8);
        $('#gc-f8-descuento-codigo').on('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var codigo = $.trim($(this).val() || '');
            if (!codigo || !cfg.urls.resolverDescuento) {
                return;
            }
            $.getJSON(cfg.urls.resolverDescuento, { codigo: codigo })
                .done(function (resp) {
                    if (!resp.ok || !resp.descuento) {
                        toast((resp && resp.mensaje) || 'Descuento inexistente', 'warning');
                        return;
                    }
                    $('#gc-f8-descuento-id').val(resp.descuento.id);
                    $('#gc-f8-descuento-codigo').val(resp.descuento.codigo);
                    $('#gc-f8-descuento-nombre').val(resp.descuento.nombre);
                    $('#gc-f8-dto-valor').text(
                        (resp.descuento.tipovalor === 'I' ? '$ ' : '')
                        + resp.descuento.valor
                        + (resp.descuento.tipovalor === 'P' ? ' %' : '')
                    );
                    if (resp.descuento.cliente_id && !$('#gc-f8-cliente-id').val()) {
                        $('#gc-f8-cliente-id').val(resp.descuento.cliente_id);
                    }
                })
                .fail(function (xhr) {
                    toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Descuento inexistente', 'error');
                });
        });

        $('#gc-pos-tools').on('click', '.gc-tool-btn:not(:disabled)', function () {
            ejecutarHerramienta($(this).data('action'));
        });

        $('#gc-opc-body').on('click', '.gc-opc-tarjeta', function () {
            var $t = $(this);
            var orden = String($t.data('orden'));
            estado.opc.seleccion[orden] = String($t.data('valor'));
            $t.closest('.gc-opc-grupo').find('.gc-opc-tarjeta').removeClass('seleccionada');
            $t.addClass('seleccionada');
            var total = estado.opc.grupos.length;
            if (estado.opc.paso < total - 1) {
                setTimeout(function () { irPasoOpcionales(estado.opc.paso + 1); }, 180);
            } else {
                renderOpcionales();
            }
        });

        $('#gc-opc-pasos').on('click', '.gc-opc-paso', function () {
            irPasoOpcionales(parseInt($(this).data('paso'), 10) || 0);
        });

        $('#gc-opc-confirmar').on('click', avanzarOConfirmarOpcionales);
        $('#gc-opc-atras').on('click', function () { irPasoOpcionales(estado.opc.paso - 1); });

        $('#gc-modal-opcionales').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                avanzarOConfirmarOpcionales();
                return;
            }
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                irPasoOpcionales(estado.opc.paso - 1);
                return;
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                irPasoOpcionales(estado.opc.paso + 1);
                return;
            }
            if (/^[1-9]$/.test(e.key)) {
                var $tarjetas = $('#gc-opc-body .gc-opc-grupo.activo .gc-opc-tarjeta');
                var $elegida = $tarjetas.eq(parseInt(e.key, 10) - 1);
                if ($elegida.length) {
                    e.preventDefault();
                    $elegida.trigger('click');
                }
            }
        });

        $('#gc-modal-opcionales').on('hidden.bs.modal', function () {
            estado.opc.contexto = null;
            $('#gc-codigo-articulo').trigger('focus');
        });

        cargarAreasKds();
        cargarMesasOCuentas();
        $('#gc-pos-root').trigger('focus');
    });
})(jQuery);
