(function ($) {
    'use strict';

    var cfg = window.SURMAR_RECEPCION || {};
    var lineas = Array.isArray(cfg.lineas) ? cfg.lineas.slice() : [];
    var lineasOc = Array.isArray(cfg.lineasOc) ? cfg.lineasOc.slice() : [];
    var $tbody = $('#tabla-lineas-surmar tbody');
    var $tbodyOc = $('#tabla-oc-pendientes-surmar tbody');
    var $msg = $('#surmar-msg-vivo');
    var overlay = document.getElementById('surmar-overlay');
    var ocElegidaId = null;

    function mostrarOverlay(titulo) {
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('surmar-overlay-titulo');
            if (t) t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function fmt(n) {
        return (Number(n) || 0).toFixed(2);
    }

    function renderOc() {
        if (!$tbodyOc.length) return;
        $tbodyOc.empty();
        if (!lineasOc.length) {
            $tbodyOc.append(
                $('<tr/>').append(
                    $('<td colspan="7" class="text-center text-muted py-3"/>')
                        .text('Sin líneas pendientes en la OC (o ya cubiertas por COM confirmadas).')
                )
            );
            return;
        }
        lineasOc.forEach(function (l) {
            var tr = $('<tr/>').attr('data-oc-art-id', l.ordencompra_articulo_id);
            if (String(ocElegidaId) === String(l.ordencompra_articulo_id)) {
                tr.addClass('js-oc-elegida');
            }
            tr.append($('<td/>').html('<button type="button" class="btn btn-warning btn-xs js-elegir-oc">Elegir</button>'));
            tr.append($('<td/>').text(l.sku || ''));
            tr.append($('<td/>').text(l.descripcion || ''));
            tr.append($('<td class="text-right"/>').text(fmt(l.cantidad_oc)));
            tr.append($('<td class="text-right"/>').text(fmt(l.cantidad_recibida)));
            tr.append($('<td class="text-right"/>').text(fmt(l.cantidad_pendiente)));
            tr.append($('<td class="text-right"/>').text(fmt(l.precio)));
            tr.data('ocLinea', l);
            $tbodyOc.append(tr);
        });
    }

    function elegirLineaOc(l) {
        if (!l || !cfg.editable) return;
        ocElegidaId = l.ordencompra_articulo_id;
        $('#ordencompra_articulo_id').val(l.ordencompra_articulo_id || '');
        $('#articulo_id').val(l.articulo_id || '');
        $('#codigoarticulo').val(l.sku || '');
        $('#descripcionarticulo').val(l.descripcion || '');
        $('#precio_oc').val(l.precio != null ? l.precio : '');
        renderOc();
        $('#lote_proveedor').focus();
        $msg.text('Línea OC seleccionada — complete lote y pesos').removeClass('text-danger text-success').addClass('text-muted');
    }

    function render() {
        $tbody.empty();
        var totalNeto = 0;
        lineas.forEach(function (l, idx) {
            totalNeto += Number(l.peso_neto) || 0;
            var tr = $('<tr/>');
            tr.append($('<td/>').text(l.orden || (idx + 1)));
            tr.append($('<td class="hora-carga"/>').text(l.hora_piqueo || '—'));
            tr.append($('<td/>').text(l.codigo || ''));
            tr.append($('<td/>').text(l.descripcion || ''));
            tr.append($('<td/>').text(l.lote_proveedor || ''));
            tr.append($('<td/>').text(l.fecha_vto || ''));
            tr.append($('<td class="text-right"/>').text(fmt(l.cant_pieza)));
            tr.append($('<td class="text-right"/>').text(fmt(l.peso_bruto)));
            tr.append($('<td class="text-right"/>').text(fmt(l.peso_neto)));
            var $etiq = $('<td/>');
            if (l.stock_etiqueta_id) {
                $etiq.append(
                    $('<a class="btn btn-xs btn-outline-secondary mr-1" target="_blank" rel="noopener"/>')
                        .attr('href', cfg.urls.zpl + '/' + l.stock_etiqueta_id + '/zpl')
                        .attr('title', 'ZPL etiqueta')
                        .html('<i class="fa fa-print"></i> #' + l.stock_etiqueta_id)
                );
            } else {
                $etiq.text('—');
            }
            tr.append($etiq);
            var $acc = $('<td class="text-nowrap"/>');
            if (cfg.editable) {
                $acc.append(
                    $('<button type="button" class="btn-accion-tabla text-danger js-quitar-linea" title="Quitar ítem"/>')
                        .attr('data-id', l.id)
                        .html('<i class="fa fa-times-circle"></i>')
                );
            }
            tr.append($acc);
            $tbody.append(tr);
        });
        $('#surmar-total-items').text(lineas.length);
        $('#surmar-total-neto').text(fmt(totalNeto));
    }

    function limpiarForm() {
        ocElegidaId = null;
        $('#ordencompra_articulo_id').val('');
        $('#articulo_id').val('');
        $('#codigoarticulo').val('');
        $('#descripcionarticulo').val('');
        $('#precio_oc').val('');
        $('#lote_proveedor').val('');
        $('#fecha_vto').val('');
        $('#cant_pieza').val('1');
        $('#peso_bruto').val('');
        $('#peso_neto').val('');
        renderOc();
    }

    function token() {
        return cfg.urls.token || $('input[name="_token"]').val() || '';
    }

    function imprimirZpl(zpl) {
        if (!zpl) return;
        try {
            if (window.BrowserPrint && typeof window.BrowserPrint.getDefaultDevice === 'function') {
                window.BrowserPrint.getDefaultDevice('printer', function (device) {
                    if (device) device.send(zpl);
                }, function () {
                    descargarZpl(zpl);
                });
                return;
            }
        } catch (e) { /* fallback */ }
        descargarZpl(zpl);
    }

    function descargarZpl(zpl) {
        var blob = new Blob([zpl], { type: 'text/plain' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'etiqueta_surmar.zpl';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            a.remove();
        }, 500);
    }

    function guardarItem() {
        if (!cfg.editable) return;
        var payload = {
            _token: token(),
            ordencompra_articulo_id: $('#ordencompra_articulo_id').val(),
            articulo_id: $('#articulo_id').val(),
            lote_proveedor: $.trim($('#lote_proveedor').val()),
            certificado: $.trim($('#lote_proveedor').val()),
            fecha_vto: $('#fecha_vto').val() || null,
            cant_pieza: $('#cant_pieza').val() || 1,
            peso_bruto: $('#peso_bruto').val() || $('#peso_neto').val(),
            peso_neto: $('#peso_neto').val(),
            precio: $('#precio_oc').val() || 0,
            imprimir: $('#imprimir_etiqueta').is(':checked') ? 1 : 0
        };
        if (!payload.ordencompra_articulo_id || !payload.articulo_id) {
            alert('Elija una línea de la OC.');
            return;
        }
        if (!payload.lote_proveedor) {
            alert('Ingrese el lote.');
            $('#lote_proveedor').focus();
            return;
        }
        if (!(Number(payload.peso_neto) > 0)) {
            alert('Ingrese peso neto.');
            $('#peso_neto').focus();
            return;
        }

        mostrarOverlay('Grabando ítem…');
        $msg.text('Grabando…').removeClass('text-success text-danger').addClass('text-muted');

        $.ajax({
            url: cfg.urls.guardarLinea,
            method: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.ok) {
                $msg.text('Error al grabar').addClass('text-danger');
                return;
            }
            lineas.push(res.linea);
            if (Array.isArray(res.lineas_oc)) {
                lineasOc = res.lineas_oc;
            }
            render();
            limpiarForm();
            $msg.text(res.mensaje || ('Grabado ' + (res.linea.hora_piqueo || ''))).removeClass('text-muted text-danger').addClass('text-success');
            if (res.zpl) {
                imprimirZpl(res.zpl);
            }
        }).fail(function (xhr) {
            var msg = 'No se pudo grabar el ítem.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errs = xhr.responseJSON.errors;
                msg = Object.keys(errs).map(function (k) { return errs[k].join(' '); }).join(' ');
            }
            $msg.text(msg).addClass('text-danger');
            alert(msg);
        }).always(ocultarOverlay);
    }

    function quitarLinea(lineaId) {
        if (!cfg.editable) return;
        if (!confirm('¿Quitar este ítem y anular su etiqueta?')) return;
        mostrarOverlay('Quitando ítem…');
        $.ajax({
            url: cfg.urls.eliminarLinea + '/' + lineaId,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.ok) return;
            lineas = lineas.filter(function (l) { return String(l.id) !== String(lineaId); });
            if (Array.isArray(res.lineas_oc)) {
                lineasOc = res.lineas_oc;
                renderOc();
            }
            render();
            $msg.text('Ítem quitado').addClass('text-muted');
        }).fail(function () {
            alert('No se pudo quitar el ítem.');
        }).always(ocultarOverlay);
    }

    $(function () {
        render();
        renderOc();
        $('#btn-agregar-item-surmar').on('click', guardarItem);
        $tbody.on('click', '.js-quitar-linea', function () {
            quitarLinea($(this).data('id'));
        });
        $tbodyOc.on('click', 'tr', function (e) {
            if (!cfg.editable) return;
            var l = $(this).data('ocLinea');
            if (!l) return;
            if ($(e.target).closest('button').length || $(e.target).is('button')) {
                e.preventDefault();
            }
            elegirLineaOc(l);
        });
        $('#peso_neto, #lote_proveedor').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                guardarItem();
            }
        });
        window.addEventListener('pageshow', ocultarOverlay);
        if (cfg.editable && lineasOc.length === 1) {
            elegirLineaOc(lineasOc[0]);
        }
    });
})(jQuery);
