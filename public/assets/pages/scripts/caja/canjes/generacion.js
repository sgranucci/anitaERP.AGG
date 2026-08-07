(function ($) {
    'use strict';

    const C = window.TICKET_CANJE_CAJA || {};
    let previewData = null;
    let puedeOperar = !!C.puedeOperar;

    function csrf() {
        return C.csrfToken || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function empresaId() {
        return parseInt($('#empresa_id').val() || C.empresaId || '0', 10) || 0;
    }

    function fmtMoney(n) {
        const v = Number(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function toast(msg, ok) {
        if (typeof toastr !== 'undefined') {
            ok ? toastr.success(msg) : toastr.error(msg);
            return;
        }
        window.alert(msg);
    }

    function exigirOperacion() {
        if (!puedeOperar) {
            toast('Debe abrir la jornada de estacionamiento para esta empresa antes de emitir tickets.', false);
            return false;
        }
        return true;
    }

    function aplicarBloqueoOperacion(ctx) {
        puedeOperar = !!(ctx.jornada_abierta && ctx.fecha_jornada);
        C.puedeOperar = puedeOperar;
        const $fs = $('#tcc-fieldset-emision');
        const $aviso = $('#tcc-aviso-bloqueo');
        if ($fs.length) {
            $fs.prop('disabled', !puedeOperar);
        }
        if (puedeOperar) {
            $aviso.addClass('d-none');
        } else {
            $aviso.removeClass('d-none');
            $aviso.text('Debe abrir la jornada de estacionamiento para esta empresa antes de emitir tickets.');
        }
    }

    function actualizarEstadoOperativo(ctx) {
        $('#fecha_jornada_fmt').val(ctx.fecha_jornada_fmt || '—');
        $('#cajero_nombre').val(ctx.cajero_nombre || '—');
        const $est = $('#estado-operativo');
        if (ctx.jornada_abierta && ctx.fecha_jornada) {
            $est.html('<span class="badge badge-success">Listo para emitir</span>');
        } else {
            $est.html('<span class="badge badge-danger">Sin jornada abierta</span>');
        }
        aplicarBloqueoOperacion(ctx);
    }

    function cargarContexto() {
        const emp = empresaId();
        if (emp <= 0) {
            return;
        }
        $.ajax({
            url: C.rutas.contexto,
            type: 'GET',
            data: { empresa_id: emp },
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.ok) {
                    actualizarEstadoOperativo(resp);
                }
            },
        });
    }

    function limpiarClienteVip() {
        $('#cliente_vip_caja_id').val('');
        $('#es_vip').val('0');
        $('#cliente_vip_nombre_txt').removeClass('text-dark').addClass('text-muted').html('');
    }

    function pintarNombreCliente(esVip, nombre) {
        const $n = $('#cliente_vip_nombre_txt');
        if (esVip) {
            $n.removeClass('text-muted').addClass('text-dark').html(
                '<span class="badge badge-success mr-1">VIP</span>' + (nombre || '')
            );
        } else {
            $n.removeClass('text-dark').addClass('text-muted').html(
                '<span class="badge badge-secondary mr-1">No VIP</span>' +
                '<span class="small">(ticket = ' + (C.porcentaje || 5) + '% del monto)</span>'
            );
        }
    }

    function aplicarVipDesdeModal(cli) {
        if (!cli) {
            return;
        }
        $('#cliente_vip_caja_id').val(cli.id || '');
        $('#nro_documento').val(cli.nrodocumento || '');
        $('#es_vip').val('1');
        pintarNombreCliente(true, cli.nombre_completo || '');
        setTimeout(function () { $('#monto_venta').trigger('focus'); }, 50);
    }

    function resolverClientePorDocumento() {
        if (!exigirOperacion()) {
            return;
        }
        const doc = ($('#nro_documento').val() || '').trim();
        const emp = empresaId();
        if (!doc || emp <= 0) {
            limpiarClienteVip();
            return;
        }
        $.ajax({
            url: C.rutas.resolverCliente,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            data: { _token: csrf(), empresa_id: emp, nro_documento: doc },
            success: function (resp) {
                if (!resp || !resp.ok) {
                    limpiarClienteVip();
                    return;
                }
                $('#nro_documento').val(resp.nro_documento || doc);
                if (resp.es_vip) {
                    $('#cliente_vip_caja_id').val(resp.cliente_vip_caja_id || '');
                    $('#es_vip').val('1');
                    pintarNombreCliente(true, resp.nombre_cliente || '');
                } else {
                    $('#cliente_vip_caja_id').val('');
                    $('#es_vip').val('0');
                    pintarNombreCliente(false, '');
                }
            },
            error: function () {
                limpiarClienteVip();
            },
        });
    }

    function abrirPreview() {
        if (!exigirOperacion()) {
            return;
        }
        const emp = empresaId();
        const doc = ($('#nro_documento').val() || '').trim();
        const monto = parseFloat($('#monto_venta').val() || '0');
        const cant = parseInt($('#cantidad').val() || '1', 10) || 1;

        if (emp <= 0) {
            toast('Seleccione empresa.', false);
            return;
        }
        if (!doc) {
            toast('Indique el documento.', false);
            $('#nro_documento').focus();
            return;
        }
        if (!(monto > 0)) {
            toast('Indique el monto de venta.', false);
            $('#monto_venta').focus();
            return;
        }

        $.ajax({
            url: C.rutas.preview,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: csrf(),
                empresa_id: emp,
                nro_documento: doc,
                monto_venta: monto,
                cantidad: cant,
                cliente_vip_caja_id: $('#cliente_vip_caja_id').val() || '',
            },
            success: function (resp) {
                if (!resp || !resp.ok) {
                    toast((resp && resp.error) || 'No se pudo calcular.', false);
                    return;
                }
                previewData = resp;
                $('#prev-documento').text(resp.nro_documento || '—');
                $('#prev-cliente').text(resp.nombre_cliente || (resp.es_vip ? 'VIP' : '—'));
                $('#prev-tipo').html(
                    resp.es_vip
                        ? '<span class="badge badge-success">VIP</span>'
                        : '<span class="badge badge-secondary">No VIP</span>'
                );
                $('#prev-monto-venta').text('$ ' + fmtMoney(resp.monto_venta));
                $('#prev-monto-ticket').text('$ ' + fmtMoney(resp.monto_ticket_total));
                $('#prev-cantidad').text(
                    resp.cantidad + ' ticket(s) de $ ' + fmtMoney(resp.monto_por_ticket)
                );
                if (resp.imprime) {
                    $('#prev-aviso-impresion').text('Se imprimirá en la impresora del PV estacionamiento.');
                    $('#prev-label-imprimir').show();
                } else {
                    $('#prev-aviso-impresion').text(
                        'Cliente VIP: se graba con monto ticket $ 0 y no se imprime.'
                    );
                    $('#prev-label-imprimir').hide();
                }
                $('#modalConfirmacionTicketCanje').modal('show');
            },
            error: function (xhr) {
                const j = xhr.responseJSON;
                toast((j && (j.error || j.mensaje)) || 'Error al calcular preview.', false);
            },
        });
    }

    function confirmarEmitir() {
        if (!previewData || !exigirOperacion()) {
            return;
        }
        const $btn = $('#btn-confirmar-emitir').prop('disabled', true);
        $.ajax({
            url: C.rutas.emitir,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: csrf(),
                empresa_id: empresaId(),
                nro_documento: previewData.nro_documento,
                monto_venta: previewData.monto_venta,
                cantidad: previewData.cantidad,
                cliente_vip_caja_id: previewData.cliente_vip_caja_id || $('#cliente_vip_caja_id').val() || '',
            },
            success: function (resp) {
                $btn.prop('disabled', false);
                if (!resp || !resp.ok) {
                    toast((resp && resp.error) || 'No se pudo emitir.', false);
                    return;
                }
                $('#modalConfirmacionTicketCanje').modal('hide');
                toast(resp.mensaje || 'Emitido.', true);
                const emp = empresaId();
                window.location.href = C.rutas.index + (emp ? ('?empresa_id=' + emp) : '');
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                const j = xhr.responseJSON;
                toast((j && (j.error || j.mensaje)) || 'Error al emitir.', false);
            },
        });
    }

    function buscarConsultaVip() {
        if (!exigirOperacion()) {
            return;
        }
        $('#datosclientevip').html(
            '<tr><td colspan="8" class="text-center text-muted">Buscando…</td></tr>'
        );
        $.ajax({
            url: C.rutas.consultaVip,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: csrf(),
                empresa_id: empresaId(),
                consulta: $('#consultaclientevip').val() || '',
            },
            success: function (resp) {
                $('#datosclientevip').html(
                    (resp && resp.data) ||
                    '<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>'
                );
            },
            error: function () {
                $('#datosclientevip').html(
                    '<tr><td colspan="8" class="text-center text-danger">Error al consultar</td></tr>'
                );
            },
        });
    }

    function enfocarDocumento() {
        const $doc = $('#nro_documento');
        if ($doc.length && !$doc.prop('disabled')) {
            $doc.trigger('focus');
        }
    }

    $(function () {
        $('#consultaclientevipModalLabel').text('Clientes VIP — canjes caja');

        $(document).on('change', '#empresa_id', function () {
            const emp = empresaId();
            limpiarClienteVip();
            $('#nro_documento').val('');
            cargarContexto();
            window.location.href = C.rutas.index + (emp ? ('?empresa_id=' + emp) : '');
        });

        $('#nro_documento').on('blur', resolverClientePorDocumento);

        $('#btn-preview-emitir').on('click', abrirPreview);
        $('#btn-confirmar-emitir').on('click', confirmarEmitir);

        $(document).on('click', '.consultaclientevip', function (e) {
            if (!exigirOperacion()) {
                e.preventDefault();
                return;
            }
            $('#consultaclientevip').val('');
            $('#datosclientevip').empty();
            $('#consultaclientevipModal').modal('show');
        });

        $('#consultaclientevipModal').on('shown.bs.modal', function () {
            $('#consultaclientevip').trigger('focus');
            buscarConsultaVip();
        });

        $('#consultaclientevip').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarConsultaVip();
            }
        });

        $('#aceptaconsultaclientevipModal').on('click', buscarConsultaVip);

        $(document).on('click', '.eligeconsultaclientevip', function (e) {
            e.preventDefault();
            const $btn = $(this);
            aplicarVipDesdeModal({
                id: $btn.data('id'),
                nrodocumento: String($btn.data('nrodocumento') || ''),
                nombre_completo: String($btn.data('nombreCompleto') || $btn.data('nombre-completo') || ''),
            });
            $('#consultaclientevipModal').modal('hide');
        });

        $(document).on('click', '.js-reimprimir', function () {
            const id = $(this).data('id');
            if (!id) {
                return;
            }
            $.ajax({
                url: C.rutas.reimprimirBase + '/' + id + '/reimprimir',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                data: { _token: csrf() },
                success: function (resp) {
                    toast((resp && resp.mensaje) || (resp && resp.ok ? 'OK' : 'Error'), !!(resp && resp.ok));
                },
                error: function (xhr) {
                    const j = xhr.responseJSON;
                    toast((j && (j.error || j.mensaje)) || 'Error al reimprimir.', false);
                },
            });
        });

        $(document).on('click', '.js-anular', function () {
            if (!C.puedeAnular) {
                return;
            }
            const id = $(this).data('id');
            const vale = String($(this).data('vale') || id);
            if (!id) {
                return;
            }
            if (!window.confirm('¿Anular el ticket ' + vale + '? Solo si sigue pendiente.')) {
                return;
            }
            const $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: C.rutas.reimprimirBase + '/' + id + '/anular',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                data: { _token: csrf() },
                success: function (resp) {
                    toast((resp && (resp.mensaje || resp.error)) || (resp && resp.ok ? 'Anulado' : 'Error'), !!(resp && resp.ok));
                    if (resp && resp.ok) {
                        window.location.reload();
                    } else {
                        $btn.prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    const j = xhr.responseJSON;
                    toast((j && (j.error || j.mensaje)) || 'Error al anular.', false);
                    $btn.prop('disabled', false);
                },
            });
        });

        setTimeout(enfocarDocumento, 150);
    });
}(jQuery));
