(function () {
    var overlay = document.getElementById('emitir-lista-overlay');
    var exportAbort = null;
    var exportSafetyTimer = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('emitir-lista-overlay-titulo');
        var s = document.getElementById('emitir-lista-overlay-subtitulo');
        if (titulo && t) {
            t.textContent = titulo;
        }
        if (subtitulo && s) {
            s.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        if (!overlay) {
            return;
        }
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
            exportSafetyTimer = null;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivoDesdeDisposition(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return fallback;
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'precios';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function idSiCodigoValido(idSelector, codigoSelector, etiqueta) {
        var codigo = String($(codigoSelector).val() || '').trim();
        var id = String($(idSelector).val() || '').trim();
        if (codigo === '') {
            $(idSelector).val('');
            return '';
        }
        if (id === '' || $(codigoSelector).attr('data-listaprecio-invalido') === codigo
            || $(codigoSelector).attr('data-mventa-invalido') === codigo
            || $(codigoSelector).attr('data-categoria-invalido') === codigo) {
            alert('El código de ' + etiqueta + ' no está resuelto. Presione Enter o elija una fila del buscador.');
            $(codigoSelector).trigger('focus');
            return null;
        }
        return id;
    }

    function armarUrl(formato) {
        var modal = document.getElementById('modal-emitir-lista-vigente');
        var base = '';
        var fallback = 'precios';
        if (formato === 'PDF') {
            base = modal.getAttribute('data-url-pdf');
            fallback += '.pdf';
        } else if (formato === 'CSV') {
            base = modal.getAttribute('data-url-csv');
            fallback += '.csv';
        } else {
            base = modal.getAttribute('data-url-excel');
            fallback += '.xlsx';
            formato = 'EXCEL';
        }

        var listaId = idSiCodigoValido('#emit_listaprecio_id', '#emit_listaprecio_id_codigo', 'lista de precios');
        if (listaId === null) {
            return null;
        }
        var marcaId = idSiCodigoValido('#emit_mventa_id', '#emit_mventa_id_codigo', 'marca');
        if (marcaId === null) {
            return null;
        }
        var categoriaId = idSiCodigoValido('#emit_categoria_id', '#emit_categoria_id_codigo', 'categoría');
        if (categoriaId === null) {
            return null;
        }

        var params = new URLSearchParams();
        var fecha = String($('#emit_fecha_vigencia').val() || '').trim();
        if (fecha !== '') {
            params.set('fecha_vigencia', fecha);
        }
        if (listaId !== '') {
            params.set('listaprecio_id', listaId);
        }
        if (marcaId !== '') {
            params.set('mventa_id', marcaId);
        }
        if (categoriaId !== '') {
            params.set('categoria_id', categoriaId);
        }
        var texto = String($('#emit_texto').val() || '').trim();
        if (texto !== '') {
            params.set('filtro_valor', texto);
            params.set('filtro_busqueda_rapida', '1');
            params.set('filtro_modo', 'todos');
            params.set('filtro_operador', 'contiene');
        }
        params.set('ocultar_precio_cero', $('#emit_ocultar_precio_cero').is(':checked') ? '1' : '0');

        return {
            href: base + (base.indexOf('?') === -1 ? '?' : '&') + params.toString(),
            formato: formato,
            fallback: fallback,
        };
    }

    function descargar(formato) {
        var pack = armarUrl(formato);
        if (!pack) {
            return;
        }

        mostrar(
            'Generando lista…',
            'Armando ' + pack.formato + '. Puede demorar según la cantidad de artículos. Pulse Esc para cerrar este aviso.'
        );

        if (exportAbort) {
            try {
                exportAbort.abort();
            } catch (e) {}
        }
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        exportAbort = controller;
        if (exportSafetyTimer) {
            clearTimeout(exportSafetyTimer);
        }
        exportSafetyTimer = setTimeout(ocultar, 600000);

        fetch(pack.href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: '*/*',
            },
        })
            .then(function (res) {
                if (res.status === 419) {
                    throw new Error('Sesión expirada. Recargue la página e intente de nuevo.');
                }
                if (!res.ok) {
                    throw new Error('No se pudo generar la lista (HTTP ' + res.status + ').');
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreArchivoDesdeDisposition(res.headers.get('Content-Disposition'), pack.fallback),
                    };
                });
            })
            .then(function (archivo) {
                dispararDescargaBlob(archivo.blob, archivo.filename);
                ocultar();
            })
            .catch(function (err) {
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                alert((err && err.message) || 'No se pudo generar la lista.');
            });
    }

    function copiarDesdeToolbar() {
        $('#emit_fecha_vigencia').val($('#fecha_vigencia_toolbar').val() || $('#emit_fecha_vigencia').val());
        var $origen = $('.precio-toolbar-lista').first();
        var $destino = $('#tm_listaprecio_emitlista');
        if ($origen.length && $destino.length) {
            $destino.find('.listaprecio_id').val($origen.find('.listaprecio_id').val() || '');
            $destino.find('.codigolistaprecio').val($origen.find('.codigolistaprecio').val() || '').removeAttr('data-listaprecio-invalido');
            $destino.find('.nombrelistaprecio').val($origen.find('.nombrelistaprecio').val() || '');
            if (typeof actualizarLinkEditarListaprecio === 'function') {
                actualizarLinkEditarListaprecio($destino, $destino.find('.listaprecio_id').val());
            }
        }
        var ocultar = $('input[name="ocultar_precio_cero"]').first().val();
        $('#emit_ocultar_precio_cero').prop('checked', String(ocultar) !== '0');
        $('#emit_texto').val($('#filtro_valor').val() || '');
    }

    $(function () {
        var $modal = $('#modal-emitir-lista-vigente');
        if (!$modal.length) {
            return;
        }
        if ($modal.parent()[0] !== document.body) {
            $modal.appendTo('body');
        }

        $('#btn-emitir-lista-vigente').on('click', function () {
            copiarDesdeToolbar();
            $modal.modal('show');
        });

        $modal.on('click', '[data-emitir-formato]', function () {
            descargar($(this).attr('data-emitir-formato'));
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !overlay || overlay.classList.contains('d-none')) {
                return;
            }
            if (exportAbort) {
                try {
                    exportAbort.abort();
                } catch (err) {}
            }
            ocultar();
        });

        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
    });
})();
