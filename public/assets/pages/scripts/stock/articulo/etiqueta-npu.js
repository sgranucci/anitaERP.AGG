$(function () {
    if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
        window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
    }

    var articuloIdActivo = 0;
    var imprimirUrlActiva = '';

    function escHtml(s) {
        if (s === null || s === undefined) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function etiquetaOrigen(texto) {
        var map = {
            erp: 'anitaERP',
            'anita.recpunica': 'Anita (recpunica)',
            'anita.stk_parte_unica': 'Anita (stk_parte_unica)',
        };
        return map[texto] || texto || '—';
    }

    function limpiarModal() {
        imprimirUrlActiva = '';
        $('#modalEtiquetaNpuArticuloError').addClass('d-none').text('');
        $('#modalEtiquetaNpuArticuloResumen').addClass('d-none');
    }

    function mostrarError(msg) {
        $('#modalEtiquetaNpuArticuloError').removeClass('d-none').text(msg || 'Error al consultar NPU.');
        $('#modalEtiquetaNpuArticuloResumen').addClass('d-none');
        imprimirUrlActiva = '';
    }

    function mostrarDatos(data) {
        var d = data.datos || {};
        $('#modalEtiquetaNpuArticuloOrigen').text(etiquetaOrigen(d.origen));
        $('#modalEtiquetaNpuArticuloCodigoProveedor').text(d.codigoproveedor || '—');
        $('#modalEtiquetaNpuArticuloNumeroRecepcion').text(d.numerorecepcion || '—');
        if (d.nombre_proveedor) {
            $('#modalEtiquetaNpuArticuloProveedorWrap').removeClass('d-none');
            $('#modalEtiquetaNpuArticuloProveedor').text(d.nombre_proveedor);
        } else {
            $('#modalEtiquetaNpuArticuloProveedorWrap').addClass('d-none');
        }
        $('#modalEtiquetaNpuArticuloResumen').removeClass('d-none');
        $('#modalEtiquetaNpuArticuloError').addClass('d-none');
        imprimirUrlActiva = data.imprimir_url || '';
    }

    function consultarNpu(onOk) {
        if (!articuloIdActivo) {
            return;
        }

        var npu = parseInt($('#modalEtiquetaNpuArticuloValor').val(), 10);
        if (!npu || npu <= 0) {
            mostrarError('Indique un NPU válido.');
            return;
        }

        var url = carpetaBase + '/stock/articulo/' + encodeURIComponent(articuloIdActivo) + '/consultar-npu-etiqueta?npu=' + encodeURIComponent(npu);

        $.getJSON(url)
            .done(function (data) {
                if (data && data.ok) {
                    mostrarDatos(data);
                    if (typeof onOk === 'function') {
                        onOk(data);
                    }
                } else {
                    mostrarError((data && data.mensaje) ? data.mensaje : 'No se pudo resolver el NPU.');
                }
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : 'Error al consultar NPU.';
                mostrarError(msg);
            });
    }

    $(document).on('click', '.btn-imprimir-etiqueta-npu', function () {
        articuloIdActivo = parseInt($(this).data('articulo-id'), 10) || 0;
        var sku = $(this).data('articulo-sku') || '';
        var desc = $(this).data('articulo-descripcion') || '';
        var subtitulo = sku;
        if (desc) {
            subtitulo += ' — ' + desc;
        }
        $('#modalEtiquetaNpuArticuloSubtitulo').text(subtitulo);
        $('#modalEtiquetaNpuArticuloValor').val('');
        limpiarModal();
        $('#modalEtiquetaNpuArticulo').modal('show');
        setTimeout(function () {
            $('#modalEtiquetaNpuArticuloValor').trigger('focus');
        }, 300);
    });

    $('#modalEtiquetaNpuArticuloConsultar').on('click', consultarNpu);

    $('#modalEtiquetaNpuArticuloValor').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            consultarNpu();
        }
    });

    $('#modalEtiquetaNpuArticuloImprimir').on('click', function () {
        if (imprimirUrlActiva) {
            window.location.href = imprimirUrlActiva;
            return;
        }
        consultarNpu(function (data) {
            if (data.imprimir_url) {
                window.location.href = data.imprimir_url;
            }
        });
    });

    $('#modalEtiquetaNpuArticulo').on('hidden.bs.modal', function () {
        limpiarModal();
        articuloIdActivo = 0;
    });
});
