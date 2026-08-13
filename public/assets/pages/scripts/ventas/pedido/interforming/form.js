(function ($) {
    'use strict';

    var FORM_ID = 'form-pedido-interforming';

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function esTeclaEnter(e) {
        return e.key === 'Enter' || e.which === 13 || e.keyCode === 13;
    }

    function modalAbierto(selector) {
        var m = document.querySelector(selector);
        return !!(m && (m.classList.contains('show') || m.classList.contains('in')));
    }

    function hayModalAbierto() {
        return document.querySelector('.modal.show, .modal.in') !== null;
    }

    function formPedidoIf() {
        return document.getElementById(FORM_ID);
    }

    function esCampoEnfocable(el) {
        if (!el || el.tagName === 'TEXTAREA') {
            return false;
        }
        if (el.matches('input[type="hidden"], [readonly], [disabled], button')) {
            return false;
        }
        if (!el.matches('input, select')) {
            return false;
        }
        return el.offsetParent !== null;
    }

    function obtenerCamposEnfocables() {
        var form = formPedidoIf();
        if (!form) {
            return [];
        }
        var nodos = form.querySelectorAll(
            'input:not([type="hidden"]):not([readonly]):not([disabled]), select:not([disabled])'
        );
        return Array.prototype.filter.call(nodos, esCampoEnfocable);
    }

    function enfocarCampo(el) {
        if (!el || !esCampoEnfocable(el)) {
            return;
        }
        el.focus();
        if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'number' || el.type === '' || el.type === 'date')) {
            if (typeof el.select === 'function') {
                el.select();
            }
        }
    }

    function siguienteCampo(actual) {
        var campos = obtenerCamposEnfocables();
        var indice = campos.indexOf(actual);
        if (indice >= 0 && indice < campos.length - 1) {
            return campos[indice + 1];
        }
        return null;
    }

    function abrirConsultaDesdeBotonCercano($input, selectorBoton) {
        var $btn = $input.closest('.form-group, .input-group, td, tr, .tm-vendedor-campo, .tm-transporte-campo, .tm-deposito-campo')
            .find(selectorBoton)
            .first();
        if (!$btn.length) {
            $btn = $(selectorBoton).first();
        }
        if ($btn.length) {
            $btn.trigger('click');
        }
    }

    function registrarAtajosF1() {
        document.addEventListener('keydown', function (e) {
            if (!esTeclaF1(e)) {
                return;
            }
            var form = formPedidoIf();
            var target = e.target;
            if (!form || !target || !form.contains(target)) {
                return;
            }
            if (target.readOnly || target.disabled) {
                return;
            }

            if (target.classList.contains('codigocliente') || target.id === 'codigocliente') {
                if (modalAbierto('#consultaclienteModal')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                abrirConsultaDesdeBotonCercano($(target), '.consultacliente');
                return;
            }

            if (target.classList.contains('codigovendedor') || target.id === 'codigovendedor') {
                if (modalAbierto('#consultavendedorModal')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                abrirConsultaDesdeBotonCercano($(target), '.consultavendedor');
                return;
            }

            if (target.classList.contains('codigotransporte') || target.id === 'codigotransporte') {
                if (modalAbierto('#consultatransporteModal')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                abrirConsultaDesdeBotonCercano($(target), '.consultatransporte');
                return;
            }

            if (target.classList.contains('codigodeposito')) {
                if (modalAbierto('#consultadepositoModal')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                abrirConsultaDesdeBotonCercano($(target), '.consultadeposito');
                return;
            }

            if (target.classList.contains('codigoarticulo')) {
                if (modalAbierto('#consultaarticuloModal')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                abrirConsultaDesdeBotonCercano($(target), '.consultaarticulo');
            }
        }, true);
    }

    function esCampoCodigoConsulta(el) {
        if (!el || !el.classList) {
            return false;
        }
        return el.classList.contains('codigocliente')
            || el.classList.contains('codigovendedor')
            || el.classList.contains('codigotransporte')
            || el.classList.contains('codigodeposito')
            || el.classList.contains('codigoarticulo');
    }

    function avanzarConEnter(e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        var form = formPedidoIf();
        var target = e.target;
        if (!form || !target || !form.contains(target)) {
            return;
        }
        if (hayModalAbierto()) {
            return;
        }
        if (target.tagName === 'TEXTAREA') {
            return;
        }
        if (!esCampoEnfocable(target) && !esCampoCodigoConsulta(target)) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (esCampoCodigoConsulta(target)) {
            $(target).trigger('change');
            window.setTimeout(function () {
                var next = siguienteCampo(target);
                if (next) {
                    enfocarCampo(next);
                }
            }, 120);
            return;
        }

        var next = siguienteCampo(target);
        if (next) {
            enfocarCampo(next);
        }
    }

    function registrarEnterNavigation() {
        var form = formPedidoIf();
        if (!form || form.dataset.pedidoIfEnterNav) {
            return;
        }
        form.dataset.pedidoIfEnterNav = '1';
        form.addEventListener('keydown', avanzarConEnter, true);
    }

    function renumerarItems() {
        $('#tbody-items-pedido-if tr.fila-item-pedido-if').each(function (i) {
            $(this).find('.item-numero').val(i + 1);
            $(this).find('input, select').each(function () {
                var name = $(this).attr('name');
                if (!name) {
                    return;
                }
                $(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + i + ']'));
            });
        });
    }

    function agregarFila() {
        var tpl = $('#tpl-fila-item-pedido-if').html();
        if (!tpl) {
            return;
        }
        var idx = $('#tbody-items-pedido-if tr.fila-item-pedido-if').length;
        tpl = tpl.replace(/__IDX__/g, String(idx)).replace(/__NUM__/g, String(idx + 1));
        $('#tbody-items-pedido-if').append(tpl);
        renumerarItems();
        if (typeof window.activa_eventos_consultaarticulo === 'function') {
            window.activa_eventos_consultaarticulo();
        }
        var $nuevo = $('#tbody-items-pedido-if tr.fila-item-pedido-if').last().find('.codigoarticulo');
        if ($nuevo.length) {
            enfocarCampo($nuevo[0]);
        }
    }

    function aplicarVendedorDesdeCliente(data) {
        if (!data || !data.vendedor_id) {
            return;
        }
        var codigo = (data.vendedores && data.vendedores.codigo) ? data.vendedores.codigo : (data.vendedor_codigo || '');
        var nombre = (data.vendedores && data.vendedores.nombre) ? data.vendedores.nombre : (data.vendedor_nombre || '');
        if (codigo) {
            $('#codigovendedor').val(codigo).trigger('change');
            return;
        }
        $('#vendedor_id').val(data.vendedor_id);
        if (nombre) {
            $('#nombrevendedor').val(nombre);
        }
    }

    window.aplicarVendedorDesdeCliente = aplicarVendedorDesdeCliente;
    window.aplicarVendedorPedidoDesdeCliente = aplicarVendedorDesdeCliente;

    /* --- Cliente: condicion / expreso / lugar (a-pedido.c) --- */

    function actualizarEstadoRequeridoLugarEntrega() {
        var obligatorio = $('#fl_cliente_tiene_entrega').val() === '1';
        var seleccionado = !!$('#cliente_entrega_id').val();

        $('#label-lugarentrega').toggleClass('requerido', obligatorio);
        $('#aviso-lugarentrega-obligatorio').toggle(obligatorio && !seleccionado);
        $('#lugarentrega').toggleClass('is-invalid', obligatorio && !seleccionado);
    }

    function limpiarLugarEntregaCliente() {
        $('#cliente_entrega_id').val('');
        $('#entrega_nombre').val('');
        $('#lugarentrega').val('');
        actualizarEstadoRequeridoLugarEntrega();
    }

    function aplicarLugarEntregaCliente(entrega) {
        if (!entrega) {
            return;
        }

        $('#cliente_entrega_id').val(entrega.id);
        $('#cliente_entrega_id_previa').val(entrega.id);
        $('#entrega_nombre').val(entrega.nombre || '');
        $('#lugarentrega').val(entrega.nombre || '').prop('readonly', true);

        // Anita: el expreso del lugar de entrega pisa el del cliente
        if (entrega.transporte_id) {
            $('#transporte_id').val(entrega.transporte_id);
            if (entrega.transporte_codigo) {
                $('#codigotransporte').val(entrega.transporte_codigo);
                $('#nombretransporte').val(entrega.transporte_nombre || '');
            } else {
                $('#codigotransporte').trigger('change');
            }
        }

        actualizarEstadoRequeridoLugarEntrega();
    }

    function renderFilasModalEntrega(entregas) {
        var html = '';
        $.each(entregas, function (index, value) {
            html += '<tr>';
            html += '<td class="nombre">' + (value.nombre || '') + '</td>';
            html += '<td class="domicilio">' + (value.domicilio || '') + '</td>';
            html += '<td class="localidad">' + (value.localidad || '') + '</td>';
            html += '<td class="provincia">' + (value.provincia || '') + '</td>';
            html += '<td class="text-nowrap"><button type="button" class="btn btn-warning btn-sm eligelugarentrega" data-id="'
                + value.id + '">Elegir</button></td>';
            html += '</tr>';
        });
        $('#datosclienteentrega').html(html);
    }

    function mostrarModalSeleccionEntrega(entregas) {
        if (!entregas || !entregas.length) {
            return;
        }
        renderFilasModalEntrega(entregas);
        $('#seleccionclienteentregaModal').modal('show');
    }

    function completarCliente_Entrega(clienteId) {
        window._entregasClienteActual = [];

        $.get(carpetaBase + '/ventas/leercliente_entrega/' + clienteId, function (data) {
            var entr = $.map(data, function (value) {
                return [value];
            });

            window._entregasClienteActual = entr;
            var flTieneEntrega = entr.length > 0;
            $('#fl_cliente_tiene_entrega').val(flTieneEntrega ? '1' : '0');

            if (!flTieneEntrega) {
                $('#cliente_entrega_id').val('');
                $('#entrega_nombre').val('');
                $('#div-cambiar-lugarentrega').hide();
                $('#lugarentrega').prop('readonly', false).attr('placeholder', '');

                $.get(carpetaBase + '/ventas/leercliente/' + clienteId, function (clienteData) {
                    $('#lugarentrega').val(clienteData.lugarentrega || '');
                });
                actualizarEstadoRequeridoLugarEntrega();
                return;
            }

            $('#lugarentrega').prop('readonly', true).attr('placeholder', 'Seleccione un lugar de entrega del cliente');

            if (entr.length === 1) {
                aplicarLugarEntregaCliente(entr[0]);
                $('#div-cambiar-lugarentrega').hide();
                actualizarEstadoRequeridoLugarEntrega();
                return;
            }

            $('#div-cambiar-lugarentrega').show();

            var entregaPreviaId = $('#cliente_entrega_id_previa').val() || $('#cliente_entrega_id').val();
            if (entregaPreviaId) {
                var entregaPrevia = null;
                $.each(entr, function (index, value) {
                    if (String(value.id) === String(entregaPreviaId)) {
                        entregaPrevia = value;
                    }
                });
                if (entregaPrevia) {
                    aplicarLugarEntregaCliente(entregaPrevia);
                    actualizarEstadoRequeridoLugarEntrega();
                    return;
                }
            }

            limpiarLugarEntregaCliente();
            actualizarEstadoRequeridoLugarEntrega();
            mostrarModalSeleccionEntrega(entr);
        });
    }

    function asignaDatosCliente(clienteId, flCambioCliente) {
        if (!clienteId || !$.isNumeric(clienteId) || parseInt(clienteId, 10) <= 0) {
            return;
        }

        $.get(carpetaBase + '/ventas/leercliente/' + clienteId, function (data) {
            var transporteId = data.transporte_id == null ? '' : data.transporte_id;
            var condicionventaId = data.condicionventa_id;
            var descuento = data.descuento;
            var lugarentrega = data.lugarentrega;
            var zonavtaId = data.zonavta_id;
            var transporteCliente = data.transportes || null;
            var codigotransporte = transporteCliente && transporteCliente.codigo ? transporteCliente.codigo : '';
            var nombretransporte = transporteCliente && transporteCliente.nombre ? transporteCliente.nombre : '';

            if (!flCambioCliente) {
                return;
            }

            aplicarVendedorDesdeCliente(data);

            if (!$('#cliente_entrega_id').val()) {
                $('#transporte_id').val(transporteId);
                $('#codigotransporte').val(codigotransporte);
                $('#nombretransporte').val(nombretransporte);
            }

            if (condicionventaId) {
                $('#condicionventa_id').val(condicionventaId);
            }

            if ($('#descuento').length) {
                $('#descuento').val(descuento != null ? descuento : 0);
            }

            if ($('#fl_cliente_tiene_entrega').val() !== '1') {
                $('#lugarentrega').val(lugarentrega || '');
            }

            if ($('#zonavta_id').length && zonavtaId) {
                $('#zonavta_id').val(zonavtaId);
            }
        });
    }

    function completaDatosCliente() {
        var clienteId = $('#cliente_id').val();
        if (!clienteId || !$.isNumeric(clienteId) || parseInt(clienteId, 10) <= 0) {
            return;
        }
        completarCliente_Entrega(clienteId);
        asignaDatosCliente(clienteId, true);
    }

    window.completaDatosCliente = completaDatosCliente;
    window.asignaDatosCliente = asignaDatosCliente;
    window.completarCliente_Entrega = completarCliente_Entrega;

    /* --- Precio por lista del cliente (a-pedido.c: clim_lista_precio / stkp) --- */

    window.asignaPrecio = function (ptr, Particulo_id, Ptalle_id) {
        var codigocliente = ($('#codigocliente').val() || '').trim();
        var $tr = $(ptr).closest('tr');
        var articuloId = $tr.find('.articulo_id').val() || Particulo_id;

        if (!codigocliente || !articuloId) {
            return;
        }

        $.get(carpetaBase + '/stock/asignapreciocliente/' + articuloId + '/' + encodeURIComponent(codigocliente), function (data) {
            var precio;
            var listaprecioId;
            var incluyeimpuesto;
            var monedaId;

            $.each($.map(data, function (value) { return [value]; }), function (index, value) {
                precio = parseFloat(value.precio);
                listaprecioId = value.listaprecio_id;
                incluyeimpuesto = value.incluyeimpuesto;
                monedaId = value.moneda_id;
            });

            if (typeof window.listaprecioIdEsValidoLineaVentas === 'function'
                && !window.listaprecioIdEsValidoLineaVentas(listaprecioId)) {
                var skuPrecio = ($tr.find('.codigoarticulo').first().val() || '').trim();
                if (typeof window.limpiarLineaArticuloSinListaprecio === 'function') {
                    window.limpiarLineaArticuloSinListaprecio($tr, skuPrecio);
                }
                return;
            }

            if (typeof precio === 'number' && !isNaN(precio)) {
                var precioRedondeado = (typeof redondearDecimales === 'function')
                    ? redondearDecimales(precio, 2)
                    : precio;
                $tr.find('.precio').val(precioRedondeado);
            } else {
                $tr.find('.precio').val(precio);
            }

            $tr.find('.listaprecio_id').val(listaprecioId);
            $tr.find('.incluyeimpuesto').val(incluyeimpuesto);
            $tr.find('.moneda_id').val(monedaId);

            var porc = parseFloat($tr.find('input[name*="[porc_fason]"]').val() || '0') || 0;
            var precioBase = parseFloat($tr.find('.precio').val() || '0') || 0;
            var precioFason = porc > 0 ? Math.round(precioBase * (porc / 100) * 1e6) / 1e6 : 0;
            $tr.find('input[name*="[precio_fason]"]').val(precioFason);
            $tr.find('input[name*="[partida]"]').val(porc > 0 ? 1 : 0);
        });
    };

    /* --- Cantidad alternativa (a-pedido.c asigna_umd: cant * stkm_peso_aprox) --- */

    function abreviaturaUmFila($tr) {
        var $opt = $tr.find('.unidadmedida_id option:selected');
        var abr = (($opt.data('abreviatura') || $opt.text() || $tr.find('.umd_abreviatura').val() || '') + '').trim();
        return abr;
    }

    function esUmKilo(abreviatura) {
        var a = (abreviatura || '').trim();
        return a.charAt(0) === 'k' || a.charAt(0) === 'K';
    }

    function convertirCantidadAlternativa($tr) {
        var cant = parseFloat($tr.find('.cantidad').val() || '0') || 0;
        var coef = parseFloat($tr.find('.coeficienteconversion').val() || '0') || 0;
        var umAlterId = $tr.find('.unidadmedida_alter_id').val();

        if (!umAlterId && coef <= 0) {
            return;
        }

        var peso = coef > 0 ? coef : 1;
        var cantAlter;
        if (esUmKilo(abreviaturaUmFila($tr))) {
            cantAlter = peso !== 0 ? (cant / peso) : cant;
        } else {
            cantAlter = cant * peso;
        }

        if (typeof redondearDecimales === 'function') {
            cantAlter = redondearDecimales(cantAlter, 6);
        } else {
            cantAlter = Math.round(cantAlter * 1e6) / 1e6;
        }

        $tr.find('.cantidad_alter').val(cantAlter);
    }

    function aplicarDatosArticuloAFila(dataArticulo, $tr) {
        if (!dataArticulo || !$tr || !$tr.length) {
            return;
        }

        if (dataArticulo.unidadmedida_id) {
            $tr.find('.unidadmedida_id').val(dataArticulo.unidadmedida_id);
        }

        var abr = '';
        if (dataArticulo.unidadesdemedidas && dataArticulo.unidadesdemedidas.abreviatura) {
            abr = dataArticulo.unidadesdemedidas.abreviatura;
        } else if (dataArticulo.unidadmedida && dataArticulo.unidadmedida.abreviatura) {
            abr = dataArticulo.unidadmedida.abreviatura;
        }
        $tr.find('.umd_abreviatura').val(abr);

        var coef = parseFloat(dataArticulo.coeficienteconversion || 0) || 0;
        $tr.find('.coeficienteconversion').val(coef > 0 ? coef : '');

        var umAlter = dataArticulo.unidadmedidaalternativa_id || '';
        $tr.find('.unidadmedida_alter_id').val(umAlter || '');

        convertirCantidadAlternativa($tr);
    }

    window.onArticuloSeleccionado = function (dataArticulo, ctx) {
        var $tr = (ctx && ctx.row && ctx.row.length) ? ctx.row : null;
        if (!$tr || !$tr.hasClass('fila-item-pedido-if')) {
            $tr = $('#tbody-items-pedido-if tr.fila-item-pedido-if').filter(function () {
                return String($(this).find('.articulo_id').val()) === String(dataArticulo.id);
            }).last();
        }
        if ($tr && $tr.length) {
            aplicarDatosArticuloAFila(dataArticulo, $tr);
        }
    };

    function validarLugarEntregaAntesGuardar() {
        if ($('#fl_cliente_tiene_entrega').val() === '1' && !$('#cliente_entrega_id').val()) {
            actualizarEstadoRequeridoLugarEntrega();
            alert('Debe seleccionar un lugar de entrega del cliente.');
            mostrarModalSeleccionEntrega(window._entregasClienteActual || []);
            $('#lugarentrega').focus();
            return false;
        }
        return true;
    }

    window.enfocarCampoTrasClienteCargado = function () {
        if ($('#codigovendedor').length) {
            $('#codigovendedor').focus().select();
            return;
        }
        if ($('#codigotransporte').length) {
            $('#codigotransporte').focus().select();
        }
    };

    window.pedidoInterformingValidarSubmit = function () {
        if (!validarLugarEntregaAntesGuardar()) {
            return false;
        }

        if (typeof window.validarListaprecioLineasFormularioVentas === 'function') {
            if (!window.validarListaprecioLineasFormularioVentas('#tbody-items-pedido-if tr.fila-item-pedido-if')) {
                return false;
            }
        }

        var ok = true;
        var filas = 0;
        $('#tbody-items-pedido-if tr.fila-item-pedido-if').each(function () {
            var art = $(this).find('.articulo_id').val();
            var cant = parseFloat($(this).find('input[name*="[cantidad]"]').val() || '0');
            if (art && cant > 0) {
                filas++;
            } else if (art || cant > 0) {
                ok = false;
            }
        });
        if (!ok || filas < 1) {
            alert('Debe cargar al menos un ítem con artículo y cantidad.');
            return false;
        }
        if (!$('#cliente_id').val()) {
            alert('Debe indicar el cliente.');
            $('#codigocliente').focus();
            return false;
        }
        if (!$('#vendedor_id').val()) {
            alert('Debe indicar el vendedor.');
            $('#codigovendedor').focus();
            return false;
        }
        return true;
    };

    function activarConsultas() {
        if (typeof window.activa_eventos_consultacliente === 'function') {
            window.activa_eventos_consultacliente();
        }
        if (typeof window.activa_eventos_consultavendedor === 'function') {
            window.activa_eventos_consultavendedor();
        }
        if (typeof window.activa_eventos_consultatransporte === 'function') {
            window.activa_eventos_consultatransporte();
        }
        if (typeof window.activa_eventos_consultaarticulo === 'function') {
            window.activa_eventos_consultaarticulo();
        }
        if (typeof window.activa_eventos_consultadeposito === 'function') {
            window.activa_eventos_consultadeposito();
        }
    }

    function enfocarCodigoClienteAlCargar() {
        window.setTimeout(function () {
            var el = document.getElementById('codigocliente');
            if (!el || el.disabled || el.readOnly || el.offsetParent === null) {
                return;
            }
            enfocarCampo(el);
        }, 150);
    }

    function inicializarEntregaSiEdicion() {
        var clienteId = $('#cliente_id').val();
        if (!clienteId || !$.isNumeric(clienteId) || parseInt(clienteId, 10) <= 0) {
            return;
        }
        $.get(carpetaBase + '/ventas/leercliente_entrega/' + clienteId, function (data) {
            var entr = $.map(data, function (value) {
                return [value];
            });
            window._entregasClienteActual = entr;
            var flTieneEntrega = entr.length > 0;
            $('#fl_cliente_tiene_entrega').val(flTieneEntrega ? '1' : '0');
            if (flTieneEntrega) {
                $('#lugarentrega').prop('readonly', true);
                if (entr.length > 1) {
                    $('#div-cambiar-lugarentrega').show();
                }
            }
            actualizarEstadoRequeridoLugarEntrega();
        });
    }

    $(function () {
        if (!formPedidoIf()) {
            return;
        }

        activarConsultas();
        registrarAtajosF1();
        registrarEnterNavigation();
        enfocarCodigoClienteAlCargar();
        inicializarEntregaSiEdicion();

        $('#btn-agregar-item-pedido-if').on('click', function () {
            agregarFila();
        });

        $(document).on('click', '.btn-quitar-item-pedido-if', function () {
            var $tbody = $('#tbody-items-pedido-if');
            if ($tbody.find('tr.fila-item-pedido-if').length <= 1) {
                return;
            }
            $(this).closest('tr').remove();
            renumerarItems();
        });

        $(document).on('input change', 'input[name*="[porc_fason]"], #tbody-items-pedido-if .precio', function () {
            var $tr = $(this).closest('tr');
            var precio = parseFloat($tr.find('.precio').val() || '0') || 0;
            var porc = parseFloat($tr.find('input[name*="[porc_fason]"]').val() || '0') || 0;
            var precioFason = porc > 0 ? Math.round(precio * (porc / 100) * 1e6) / 1e6 : 0;
            $tr.find('input[name*="[precio_fason]"]').val(precioFason);
            $tr.find('input[name*="[partida]"]').val(porc > 0 ? 1 : 0);
        });

        $(document).on('input change', '#tbody-items-pedido-if .cantidad, #tbody-items-pedido-if .unidadmedida_id', function () {
            convertirCantidadAlternativa($(this).closest('tr'));
        });

        $(document).on('click', '#btn-cambiar-lugarentrega', function () {
            mostrarModalSeleccionEntrega(window._entregasClienteActual || []);
        });

        $(document).on('click', '.eligelugarentrega', function () {
            var entregaId = $(this).data('id');
            var entrega = null;
            $.each(window._entregasClienteActual || [], function (index, value) {
                if (String(value.id) === String(entregaId)) {
                    entrega = value;
                }
            });
            if (entrega) {
                aplicarLugarEntregaCliente(entrega);
                $('#seleccionclienteentregaModal').modal('hide');
            }
        });
    });
})(jQuery);
