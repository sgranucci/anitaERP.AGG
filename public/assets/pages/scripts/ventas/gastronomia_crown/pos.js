(function ($) {
    'use strict';

    var cfg = window.GastroCrownPos || {};
    var estado = {
        cuentaId: null,
        cuenta: null,
        lineaSeleccionadaId: null,
        busy: false,
        areas: [],
        planos: [],
        mesasPorId: {},
        planoAbierto: null,
        opc: {
            grupos: [],
            seleccion: {},
            paso: 0,
            contexto: null
        }
    };

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

    function aplicarCuenta(cuenta) {
        estado.cuenta = cuenta || null;
        estado.cuentaId = cuenta ? cuenta.id : null;
        pintarEstado();
        pintarLineas();
        $('.gc-mesa-btn').removeClass('activa');
        if (cuenta) {
            $('.gc-mesa-btn[data-cuenta-id="' + cuenta.id + '"]').addClass('activa');
            if (cuenta.mesa_id) {
                $('.gc-mesa-btn[data-mesa-id="' + cuenta.mesa_id + '"]').addClass('activa');
            }
        }
        pintarPlanos();
        $('#gc-codigo-articulo').trigger('focus');
        $('.gc-pos-topbar').addClass('gc-pulse');
        setTimeout(function () { $('.gc-pos-topbar').removeClass('gc-pulse'); }, 450);
    }

    function pintarEstado() {
        var $el = $('#gc-estado-cuenta');
        if (!estado.cuenta) {
            $el.removeClass('alert-success alert-warning').addClass('alert-secondary')
                .text('Seleccione una mesa o cuenta para comenzar.');
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
            $tb.html('<tr><td colspan="5" class="text-muted text-center py-3">Sin ítems</td></tr>');
            pintarTotales();
            return;
        }
        var html = '';
        lineas.forEach(function (l) {
            if (l.es_separador) {
                html += '<tr class="gc-separador" data-linea-id="' + l.id + '">'
                    + '<td colspan="5" class="text-center text-muted small py-1">— separador —</td></tr>';
                return;
            }
            var cls = [];
            if (l.marcado) {
                cls.push('marcada');
            }
            if (l.fired) {
                cls.push('fired');
            }
            if (estado.lineaSeleccionadaId === l.id) {
                cls.push('seleccionada');
            }
            html += '<tr class="' + cls.join(' ') + '" data-linea-id="' + l.id + '">'
                + '<td><input type="checkbox" class="gc-marca-linea"' + (l.marcado ? ' checked' : '') + '></td>'
                + '<td>' + l.cantidad + '</td>'
                + '<td>' + $('<div>').text(l.descripcion || l.sku || '').html()
                + (l.observacion ? '<br><small class="text-muted">' + $('<div>').text(l.observacion).html() + '</small>' : '')
                + (l.area ? ' <span class="badge badge-info" title="KDS">' + $('<div>').text(l.area_codigo || l.area).html() + '</span>' : '')
                + (l.tiene_opcionales ? ' <span class="badge badge-success" title="Opcionales elegidos">opc</span>' : '')
                + (l.prepara === false ? ' <span class="badge badge-secondary">no prepara</span>' : '')
                + (l.fired ? ' <span class="badge badge-dark">marcha</span>' : '')
                + '</td>'
                + '<td class="text-right">' + money(l.importe) + '</td>'
                + '<td class="text-center">'
                + '<button type="button" class="btn btn-xs btn-link gc-destino-linea" title="KDS / prepara"><i class="fa fa-utensils"></i></button>'
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
            var label = (m.codigo ? m.codigo + ' — ' : '') + (m.nombre || '');
            html += '<button type="button" class="btn btn-sm btn-outline-secondary gc-mesa-btn'
                + (m.ocupada ? ' ocupada' : '')
                + (estado.cuentaId && m.cuenta_abierta_id === estado.cuentaId ? ' activa' : '')
                + '" data-mesa-id="' + m.id + '" data-cuenta-id="' + (m.cuenta_abierta_id || '') + '">'
                + '<strong>' + $('<div>').text(label).html() + '</strong>'
                + (m.ocupada ? ' <span class="badge badge-warning">ocupada</span>' : '')
                + '</button>';
        });
        $panel.html(html);
    }

    function renderCuentas(cuentas) {
        var $panel = $('#gc-panel-mesas');
        if (!cuentas.length) {
            $panel.html('<div class="text-muted small p-2">Sin cuentas activas. Use + para abrir una.</div>');
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
        post(cfg.urls.abrirMesa, { mesa_id: mesaId, cubiertos: cubiertos })
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo abrir', 'error');
                    return;
                }
                aplicarCuenta(resp.cuenta);
                cargarMesasOCuentas();
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al abrir mesa', 'error');
            })
            .always(function () { estado.busy = false; });
    }

    function abrirCuentaLibre() {
        if (estado.busy) {
            return;
        }
        estado.busy = true;
        post(cfg.urls.abrirCuenta, {})
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo abrir', 'error');
                    return;
                }
                aplicarCuenta(resp.cuenta);
                cargarMesasOCuentas();
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al abrir cuenta', 'error');
            })
            .always(function () { estado.busy = false; });
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
                $('#gc-desc-articulo').val('');
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
        var id = estado.lineaSeleccionadaId;
        var linea = id
            ? (estado.cuenta.lineas || []).find(function (l) { return l.id === id; })
            : null;
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
        var articuloId = null;
        var id = estado.lineaSeleccionadaId;
        if (id && estado.cuenta) {
            var linea = (estado.cuenta.lineas || []).find(function (l) { return l.id === id; });
            if (linea && linea.articulo_id) {
                articuloId = linea.articulo_id;
            }
        }
        if (articuloId) {
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

    function editarDestinoLinea(lineaId) {
        if (!requiereCuenta() || !lineaId) {
            return;
        }
        var linea = (estado.cuenta.lineas || []).find(function (l) { return l.id === lineaId; });
        if (!linea || linea.es_separador) {
            return;
        }
        if (linea.fired) {
            toast('Ítem ya comandado: no se cambia el KDS.', 'warning');
            return;
        }
        var opciones = ['0 = Auto / sin área'];
        estado.areas.forEach(function (a) {
            opciones.push(a.id + ' = ' + (a.codigo || '') + ' ' + (a.nombre || ''));
        });
        var areaStr = window.prompt(
            'KDS destino (id):\n' + opciones.join('\n'),
            String(linea.area_id || 0)
        );
        if (areaStr === null) {
            return;
        }
        var preparaStr = window.prompt('¿Prepara? (1=sí / 0=no):', linea.prepara ? '1' : '0');
        if (preparaStr === null) {
            return;
        }
        post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + lineaId + '/destino'), {
            area_comanda_gastronomia_id: parseInt(areaStr, 10) || 0,
            prepara: parseInt(preparaStr, 10) ? 1 : 0
        })
            .done(function (resp) {
                if (resp.ok) {
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
        var id = estado.lineaSeleccionadaId;
        if (!id) {
            var marcadas = (estado.cuenta.lineas || []).filter(function (l) { return l.marcado && !l.es_separador; });
            if (marcadas.length === 1) {
                id = marcadas[0].id;
            }
        }
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

    function obsItem() {
        if (!requiereCuenta()) {
            return;
        }
        var id = estado.lineaSeleccionadaId;
        if (!id) {
            toast('Seleccione un ítem (clic en la fila).', 'warning');
            return;
        }
        var linea = (estado.cuenta.lineas || []).find(function (l) { return l.id === id; });
        var obs = window.prompt('Observación del ítem:', (linea && linea.observacion) || '');
        if (obs === null) {
            return;
        }
        post(urlCuenta(cfg.urls.agregarLinea, estado.cuentaId, 'linea/' + id + '/observacion'), {
            observacion: obs
        })
            .done(function (resp) {
                if (resp.ok) {
                    aplicarCuenta(resp.cuenta);
                }
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error', 'error');
            });
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
        var sku = $.trim($('#gc-codigo-articulo').val());
        if (!sku) {
            sku = window.prompt('Código / SKU a consultar:', '') || '';
        }
        sku = $.trim(sku);
        if (!sku) {
            return;
        }
        $.getJSON(cfg.urls.articulo, { sku: sku })
            .done(function (resp) {
                if (!resp.ok || !resp.articulo) {
                    toast('Artículo no encontrado', 'warning');
                    return;
                }
                var a = resp.articulo;
                $('#gc-codigo-articulo').val(a.sku);
                $('#gc-desc-articulo').val(a.descripcion + ' · ' + money(a.precio));
                toast(a.sku + ' — ' + a.descripcion + ' · ' + money(a.precio), 'info');
            })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                toast(r.mensaje || r.error || 'Artículo no encontrado', 'error');
            });
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

    function cobrar(tipo) {
        if (!requiereCuenta() || estado.busy) {
            return;
        }
        if (cfg.soloMarcha) {
            toast('Este puesto es solo marcha.', 'warning');
            return;
        }
        var total = estado.cuenta ? estado.cuenta.total : 0;
        var data = { tipo: tipo };
        var label = tipo;

        if (tipo === 'hotel') {
            if (!cfg.habilitaHotel) {
                toast('Hotel no habilitado en este PC.', 'warning');
                return;
            }
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
            label = 'Hotel hab. ' + data.habitacion;
        } else if (tipo === 'interna') {
            if (cfg.habilitaGst) {
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
            } else {
                data.sin_gst = 1;
            }
            label = 'Interna / GST';
        } else if (tipo === 'efectivo') {
            label = 'Efectivo';
        } else if (tipo === 'factura_sola') {
            label = 'Factura sola';
        }

        if (!window.confirm('¿Cobrar ' + label + ' por ' + money(total) + '?\nSe emite la factura electrónica en ARCA.')) {
            return;
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
                cobrar('efectivo');
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
        if ($('#gc-modal-opcionales').hasClass('show')) {
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
        }
    }

    $(function () {
        if (!$('#gc-pos-root').length) {
            return;
        }

        $(document).on('keydown.gcPos', onHotkey);

        $('#gc-btn-refresh-mesas').on('click', cargarMesasOCuentas);
        $('#gc-btn-nueva-cuenta').on('click', abrirCuentaLibre);

        $('#gc-panel-mesas').on('click', '.gc-mesa-btn', function () {
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
            if (e.key === 'Enter') {
                e.preventDefault();
                agregarLinea();
            }
        });

        $('#gc-tabla-lineas').on('click', 'tr[data-linea-id]', function (e) {
            if ($(e.target).closest('.gc-marca-linea, .gc-void-linea, .gc-destino-linea').length) {
                return;
            }
            estado.lineaSeleccionadaId = parseInt($(this).data('linea-id'), 10) || null;
            pintarLineas();
        });

        $('#gc-tabla-lineas').on('click', '.gc-destino-linea', function (e) {
            e.stopPropagation();
            editarDestinoLinea(parseInt($(this).closest('tr').data('linea-id'), 10) || null);
        });

        $('#gc-prepara').on('change', function () {
            $('#gc-prepara-label').text($(this).is(':checked') ? 'Sí' : 'No');
        });

        $('#gc-codigo-articulo').on('blur', function () {
            var sku = $.trim($(this).val());
            if (!sku || !cfg.urls.articulo) {
                return;
            }
            $.getJSON(cfg.urls.articulo, { sku: sku })
                .done(function (resp) {
                    if (!resp.ok || !resp.articulo) {
                        $('#gc-desc-articulo').val('');
                        if (resp && resp.mensaje) {
                            toast(resp.mensaje, 'warning');
                        }
                        return;
                    }
                    var a = resp.articulo;
                    $('#gc-desc-articulo').val(a.descripcion);
                    if (typeof a.prepara === 'boolean') {
                        $('#gc-prepara').prop('checked', a.prepara).trigger('change');
                    }
                    if (a.area_comanda_gastronomia_id) {
                        $('#gc-area-kds').val(String(a.area_comanda_gastronomia_id));
                    }
                })
                .fail(function (xhr) {
                    var r = xhr.responseJSON || {};
                    $('#gc-desc-articulo').val('');
                    toast(r.mensaje || r.error || 'Artículo no encontrado', 'error');
                });
        });

        $('#gc-tabla-lineas').on('change', '.gc-marca-linea', function () {
            var id = $(this).closest('tr').data('linea-id');
            if (!id || !estado.cuentaId) {
                return;
            }
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
            estado.lineaSeleccionadaId = parseInt($(this).closest('tr').data('linea-id'), 10) || null;
            voidLineaSeleccionada();
        });

        $('#gc-btn-f1').on('click', consultarArticulo);
        $('#gc-btn-f2').on('click', function () { cobrar('hotel'); });
        $('#gc-btn-f5').on('click', function () { cobrar('efectivo'); });
        $('#gc-btn-f6').on('click', voidLineaSeleccionada);
        $('#gc-btn-f8').on('click', function () { cobrar('interna'); });
        $('#gc-btn-f9').on('click', function () { cobrar('factura_sola'); });
        $('#gc-btn-f10').on('click', descuentoMesa);
        $('#gc-btn-f11').on('click', obsItem);
        $('#gc-btn-f12').on('click', function () { marcha('normal', false); });

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
