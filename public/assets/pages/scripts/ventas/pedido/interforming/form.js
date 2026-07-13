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

    $(function () {
        if (!formPedidoIf()) {
            return;
        }

        activarConsultas();
        registrarAtajosF1();
        registrarEnterNavigation();
        enfocarCodigoClienteAlCargar();

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

        $(document).on('input change', 'input[name*="[porc_fason]"], input[name*="[precio]"]', function () {
            var $tr = $(this).closest('tr');
            var precio = parseFloat($tr.find('input[name*="[precio]"]').val() || '0') || 0;
            var porc = parseFloat($tr.find('input[name*="[porc_fason]"]').val() || '0') || 0;
            var precioFason = porc > 0 ? Math.round(precio * (porc / 100) * 1e6) / 1e6 : 0;
            $tr.find('input[name*="[precio_fason]"]').val(precioFason);
            $tr.find('input[name*="[partida]"]').val(porc > 0 ? 1 : 0);
        });
    });
})(jQuery);
