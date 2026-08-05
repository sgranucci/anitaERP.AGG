(function ($) {
    'use strict';

    var cfg = window.SURMAR_RECEPCION || {};
    var lineas = Array.isArray(cfg.lineas) ? cfg.lineas.slice() : [];
    var $tbody = $('#tabla-lineas-surmar tbody');
    var $msg = $('#surmar-msg-vivo');
    var overlay = document.getElementById('surmar-overlay');

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

    function render() {
        $tbody.empty();
        var totalNeto = 0;
        lineas.forEach(function (l, idx) {
            totalNeto += Number(l.peso_neto) || 0;
            var tr = $('<tr/>');
            tr.append($('<td/>').text(l.orden || (idx + 1)));
            tr.append($('<td class="hora-piqueo"/>').text(l.hora_piqueo || '—'));
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
        $('#articulo_id').val('');
        $('#codigoarticulo').val('');
        $('#descripcionarticulo').val('');
        $('#lote_proveedor').val('');
        $('#fecha_vto').val('');
        $('#cant_pieza').val('1');
        $('#peso_bruto').val('');
        $('#peso_neto').val('');
        $('#codigoarticulo').focus();
    }

    function token() {
        return cfg.urls.token || $('input[name="_token"]').val() || '';
    }

    function imprimirZpl(zpl) {
        if (!zpl) return;
        try {
            if (window.BrowserPrint && typeof window.BrowserPrint.getDefaultDevice === 'function') {
                // Zebra Browser Print si está disponible
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
            articulo_id: $('#articulo_id').val(),
            lote_proveedor: $.trim($('#lote_proveedor').val()),
            certificado: $.trim($('#lote_proveedor').val()),
            fecha_vto: $('#fecha_vto').val() || null,
            cant_pieza: $('#cant_pieza').val() || 1,
            peso_bruto: $('#peso_bruto').val() || $('#peso_neto').val(),
            peso_neto: $('#peso_neto').val(),
            imprimir: $('#imprimir_etiqueta').is(':checked') ? 1 : 0
        };
        if (!payload.articulo_id) {
            alert('Seleccione un artículo.');
            $('#codigoarticulo').focus();
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
            render();
            $msg.text('Ítem quitado').addClass('text-muted');
        }).fail(function () {
            alert('No se pudo quitar el ítem.');
        }).always(ocultarOverlay);
    }

    $(function () {
        render();
        if (typeof window.activa_eventos_consultaarticulo === 'function') {
            window.activa_eventos_consultaarticulo();
        }
        $('#btn-agregar-item-surmar').on('click', guardarItem);
        $tbody.on('click', '.js-quitar-linea', function () {
            quitarLinea($(this).data('id'));
        });
        $('#peso_neto, #lote_proveedor').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                guardarItem();
            }
        });
        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
