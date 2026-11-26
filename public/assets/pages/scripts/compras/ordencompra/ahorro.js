/**
 * Ahorro por línea de orden de compra: "Guardar ahorro" (sin circuito de aprobación).
 * La fila de la línea tiene hidden .oc-ahorro-dato (mismos names que requisición).
 * Al guardar, el servidor recalcula con precio/cantidad/moneda grabados en la línea.
 */
(function ($) {
    'use strict';

    var $modal = null;
    var $filaPrincipal = null;
    var urlGuardar = null;

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function fmtEntero(n) {
        return Math.abs(n).toLocaleString('es-AR', { maximumFractionDigits: 0 });
    }

    // Total de ahorro de la orden: suma de las líneas con ahorro cargado (ahorro y base en pesos).
    function recalcularTotalAhorro() {
        var ahorro = 0;
        var base = 0;
        var n = 0;
        $('#tabla-articulos-ordencompra tr.item-ordencompra-articulo-sub[data-ahorro-pesos]').each(function () {
            ahorro += parseFloat($(this).attr('data-ahorro-pesos')) || 0;
            base += parseFloat($(this).attr('data-ahorro-base')) || 0;
            n++;
        });
        var $fila = $('#oc-tot-ahorro-fila');
        if (!$fila.length) {
            return;
        }
        if (n === 0) {
            $fila.hide();
            return;
        }
        var neg = ahorro < 0;
        var txt = (neg ? '-' : '') + '$ ' + fmtEntero(ahorro);
        if (base > 0) {
            txt += ' · ' + (ahorro / base * 100).toLocaleString('es-AR', { maximumFractionDigits: 1 }) + '%';
        }
        $('#oc-tot-ahorro-etiqueta').text(neg ? 'Sobrecosto' : 'Ahorro');
        $('#oc-tot-ahorro-valor').text(txt);
        $('#oc-tot-ahorro-celda').toggleClass('text-danger', neg).toggleClass('text-success', !neg);
        $fila.show();
    }

    function mostrarResultado(tipo, texto) {
        $('#oc-ah-resultado')
            .removeClass('alert-success alert-danger alert-light')
            .addClass(tipo === 'ok' ? 'alert-success' : 'alert-danger')
            .text(texto)
            .show();
    }

    function datosDeFila($fila) {
        var $datos = $fila.find('.oc-ahorro-dato');
        var valor = function (prefijo) {
            var $h = $datos.filter(function () {
                return (this.name || '').indexOf(prefijo) === 0;
            }).first();
            return $h.length ? $h.val() : '';
        };
        return {
            precio: valor('preciooriginales'),
            moneda: valor('moneda_anterior_ids'),
            origen: valor('origen_anteriores'),
            referencia: valor('referencia_anteriores'),
            tipoId: valor('tipoahorro_ids'),
            observacion: valor('motivoahorros')
        };
    }

    function tipoNombre(id) {
        var t = (window.ocAhorroConfig && window.ocAhorroConfig.tipos) || [];
        for (var i = 0; i < t.length; i++) {
            if (String(t[i].id) === String(id)) {
                return t[i].nombre;
            }
        }
        return '';
    }

    function abrir(e) {
        e.preventDefault();
        var $btn = $(this);
        $filaPrincipal = $btn.closest('tr.item-ordencompra-articulo-sub').prev('tr');
        urlGuardar = $btn.data('url');
        var d = datosDeFila($filaPrincipal);

        $('#oc-ah-articulo').text($btn.data('articulo') || '');
        $('#oc-ah-precio').val(d.precio || '');
        $('#oc-ah-moneda').val(d.moneda || $('#oc-ah-moneda option:first').val());
        $('#oc-ah-origen').val(d.origen || '');
        $('#oc-ah-referencia').val(d.referencia || '');
        $('#oc-ah-tipo-id').val(d.tipoId || '');
        $('#oc-ah-tipo-nombre').val(tipoNombre(d.tipoId));
        $('#oc-ah-observacion').val(d.observacion || '');
        $('#oc-ah-tipo-lista').addClass('d-none').empty();
        $('#oc-ah-resultado').hide();
        $('#modalOcAhorroLinea').modal('show');
    }

    function renderTipos() {
        var $lista = $('#oc-ah-tipo-lista').empty();
        var t = (window.ocAhorroConfig && window.ocAhorroConfig.tipos) || [];
        if (!t.length) {
            $lista.append($('<div class="list-group-item small text-muted">No hay tipos de ahorro activos.</div>'));
        }
        t.forEach(function (tipo) {
            $lista.append(
                $('<button type="button" class="list-group-item list-group-item-action py-1"></button>')
                    .text(tipo.nombre)
                    .attr('data-id', tipo.id)
            );
        });
        $lista.removeClass('d-none');
    }

    function guardar() {
        var $btn = $('#oc-ah-guardar').prop('disabled', true);
        var idLinea = $filaPrincipal.find('.ordencompra_articulo_id').val();
        $.ajax({
            url: urlGuardar,
            method: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: {
                _token: csrf(),
                ordencompra_articulo_id: idLinea,
                precio_anterior: $('#oc-ah-precio').val(),
                moneda_anterior_id: $('#oc-ah-moneda').val(),
                origen_anterior: $('#oc-ah-origen').val(),
                referencia_anterior: $('#oc-ah-referencia').val(),
                tipoahorro_id: $('#oc-ah-tipo-id').val(),
                observacion: $('#oc-ah-observacion').val()
            }
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                mostrarResultado('ng', (resp && resp.detalle) || 'No se pudo guardar el ahorro.');
                return;
            }
            var l = resp.linea;
            // Hidden de la fila: quedan alineados con lo grabado.
            var $datos = $filaPrincipal.find('.oc-ahorro-dato');
            $datos.filter(function () { return this.name === 'preciooriginales[]'; }).val($('#oc-ah-precio').val());
            $datos.filter(function () { return this.name === 'moneda_anterior_ids[]'; }).val($('#oc-ah-moneda').val());
            $datos.filter(function () { return this.name === 'origen_anteriores[]'; }).val($('#oc-ah-origen').val());
            $datos.filter(function () { return this.name === 'referencia_anteriores[]'; }).val($('#oc-ah-referencia').val());
            $datos.filter(function () { return this.name === 'tipoahorro_ids[]'; }).val($('#oc-ah-tipo-id').val());
            $datos.filter(function () { return this.name === 'motivoahorros[]'; }).val($('#oc-ah-observacion').val());

            var neg = l.ahorro_total_pesos !== null && l.ahorro_total_pesos < 0;
            var txt = l.ahorro_total_pesos === null ? '—' :
                (neg ? '-' : '') + '$ ' + Math.abs(l.ahorro_total_pesos).toLocaleString('es-AR', { maximumFractionDigits: 0 }) +
                (l.porc_ahorro !== null ? ' · ' + l.porc_ahorro.toLocaleString('es-AR', { maximumFractionDigits: 1 }) + '%' : '');
            var $subFila = $filaPrincipal.next('tr.item-ordencompra-articulo-sub');
            $subFila.attr('data-ahorro-pesos', l.ahorro_total_pesos === null ? 0 : l.ahorro_total_pesos);
            $subFila.attr('data-ahorro-base', l.base_pesos || 0);
            recalcularTotalAhorro();
            var $res = $subFila.find('.oc-ahorro-resumen').empty();
            $('<span class="font-weight-bold"></span>').addClass(neg ? 'text-danger' : 'text-success').text(txt).appendTo($res);
            if (l.tipoahorro_nombre) {
                $('<span class="text-muted small ml-1"></span>').text(l.tipoahorro_nombre).appendTo($res);
            }

            mostrarResultado('ok', 'Ahorro guardado: ' + txt + '.' + (resp.requisicion_actualizada ? ' Se copió también a la requisición de origen.' : ''));
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && (xhr.responseJSON.detalle || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0] && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Error al guardar el ahorro.';
            mostrarResultado('ng', msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    $(function () {
        $modal = $('#modalOcAhorroLinea');
        if (!$modal.length) {
            return;
        }
        $(document).on('click', '.oc-btn-guardar-ahorro', abrir);
        $(document).on('click', '#oc-ah-tipo-lupa', function (e) {
            e.preventDefault();
            renderTipos();
        });
        $(document).on('click', '#oc-ah-tipo-lista [data-id]', function () {
            $('#oc-ah-tipo-id').val($(this).data('id'));
            $('#oc-ah-tipo-nombre').val($(this).text());
            $('#oc-ah-tipo-lista').addClass('d-none').empty();
        });
        $(document).on('click', '#oc-ah-tipo-quitar', function () {
            $('#oc-ah-tipo-id').val('');
            $('#oc-ah-tipo-nombre').val('');
        });
        $(document).on('click', '#oc-ah-guardar', guardar);
        // Línea quitada o vaciada en el formulario: deja de sumar en el total del ahorro.
        $(document).on('click', '.eliminar_ordencompra_articulo', function () {
            var $sub = $(this).closest('tr.item-ordencompra-articulo').next('tr.item-ordencompra-articulo-sub');
            setTimeout(function () {
                $sub.removeAttr('data-ahorro-pesos data-ahorro-base');
                recalcularTotalAhorro();
            }, 0);
        });
    });
})(jQuery);
