(function ($) {
    'use strict';

    var cola = [];
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

    function renderLista() {
        var $lista = $('#ac_lista_pendientes').empty();
        cola.forEach(function (item, i) {
            var $row = $('<div class="ac-item"/>')
                .toggleClass('ac-actual', i === indice)
                .html(
                    '<strong>' + $('<div/>').text(item.sku || '').html() + '</strong> — ' +
                    $('<div/>').text(item.descripcion || '').html()
                );
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
                    'Vas de a uno: elegí la compra si hay varias, grabá o tocá <em>Siguiente sin grabar</em>. ' +
                    'Abrí <em>Ver cola pendiente</em> para ver el listado completo.'
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

    function guardar(codigo, proveedorId) {
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
        if (compra && compra.tiene_codigo) {
            setEstado('Esa compra ya tiene código de proveedor (' + (compra.codigobarra || '') + '). Elegí otra.', true);
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

        $.ajax({
            url: window.AC_URLS.guardar,
            method: 'POST',
            data: payload,
        }).done(function (resp) {
            if (resp && resp.necesita_proveedor) {
                abrirModalProveedor(codigo);
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
            setEstado('Guardado: ' + codigo, false);
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
        $('#ac_btn_cargar').on('click', cargarPendientes);
        $('#ac_btn_saltar').on('click', saltar);
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
            $('#ac_panel_trabajo').hide();
            $('#ac_vacio').hide();
            setEstado('');
        });
    });
})(jQuery);
