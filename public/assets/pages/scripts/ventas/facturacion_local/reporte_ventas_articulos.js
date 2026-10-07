(function () {
    var overlay = document.getElementById('fl-reporte-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('fl-reporte-overlay-titulo');
        var s = document.getElementById('fl-reporte-overlay-subtitulo');
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
        if (exportAbort) {
            try {
                exportAbort.abort();
            } catch (e) {}
            exportAbort = null;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivo(disposition, fallback) {
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

    function dispararDescarga(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'facturacion_local_ventas_articulos.xlsx';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function contexto() {
        return $('#tm_puntoventa_fl_reporte');
    }

    function limpiarBorrador($ctx) {
        $ctx.find('.puntoventa_id').val('');
        $ctx.find('.codigopuntoventa').val('');
        $ctx.find('.descripcionpuntoventa').val('');
        if (typeof actualizarLinkEditarPuntoventa === 'function') {
            actualizarLinkEditarPuntoventa($ctx, 0);
        }
    }

    window.flReporteAgregarPuntoventa = function () {
        var $ctx = contexto();
        if (!$ctx.length) {
            return;
        }
        var id = String($ctx.find('.puntoventa_id').val() || '').trim();
        var codigo = String($ctx.find('.codigopuntoventa').val() || '').trim();
        var nombre = String($ctx.find('.descripcionpuntoventa').val() || '').trim();
        if (id === '' || id === '0') {
            return;
        }
        if ($('#puntosventa-elegidos input[name="puntoventa_id[]"][value="' + id + '"]').length) {
            limpiarBorrador($ctx);
            $ctx.find('.codigopuntoventa').trigger('focus');
            return;
        }
        var chip = document.createElement('span');
        chip.className = 'badge badge-info mr-1 mb-1 fl-reporte-pv-chip';
        chip.appendChild(document.createTextNode(codigo + (nombre ? ' — ' + nombre : '')));
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link btn-sm text-white p-0 ml-1 quitar-puntoventa-fl';
        btn.title = 'Quitar';
        btn.appendChild(document.createTextNode('×'));
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'puntoventa_id[]';
        hidden.value = id;
        chip.appendChild(btn);
        chip.appendChild(hidden);
        document.getElementById('puntosventa-elegidos').appendChild(chip);
        limpiarBorrador($ctx);
        $ctx.find('.codigopuntoventa').trigger('focus');
    };

    $(function () {
        if (typeof activa_eventos_consultapuntoventa === 'function') {
            activa_eventos_consultapuntoventa();
        }

        var $modal = $('#consultapuntoventaModal');
        if ($modal.length && $modal.parent()[0] !== document.body) {
            $modal.appendTo('body');
        }

        $('#btn-agregar-puntoventa').on('click', function () {
            var $ctx = contexto();
            var codigo = String($ctx.find('.codigopuntoventa').val() || '').trim();
            var id = String($ctx.find('.puntoventa_id').val() || '').trim();
            if (codigo !== '' && (id === '' || id === '0')) {
                alert('El punto de venta no está resuelto. Presione Enter o elija una fila del buscador.');
                $ctx.find('.codigopuntoventa').trigger('focus');
                return;
            }
            window.flReporteAgregarPuntoventa();
        });

        $('#puntosventa-elegidos').on('click', '.quitar-puntoventa-fl', function () {
            $(this).closest('.fl-reporte-pv-chip').remove();
        });

        var form = document.getElementById('form-fl-ventas-articulos');
        if (form) {
            form.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && e.target && e.target.classList && e.target.classList.contains('codigopuntoventa')) {
                    e.preventDefault();
                }
            });
            form.addEventListener('submit', function (e) {
                var $ctx = contexto();
                var codigo = String($ctx.find('.codigopuntoventa').val() || '').trim();
                var id = String($ctx.find('.puntoventa_id').val() || '').trim();
                if (codigo !== '' && id !== '' && id !== '0') {
                    window.flReporteAgregarPuntoventa();
                } else if (codigo !== '' && (id === '' || id === '0')) {
                    e.preventDefault();
                    alert('El punto de venta no está resuelto. Presione Enter o elija una fila del buscador.');
                    $ctx.find('.codigopuntoventa').trigger('focus');
                    return;
                }
                if (!$('#puntosventa-elegidos input[name="puntoventa_id[]"]').length) {
                    e.preventDefault();
                    alert('Agregá al menos un punto de venta. Por ejemplo el 17, o el 17 y el 25.');
                    return;
                }
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                mostrar('Consultando ventas…', 'Puede demorar según el período y los puntos de venta.');
            });
        }

        document.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a') : null;
            if (!a || !a.href || a.href.indexOf('listar-reportes') === -1) {
                return;
            }
            e.preventDefault();
            var formato = /CSV/i.test(a.href) ? 'CSV' : (/PDF/i.test(a.href) ? 'PDF' : 'Excel');
            mostrar('Generando reporte…', 'Armando ' + formato + '. Puede demorar. Pulse Esc para cerrar este aviso.');
            if (exportAbort) {
                try {
                    exportAbort.abort();
                } catch (err) {}
            }
            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            exportAbort = controller;
            fetch(a.href, {
                method: 'GET',
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: '*/*' },
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('No se pudo generar el reporte (HTTP ' + res.status + ').');
                    }
                    var fallback = formato === 'PDF'
                        ? 'facturacion_local_ventas_articulos.pdf'
                        : (formato === 'CSV' ? 'facturacion_local_ventas_articulos.csv' : 'facturacion_local_ventas_articulos.xlsx');
                    return res.blob().then(function (blob) {
                        return { blob: blob, filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback) };
                    });
                })
                .then(function (archivo) {
                    dispararDescarga(archivo.blob, archivo.filename);
                    ocultar();
                })
                .catch(function (err) {
                    ocultar();
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    alert((err && err.message) || 'No se pudo generar el reporte.');
                });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                ocultar();
            }
        });
        window.addEventListener('pageshow', ocultar);
    });
})();
