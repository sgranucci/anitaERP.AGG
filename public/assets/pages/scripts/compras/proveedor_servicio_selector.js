/**
 * Selector de cliente/medidor (proveedor_servicio) en precarga y comprobante.
 * Escucha change.cpProveedorCargado (consulta.js) con el JSON del proveedor.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var LEYENDA_AUTO_ATTR = 'data-leyenda-servicio-auto';

    function $wrap() {
        return $('#div-proveedor-servicio');
    }

    function $select() {
        return $('#proveedor_servicio_id');
    }

    function $leyenda() {
        return $('#leyenda');
    }

    function $clienteSnap() {
        return $('#servicio_cliente');
    }

    function syncClienteSnapshot() {
        var $sel = $select();
        var $snap = $clienteSnap();
        if (!$sel.length || !$snap.length) {
            return;
        }
        var cliente = String($sel.find('option:selected').attr('data-cliente') || '').trim();
        $snap.val(cliente);
    }

    function opcionesDesdeProveedor(data) {
        var lista = (data && data.proveedor_servicios) ? data.proveedor_servicios : [];
        if (!$.isArray(lista)) {
            return [];
        }
        return lista.map(function (s) {
            var id = parseInt(s.id || 0, 10) || 0;
            var cliente = String(s.cliente || '').trim();
            var detalle = String(s.detalle || '').trim();
            var etiqueta = cliente;
            if (cliente && detalle) {
                etiqueta = cliente + ' — ' + detalle;
            } else if (!cliente && detalle) {
                etiqueta = detalle;
            } else if (!etiqueta) {
                etiqueta = '#' + id;
            }
            return {
                id: id,
                cliente: cliente,
                detalle: detalle,
                etiqueta: etiqueta,
                leyenda: ('Cliente ' + etiqueta).substring(0, 255)
            };
        }).filter(function (s) {
            return s.id > 0;
        });
    }

    function rellenarSelect(opciones, selectedId) {
        var $sel = $select();
        if (!$sel.length) {
            return;
        }
        var prev = selectedId > 0 ? String(selectedId) : String($sel.val() || '');
        $sel.empty().append($('<option>').val('').text('— Seleccionar —'));
        opciones.forEach(function (op) {
            var $opt = $('<option>')
                .val(String(op.id))
                .text(op.etiqueta)
                .attr('data-cliente', op.cliente)
                .attr('data-detalle', op.detalle)
                .attr('data-leyenda', op.leyenda);
            if (prev === String(op.id)) {
                $opt.prop('selected', true);
            }
            $sel.append($opt);
        });
        if (opciones.length === 1 && (!$sel.val() || $sel.val() === '')) {
            $sel.val(String(opciones[0].id));
        }
        // Reenganche por snapshot si el id del maestro cambió.
        if ((!$sel.val() || $sel.val() === '') && $clienteSnap().length) {
            var snap = String($clienteSnap().val() || '').trim();
            if (snap !== '') {
                $sel.find('option').each(function () {
                    if (String($(this).attr('data-cliente') || '').trim() === snap) {
                        $sel.val(String($(this).val()));
                        return false;
                    }
                });
            }
        }
        syncClienteSnapshot();
        actualizarVisibilidad(opciones.length > 0 || String($clienteSnap().val() || '').trim() !== '');
        sugerirLeyendaSiCorresponde();
    }

    function actualizarVisibilidad(hay) {
        var $w = $wrap();
        if (!$w.length) {
            return;
        }
        var requerido = String($w.attr('data-requerido-si-hay') || '1') === '1';
        if (hay) {
            $w.removeClass('d-none');
            if (requerido && !$select().prop('disabled')) {
                $select().prop('required', true);
                $w.find('label').addClass('requerido');
            }
        } else {
            $w.addClass('d-none');
            $select().prop('required', false).val('');
            $w.find('label').removeClass('requerido');
        }
    }

    function leyendaActualEsAuto() {
        var $l = $leyenda();
        if (!$l.length) {
            return false;
        }
        var auto = String($l.attr(LEYENDA_AUTO_ATTR) || '');
        var val = String($l.val() || '').trim();
        if (auto !== '' && val === auto) {
            return true;
        }
        return val === '';
    }

    function sugerirLeyendaSiCorresponde() {
        var $l = $leyenda();
        var $sel = $select();
        if (!$l.length || !$sel.length) {
            return;
        }
        var $opt = $sel.find('option:selected');
        var sugerida = String($opt.attr('data-leyenda') || '').trim();
        if (!sugerida || $sel.val() === '') {
            if (leyendaActualEsAuto()) {
                $l.val('').removeAttr(LEYENDA_AUTO_ATTR);
            }
            return;
        }
        if (!leyendaActualEsAuto()) {
            return;
        }
        $l.val(sugerida).attr(LEYENDA_AUTO_ATTR, sugerida);
    }

    function aplicarDesdeProveedorData(data, preferredId) {
        var opciones = opcionesDesdeProveedor(data);
        var sel = preferredId > 0
            ? preferredId
            : (parseInt($select().val() || '0', 10) || 0);
        rellenarSelect(opciones, sel);
    }

    function init() {
        if (!$wrap().length) {
            return;
        }

        $(document).on('change.cpProveedorCargado', '#proveedor_id', function (_e, data) {
            if (data && typeof data === 'object') {
                aplicarDesdeProveedorData(data, 0);
                return;
            }
            var pid = parseInt($('#proveedor_id').val() || '0', 10) || 0;
            if (pid <= 0) {
                rellenarSelect([], 0);
                return;
            }
            // Sin payload: pedimos el proveedor (incluye proveedor_servicios).
            if (typeof window.leeUnProveedor === 'function') {
                // leeUnProveedor dispara de nuevo change.cpProveedorCargado con data.
                return;
            }
        });

        $select().on('change', function () {
            syncClienteSnapshot();
            sugerirLeyendaSiCorresponde();
        });

        // Alta/edición: si ya hay proveedor y opciones server-side, solo wire leyenda.
        if ($select().find('option[value!=""]').length > 0) {
            actualizarVisibilidad(true);
            if ($select().find('option[value!=""]').length === 1 && !$select().val()) {
                $select().val(String($select().find('option[value!=""]').first().val()));
            }
            sugerirLeyendaSiCorresponde();
        }
    }

    $(init);

    window.cpProveedorServicioSelector = {
        aplicarDesdeProveedorData: aplicarDesdeProveedorData,
        rellenarSelect: rellenarSelect
    };
})(window, window.jQuery);
