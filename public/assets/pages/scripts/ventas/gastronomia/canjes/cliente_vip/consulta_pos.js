(function ($) {
    'use strict';

    const G = window.CANJE_MARKETING || {};
    const apiBase = (G.rutas && G.rutas.apiBase) ? G.rutas.apiBase.replace(/\/$/, '') : '';
    let ptrClienteVipId = '#cliente_vip_id';
    let ptrClienteVipNumeroid = '#cliente_vip_numeroid';
    let ptrClienteVipDocumento = '#cliente_vip_documento';
    let ptrClienteVipNombre = '#cliente_vip_nombre';
    let cmVipBuscarTimer = null;

    function csrf() {
        return G.csrfToken || $('meta[name="csrf-token"]').attr('content') || '';
    }

    window.cmConfigurarConsultaClienteVip = function (opts) {
        if (opts.ptrId) { ptrClienteVipId = opts.ptrId; }
        if (opts.ptrNumeroid) { ptrClienteVipNumeroid = opts.ptrNumeroid; }
        if (opts.ptrDocumento) { ptrClienteVipDocumento = opts.ptrDocumento; }
        if (opts.ptrNombre) { ptrClienteVipNombre = opts.ptrNombre; }
    };

    function aplicarClienteVip(cli) {
        if (!cli) { return; }
        $(ptrClienteVipId).val(cli.id || '');
        $(ptrClienteVipNumeroid).val(cli.numeroid != null ? String(cli.numeroid) : '');
        $(ptrClienteVipDocumento).val(cli.nrodocumento || '');
        $(ptrClienteVipNombre).val(cli.nombre_completo || ((cli.apellido || '') + ' ' + (cli.nombre || '')).trim());
        if (typeof window.cmOnClienteVipElegido === 'function') {
            window.cmOnClienteVipElegido(cli);
        }
    }

    window.cmAplicarClienteVip = aplicarClienteVip;

    function mensajeErrorConsulta(xhr) {
        const j = xhr && xhr.responseJSON;
        return (j && (j.error || j.mensaje)) || 'No se pudo consultar clientes VIP.';
    }

    function htmlDesdeRespuestaConsulta(resp) {
        if (typeof resp === 'string') {
            try {
                const parsed = JSON.parse(resp);
                return parsed.data || '';
            } catch (e) {
                return resp;
            }
        }
        if (resp && typeof resp.data === 'string') {
            return resp.data;
        }
        return '';
    }

    function buscarConsulta() {
        $('#datosclientevip').html(
            '<tr><td colspan="8" class="text-center text-muted">Buscando…</td></tr>'
        );
        $.ajax({
            url: apiBase + '/consulta-cliente-vip',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            data: { _token: csrf(), consulta: $('#consultaclientevip').val() || '' },
            success: function (resp) {
                const html = htmlDesdeRespuestaConsulta(resp);
                $('#datosclientevip').html(
                    html || '<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>'
                );
            },
            error: function (xhr) {
                $('#datosclientevip').html(
                    '<tr><td colspan="8" class="text-center text-danger">' + mensajeErrorConsulta(xhr) + '</td></tr>'
                );
            },
        });
    }

    function programarBusquedaVip() {
        if (cmVipBuscarTimer) {
            clearTimeout(cmVipBuscarTimer);
        }
        cmVipBuscarTimer = setTimeout(function () {
            cmVipBuscarTimer = null;
            buscarConsulta();
        }, 280);
    }

    $(document).off('click.cmConsultaVip', '.consultaclientevip');
    $(document).on('click.cmConsultaVip', '.consultaclientevip', function () {
        $('#consultaclientevip').val('');
        $('#datosclientevip').empty();
        $('#consultaclientevipModal').modal('show');
    });

    $('#consultaclientevipModal').off('shown.bs.modal.cmConsultaVip').on('shown.bs.modal.cmConsultaVip', function () {
        $('#consultaclientevip').trigger('focus');
        buscarConsulta();
    });

    $('#consultaclientevip').off('keyup.cmConsultaVip input.cmConsultaVip').on('keyup.cmConsultaVip input.cmConsultaVip', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (cmVipBuscarTimer) {
                clearTimeout(cmVipBuscarTimer);
                cmVipBuscarTimer = null;
            }
            buscarConsulta();
            return;
        }
        programarBusquedaVip();
    });

    $(document).off('click.cmEligeVip', '.eligeconsultaclientevip');
    $(document).on('click.cmEligeVip', '.eligeconsultaclientevip', function (e) {
        e.preventDefault();
        const $btn = $(this);
        aplicarClienteVip({
            id: $btn.data('id'),
            numeroid: $btn.data('numeroid'),
            nrodocumento: String($btn.data('nrodocumento') || ''),
            nombre_completo: String($btn.data('nombreCompleto') || $btn.data('nombre-completo') || '').trim(),
        });
        $('#consultaclientevipModal').modal('hide');
    });

    $('#aceptaconsultaclientevipModal').off('click.cmConsultaVip').on('click.cmConsultaVip', function () {
        if (cmVipBuscarTimer) {
            clearTimeout(cmVipBuscarTimer);
            cmVipBuscarTimer = null;
        }
        buscarConsulta();
    });
}(jQuery));
