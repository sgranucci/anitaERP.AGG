(function ($) {
    'use strict';

    var cfg = window.SURMAR_CERT_SENASA || {};
    var lineas = Array.isArray(cfg.lineas) ? cfg.lineas.slice() : [];
    var etiquetasPendientes = [];
    var $tbody = $('#tabla-lineas-cert-senasa tbody');
    var $msg = $('#surmar-msg-vivo');
    var overlay = document.getElementById('surmar-cert-overlay');

    function mostrarOverlay(titulo) {
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('surmar-cert-overlay-titulo');
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

    function renderEtiquetasPendientes() {
        var $box = $('#lista-etiquetas-pendientes');
        $box.empty();
        etiquetasPendientes.forEach(function (e) {
            $box.append(
                $('<span class="badge badge-info"/>')
                    .text('#' + e.etiqueta_id + ' ' + fmt(e.peso_neto) + ' kg')
                    .append(
                        $('<a href="#" class="text-white ml-1 js-quitar-etiq"/>')
                            .attr('data-id', e.etiqueta_id)
                            .html('&times;')
                    )
            );
        });
        if (etiquetasPendientes.length && !$('#kilos').val()) {
            var sum = etiquetasPendientes.reduce(function (a, e) {
                return a + (Number(e.peso_neto) || 0);
            }, 0);
            $('#kilos').val(sum.toFixed(3));
        }
        if (etiquetasPendientes.length && !$('#cajas').val()) {
            $('#cajas').val(String(etiquetasPendientes.length));
        }
    }

    function render() {
        $tbody.empty();
        var totalKilos = 0;
        lineas.forEach(function (l, idx) {
            totalKilos += Number(l.kilos) || 0;
            var tr = $('<tr/>');
            tr.append($('<td/>').text(l.linea || (idx + 1)));
            tr.append($('<td class="hora-piqueo"/>').text(l.hora_piqueo || '—'));
            tr.append($('<td/>').text(l.sku || ''));
            tr.append($('<td/>').text(l.descripcion || ''));
            tr.append($('<td/>').text(l.cod_tipo_prod || ''));
            tr.append($('<td class="text-right"/>').text(fmt(l.kilos)));
            tr.append($('<td class="text-right"/>').text(fmt(l.cajas)));
            var etiqTxt = (l.etiquetas || []).map(function (e) {
                return '#' + e.etiqueta_id;
            }).join(', ') || '—';
            tr.append($('<td/>').text(etiqTxt));
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
        $('#surmar-total-kilos').text(fmt(totalKilos));
    }

    function limpiarForm() {
        $('#articulo_id').val('');
        $('#codigoarticulo').val('');
        $('#descripcionarticulo').val('');
        $('#etiqueta_scan').val('');
        $('#kilos').val('');
        $('#cajas').val('');
        $('#tropa').val('');
        etiquetasPendientes = [];
        renderEtiquetasPendientes();
    }

    function agregarEtiqueta() {
        if (!cfg.editable) return;
        var raw = String($('#etiqueta_scan').val() || '').trim();
        if (!raw) return;
        if (etiquetasPendientes.some(function (e) { return String(e.etiqueta_id) === String(raw) || String(e.etiqueta_id) === String(parseInt(raw, 10)); })) {
            // puede ser barcode Anita; se valida tras resolver
        }
        $msg.text('Resolviendo etiqueta…').removeClass('text-danger text-success');
        $.ajax({
            url: cfg.urls.resolverEtiqueta,
            method: 'POST',
            data: { _token: cfg.urls.token, codigo: raw },
            success: function (resp) {
                if (!resp || !resp.ok) {
                    $msg.text('No se pudo resolver').addClass('text-danger');
                    return;
                }
                var e = resp.etiqueta;
                if (etiquetasPendientes.some(function (x) { return x.etiqueta_id === e.etiqueta_id; })) {
                    $msg.text('Etiqueta ya agregada').removeClass('text-success').addClass('text-danger');
                    return;
                }
                if ($('#articulo_id').val() && String($('#articulo_id').val()) !== String(e.articulo_id)) {
                    $msg.text('La etiqueta es de otro artículo').addClass('text-danger');
                    return;
                }
                if (!$('#articulo_id').val()) {
                    $('#articulo_id').val(e.articulo_id);
                    $('#codigoarticulo').val(e.sku || '');
                    $('#descripcionarticulo').val(e.descripcion || '');
                }
                etiquetasPendientes.push(e);
                $('#etiqueta_scan').val('');
                renderEtiquetasPendientes();
                $msg.text('Etiqueta #' + e.etiqueta_id + ' agregada').removeClass('text-danger').addClass('text-success');
                $('#etiqueta_scan').focus();
            },
            error: function (xhr) {
                var err = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                var first = Object.keys(err).map(function (k) { return err[k][0]; })[0] || 'Error al resolver etiqueta';
                $msg.text(first).addClass('text-danger');
            }
        });
    }

    function aceptarLinea() {
        if (!cfg.editable) return;
        var articuloId = parseInt($('#articulo_id').val(), 10) || 0;
        if (!articuloId) {
            $msg.text('Seleccione artículo').addClass('text-danger');
            return;
        }
        if (!etiquetasPendientes.length) {
            $msg.text('Agregue al menos una etiqueta').addClass('text-danger');
            return;
        }
        var payload = {
            _token: cfg.urls.token,
            articulo_id: articuloId,
            etiqueta_ids: etiquetasPendientes.map(function (e) { return e.etiqueta_id; }),
            kilos: $('#kilos').val() || null,
            cajas: $('#cajas').val() || null,
            tropa: $('#tropa').val() || null
        };
        mostrarOverlay('Grabando ítem…');
        $.ajax({
            url: cfg.urls.guardarLinea,
            method: 'POST',
            data: payload,
            success: function (resp) {
                ocultarOverlay();
                if (!resp || !resp.ok) {
                    $msg.text('Error al grabar').addClass('text-danger');
                    return;
                }
                lineas.push(resp.linea);
                render();
                limpiarForm();
                $msg.text('Ítem grabado ' + (resp.linea.hora_piqueo || '')).removeClass('text-danger').addClass('text-success');
                $('#codigoarticulo').focus();
            },
            error: function (xhr) {
                ocultarOverlay();
                var err = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                var first = Object.keys(err).map(function (k) { return err[k][0]; })[0] || 'Error al grabar línea';
                $msg.text(first).addClass('text-danger');
            }
        });
    }

    $(document).on('click', '#btn-agregar-etiqueta', function (e) {
        e.preventDefault();
        agregarEtiqueta();
    });
    $('#etiqueta_scan').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            agregarEtiqueta();
        }
    });
    $(document).on('click', '#btn-aceptar-linea', function (e) {
        e.preventDefault();
        aceptarLinea();
    });
    $(document).on('click', '.js-quitar-etiq', function (e) {
        e.preventDefault();
        var id = parseInt($(this).data('id'), 10);
        etiquetasPendientes = etiquetasPendientes.filter(function (x) { return x.etiqueta_id !== id; });
        renderEtiquetasPendientes();
    });
    $(document).on('click', '.js-quitar-linea', function (e) {
        e.preventDefault();
        if (!cfg.editable) return;
        var id = $(this).data('id');
        if (!confirm('¿Quitar este ítem?')) return;
        mostrarOverlay('Eliminando…');
        $.ajax({
            url: cfg.urls.eliminarLinea + '/' + id,
            method: 'POST',
            data: { _token: cfg.urls.token, _method: 'DELETE' },
            success: function (resp) {
                ocultarOverlay();
                if (resp && resp.ok) {
                    lineas = lineas.filter(function (l) { return String(l.id) !== String(id); });
                    render();
                    $msg.text('Ítem eliminado').addClass('text-success');
                }
            },
            error: function () {
                ocultarOverlay();
                $msg.text('No se pudo eliminar').addClass('text-danger');
            }
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);
    render();
})(jQuery);
