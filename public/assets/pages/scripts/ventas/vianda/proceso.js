(function () {
    'use strict';

    var cfg = window.VIANDA || {};
    var apiBase = (cfg.rutas && cfg.rutas.apiBase) || '';
    var csrf = cfg.csrf || '';
    var previewPantalla = !!cfg.previewPantalla;

    var state = {
        usuario: null,
        menu: null,
        lineas: [],
        jornadaAbierta: false,
        puedePedir: true,
        pedidoDiarioMensaje: null,
        comentarioIdx: null,
        ultimoConsumoId: null,
    };

    function notify(tipo, msg) {
        if (window.toastr && typeof window.toastr[tipo] === 'function') {
            window.toastr[tipo](msg);
        } else if (tipo === 'error') {
            alert(msg);
        }
    }

    function req(metodo, url, data) {
        var opts = {
            method: metodo,
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        };
        if (data !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        return fetch(url, opts).then(function (r) {
            return r.json().then(function (j) {
                return { status: r.status, body: j };
            }).catch(function () {
                return { status: r.status, body: {} };
            });
        });
    }

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    // ---------- Login ----------
    function abrirLogin() {
        $('#vianda-login-error').addClass('d-none').text('');
        $('#vianda-login-codigo').val('');
        $('#vianda-login-clave').val('');
        $('#modal-vianda-login').modal('show');
    }

    function confirmarLogin() {
        var codigo = $('#vianda-login-codigo').val();
        var password = $('#vianda-login-clave').val();
        if (!codigo) {
            $('#vianda-login-error').removeClass('d-none').text('Ingrese el código de usuario.');
            return;
        }
        req('POST', apiBase + '/login', { codigo: codigo, password: password }).then(function (res) {
            if (res.status === 200 && res.body.ok) {
                $('#modal-vianda-login').modal('hide');
                aplicarSesion(res.body.usuario, res.body.menu, res.body.jornada, res.body.pedido_diario);
            } else {
                $('#vianda-login-error').removeClass('d-none').text(res.body.mensaje || 'No se pudo ingresar.');
            }
        });
    }

    function aplicarSesion(usuario, menu, jornada, pedidoDiario) {
        state.usuario = usuario;
        state.menu = menu;
        state.lineas = [];
        $('#vianda-observacion').val('');
        aplicarJornada(jornada);
        aplicarPedidoDiario(pedidoDiario);
        renderEmpleado();
        renderMenu();
        renderLineas();
        $('#vianda-shell').removeClass('vianda-bloqueado');
    }

    function aplicarPedidoDiario(pedidoDiario) {
        var pd = pedidoDiario || {};
        state.puedePedir = pd.puede_pedir !== false;
        state.pedidoDiarioMensaje = pd.mensaje || null;
        var $al = $('#vianda-pedido-diario-alerta');
        if (!state.puedePedir && state.pedidoDiarioMensaje) {
            $al.removeClass('d-none').text(state.pedidoDiarioMensaje);
            $('#vianda-shell').addClass('vianda-pedido-bloqueado');
        } else {
            $al.addClass('d-none').text('');
            $('#vianda-shell').removeClass('vianda-pedido-bloqueado');
        }
        actualizarBotonesOperacion();
    }

    function actualizarBotonesOperacion() {
        $('#vianda-btn-marchar').prop('disabled', !state.jornadaAbierta || !state.puedePedir);
    }

    function restablecerPantallaSinSesion() {
        state.usuario = null;
        state.menu = null;
        state.lineas = [];
        state.puedePedir = true;
        state.pedidoDiarioMensaje = null;
        state.ultimoConsumoId = null;
        $('#vianda-observacion').val('');
        $('#vianda-pedido-diario-alerta').addClass('d-none').text('');
        $('#vianda-shell').removeClass('vianda-pedido-bloqueado');
        renderEmpleado();
        renderMenu();
        renderLineas();
        actualizarBotonesOperacion();
        $('#vianda-shell').addClass('vianda-bloqueado');
    }

    function cerrarSesionLocal() {
        restablecerPantallaSinSesion();
        req('POST', apiBase + '/logout');
        abrirLogin();
    }

    // ---------- Jornada ----------
    function aplicarJornada(jornada) {
        state.jornadaAbierta = !!(jornada && jornada.jornada_abierta);
        var $al = $('#vianda-jornada-alerta');
        if (jornada && !jornada.jornada_abierta) {
            $al.removeClass('d-none').text(jornada.mensaje || 'No hay jornada abierta.');
        } else {
            $al.addClass('d-none').text('');
        }
        actualizarBotonesOperacion();
    }

    // ---------- Empleado ----------
    function renderEmpleado() {
        var u = state.usuario;
        if (!u) {
            $('#vianda-empleado-badge').text('Sin empleado');
            $('#vianda-empleado-detalle').text('');
            return;
        }
        $('#vianda-empleado-badge').text(u.codigo + ' — ' + u.nombre);
        var det = [];
        if (u.centrocosto) { det.push('C.Costo: ' + u.centrocosto); }
        if (u.tipo_menu) { det.push('Menú: ' + u.tipo_menu); }
        $('#vianda-empleado-detalle').text(det.join(' · '));
    }

    // ---------- Menú ----------
    function renderMenu() {
        var menu = state.menu;
        var $cont = $('#vianda-menu-contenedor');
        $('#vianda-dia-label').text(menu && menu.dia ? menu.dia.etiqueta : '—');
        $('#vianda-tipomenu-label').text(menu && menu.tipo_menu ? menu.tipo_menu.nombre : '');
        $cont.empty();

        if (!menu || !menu.grupos || !menu.grupos.length) {
            $cont.html('<p class="text-muted text-center my-4">El empleado no tiene menú definido para hoy.</p>');
            return;
        }

        menu.grupos.forEach(function (g) {
            $cont.append('<div class="vianda-grupo-titulo">' + esc(g.tipo) + '</div>');
            var $grid = $('<div class="vianda-grilla-menu"></div>');
            g.articulos.forEach(function (a) {
                var ico = a.foto_url
                    ? '<img class="vianda-ico" src="' + esc(a.foto_url) + '" alt="">'
                    : '<span class="vianda-ico"><i class="fa fa-utensils"></i></span>';
                var $card = $(
                    '<div class="vianda-tarjeta" role="button" tabindex="0">' +
                        ico +
                        '<span class="vianda-desc">' + esc(a.descripcion) + '</span>' +
                        '<span class="vianda-sku">' + esc(a.sku) + '</span>' +
                    '</div>'
                );
                $card.on('click', function () { agregarLinea(a); });
                $card.on('keypress', function (e) { if (e.which === 13) { agregarLinea(a); } });
                $grid.append($card);
            });
            $cont.append($grid);
        });
    }

    // ---------- Comanda ----------
    function agregarLinea(a) {
        if (!state.puedePedir) {
            notify('error', state.pedidoDiarioMensaje || 'Ya retiró vianda hoy.');
            return;
        }
        var existente = null;
        state.lineas.forEach(function (l) {
            if (l.articulo_id === a.articulo_id && !l.comentario) { existente = l; }
        });
        if (existente) {
            existente.cantidad += 1;
        } else {
            state.lineas.push({
                articulo_id: a.articulo_id,
                sku: a.sku,
                descripcion: a.descripcion,
                cantidad: 1,
                comentario: '',
            });
        }
        renderLineas();
    }

    function renderLineas() {
        var $tb = $('#vianda-tbody-lineas');
        $tb.empty();
        if (!state.lineas.length) {
            $tb.html('<tr><td colspan="3" class="text-muted text-center">Sin ítems</td></tr>');
            $('#vianda-total-items').text('0');
            return;
        }
        var total = 0;
        state.lineas.forEach(function (l, idx) {
            total += l.cantidad;
            var comentarioHtml = l.comentario
                ? '<div class="small text-info"><i class="fa fa-comment"></i> ' + esc(l.comentario) + '</div>'
                : '';
            var $tr = $(
                '<tr>' +
                    '<td>' + esc(l.descripcion) +
                        ' <span class="text-muted small">(' + esc(l.sku) + ')</span>' +
                        comentarioHtml +
                        '<button type="button" class="btn btn-link btn-sm p-0 vianda-comentario-btn"><i class="fa fa-comment-o"></i> comentario</button>' +
                    '</td>' +
                    '<td class="text-center">' +
                        '<div class="input-group input-group-sm justify-content-center">' +
                            '<button type="button" class="btn btn-outline-secondary vianda-menos">-</button>' +
                            '<input type="number" min="1" step="1" class="form-control vianda-qty-input" value="' + l.cantidad + '">' +
                            '<button type="button" class="btn btn-outline-secondary vianda-mas">+</button>' +
                        '</div>' +
                    '</td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger vianda-borrar"><i class="fa fa-times"></i></button></td>' +
                '</tr>'
            );
            $tr.find('.vianda-menos').on('click', function () { cambiarCantidad(idx, l.cantidad - 1); });
            $tr.find('.vianda-mas').on('click', function () { cambiarCantidad(idx, l.cantidad + 1); });
            $tr.find('.vianda-qty-input').on('change', function () { cambiarCantidad(idx, parseInt($(this).val(), 10)); });
            $tr.find('.vianda-borrar').on('click', function () { state.lineas.splice(idx, 1); renderLineas(); });
            $tr.find('.vianda-comentario-btn').on('click', function () { abrirComentario(idx); });
            $tb.append($tr);
        });
        $('#vianda-total-items').text(String(total));
    }

    function cambiarCantidad(idx, valor) {
        if (!state.lineas[idx]) { return; }
        if (isNaN(valor) || valor < 1) { valor = 1; }
        state.lineas[idx].cantidad = valor;
        renderLineas();
    }

    function abrirComentario(idx) {
        state.comentarioIdx = idx;
        var l = state.lineas[idx];
        $('#vianda-comentario-articulo').text(l.descripcion);
        $('#vianda-comentario-texto').val(l.comentario || '');
        $('#modal-vianda-comentario').modal('show');
    }

    function guardarComentario() {
        if (state.comentarioIdx != null && state.lineas[state.comentarioIdx]) {
            state.lineas[state.comentarioIdx].comentario = $('#vianda-comentario-texto').val().trim();
        }
        $('#modal-vianda-comentario').modal('hide');
        renderLineas();
    }

    // ---------- Marchar ----------
    function marchar() {
        if (!state.jornadaAbierta) {
            notify('error', 'No hay jornada abierta. No se puede marchar.');
            return;
        }
        if (!state.puedePedir) {
            notify('error', state.pedidoDiarioMensaje || 'Ya retiró vianda hoy.');
            return;
        }
        if (!state.lineas.length) {
            notify('error', 'Agregue al menos un ítem del menú.');
            return;
        }
        var payload = {
            observacion: $('#vianda-observacion').val() || '',
            lineas: state.lineas.map(function (l) {
                return { articulo_id: l.articulo_id, cantidad: l.cantidad, comentario: l.comentario || '' };
            }),
        };
        $('#vianda-procesando-overlay').removeClass('d-none');
        $('#vianda-btn-marchar').prop('disabled', true);
        req('POST', apiBase + '/marchar', payload).then(function (res) {
            $('#vianda-procesando-overlay').addClass('d-none');
            actualizarBotonesOperacion();
            if (res.status === 401 && res.body.requiere_login) {
                notify('error', res.body.mensaje || 'Sesión expirada.');
                cerrarSesionLocal();
                return;
            }
            if (res.status === 200 && res.body.ok) {
                mostrarVoucher(res.body);
            } else {
                notify('error', res.body.mensaje || 'No se pudo marchar la comanda.');
            }
        }).catch(function () {
            $('#vianda-procesando-overlay').addClass('d-none');
            actualizarBotonesOperacion();
            notify('error', 'Error de red al marchar la comanda.');
        });
    }

    function mostrarVoucher(body) {
        var c = body.consumo || {};
        var v = body.voucher || {};
        state.ultimoConsumoId = c.id || null;

        // Impresión directa (config VIANDA_VOUCHER_PREVIEW_PANTALLA=false, default): si el voucher
        // se imprimió bien no se muestra la pantalla; la terminal vuelve al login del próximo empleado.
        // Solo se abre el comprobante en pantalla si está activado el preview o si la impresión falló.
        if (!previewPantalla && v.impreso) {
            notify('success', 'Vianda ' + (c.codigo_retiro || '') + ' marchada. Voucher enviado a la impresora.');
            volverProximoEmpleado();
            return;
        }

        $('#vianda-voucher-codigo').text(c.codigo_retiro || '—');
        $('#vianda-voucher-preview').text(v.texto_preview || '');
        var $aviso = $('#vianda-voucher-aviso');
        if (!v.impreso) {
            $aviso.removeClass('d-none').text('El voucher no se pudo imprimir: ' + (v.mensaje || 'error') + '. Muestre este comprobante en pantalla o use Reimprimir.');
        } else {
            $aviso.addClass('d-none').text('');
        }
        $('#vianda-voucher-reimprimir').prop('disabled', !state.ultimoConsumoId);
        $('#modal-vianda-voucher').modal('show');
    }

    function reimprimirVoucher() {
        if (!state.ultimoConsumoId) { return; }
        var $btn = $('#vianda-voucher-reimprimir').prop('disabled', true);
        req('POST', apiBase + '/reimprimir', { consumo_id: state.ultimoConsumoId }).then(function (res) {
            $btn.prop('disabled', false);
            var v = (res.body && res.body.voucher) || {};
            if (res.status === 200 && res.body.ok) {
                var $aviso = $('#vianda-voucher-aviso');
                if (!v.impreso) {
                    $aviso.removeClass('d-none').text('El voucher no se pudo imprimir: ' + (v.mensaje || 'error') + '. Muestre este comprobante en pantalla o use Reimprimir.');
                    notify('error', v.mensaje || 'No se pudo imprimir el voucher.');
                } else {
                    $aviso.addClass('d-none').text('');
                    notify('success', 'Voucher reenviado a la impresora.');
                }
            } else {
                notify('error', (res.body && res.body.mensaje) || 'No se pudo reimprimir el voucher.');
            }
        }).catch(function () {
            $btn.prop('disabled', false);
            notify('error', 'Error de red al reimprimir el voucher.');
        });
    }

    function cerrarVoucher() {
        $('#modal-vianda-voucher').modal('hide');
        volverProximoEmpleado();
    }

    function volverProximoEmpleado() {
        // Auto-logout ya ocurrió en el server: vuelve al login para el próximo empleado.
        restablecerPantallaSinSesion();
        abrirLogin();
    }

    // ---------- Init ----------
    function init() {
        if (!cfg.tieneCfg) { return; }

        $('#modal-vianda-login').on('shown.bs.modal', function () {
            $('#vianda-login-codigo').trigger('focus').trigger('select');
        });
        $('#modal-vianda-comentario').on('shown.bs.modal', function () {
            $('#vianda-comentario-texto').trigger('focus');
        });
        $('#vianda-login-confirmar').on('click', confirmarLogin);
        $('#vianda-login-clave').on('keypress', function (e) { if (e.which === 13) { confirmarLogin(); } });
        $('#vianda-login-codigo').on('keypress', function (e) { if (e.which === 13) { $('#vianda-login-clave').trigger('focus'); } });
        $('#vianda-btn-cambiar-empleado').on('click', cerrarSesionLocal);
        $('#vianda-btn-marchar').on('click', marchar);
        $('#vianda-btn-limpiar').on('click', function () { state.lineas = []; renderLineas(); });
        $('#vianda-comentario-guardar').on('click', guardarComentario);
        $('#vianda-voucher-reimprimir').on('click', reimprimirVoucher);
        $('#vianda-voucher-cerrar').on('click', cerrarVoucher);

        req('GET', apiBase + '/estado').then(function (res) {
            if (res.status !== 200 || !res.body.ok) {
                abrirLogin();
                return;
            }
            aplicarJornada(res.body.jornada);
            if (res.body.usuario) {
                aplicarSesion(res.body.usuario, res.body.menu, res.body.jornada, res.body.pedido_diario);
            } else {
                abrirLogin();
            }
        }).catch(function () {
            abrirLogin();
        });
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
