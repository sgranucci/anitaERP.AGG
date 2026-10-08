/**
 * Modal rechazo CHT → ND: nominal no gravado + gastos netos por concepto.
 */
(function ($) {
    'use strict';

    var chequeIdActual = 0;
    var emitiendo = false;
    var impuestos = [];
    var nominal = 0;

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

    function tasaDe($row) {
        var id = parseInt($row.find('.rechazo-nd-impuesto').val(), 10) || 0;
        var imp = impuestos.find(function (item) { return item.id === id; });
        return imp ? Number(imp.valor) || 0 : 0;
    }

    function llenarImpuestos($select, selectedId) {
        $select.empty();
        impuestos.forEach(function (imp) {
            var opt = $('<option>').val(String(imp.id)).text(imp.nombre).attr('data-tasa', String(imp.valor));
            if (selectedId && String(imp.id) === String(selectedId)) {
                opt.prop('selected', true);
            }
            $select.append(opt);
        });
    }

    function recalcularTotal() {
        var noGravado = nominal;
        var gravado = 0;
        var exento = 0;
        var iva = 0;
        $('#tbody-rechazo-nd-lineas tr.item-rechazo-nd-linea[data-rol="gasto"]').each(function () {
            var neto = parseFloat($(this).find('.rechazo-nd-precio').val()) || 0;
            if (neto <= 0) {
                return;
            }
            var tasa = tasaDe($(this));
            if (tasa > 0) {
                gravado += neto;
                iva += Math.round(neto * tasa) / 100;
            } else {
                exento += neto;
            }
        });
        iva = Math.round(iva * 100) / 100;
        $('#rechazo-nd-nogravado').text(formatMoney(noGravado));
        $('#rechazo-nd-gravado').text(formatMoney(gravado));
        $('#rechazo-nd-exento').text(formatMoney(exento));
        $('#rechazo-nd-iva').text(formatMoney(iva));
        $('#rechazo-nd-total').text(formatMoney(noGravado + gravado + exento + iva));
    }

    function agregarLineaCheque(linea) {
        var $row = $('<tr class="item-rechazo-nd-linea" data-rol="cheque">');
        $row.append(
            $('<td>').text((linea.codigo || '') + (linea.codigo ? ' — ' : '') + 'Nominal del cheque')
                .append($('<input type="hidden" class="rechazo-nd-concepto-id">').val(linea.concepto_venta_id || ''))
        );
        $row.append($('<td>').append(
            $('<input type="text" class="form-control form-control-sm rechazo-nd-descripcion" maxlength="255">')
                .val(linea.descripcion || '')
        ));
        $row.append($('<td>').append(
            $('<input type="text" class="form-control form-control-sm" readonly>').val(formatMoney(linea.precio || nominal))
        ));
        $row.append($('<td class="align-middle">').text('No gravado'));
        $row.append($('<td>'));
        $('#tbody-rechazo-nd-lineas').append($row);
    }

    function agregarLineaGasto(linea) {
        var tpl = document.getElementById('template-rechazo-nd-linea');
        if (!tpl) {
            return;
        }
        var $row = $(tpl.content.cloneNode(true));
        $row.find('.rechazo-nd-concepto-id').val(linea && linea.concepto_venta_id ? linea.concepto_venta_id : '');
        $row.find('.codigoconceptoventa').val(linea && linea.codigo ? linea.codigo : '');
        $row.find('.nombreconceptoventa').val(linea && linea.codigo ? (linea.descripcion || '') : '');
        $row.find('.rechazo-nd-descripcion').val(linea && linea.descripcion ? linea.descripcion : '');
        $row.find('.rechazo-nd-precio').val(linea && linea.precio != null ? linea.precio : 0);
        llenarImpuestos($row.find('.rechazo-nd-impuesto'), linea && linea.impuesto_id);
        $('#tbody-rechazo-nd-lineas').append($row);
    }

    function recolectarLineas() {
        var lineas = [];
        $('#tbody-rechazo-nd-lineas tr.item-rechazo-nd-linea').each(function () {
            var rol = $(this).attr('data-rol') || 'gasto';
            if (rol === 'cheque') {
                lineas.push({
                    rol: 'cheque',
                    concepto_venta_id: parseInt($(this).find('.rechazo-nd-concepto-id').val(), 10) || 0,
                    cantidad: 1,
                    precio: nominal,
                    descripcion: $.trim($(this).find('.rechazo-nd-descripcion').val() || '')
                });
                return;
            }
            var conceptoId = parseInt($(this).find('.rechazo-nd-concepto-id').val(), 10) || 0;
            var precio = parseFloat($(this).find('.rechazo-nd-precio').val()) || 0;
            if (conceptoId <= 0 || precio <= 0) {
                return;
            }
            lineas.push({
                rol: 'gasto',
                concepto_venta_id: conceptoId,
                cantidad: 1,
                precio: precio,
                descripcion: $.trim($(this).find('.rechazo-nd-descripcion').val() || ''),
                impuesto_id: parseInt($(this).find('.rechazo-nd-impuesto').val(), 10) || 0
            });
        });
        return lineas;
    }

    function payload() {
        return {
            _token: csrfToken(),
            fecha: $('#rechazo_nd_fecha').val(),
            leyenda: $('#rechazo_nd_leyenda').val(),
            motivo_rechazo: $('#rechazo_nd_motivo').val(),
            puntoventa_id: $('#rechazo_nd_puntoventa_id').val(),
            lineas: recolectarLineas()
        };
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

    function pintarAsiento(filas) {
        var $tb = $('#tbody-rechazo-nd-asiento').empty();
        (filas || []).forEach(function (fila) {
            var $tr = $('<tr>');
            $tr.append($('<td>').text((fila.codigo || '') + ' ' + (fila.nombre || '')));
            $tr.append($('<td class="text-right">').text(fila.debe > 0 ? formatMoney(fila.debe) : ''));
            $tr.append($('<td class="text-right">').text(fila.haber > 0 ? formatMoney(fila.haber) : ''));
            $tb.append($tr);
        });
        $('#rechazo-nd-asiento').removeClass('d-none');
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
        $('#tbody-rechazo-nd-asiento').empty();
        $('#rechazo-nd-asiento').addClass('d-none');
        $('#rechazo-nd-config-error').hide().text('');
        $('#rechazo_nd_motivo').val('');
        $('#rechazo-nd-cuenta-nominal').text('');

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
            impuestos = d.impuestos || [];
            nominal = Number(c.monto) || 0;
            $('#rechazo-nd-ref').text(
                (c.numerocheque || '') +
                (c.banco ? ' / ' + c.banco : '') +
                (c.nro_interno_anita ? ' (int. ' + c.nro_interno_anita + ')' : '')
            );
            $('#rechazo-nd-cliente').text(c.cliente || '');
            $('#rechazo-nd-monto').text(formatMoney(nominal) + ' ' + (c.moneda || ''));
            $('#rechazo_nd_puntoventa_id').val(pv.id || '');
            $('#rechazo_nd_puntoventa_id_codigo').val(pv.codigo || '');
            $('#rechazo_nd_puntoventa_id_nombre').val(pv.nombre || '');
            var modo = String(pv.modofacturacion || 'M');
            var modoTxt = { C: 'CAE online', E: 'Exportación', A: 'CAEA', M: 'Manual (sin WS)' }[modo] || modo;
            var ws = pv.webservice ? ' · ' + pv.webservice : '';
            $('#rechazo-nd-modo-fe').text(modoTxt + ws);
            $('#rechazo_nd_fecha').val(d.fecha || '');
            $('#rechazo_nd_leyenda').val(d.leyenda_sugerida || '');

            var cuenta = d.cuenta_nominal || null;
            if (cuenta && cuenta.origen === 'error') {
                $('#rechazo-nd-config-error').text(cuenta.nombre || '').show();
            } else if (cuenta && cuenta.codigo) {
                var donde = cuenta.origen === 'deposito'
                    ? 'El nominal acredita el banco del depósito: '
                    : 'El nominal acredita cheques en cartera: ';
                $('#rechazo-nd-cuenta-nominal').text(donde + cuenta.codigo + ' ' + cuenta.nombre);
            }

            if (d.config_error) {
                $('#rechazo-nd-config-error').text(d.config_error).show();
            }

            var gastos = [];
            (d.lineas || []).forEach(function (linea) {
                if (linea.rol === 'cheque') {
                    agregarLineaCheque(linea);
                } else {
                    gastos.push(linea);
                }
            });
            if (!$('#tbody-rechazo-nd-lineas tr[data-rol="cheque"]').length) {
                agregarLineaCheque({ precio: nominal, descripcion: '' });
            }
            if (!gastos.length) {
                agregarLineaGasto(null);
            } else {
                gastos.forEach(agregarLineaGasto);
            }
            recalcularTotal();
            $('#modalRechazoNdCheque').modal('show');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error)
                ? xhr.responseJSON.error
                : 'Error al cargar datos del rechazo.';
            avisar(msg);
        });
    }

    function preview() {
        var urls = window.chequeRechazoNdUrls || {};
        var urlPreview = urlConId(urls.preview, chequeIdActual);
        if (!urlPreview) {
            avisar('No está configurada la vista previa del asiento.');
            return;
        }
        $.ajax({
            url: urlPreview,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            data: JSON.stringify(payload())
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                $('#rechazo-nd-config-error').text((resp && resp.error) ? resp.error : 'No se pudo armar el asiento.').show();
                return;
            }
            $('#rechazo-nd-config-error').hide();
            var tot = (resp.data && resp.data.totales) || {};
            if (tot.total != null) {
                $('#rechazo-nd-nogravado').text(formatMoney(tot.no_gravado));
                $('#rechazo-nd-gravado').text(formatMoney(tot.gravado));
                $('#rechazo-nd-exento').text(formatMoney(tot.exento));
                $('#rechazo-nd-iva').text(formatMoney(tot.iva));
                $('#rechazo-nd-total').text(formatMoney(tot.total));
            }
            pintarAsiento((resp.data && resp.data.asiento) || []);
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error)
                ? xhr.responseJSON.error
                : 'No se pudo armar el asiento.';
            $('#rechazo-nd-config-error').text(msg).show();
        });
    }

    function emitir() {
        if (emitiendo || chequeIdActual <= 0) {
            return;
        }
        var lineas = recolectarLineas();
        if (!lineas.length) {
            avisar('Indique el nominal del cheque.');
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
            data: JSON.stringify(payload())
        }).done(function (resp) {
            if (!resp || resp.mensaje !== 'ok') {
                $('#rechazo-nd-config-error').text((resp && resp.error) ? resp.error : 'No se pudo emitir la ND.').show();
                return;
            }
            var data = resp.data || {};
            var msg = 'Cheque rechazado. ND: ' + (data.codigo_nd || data.venta_nd_id || '');
            if (data.anita_ok === false) {
                msg += ' (Anita no actualizado; revisar log).';
            }
            var deuda = data.deuda_proveedor;
            if (deuda && deuda.codigo) {
                msg += ' Deuda en ' + (deuda.proveedor || 'el proveedor') + ': ' + deuda.codigo;
                if (deuda.error) {
                    msg += ' (no se pudo contabilizar: ' + deuda.error + ')';
                }
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
            $('#rechazo-nd-config-error').text(msg).show();
        }).always(function () {
            emitiendo = false;
            $('#rechazo_nd_emitir').prop('disabled', false);
        });
    }

    $(document).on('show.bs.modal', '#consultaconceptoventaModal, #consultapuntoventaModal', function () {
        $(document).off('focusin.modal');
    });

    $(document).on('click', '.btn-rechazo-nd-cheque', function (e) {
        e.preventDefault();
        var id = parseInt($(this).data('cheque-id'), 10) || 0;
        if (id > 0) {
            abrirModal(id);
        }
    });

    $(document).on('click', '#rechazo_nd_agregar_linea', function (e) {
        e.preventDefault();
        agregarLineaGasto(null);
    });

    $(document).on('click', '.rechazo-nd-quitar-linea', function (e) {
        e.preventDefault();
        var $tbody = $('#tbody-rechazo-nd-lineas');
        var $gastos = $tbody.find('tr[data-rol="gasto"]');
        if ($gastos.length <= 1) {
            var $tr = $(this).closest('tr');
            $tr.find('.concepto_venta_id, .codigoconceptoventa, .nombreconceptoventa, .rechazo-nd-descripcion').val('');
            $tr.find('.rechazo-nd-precio').val(0);
        } else {
            $(this).closest('tr').remove();
        }
        recalcularTotal();
    });

    $(document).on('concepto-venta:aplicado', '.tm-concepto-venta-campo', function (e, data) {
        var $tr = $(this).closest('tr.item-rechazo-nd-linea');
        if (!$tr.length || !data) {
            return;
        }
        if (data.impuesto_id) {
            $tr.find('.rechazo-nd-impuesto').val(String(data.impuesto_id));
        }
        var $desc = $tr.find('.rechazo-nd-descripcion');
        if (!$.trim($desc.val())) {
            $desc.val(data.descripcion || data.nombre || '');
        }
        recalcularTotal();
    });

    $(document).on('input change', '#tbody-rechazo-nd-lineas input, #tbody-rechazo-nd-lineas select', recalcularTotal);
    $(document).on('click', '#rechazo_nd_preview', function (e) {
        e.preventDefault();
        preview();
    });
    $(document).on('click', '#rechazo_nd_emitir', function (e) {
        e.preventDefault();
        emitir();
    });

    window.abrirModalRechazoNdCheque = abrirModal;
})(jQuery);
