(function ($) {
    'use strict';

    var carpetaBase = window.carpetaBase || '';
    var urls = (window.SURMAR_RECEPCION_CREAR && window.SURMAR_RECEPCION_CREAR.urls) || {};

    function escHtml(texto) {
        return $('<div>').text(texto == null ? '' : texto).html();
    }

    function urlBuscarOc() {
        return urls.buscarOc || (carpetaBase + '/stock/recepcion-proveedor-surmar/api/buscar-oc-pendientes');
    }

    function urlPrecargaOc() {
        return urls.precargaOc || (carpetaBase + '/stock/recepcion-proveedor-surmar/api/precarga-oc');
    }

    function actualizarLinkConsultarOc(ocId) {
        var id = parseInt(ocId, 10) || 0;
        var $a = $('#btn-consultar-oc-recepcion-surmar');
        if (!$a.length) {
            return;
        }
        if (id <= 0) {
            $a.addClass('d-none').attr('href', '#');
            return;
        }
        $a.removeClass('d-none').attr(
            'href',
            carpetaBase + '/compras/ordencompra/' + id + '/editar?origen=modal_consulta&vista=consulta'
        );
    }

    function aplicarOc(data) {
        if (!data || !data.ordencompra_id) {
            return;
        }
        $('#ordencompra_id').val(data.ordencompra_id);
        $('#numero_oc_buscar').val(data.numeroordencompra || '');
        $('#proveedor_id').val(data.proveedor_id || '');
        $('#proveedor_nombre').val(data.proveedor_nombre || '');
        $('#codigoproveedor').val(data.proveedor_codigo || '');
        actualizarLinkConsultarOc(data.ordencompra_id);
    }

    function limpiarOc() {
        $('#ordencompra_id').val('');
        $('#proveedor_id').val('');
        $('#proveedor_nombre').val('');
        $('#codigoproveedor').val('');
        actualizarLinkConsultarOc(0);
    }

    function cargaTablaOcPendientes() {
        var q = $('#consultaocrecepcion').val() || '';
        $.getJSON(urlBuscarOc(), { q: q })
            .done(function (rows) {
                var $body = $('#datosocrecepcion').empty();
                (rows || []).forEach(function (r) {
                    var $tr = $('<tr/>');
                    $tr.data('oc', r);
                    $tr.append('<td class="oc_tabla_num">' + escHtml(r.numeroordencompra) + '</td>');
                    $tr.append('<td>' + escHtml(r.fecha || '') + '</td>');
                    $tr.append('<td>' + escHtml(r.proveedor_nombre || '') + '</td>');
                    $tr.append('<td>' + escHtml(r.empresa_nombre || '') + '</td>');
                    $tr.append(
                        '<td><span class="badge badge-' +
                        (r.estado_com === 'SIN COM' ? 'warning' : 'info') +
                        '">' +
                        escHtml(r.estado_com) +
                        '</span></td>'
                    );
                    $tr.append(
                        '<td class="text-right">' +
                        escHtml(
                            r.cantidad_pendiente != null
                                ? Number(r.cantidad_pendiente).toLocaleString('es-AR', { maximumFractionDigits: 3 })
                                : ''
                        ) +
                        '</td>'
                    );
                    $tr.append('<td><a href="#" class="btn btn-warning btn-sm eligeconsultaocrecepcion">Elegir</a></td>');
                    $tr.append('<td><a href="#" class="btn btn-info btn-sm consultaocrecepciontabla">Consultar</a></td>');
                    $body.append($tr);
                });
            })
            .fail(function (xhr) {
                alert(
                    (xhr.responseJSON && xhr.responseJSON.error) ||
                    'Error al buscar OC Surmar pendientes'
                );
            });
    }

    function precargarOc(opciones) {
        opciones = opciones || {};
        var ocId = parseInt(opciones.ordencompra_id || $('#ordencompra_id').val(), 10) || 0;
        var numeroOc = parseInt(opciones.numero_oc || $('#numero_oc_buscar').val(), 10) || 0;
        if (!ocId && !numeroOc) {
            alert('Indique el número de OC o búsquela con la lupa.');
            return;
        }
        var params = ocId ? { ordencompra_id: ocId } : { numero_oc: numeroOc };
        $.getJSON(urlPrecargaOc(), params)
            .done(function (data) {
                aplicarOc(data);
            })
            .fail(function (xhr) {
                limpiarOc();
                alert((xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cargar la OC.');
            });
    }

    function elegirOcDesdeModal(r) {
        if (!r || !r.id) {
            return;
        }
        $('#consultaocrecepcionModal').modal('hide');
        precargarOc({ ordencompra_id: r.id });
    }

    $(function () {
        $('#btn-consulta-oc-recepcion-modal').on('click', function () {
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
            elegirOcDesdeModal($(this).closest('tr').data('oc'));
        });

        $(document).on('click', '.consultaocrecepciontabla', function (e) {
            e.preventDefault();
            var r = $(this).closest('tr').data('oc');
            if (r && r.url_consulta) {
                window.open(r.url_consulta, '_blank', 'noopener,noreferrer');
            }
        });

        $(document).on('keyup', '#consultaocrecepcion', function () {
            clearTimeout(window._tocrecepSurmar);
            window._tocrecepSurmar = setTimeout(cargaTablaOcPendientes, 300);
        });

        $('#numero_oc_buscar').on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
                precargarOc({ numero_oc: $(this).val(), forzar: true });
            }
        }).on('blur', function () {
            var n = parseInt($(this).val(), 10) || 0;
            var actual = parseInt($('#ordencompra_id').val(), 10) || 0;
            if (n > 0 && !actual) {
                precargarOc({ numero_oc: n });
            }
        });

        $('#form-recepcion-surmar').on('submit', function (e) {
            if (!(parseInt($('#ordencompra_id').val(), 10) > 0)) {
                e.preventDefault();
                alert('Debe cargar una orden de compra Surmar pendiente.');
                $('#numero_oc_buscar').focus();
            }
        });

        actualizarLinkConsultarOc($('#ordencompra_id').val());
    });
})(jQuery);
