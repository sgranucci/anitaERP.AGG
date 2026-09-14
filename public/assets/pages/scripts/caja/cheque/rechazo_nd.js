/**
 * Modal rechazo CHT → ND por concepto_venta (FacturacionService).
 */
(function ($) {
    'use strict';

    var chequeIdActual = 0;
    var emitiendo = false;

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function urlConId(plantilla, id) {
        if (!plantilla) {
            return '';
        }
        return String(plantilla).replace(':id', String(id)).replace('{id}', String(id));
    }

    function formatMoney(n) {
        var v = Number(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcularTotal() {
        var total = 0;
        $('#tbody-rechazo-nd-lineas tr.item-rechazo-nd-linea').each(function () {
            var cant = parseFloat($(this).find('.rechazo-nd-cantidad').val()) || 0;
            var precio = parseFloat($(this).find('.rechazo-nd-precio').val()) || 0;
            total += cant * precio;
        });
        $('#rechazo-nd-total').text(formatMoney(total));
    }

    function agregarLinea(linea) {
        var tpl = document.getElementById('template-rechazo-nd-linea');
        if (!tpl) {
            return;
        }
        var $row = $(tpl.content.cloneNode(true));
        if (linea) {
            $row.find('.rechazo-nd-concepto-id').val(linea.concepto_venta_id || '');
            $row.find('.rechazo-nd-descripcion').val(linea.descripcion || '');
            $row.find('.rechazo-nd-cantidad').val(linea.cantidad != null ? linea.cantidad : 1);
            $row.find('.rechazo-nd-precio').val(linea.precio != null ? linea.precio : 0);
        }
        $('#tbody-rechazo-nd-lineas').append($row);
        recalcularTotal();
    }

    function recolectarLineas() {
        var lineas = [];
        $('#tbody-rechazo-nd-lineas tr.item-rechazo-nd-linea').each(function () {
            var conceptoId = parseInt($(this).find('.rechazo-nd-concepto-id').val(), 10) || 0;
            var cantidad = parseFloat($(this).find('.rechazo-nd-cantidad').val()) || 0;
            var precio = parseFloat($(this).find('.rechazo-nd-precio').val()) || 0;
            var descripcion = $.trim($(this).find('.rechazo-nd-descripcion').val() || '');
            if (conceptoId <= 0 || cantidad <= 0 || precio <= 0) {
                return;
            }
            lineas.push({
                concepto_venta_id: conceptoId,
                cantidad: cantidad,
                precio: precio,
                descripcion: descripcion,
                incluyeimpuesto: '1'
            });
        });
        return lineas;
    }

    function avisar(msg) {
        var $modal = $('#modalRechazoNdCheque');
        if ($modal.hasClass('show')) {
            $modal.one('hidden.bs.modal', function () {
                setTimeout(function () { alert(msg); }, 0);
            });
            $modal.modal('hide');
        } else {
            setTimeout(function () { alert(msg); }, 0);
        }
    }

    function abrirModal(chequeId) {
        var urls = window.chequeRechazoNdUrls || {};
        var urlDatos = urlConId(urls.datos, chequeId);
        if (!urlDatos) {
            alert('No está configurada la URL de rechazo ND.');
            return;
        }

        chequeIdActual = chequeId;
        $('#tbody-rechazo-nd-lineas').empty();
        $('#rechazo-nd-config-error').hide().text('');
        $('#rechazo_nd_motivo').val('');

        $.ajax({
            url: urlDatos,
            method: 'GET',
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok' || !resp.data) {
                avisar((resp && resp.error) ? resp.error : 'No se pudo cargar el cheque.');
                return;
            }
            var d = resp.data;
            var c = d.cheque || {};
            var pv = d.puntoventa || {};
            $('#rechazo-nd-ref').text(
                (c.numerocheque || '') +
                (c.banco ? ' / ' + c.banco : '') +
                (c.nro_interno_anita ? ' (int. ' + c.nro_interno_anita + ')' : '')
            );
            $('#rechazo-nd-cliente').text(c.cliente || '');
            $('#rechazo-nd-monto').text(formatMoney(c.monto) + ' ' + (c.moneda || ''));
            $('#rechazo-nd-pv').text((pv.codigo || '') + ' — ' + (pv.nombre || '') + ' (id ' + (pv.id || '') + ')');
            var modo = String(pv.modofacturacion || 'M');
            var modoTxt = { C: 'CAE online', E: 'Exportación', A: 'CAEA', M: 'Manual (sin WS)' }[modo] || modo;
            var ws = pv.webservice ? ' · ' + pv.webservice : '';
            $('#rechazo-nd-modo-fe').text(' — ' + modoTxt + ws);
            $('#rechazo_nd_fecha').val(d.fecha || '');
            $('#rechazo_nd_leyenda').val(d.leyenda_sugerida || '');

            if (d.config_error) {
                $('#rechazo-nd-config-error').text(d.config_error).show();
            }

            (d.lineas || []).forEach(function (linea) { agregarLinea(linea); });
            if (!(d.lineas || []).length) {
                agregarLinea({ concepto_venta_id: '', descripcion: '', cantidad: 1, precio: c.monto || 0 });
            }

            $('#modalRechazoNdCheque').modal('show');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error)
                ? xhr.responseJSON.error
                : 'Error al cargar datos del rechazo.';
            avisar(msg);
        });
    }

    function emitir() {
        if (emitiendo || chequeIdActual <= 0) {
            return;
        }
        var lineas = recolectarLineas();
        if (!lineas.length) {
            avisar('Indique al menos una línea con concepto e importe mayor a cero.');
            return;
        }
        var urls = window.chequeRechazoNdUrls || {};
        var urlEmitir = urlConId(urls.emitir, chequeIdActual);
        if (!urlEmitir) {
            avisar('No está configurada la URL de emisión ND.');
            return;
        }

        emitiendo = true;
        $('#rechazo_nd_emitir').prop('disabled', true);

        $.ajax({
            url: urlEmitir,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            data: JSON.stringify({
                _token: csrfToken(),
                fecha: $('#rechazo_nd_fecha').val(),
                leyenda: $('#rechazo_nd_leyenda').val(),
                motivo_rechazo: $('#rechazo_nd_motivo').val(),
                lineas: lineas
            })
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                avisar((resp && resp.error) ? resp.error : 'No se pudo emitir la ND.');
                return;
            }
            var data = resp.data || {};
            var msg = 'Cheque rechazado. ND: ' + (data.codigo_nd || data.venta_nd_id || '');
            if (data.anita_ok === false) {
                msg += ' (Anita no actualizado; revisar log).';
            }
            $('#modalRechazoNdCheque').one('hidden.bs.modal', function () {
                setTimeout(function () {
                    alert(msg);
                    window.location.reload();
                }, 0);
            });
            $('#modalRechazoNdCheque').modal('hide');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error)
                ? xhr.responseJSON.error
                : 'Error al emitir la nota de débito.';
            avisar(msg);
        }).always(function () {
            emitiendo = false;
            $('#rechazo_nd_emitir').prop('disabled', false);
        });
    }

    $(document).on('click', '.btn-rechazo-nd-cheque', function (e) {
        e.preventDefault();
        var id = parseInt($(this).data('cheque-id'), 10) || 0;
        if (id > 0) {
            abrirModal(id);
        }
    });

    $(document).on('click', '#rechazo_nd_agregar_linea', function (e) {
        e.preventDefault();
        agregarLinea({ concepto_venta_id: '', descripcion: '', cantidad: 1, precio: 0 });
    });

    $(document).on('click', '.rechazo-nd-quitar-linea', function (e) {
        e.preventDefault();
        var $tbody = $('#tbody-rechazo-nd-lineas');
        if ($tbody.find('tr.item-rechazo-nd-linea').length <= 1) {
            $(this).closest('tr').find('input').val('');
            $(this).closest('tr').find('.rechazo-nd-cantidad').val(1);
            $(this).closest('tr').find('.rechazo-nd-precio').val(0);
        } else {
            $(this).closest('tr').remove();
        }
        recalcularTotal();
    });

    $(document).on('input change', '#tbody-rechazo-nd-lineas input', recalcularTotal);
    $(document).on('click', '#rechazo_nd_emitir', function (e) {
        e.preventDefault();
        emitir();
    });

    window.abrirModalRechazoNdCheque = abrirModal;
})(jQuery);
