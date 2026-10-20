(function ($) {
    'use strict';

    var opIdActual = null;
    var retenciones = [];

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function urlPara(opId, tipo) {
        var $modal = $('#modalOpDevolverRetenciones');
        var tpl = tipo === 'post'
            ? ($modal.data('url-post-template') || '/compras/pagoproveedor/__ID__/devolver-retenciones')
            : ($modal.data('url-datos-template') || '/compras/pagoproveedor/__ID__/devolver-retenciones/datos');
        return String(tpl).replace('__ID__', String(opId));
    }

    function fmt(n) {
        return (Number(n) || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function resetModal() {
        opIdActual = null;
        retenciones = [];
        $('#op-devret-cargando').addClass('d-none');
        $('#op-devret-error').addClass('d-none').text('');
        $('#op-devret-form-wrap').addClass('d-none');
        $('#op-devret-retenciones-body').empty();
        $('#op_devret_confirmar').addClass('d-none').prop('disabled', false);
        $('#op_devret_medio_trf').prop('checked', true);
        $('#op-devret-wrap-chp').addClass('d-none');
        $('#op-devret-wrap-cuenta').removeClass('d-none');
        $('#op_devret_obs').val('');
        $('#op_devret_numerocheque').val('');
        $('#op-devret-total').text('0,00');
    }

    function mostrarError(msg) {
        $('#op-devret-error').removeClass('d-none').text(msg || 'No se pudo completar la operación.');
        $('#op-devret-form-wrap').addClass('d-none');
        $('#op_devret_confirmar').addClass('d-none');
    }

    function totalSeleccionado() {
        var total = 0;
        $('#op-devret-retenciones-body tr').each(function () {
            var $tr = $(this);
            if (!$tr.find('.op-devret-check').is(':checked')) {
                return;
            }
            total += parseFloat(String($tr.find('.op-devret-importe').val() || '0').replace(',', '.')) || 0;
        });
        return Math.round(total * 100) / 100;
    }

    function refreshTotal() {
        $('#op-devret-total').text(fmt(totalSeleccionado()));
    }

    function fillSelect($sel, items, placeholder) {
        $sel.empty();
        $sel.append($('<option>').val('').text(placeholder || 'Seleccione…'));
        (items || []).forEach(function (it) {
            $sel.append($('<option>').val(it.id).text(it.nombre).attr('data-cuentacaja-id', it.cuentacaja_id || ''));
        });
    }

    function renderRetenciones(lista) {
        var $body = $('#op-devret-retenciones-body').empty();
        (lista || []).forEach(function (r) {
            var disp = Number(r.disponible) || 0;
            var disabled = disp < 0.001;
            var $tr = $('<tr>');
            $tr.append(
                $('<td>').append(
                    $('<input>', {
                        type: 'checkbox',
                        class: 'op-devret-check',
                        disabled: disabled,
                        'data-id': r.id
                    })
                )
            );
            $tr.append($('<td>').text(r.etiqueta + (r.nro_certificado ? ' · cert ' + r.nro_certificado : '')));
            $tr.append($('<td class="text-right">').text(fmt(r.importe_original)));
            $tr.append($('<td class="text-right">').text(fmt(r.importe_devuelto)));
            $tr.append($('<td class="text-right">').text(fmt(disp)));
            $tr.append(
                $('<td>').append(
                    $('<input>', {
                        type: 'number',
                        class: 'form-control form-control-sm text-right op-devret-importe',
                        step: '0.01',
                        min: '0',
                        max: disp,
                        value: disp > 0 ? disp.toFixed(2) : '0',
                        disabled: true,
                        'data-max': disp
                    })
                )
            );
            $body.append($tr);
        });
        refreshTotal();
    }

    function toggleMedio() {
        var medio = $('input[name="op_devret_medio"]:checked').val();
        if (medio === 'chp') {
            $('#op-devret-wrap-chp').removeClass('d-none');
        } else {
            $('#op-devret-wrap-chp').addClass('d-none');
        }
    }

    function abrir(opId) {
        resetModal();
        opIdActual = opId;
        $('#modalOpDevolverRetenciones').modal('show');
        $('#op-devret-cargando').removeClass('d-none');

        $.ajax({
            url: urlPara(opId, 'datos'),
            method: 'GET',
            dataType: 'json'
        }).done(function (resp) {
            $('#op-devret-cargando').addClass('d-none');
            if (!resp || resp.mensaje !== 'ok' || !resp.datos) {
                mostrarError((resp && resp.mensaje) || 'Respuesta inválida.');
                return;
            }
            var d = resp.datos;
            if (!d.puede_devolver) {
                mostrarError('No hay retenciones con saldo disponible para devolver en esta OP.');
                return;
            }
            retenciones = d.retenciones || [];
            $('#op-devret-comprobante').text(d.comprobante || '');
            $('#op-devret-proveedor').text(d.proveedor ? ' — ' + d.proveedor : '');
            $('#op_devret_fecha').val(d.fecha_hoy || $('#op_devret_fecha').val());
            fillSelect($('#op_devret_cuentacaja'), d.cuentacajas, 'Cuenta banco…');
            fillSelect($('#op_devret_chequera'), d.chequeras, 'Chequera…');
            renderRetenciones(retenciones);
            $('#op-devret-form-wrap').removeClass('d-none');
            $('#op_devret_confirmar').removeClass('d-none');
        }).fail(function (xhr) {
            $('#op-devret-cargando').addClass('d-none');
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || xhr.statusText || 'Error al cargar.';
            mostrarError(msg);
        });
    }

    function payload() {
        var ret = [];
        $('#op-devret-retenciones-body tr').each(function () {
            var $tr = $(this);
            if (!$tr.find('.op-devret-check').is(':checked')) {
                return;
            }
            var id = parseInt($tr.find('.op-devret-check').data('id'), 10) || 0;
            var importe = parseFloat(String($tr.find('.op-devret-importe').val() || '0').replace(',', '.')) || 0;
            if (id > 0 && importe > 0) {
                ret.push({ id: id, importe: importe });
            }
        });
        var medio = $('input[name="op_devret_medio"]:checked').val() || 'transferencia';
        var data = {
            _token: csrf(),
            fecha: $('#op_devret_fecha').val(),
            medio: medio,
            cuentacaja_id: $('#op_devret_cuentacaja').val() || '',
            observacion: $.trim($('#op_devret_obs').val() || ''),
            retenciones: ret
        };
        if (medio === 'chp') {
            data.chequera_id = $('#op_devret_chequera').val() || '';
            data.numerocheque = $('#op_devret_numerocheque').val() || '';
            data.fechapago = $('#op_devret_fechapago').val() || '';
            var cajacheq = $('#op_devret_chequera option:selected').attr('data-cuentacaja-id');
            if ((!data.cuentacaja_id || data.cuentacaja_id === '') && cajacheq) {
                data.cuentacaja_id = cajacheq;
            }
        }
        return data;
    }

    function confirmar() {
        if (!opIdActual) {
            return;
        }
        var data = payload();
        if (!data.retenciones.length) {
            alert('Seleccione al menos una retención con importe.');
            return;
        }
        if (!data.cuentacaja_id && data.medio === 'transferencia') {
            alert('Seleccione la cuenta banco.');
            return;
        }
        if (data.medio === 'chp' && !data.chequera_id) {
            alert('Seleccione la chequera.');
            return;
        }
        if (!window.confirm('¿Confirmar devolución de retenciones por $' + fmt(totalSeleccionado()) + '?')) {
            return;
        }

        var ui = window.IngresoEgresoAnularRevertirUi;
        if (ui && typeof ui.mostrar === 'function') {
            ui.mostrar('Devolviendo retenciones…');
        }
        $('#op_devret_confirmar').prop('disabled', true);

        $.ajax({
            url: urlPara(opIdActual, 'post'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json'
            }
        }).done(function (resp) {
            if (ui && typeof ui.ocultar === 'function') {
                ui.ocultar();
            }
            if (!resp || resp.mensaje !== 'ok') {
                $('#op_devret_confirmar').prop('disabled', false);
                alert((resp && resp.mensaje) || 'No se pudo grabar la devolución.');
                return;
            }
            $('#modalOpDevolverRetenciones').modal('hide');
            if (resp.imprimir_url) {
                window.open(resp.imprimir_url, '_blank', 'noopener');
            }
            if (resp.texto) {
                alert(resp.texto);
            }
            window.location.reload();
        }).fail(function (xhr) {
            if (ui && typeof ui.ocultar === 'function') {
                ui.ocultar();
            }
            $('#op_devret_confirmar').prop('disabled', false);
            var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) || xhr.statusText || 'Error al grabar.';
            alert(msg);
        });
    }

    $(document).on('click', '.js-op-devolver-retenciones', function (e) {
        e.preventDefault();
        var id = parseInt($(this).data('pagoproveedor-id') || $(this).data('id'), 10) || 0;
        if (id > 0) {
            abrir(id);
        }
    });

    $(document).on('change', '.op-devret-check', function () {
        var $tr = $(this).closest('tr');
        $tr.find('.op-devret-importe').prop('disabled', !this.checked);
        refreshTotal();
    });

    $(document).on('input change', '.op-devret-importe', refreshTotal);
    $(document).on('change', 'input[name="op_devret_medio"]', toggleMedio);
    $(document).on('change', '#op_devret_chequera', function () {
        var cid = $('#op_devret_chequera option:selected').attr('data-cuentacaja-id');
        if (cid) {
            $('#op_devret_cuentacaja').val(cid);
        }
    });
    $('#op_devret_confirmar').on('click', confirmar);
    $('#modalOpDevolverRetenciones').on('hidden.bs.modal', resetModal);
})(jQuery);
