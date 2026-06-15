(function ($) {
    'use strict';

    var carpetaBase = window.carpetaBase || '';

    function proveedorIdFormulario() {
        var id = parseInt($('#proveedor_id').val(), 10);
        return id > 0 ? id : null;
    }

    function actualizarAvisoFiltroProveedor() {
        var $aviso = $('#consultaocrecepcion-filtro-proveedor');
        if (!$aviso.length) {
            return;
        }
        if (proveedorIdFormulario()) {
            $aviso.removeClass('d-none');
        } else {
            $aviso.addClass('d-none');
        }
    }

    function escHtml(texto) {
        return $('<div>').text(texto == null ? '' : texto).html();
    }

    function cargaTablaOcPendientes() {
        var q = $('#consultaocrecepcion').val() || '';
        var params = { q: q };
        var proveedorId = proveedorIdFormulario();
        if (proveedorId) {
            params.proveedor_id = proveedorId;
        }

        $.getJSON(carpetaBase + '/stock/recepcion-proveedor/api/buscar-oc-pendientes', params)
            .done(function (rows) {
                var $body = $('#datosocrecepcion').empty();
                (rows || []).forEach(function (r) {
                    var $tr = $('<tr/>');
                    $tr.data('oc', r);
                    $tr.append('<td class="oc_tabla_num">' + escHtml(r.numeroordencompra) + '</td>');
                    $tr.append('<td>' + escHtml(r.fecha || '') + '</td>');
                    $tr.append('<td>' + escHtml(r.proveedor_nombre || '') + '</td>');
                    $tr.append('<td>' + escHtml(r.empresa_nombre || '') + '</td>');
                    $tr.append('<td><span class="badge badge-' + (r.estado_com === 'SIN COM' ? 'warning' : 'info') + '">' + escHtml(r.estado_com) + '</span></td>');
                    $tr.append('<td class="text-right">' + escHtml(r.cantidad_pendiente != null ? Number(r.cantidad_pendiente).toLocaleString('es-AR', { maximumFractionDigits: 3 }) : '') + '</td>');
                    $tr.append('<td><a href="#" class="btn btn-warning btn-sm eligeconsultaocrecepcion">Elegir</a></td>');
                    $tr.append('<td><a href="#" class="btn btn-outline-primary btn-sm consultaocrecepciontabla">Consultar</a></td>');
                    $body.append($tr);
                });
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al buscar OC en AnitaERP');
            });
    }

    function elegirOcDesdeModal(r) {
        if (!r || !r.id) {
            return;
        }
        $('#ordencompra_id').val(r.id);
        $('#numero_oc_buscar').val(r.numeroordencompra);
        $('#proveedor_id').val(r.proveedor_id || '');
        $('#proveedor_nombre').val(r.proveedor_nombre || '');
        if (r.empresa_id) {
            $('#empresa_id').val(r.empresa_id);
        }
        $('#consultaocrecepcionModal').modal('hide');

        if (typeof window.recepcionProveedorCargarOc === 'function') {
            window.recepcionProveedorCargarOc(false, { ordencompra_id: r.id, forzar: true });
        }
    }

    $(function () {
        $('#btn-consulta-oc-recepcion-modal').on('click', function () {
            actualizarAvisoFiltroProveedor();
            $('#consultaocrecepcionModal').modal('show');
            $('#consultaocrecepcion').val('').trigger('focus');
            cargaTablaOcPendientes();
        });

        $('#consultaocrecepcionModal').on('shown.bs.modal', function () {
            $('#consultaocrecepcion').trigger('focus');
        });

        $('#aceptaconsultaocrecepcionModal').on('click', function () {
            $('#consultaocrecepcionModal').modal('hide');
        });

        $(document).on('click', '.eligeconsultaocrecepcion', function (e) {
            e.preventDefault();
            var r = $(this).closest('tr').data('oc');
            elegirOcDesdeModal(r);
        });

        $(document).on('click', '.consultaocrecepciontabla', function (e) {
            e.preventDefault();
            var r = $(this).closest('tr').data('oc');
            if (r && r.url_consulta) {
                window.open(r.url_consulta, '_blank', 'noopener,noreferrer');
            }
        });

        $(document).on('keyup', '#consultaocrecepcion', function () {
            clearTimeout(window._tocrecep);
            window._tocrecep = setTimeout(cargaTablaOcPendientes, 300);
        });
    });
})(jQuery);
