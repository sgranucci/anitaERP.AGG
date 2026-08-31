/**
 * Elección de CBU de pago al proveedor (múltiples cuentas en proveedor_formapago).
 * Uso: incluir modal + campo; llamar activa_eventos_consulta_cbu_pago().
 * Dispara change en #proveedor_id → carga CBUs; si hay >1 abre modal.
 */
(function (window, $) {
    'use strict';

    var urlCbusTpl = null;
    var cacheCbus = {};
    var abriendoModal = false;

    function urlCbus(proveedorId) {
        if (!urlCbusTpl) {
            urlCbusTpl = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') +
                '/compras/proveedor/__ID__/cbus-pago';
        }
        return urlCbusTpl.replace('__ID__', String(proveedorId));
    }

    function setCbu(id, cbu, etiqueta) {
        $('#proveedor_formapago_id').val(id || '');
        $('#cbu_pago').val(cbu || '');
        $('#cbu_pago_mostrar').val(cbu || '');
        $('#cbu_pago_etiqueta').text(etiqueta || '');
        $('#cbu_pago_aviso').addClass('d-none').text('');
        $(document).trigger('change.cbuPagoElegido', [{ id: id, cbu: cbu, etiqueta: etiqueta }]);
    }

    function limpiarCbu() {
        setCbu('', '', '');
    }

    function mostrarAviso(msg) {
        var $a = $('#cbu_pago_aviso');
        if (!$a.length) return;
        if (msg) {
            $a.removeClass('d-none').text(msg);
        } else {
            $a.addClass('d-none').text('');
        }
    }

    function renderModal(cbus, nombreProveedor) {
        $('#modal-cbu-pago-proveedor-nombre').text(nombreProveedor || '');
        var $tb = $('#tbody-cbu-pago').empty();
        if (!cbus || !cbus.length) {
            $tb.append('<tr><td colspan="5" class="text-center text-muted">El proveedor no tiene CBU válido de transferencia.</td></tr>');
            return;
        }
        cbus.forEach(function (row) {
            var $tr = $('<tr/>');
            $tr.append($('<td/>').text(row.formapago || 'Transfer.'));
            $tr.append($('<td/>').text(row.banco || ''));
            $tr.append($('<td/>').text(row.titular || ''));
            $tr.append($('<td class="text-monospace"/>').text(row.cbu || ''));
            var $btn = $('<button type="button" class="btn btn-warning btn-sm elige-cbu-pago"/>')
                .text('Elegir')
                .data('id', row.id)
                .data('cbu', row.cbu)
                .data('etiqueta', row.etiqueta || '');
            $tr.append($('<td class="text-nowrap"/>').append($btn));
            $tb.append($tr);
        });
    }

    function abrirModal(cbus, nombreProveedor) {
        if (abriendoModal) return;
        abriendoModal = true;
        renderModal(cbus, nombreProveedor);
        var $m = $('#modal-consulta-cbu-pago');
        $m.one('hidden.bs.modal', function () { abriendoModal = false; });
        $m.modal('show');
    }

    function aplicarLista(proveedorId, data, forzarModal) {
        var cbus = (data && data.cbus) ? data.cbus : [];
        cacheCbus[proveedorId] = cbus;
        var nombre = ($('#descripcionproveedor').val() || $('#nombreproveedor').val() || '').trim();

        if (cbus.length === 0) {
            limpiarCbu();
            mostrarAviso('Proveedor sin CBU de transferencia válido. Complete formas de pago del proveedor.');
            return;
        }
        if (cbus.length === 1 && !forzarModal) {
            setCbu(cbus[0].id, cbus[0].cbu, cbus[0].etiqueta);
            return;
        }
        // Más de uno (o forzar lupa): modal
        if (!forzarModal && $('#cbu_pago').val()) {
            // Ya hay uno elegido (edición); no forzar modal al recargar
            var actual = $('#cbu_pago').val();
            var match = cbus.filter(function (c) { return c.cbu === actual; })[0];
            if (match) {
                setCbu(match.id, match.cbu, match.etiqueta);
                return;
            }
        }
        abrirModal(cbus, nombre);
    }

    function cargarCbusProveedor(proveedorId, forzarModal) {
        proveedorId = parseInt(proveedorId || '0', 10);
        if (proveedorId <= 0) {
            limpiarCbu();
            return;
        }
        if (!forzarModal && cacheCbus[proveedorId]) {
            aplicarLista(proveedorId, { cbus: cacheCbus[proveedorId] }, forzarModal);
            return;
        }
        $.getJSON(urlCbus(proveedorId))
            .done(function (data) {
                aplicarLista(proveedorId, data || {}, !!forzarModal);
            })
            .fail(function () {
                mostrarAviso('No se pudieron leer los CBU del proveedor.');
            });
    }

    window.activa_eventos_consulta_cbu_pago = function () {
        $(document)
            .off('change.cbuPagoProv', '#proveedor_id')
            .on('change.cbuPagoProv', '#proveedor_id', function () {
                var id = parseInt($(this).val() || '0', 10);
                // Al cambiar proveedor, blanquear y resolver (modal si >1)
                limpiarCbu();
                delete cacheCbus[id];
                cargarCbusProveedor(id, false);
            });

        // Tras elegir del modal de proveedores (varios módulos disparan change)
        $(document)
            .off('change.cpProveedorCargado.cbuPago')
            .on('change.cpProveedorCargado.cbuPago', '#proveedor_id', function () {
                var id = parseInt($(this).val() || '0', 10);
                if (id > 0 && !$('#cbu_pago').val()) {
                    cargarCbusProveedor(id, false);
                }
            });

        $(document)
            .off('click.cbuPagoElige', '.elige-cbu-pago')
            .on('click.cbuPagoElige', '.elige-cbu-pago', function () {
                var $b = $(this);
                setCbu($b.data('id'), $b.data('cbu'), $b.data('etiqueta'));
                $('#modal-consulta-cbu-pago').modal('hide');
                // Evitar blur/alert del código proveedor sobre el backdrop
                setTimeout(function () {
                    if (typeof window.liberarPantallaModalesBloqueados === 'function') {
                        window.liberarPantallaModalesBloqueados();
                    }
                }, 0);
            });

        $('#btn-consulta-cbu-pago').off('click.cbuPago').on('click.cbuPago', function (e) {
            e.preventDefault();
            var id = parseInt($('#proveedor_id').val() || '0', 10);
            if (id <= 0) {
                alert('Seleccione primero un proveedor.');
                return;
            }
            cargarCbusProveedor(id, true);
        });

        $('#btn-limpiar-cbu-pago').off('click.cbuPago').on('click.cbuPago', function (e) {
            e.preventDefault();
            limpiarCbu();
        });

        // Si ya hay proveedor al cargar (edición / SP), resolver sin forzar modal si ya hay cbu
        var inicial = parseInt($('#proveedor_id').val() || '0', 10);
        if (inicial > 0) {
            cargarCbusProveedor(inicial, false);
        }
    };

    window.ProveedorCbuPago = {
        setCbu: setCbu,
        limpiar: limpiarCbu,
        cargar: cargarCbusProveedor,
    };
})(window, jQuery);
