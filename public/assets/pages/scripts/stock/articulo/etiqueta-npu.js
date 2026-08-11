$(function () {
    if (typeof window.carpetaBase === 'undefined') {
        var __locCb = window.location.pathname || '';
        var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
        window.carpetaBase = __mCb ? __mCb[1] : '';
    }

    var articuloIdActivo = 0;
    var imprimirUrlActiva = '';
    var paginaListaActiva = 1;

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
        paginaListaActiva = 1;
        $('#modalEtiquetaNpuArticuloError').addClass('d-none').text('');
        $('#modalEtiquetaNpuArticuloAvisoImpresion').addClass('d-none').text('');
        $('#modalEtiquetaNpuArticuloResumen').addClass('d-none');
        $('#modalEtiquetaNpuArticuloListaWrap').addClass('d-none');
        $('#modalEtiquetaNpuArticuloLista').empty();
        $('#modalEtiquetaNpuArticuloPaginacion').empty();
    }

    function mostrarError(msg) {
        $('#modalEtiquetaNpuArticuloError').removeClass('d-none').text(msg || 'Error al consultar NPU.');
        $('#modalEtiquetaNpuArticuloResumen').addClass('d-none');
        imprimirUrlActiva = '';
    }

    function entradaNpu() {
        return {
            desde: $.trim($('#modalEtiquetaNpuArticuloDesde').val()),
            hasta: $.trim($('#modalEtiquetaNpuArticuloHasta').val()),
        };
    }

    function sinCriterioNpu(entrada) {
        return !entrada.desde && !entrada.hasta;
    }

    function pintarListaNpus(npus) {
        var rows = '';
        (npus || []).forEach(function (n) {
            rows += '<tr class="modal-etiqueta-npu-row" data-npu="' + escHtml(n) + '" style="cursor:pointer;">';
            rows += '<td><strong>' + escHtml(n) + '</strong></td>';
            rows += '</tr>';
        });
        if (!rows) {
            rows = '<tr><td class="text-muted text-center">Sin NPUs registrados</td></tr>';
        }
        $('#modalEtiquetaNpuArticuloLista').html(rows);
        if ((npus || []).length > 0) {
            $('#modalEtiquetaNpuArticuloListaWrap').removeClass('d-none');
        } else {
            $('#modalEtiquetaNpuArticuloListaWrap').addClass('d-none');
        }
    }

    function pintarPaginacion(page, lastPage) {
        var html = '';
        if (lastPage > 1) {
            html += '<span class="text-muted mr-2">Pág. ' + page + ' / ' + lastPage + '</span>';
            if (page > 1) {
                html += '<button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 btn-npu-lista-pag" data-page="' + (page - 1) + '">&laquo;</button> ';
            }
            if (page < lastPage) {
                html += '<button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 btn-npu-lista-pag" data-page="' + (page + 1) + '">&raquo;</button>';
            }
        }
        $('#modalEtiquetaNpuArticuloPaginacion').html(html);
    }

    function mostrarDatos(data) {
        var d = data.datos || null;
        var cantidad = parseInt(data.cantidad, 10) || 0;
        var criterio = data.criterio || '';

        if (data.npus && data.npus.length) {
            pintarListaNpus(data.npus);
        }

        if (data.listado && data.last_page) {
            pintarPaginacion(parseInt(data.page, 10) || 1, parseInt(data.last_page, 10) || 1);
            paginaListaActiva = parseInt(data.page, 10) || 1;
        } else {
            $('#modalEtiquetaNpuArticuloPaginacion').empty();
        }

        if (criterio || cantidad > 0) {
            $('#modalEtiquetaNpuArticuloCriterio').text(criterio || '—');
            $('#modalEtiquetaNpuArticuloCantidad').text(cantidad > 0 ? cantidad : '—');
            $('#modalEtiquetaNpuArticuloCriterioWrap').removeClass('d-none');
        } else {
            $('#modalEtiquetaNpuArticuloCriterioWrap').addClass('d-none');
        }

        if (d && cantidad === 1 && !data.listado) {
            $('#modalEtiquetaNpuArticuloDetalleWrap').removeClass('d-none');
            $('#modalEtiquetaNpuArticuloOrigen').text(etiquetaOrigen(d.origen));
            $('#modalEtiquetaNpuArticuloCodigoProveedor').text(d.codigoproveedor || '—');
            $('#modalEtiquetaNpuArticuloNumeroRecepcion').text(d.numerorecepcion || '—');
            if (d.nombre_proveedor) {
                $('#modalEtiquetaNpuArticuloProveedorWrap').removeClass('d-none');
                $('#modalEtiquetaNpuArticuloProveedor').text(d.nombre_proveedor);
            } else {
                $('#modalEtiquetaNpuArticuloProveedorWrap').addClass('d-none');
            }
        } else {
            $('#modalEtiquetaNpuArticuloDetalleWrap').addClass('d-none');
        }

        $('#modalEtiquetaNpuArticuloResumen').removeClass('d-none');
        $('#modalEtiquetaNpuArticuloError').addClass('d-none').text('');
        imprimirUrlActiva = data.imprimir_url || '';

        if (data.mensaje_impresion && !imprimirUrlActiva) {
            $('#modalEtiquetaNpuArticuloAvisoImpresion')
                .removeClass('d-none')
                .text(data.mensaje_impresion);
        } else {
            $('#modalEtiquetaNpuArticuloAvisoImpresion').addClass('d-none').text('');
        }
    }

    function consultarNpu(onOk, page) {
        if (!articuloIdActivo) {
            return;
        }

        var entrada = entradaNpu();
        var params = [];
        if (sinCriterioNpu(entrada)) {
            params.push('page=' + encodeURIComponent(page || paginaListaActiva || 1));
        } else {
            if (entrada.desde !== '') {
                params.push('npu_desde=' + encodeURIComponent(entrada.desde));
            }
            if (entrada.hasta !== '') {
                params.push('npu_hasta=' + encodeURIComponent(entrada.hasta));
            }
        }

        var url = carpetaBase + '/stock/articulo/' + encodeURIComponent(articuloIdActivo) + '/consultar-npu-etiqueta';
        if (params.length) {
            url += '?' + params.join('&');
        }

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
        $('#modalEtiquetaNpuArticuloDesde').val('');
        $('#modalEtiquetaNpuArticuloHasta').val('');
        limpiarModal();
        $('#modalEtiquetaNpuArticulo').modal('show');
        setTimeout(function () {
            $('#modalEtiquetaNpuArticuloDesde').trigger('focus');
            consultarNpu(null, 1);
        }, 300);
    });

    $('#modalEtiquetaNpuArticuloConsultar').on('click', function () {
        paginaListaActiva = 1;
        consultarNpu(null, 1);
    });

    $('#modalEtiquetaNpuArticuloDesde, #modalEtiquetaNpuArticuloHasta').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            paginaListaActiva = 1;
            consultarNpu(null, 1);
        }
    });

    $(document).on('click', '.modal-etiqueta-npu-row', function () {
        var npu = $(this).attr('data-npu') || $(this).data('npu');
        if (!npu) {
            return;
        }
        $('#modalEtiquetaNpuArticuloDesde').val(npu);
        $('#modalEtiquetaNpuArticuloHasta').val('');
        paginaListaActiva = 1;
        consultarNpu(null, 1);
    });

    $(document).on('click', '.btn-npu-lista-pag', function () {
        var page = parseInt($(this).data('page'), 10) || 1;
        consultarNpu(null, page);
    });

    $('#modalEtiquetaNpuArticuloImprimir').on('click', function () {
        var entrada = entradaNpu();
        if (sinCriterioNpu(entrada)) {
            mostrarError('Indique un NPU, lista o rango para imprimir (o elija uno de la lista).');
            return;
        }
        if (imprimirUrlActiva) {
            if (typeof window.imprimirEtiquetaArticulo === 'function') {
                window.imprimirEtiquetaArticulo(imprimirUrlActiva);
            } else {
                window.location.href = imprimirUrlActiva;
            }
            return;
        }
        consultarNpu(function (data) {
            if (data.imprimir_url) {
                if (typeof window.imprimirEtiquetaArticulo === 'function') {
                    window.imprimirEtiquetaArticulo(data.imprimir_url);
                } else {
                    window.location.href = data.imprimir_url;
                }
            } else if (data.mensaje_impresion) {
                mostrarError(data.mensaje_impresion);
            } else if (data.listado) {
                mostrarError('Seleccione un NPU o indique un criterio de impresión.');
            }
        }, 1);
    });

    $('#modalEtiquetaNpuArticulo').on('hidden.bs.modal', function () {
        limpiarModal();
        articuloIdActivo = 0;
    });
});
