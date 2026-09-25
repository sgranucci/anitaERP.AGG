(function ($) {
    'use strict';

    function meta() {
        return typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta()
            : { nombre: '', operacion: '' };
    }

    function nombreTipoSeleccionado() {
        return meta().nombre || '';
    }

    function tituloGrabando() {
        var operacion = meta().operacion || '';
        var nombre = nombreTipoSeleccionado();

        if (operacion === 'T') {
            return nombre !== ''
                ? 'Grabando transferencia «' + nombre + '»…'
                : 'Grabando transferencia…';
        }

        return nombre !== ''
            ? 'Grabando movimiento «' + nombre + '»…'
            : 'Grabando movimiento de stock…';
    }

    function mostrarOverlayGrabando() {
        if (!window.PedidoProcesoOverlay || typeof PedidoProcesoOverlay.iniciar !== 'function') {
            return;
        }

        PedidoProcesoOverlay.iniciar(
            [
                'Grabando movimiento de stock…',
                'Registrando líneas de artículos…',
                'Actualizando stock…',
            ],
            tituloGrabando()
        );
        $('#formgeneral button[type="submit"]').prop('disabled', true);
    }

    function validarPreciosObligatorios() {
        var m = meta();
        if (!m.pidePrecio) {
            return true;
        }

        var lineas = [];
        $('#tabla-items-movimientostock tr.item-pedido').each(function (idx) {
            var $tr = $(this);
            var articuloId = parseInt($tr.find('input.articulo_id[name="articulos_id[]"]').val(), 10) || 0;
            if (articuloId <= 0) {
                return;
            }
            var cant = parseFloat(String(
                $tr.find('input.cantidad-stock').val()
                || $tr.find('input.cantidad').val()
                || '0'
            ).replace(',', '.'));
            if (!isFinite(cant) || Math.abs(cant) < 1e-9) {
                return;
            }
            var precio = parseFloat(String($tr.find('input.precio').val() || '0').replace(',', '.'));
            if (!isFinite(precio) || precio <= 0) {
                lineas.push(idx + 1);
            }
        });

        if (lineas.length === 0) {
            return true;
        }

        alert('Este tipo de transacción exige precio mayor a cero en cada línea con artículo. Revisá: ' + lineas.join(', ') + '.');
        var $primera = $('#tabla-items-movimientostock tr.item-pedido').eq(lineas[0] - 1).find('input.precio').first();
        if ($primera.length) {
            $primera.focus().select();
        }

        return false;
    }

    $(function () {
        var $form = $('#formgeneral');
        if (!$form.length) {
            return;
        }

        $form.on('submit', function (e) {
            if (!validarPreciosObligatorios()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
            mostrarOverlayGrabando();
        });
    });
}(jQuery));
