(function ($) {
    'use strict';

    var cola = [];
    var catalogoCom = [];
    var indice = 0;
    var guardando = false;
    var codigoPendienteProveedor = '';
    var proveedoresCache = [];

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function depositoId() {
        return parseInt($('#deposito_ac_id').val(), 10) || 0;
    }

    function setEstado(texto, esError) {
        var $e = $('#ac_estado');
        $e.text(texto || '');
        $e.toggleClass('text-danger', !!esError);
        $e.toggleClass('text-success', !esError && !!texto);
    }

    function actual() {
        return cola[indice] || null;
    }

    function escapar(texto) {
        return $('<div/>').text(texto || '').html();
    }

    function normalizarBusqueda(texto) {
        return String(texto || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function irA(i) {
        if (i < 0 || i >= cola.length) {
            return;
        }
        indice = i;
        $('#ac_buscar_articulo').val('');
        $('#ac_buscar_resultados').hide().empty();
        renderActual();
    }

    function clonarArticulo(item) {
        return $.extend(true, {}, item);
    }

    function activarArticulo(item) {
        var id = parseInt(item.articulo_id, 10) || 0;
        var i;
        for (i = 0; i < cola.length; i++) {
            if ((parseInt(cola[i].articulo_id, 10) || 0) === id) {
                irA(i);
                return;
            }
        }
        if (indice < 0 || indice > cola.length) {
            indice = 0;
        }
        cola.splice(indice, 0, item);
        $('#ac_buscar_articulo').val('');
        $('#ac_buscar_resultados').hide().empty();
        renderActual();
    }

    function coincidencias(q) {
        q = normalizarBusqueda(q);
        if (!q) {
            return [];
        }
        var partes = q.split(/\s+/).filter(Boolean);
        var out = [];
        catalogoCom.forEach(function (item) {
            var texto = normalizarBusqueda((item.sku || '') + ' ' + (item.descripcion || ''));
            var ok = partes.every(function (parte) {
                return texto.indexOf(parte) !== -1;
            });
            if (ok) {
                out.push(item);
            }
        });
        return out;
    }

    function renderBusqueda() {
        var $box = $('#ac_buscar_resultados').empty();
        var q = $('#ac_buscar_articulo').val();
        if (!normalizarBusqueda(q)) {
            $box.hide();
            return;
        }
        if (!catalogoCom.length) {
            $box.show().append($('<div class="ac-buscar-vacio"/>').text('Todavía no está la lista de COM.'));
            return;
        }
        var lista = coincidencias(q);
        if (!lista.length) {
            $box.show().append($('<div class="ac-buscar-vacio"/>').text('Ningún artículo recibido por COM en el depósito 1 coincide.'));
            return;
        }
        var tope = lista.slice(0, 20);
        tope.forEach(function (item) {
            $('<button type="button"/>')
                .html('<strong>' + escapar(item.sku) + '</strong> — ' + escapar(item.descripcion))
                .on('click', function () {
                    activarArticulo(clonarArticulo(item));
                })
                .appendTo($box);
        });
        if (lista.length > tope.length) {
            $box.append($('<div class="ac-buscar-vacio"/>').text('Hay más coincidencias. Afiná SKU o descripción.'));
        }
        $box.show();
    }

    function traerDesdeAbm(articuloId) {
        articuloId = parseInt(articuloId, 10) || 0;
        if (articuloId <= 0 || guardando) {
            return;
        }
        setEstado('Trayendo artículo del ABM…');
        $.ajax({
            url: window.AC_URLS.traerAbm,
            method: 'GET',
            data: { articulo_id: articuloId },
        }).done(function (resp) {
            if (!resp || !resp.ok || !resp.fila) {
                setEstado((resp && resp.mensaje) || 'No se pudo traer el artículo.', true);
                return;
            }
            activarArticulo(resp.fila);
            setEstado('Artículo del ABM listo para pickear.', false);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo traer el artículo.';
            setEstado(msg, true);
        });
    }

    function cargarCatalogoCom() {
        var $ayuda = $('#ac_buscar_ayuda');
        $ayuda.removeClass('text-danger').text('Cargando artículos de COM del depósito 1…');
        $.ajax({
            url: window.AC_URLS.catalogoCom,
            method: 'GET',
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                catalogoCom = [];
                $ayuda.addClass('text-danger').text((resp && resp.mensaje) || 'No se pudo armar la lista de COM.');
                return;
            }
            catalogoCom = resp.filas || [];
            var deposito = resp.deposito_nombre || 'depósito 1';
            $ayuda.removeClass('text-danger').text(
                catalogoCom.length + ' artículo(s) recibidos por COM en ' + deposito + '. La búsqueda usa esta lista.'
            );
        }).fail(function (xhr) {
            catalogoCom = [];
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo armar la lista de COM.';
            $ayuda.addClass('text-danger').text(msg);
        });
    }

    function renderLista() {
        var $lista = $('#ac_lista_pendientes').empty();
        cola.forEach(function (item, i) {
            var $row = $('<div class="ac-item" role="button" tabindex="0"/>')
                .toggleClass('ac-actual', i === indice)
                .html('<strong>' + escapar(item.sku) + '</strong> — ' + escapar(item.descripcion))
                .on('click', function () {
                    irA(i);
                });
            $lista.append($row);
        });
    }

    function renderActual() {
        var item = actual();
        if (!item) {
            $('#ac_panel_trabajo').hide();
            $('#ac_vacio').show();
            setEstado('Listo: no quedan pendientes.', false);
            return;
        }

        $('#ac_reemplazo_panel').hide();
        $('#ac_vacio').hide();
        $('#ac_panel_trabajo').show();
        $('#ac_progreso').text('Artículo ' + (indice + 1) + ' de ' + cola.length);
        $('#ac_actual_sku').text(item.sku || '');
        $('#ac_actual_desc').text(item.descripcion || '');
        $('#ac_actual_saldo').text(Number(item.saldo || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        }));

        renderCompras(item);
        renderProveedor(item);

        $('#ac_pickeo_codigo').val('').focus();
        renderLista();
    }

    function compraSeleccionada(item) {
        item = item || actual();
        var compras = (item && item.compras) || [];
        if (!compras.length) {
            return null;
        }
        var id = parseInt($('#ac_compra_select').val(), 10) || 0;
        for (var i = 0; i < compras.length; i++) {
            if (parseInt(compras[i].recepcion_id, 10) === id) {
                return compras[i];
            }
        }
        for (var j = 0; j < compras.length; j++) {
            if (compras[j].seleccionada) {
                return compras[j];
            }
        }
        return compras[0];
    }

    function renderCompras(item) {
        var $wrap = $('#ac_compra_wrap');
        var $sel = $('#ac_compra_select').empty();
        var compras = (item && item.compras) || [];
        if (!compras.length) {
            $wrap.hide();
            return;
        }
        var seleccionId = 0;
        compras.forEach(function (compra) {
            var texto = compra.etiqueta || compra.proveedor_nombre || ('Recepción ' + compra.recepcion_id);
            if (compra.tiene_codigo) {
                texto += ' — ya tiene código';
            }
            if (compra.seleccionada && !seleccionId) {
                seleccionId = compra.recepcion_id;
            }
            $('<option/>')
                .val(compra.recepcion_id)
                .text(texto)
                .appendTo($sel);
        });
        if (!seleccionId && compras.length) {
            seleccionId = compras[0].recepcion_id;
        }
        $sel.val(String(seleccionId));
        $wrap.show();
    }

    function renderProveedor(item) {
        var $prov = $('#ac_actual_proveedor_wrap');
        var compra = compraSeleccionada(item);
        if (compra) {
            var nombre = compra.proveedor_nombre || '';
            if (compra.tiene_codigo) {
                $prov.html(
                    '<span class="text-warning">Proveedor: ' + $('<div/>').text(nombre).html() +
                    '. Ya tiene código ' + $('<div/>').text(compra.codigobarra || '').html() + '.</span>'
                );
            } else {
                $prov.text('Proveedor de la compra: ' + nombre);
            }
            return;
        }
        if (item.necesita_proveedor) {
            $prov.html('<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Sin compras ni vínculo proveedor: se pedirá al guardar.</span>');
        } else if (item.proveedor_nombre) {
            $prov.text('Proveedor: ' + item.proveedor_nombre);
        } else {
            $prov.text('');
        }
    }

    function proveedorParaGuardar(item, proveedorId) {
        if (proveedorId > 0) {
            return proveedorId;
        }
        var compra = compraSeleccionada(item);
        if (compra && parseInt(compra.proveedor_id, 10) > 0) {
            return parseInt(compra.proveedor_id, 10);
        }
        return parseInt(item && item.proveedor_id, 10) || 0;
    }

    function cargarPendientes() {
        var depId = depositoId();
        if (depId <= 0) {
            setEstado('Seleccioná un depósito.', true);
            return;
        }

        setEstado('Cargando…');
        $('#ac_btn_cargar').prop('disabled', true);

        $.ajax({
            url: window.AC_URLS.pendientes,
            method: 'GET',
            data: { deposito_id: depId },
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                setEstado((resp && resp.mensaje) || 'No se pudo cargar.', true);
                return;
            }
            cola = resp.filas || [];
            indice = 0;
            if (!cola.length) {
                $('#ac_panel_trabajo').hide();
                $('#ac_vacio').show();
                $('#ac_resumen_carga').hide();
                setEstado('Sin pendientes en este depósito.', false);
                return;
            }
            $('#ac_resumen_carga')
                .show()
                .html(
                    '<strong>' + cola.length + '</strong> artículo(s) con saldo y sin código de proveedor. ' +
                    'Avanzá de a uno, o buscá arriba un artículo recibido por COM en el depósito 1.'
                );
            setEstado(cola.length + ' artículo(s) pendientes.', false);
            renderActual();
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al cargar pendientes.';
            setEstado(msg, true);
        }).always(function () {
            $('#ac_btn_cargar').prop('disabled', false);
        });
    }

    function saltar() {
        if (!actual()) {
            return;
        }
        indice += 1;
        renderActual();
    }

    function llenarSelectProveedores(opciones) {
        var $sel = $('#ac_prov_select').empty();
        (opciones || []).forEach(function (op) {
            $('<option/>')
                .val(op.id)
                .text(op.etiqueta || (op.codigo + ' — ' + op.nombre))
                .appendTo($sel);
        });
    }

    function abrirModalProveedor(codigo) {
        codigoPendienteProveedor = codigo;
        setEstado('Elegí proveedor para guardar el código.', false);

        var cargar = function (q) {
            return $.ajax({
                url: window.AC_URLS.proveedores,
                method: 'GET',
                data: { q: q || '' },
            }).done(function (resp) {
                proveedoresCache = (resp && resp.opciones) || [];
                llenarSelectProveedores(proveedoresCache);
            });
        };

        cargar('').always(function () {
            $('#ac_prov_buscar').val('');
            $('#ac_modal_proveedor').modal('show');
            setTimeout(function () {
                $('#ac_prov_buscar').focus();
            }, 300);
        });
    }

    function marcarCodigoCargado(articuloId, proveedorId, codigo) {
        articuloId = parseInt(articuloId, 10) || 0;
        proveedorId = parseInt(proveedorId, 10) || 0;
        function aplicar(item) {
            if (!item || (parseInt(item.articulo_id, 10) || 0) !== articuloId) {
                return;
            }
            (item.compras || []).forEach(function (compra) {
                if (!proveedorId || (parseInt(compra.proveedor_id, 10) || 0) === proveedorId) {
                    compra.tiene_codigo = true;
                    compra.codigobarra = codigo || compra.codigobarra || '';
                }
            });
        }
        aplicar(actual());
        catalogoCom.forEach(aplicar);
    }

    function avisarYaCargado(resp, codigo) {
        if (typeof window.acCerrarCamaraPickeo === 'function') {
            window.acCerrarCamaraPickeo();
        }
        marcarCodigoCargado(
            (resp && resp.articulo_id) || (actual() && actual().articulo_id),
            resp && resp.proveedor_id,
            (resp && resp.codigobarra) || codigo
        );
        var item = actual();
        if (item) {
            renderCompras(item);
            renderProveedor(item);
        }
        setEstado((resp && resp.mensaje) || 'Ya está cargado ese código para este proveedor.', true);
    }

    function ofrecerReemplazo(codigo, proveedorId, resp) {
        var actualCodigo = (resp && (resp.codigobarra_actual || resp.codigobarra)) || '';
        pedirReemplazo(actualCodigo, codigo, function () {
            guardar(codigo, proveedorId, true);
        });
    }

    function pedirReemplazo(actualCodigo, codigoNuevo, alAceptar) {
        if (typeof window.acCerrarCamaraPickeo === 'function') {
            window.acCerrarCamaraPickeo();
        }
        guardando = false;
        $('#ac_btn_guardar').prop('disabled', false);
        $('#ac_pickeo_codigo').val(codigoNuevo || '');
        $('#ac_reemplazo_actual_inline').text(actualCodigo || '');
        $('#ac_reemplazo_nuevo_inline').text(codigoNuevo || '');
        $('#ac_btn_reemplazar').off('click').on('click', function () {
            $('#ac_reemplazo_panel').hide();
            alAceptar();
        });
        $('#ac_reemplazo_panel').show();
        setEstado('Tocá Reemplazar código para cambiar el que ya está cargado.', true);
        var panel = document.getElementById('ac_reemplazo_panel');
        if (panel && panel.scrollIntoView) {
            panel.scrollIntoView({ block: 'nearest' });
        }
    }

    function guardar(codigo, proveedorId, reemplazar) {
        var item = actual();
        if (!item || guardando) {
            return;
        }
        codigo = String(codigo || '').replace(/\s+/g, '').trim();
        if (!codigo) {
            setEstado('Leé o ingresá un código de barras.', true);
            return;
        }

        proveedorId = proveedorParaGuardar(item, proveedorId);
        var compra = compraSeleccionada(item);
        if (compra && compra.tiene_codigo && !reemplazar) {
            var cargado = String(compra.codigobarra || '').replace(/\s+/g, '').trim();
            if (cargado && cargado.toUpperCase() === codigo.toUpperCase()) {
                setEstado('Ya está cargado el código ' + cargado + ' para este proveedor.', true);
                return;
            }
            pedirReemplazo(cargado || 'anterior', codigo, function () {
                guardar(codigo, proveedorId, true);
            });
            return;
        }

        if (item.necesita_proveedor && !(proveedorId > 0)) {
            if (typeof window.acCerrarCamaraPickeo === 'function') {
                window.acCerrarCamaraPickeo();
            }
            abrirModalProveedor(codigo);
            return;
        }

        guardando = true;
        setEstado('Guardando…');
        $('#ac_btn_guardar').prop('disabled', true);

        var payload = {
            _token: csrf(),
            articulo_id: item.articulo_id,
            codigobarra: codigo,
        };
        if (proveedorId > 0) {
            payload.proveedor_id = proveedorId;
        }
        if (reemplazar) {
            payload.reemplazar = 1;
        }

        $.ajax({
            url: window.AC_URLS.guardar,
            method: 'POST',
            data: payload,
        }).done(function (resp) {
            if (resp && resp.necesita_proveedor) {
                abrirModalProveedor(codigo);
                return;
            }
            if (resp && resp.puede_reemplazar) {
                ofrecerReemplazo(codigo, proveedorId, resp);
                return;
            }
            if (resp && resp.ya_cargado) {
                avisarYaCargado(resp, codigo);
                return;
            }
            if (!resp || !resp.ok) {
                setEstado((resp && resp.mensaje) || 'No se pudo guardar.', true);
                return;
            }

            if (typeof window.acCerrarCamaraPickeo === 'function') {
                window.acCerrarCamaraPickeo();
            }
            $('#ac_modal_proveedor').modal('hide');
            marcarCodigoCargado(item.articulo_id, resp.proveedor_id || proveedorId, codigo);
            setEstado(resp.reemplazado ? (resp.mensaje || ('Reemplazado: ' + codigo)) : ('Guardado: ' + codigo), false);
            cola.splice(indice, 1);
            if (indice >= cola.length) {
                indice = Math.max(0, cola.length - 1);
            }
            renderActual();
        }).fail(function (xhr) {
            var body = xhr.responseJSON || {};
            if (body.necesita_proveedor) {
                abrirModalProveedor(codigo);
                return;
            }
            if (body.puede_reemplazar) {
                ofrecerReemplazo(codigo, proveedorId, body);
                return;
            }
            if (body.ya_cargado) {
                avisarYaCargado(body, codigo);
                return;
            }
            setEstado(body.mensaje || 'Error al guardar.', true);
        }).always(function () {
            guardando = false;
            $('#ac_btn_guardar').prop('disabled', false);
        });
    }

    window.acAplicarCodigoPickeo = function (codigo) {
        $('#ac_pickeo_codigo').val(codigo);
        if (typeof window.acCerrarCamaraPickeo === 'function') {
            window.acCerrarCamaraPickeo();
        }
        guardar(codigo, null);
    };

    $(function () {
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        window.onArticuloSeleccionado = function (data, ctx) {
            var $row = ctx && ctx.row ? $(ctx.row) : $();
            if (!$row.length || !$row.closest('#ac_abm_wrap').length && !$row.is('#ac_abm_wrap')) {
                return;
            }
            traerDesdeAbm(data && data.id);
        };
        $('#ac_abm_sku').on('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            $(this).trigger('change');
        });
        cargarCatalogoCom();
        $('#ac_btn_cargar').on('click', cargarPendientes);
        $('#ac_btn_saltar').on('click', saltar);
        $('#ac_buscar_articulo').on('input focus', renderBusqueda);
        $('#ac_buscar_articulo').on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('');
                $('#ac_buscar_resultados').hide().empty();
                return;
            }
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var lista = coincidencias($(this).val());
            if (lista.length) {
                activarArticulo(clonarArticulo(lista[0]));
            }
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.ac-buscar').length) {
                $('#ac_buscar_resultados').hide();
            }
        });
        $('#ac_btn_guardar').on('click', function () {
            guardar($('#ac_pickeo_codigo').val(), null);
        });
        $('#ac_pickeo_codigo').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                guardar($(this).val(), null);
            }
        });

        var buscaTimer = null;
        $('#ac_prov_buscar').on('input', function () {
            var q = $(this).val();
            clearTimeout(buscaTimer);
            buscaTimer = setTimeout(function () {
                $.ajax({
                    url: window.AC_URLS.proveedores,
                    method: 'GET',
                    data: { q: q },
                }).done(function (resp) {
                    llenarSelectProveedores((resp && resp.opciones) || []);
                });
            }, 280);
        });

        $('#ac_compra_select').on('change', function () {
            var item = actual();
            if (!item) {
                return;
            }
            var compra = compraSeleccionada(item);
            if (compra) {
                item.proveedor_id = parseInt(compra.proveedor_id, 10) || null;
                item.proveedor_nombre = compra.proveedor_nombre || '';
                item.recepcion_id = parseInt(compra.recepcion_id, 10) || null;
                item.necesita_proveedor = false;
            }
            renderProveedor(item);
        });

        $('#ac_prov_confirmar').on('click', function () {
            var provId = parseInt($('#ac_prov_select').val(), 10) || 0;
            if (provId <= 0) {
                setEstado('Seleccioná un proveedor.', true);
                return;
            }
            var item = actual();
            if (item) {
                item.necesita_proveedor = false;
                item.proveedor_id = provId;
            }
            guardar(codigoPendienteProveedor || $('#ac_pickeo_codigo').val(), provId);
        });

        $(document).on('change', '#deposito_ac_id', function () {
            cola = [];
            indice = 0;
            $('#ac_buscar_articulo').val('');
            $('#ac_buscar_resultados').hide().empty();
            $('#ac_panel_trabajo').hide();
            $('#ac_vacio').hide();
            setEstado('');
        });
    });
})(jQuery);
