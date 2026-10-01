(function () {
    var overlay = document.getElementById('lista-ferli-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('lista-ferli-overlay-titulo');
        var s = document.getElementById('lista-ferli-overlay-subtitulo');
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
        a.download = filename || 'lista_precios_ferli.xlsx';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function limpiarBorrador($ctx) {
        $ctx.find('.listaprecio_id').val('');
        $ctx.find('.codigolistaprecio').val('').removeAttr('data-listaprecio-invalido');
        $ctx.find('.nombrelistaprecio').val('');
    }

    window.listaFerliAgregarLista = function () {
        var $ctx = $('#tm_listaprecio_listaferli');
        if (!$ctx.length) {
            return;
        }
        var id = String($ctx.find('.listaprecio_id').val() || '').trim();
        var codigo = String($ctx.find('.codigolistaprecio').val() || '').trim();
        var nombre = String($ctx.find('.nombrelistaprecio').val() || '').trim();
        if (id === '' || id === '0') {
            return;
        }
        if ($('#listas-elegidas input[name="listaprecio_id[]"][value="' + id + '"]').length) {
            limpiarBorrador($ctx);
            $ctx.find('.codigolistaprecio').trigger('focus');
            return;
        }
        var chip = document.createElement('span');
        chip.className = 'badge badge-info mr-1 mb-1 lista-ferli-chip';
        chip.appendChild(document.createTextNode(codigo + (nombre ? ' — ' + nombre : '')));
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link btn-sm text-white p-0 ml-1 quitar-lista-ferli';
        btn.title = 'Quitar';
        btn.appendChild(document.createTextNode('×'));
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'listaprecio_id[]';
        hidden.value = id;
        chip.appendChild(btn);
        chip.appendChild(hidden);
        document.getElementById('listas-elegidas').appendChild(chip);
        limpiarBorrador($ctx);
        $ctx.find('.codigolistaprecio').trigger('focus');
    };

    $(function () {
        var $modal = $('#consultalistaprecioModal');
        if ($modal.length && $modal.parent()[0] !== document.body) {
            $modal.appendTo('body');
        }

        $('#btn-agregar-lista').on('click', function () {
            var $ctx = $('#tm_listaprecio_listaferli');
            var codigo = String($ctx.find('.codigolistaprecio').val() || '').trim();
            var id = String($ctx.find('.listaprecio_id').val() || '').trim();
            if (codigo !== '' && (id === '' || $ctx.find('.codigolistaprecio').attr('data-listaprecio-invalido') === codigo)) {
                alert('La lista no está resuelta. Presione Enter o elija una fila del buscador.');
                $ctx.find('.codigolistaprecio').trigger('focus');
                return;
            }
            window.listaFerliAgregarLista();
        });

        $('#listas-elegidas').on('click', '.quitar-lista-ferli', function () {
            $(this).closest('.lista-ferli-chip').remove();
        });

        var form = document.getElementById('form-lista-ferli');
        if (form) {
            form.addEventListener('submit', function (e) {
                var $ctx = $('#tm_listaprecio_listaferli');
                var codigo = String($ctx.find('.codigolistaprecio').val() || '').trim();
                var id = String($ctx.find('.listaprecio_id').val() || '').trim();
                if (codigo !== '' && id !== '' && $ctx.find('.codigolistaprecio').attr('data-listaprecio-invalido') !== codigo) {
                    window.listaFerliAgregarLista();
                } else if (codigo !== '' && id === '') {
                    e.preventDefault();
                    alert('La lista no está resuelta. Presione Enter o elija una fila del buscador.');
                    $ctx.find('.codigolistaprecio').trigger('focus');
                    return;
                }
                if (!$('#listas-elegidas input[name="listaprecio_id[]"]').length) {
                    e.preventDefault();
                    alert('Agregá al menos una lista. Por ejemplo la 11, o la 11, 12 y 13.');
                    return;
                }
                if (!$('input[name="mventa_id[]"]:checked').length) {
                    e.preventDefault();
                    alert('Elegí al menos una marca.');
                    return;
                }
                mostrar('Consultando…', 'Puede demorar según la cantidad de artículos.');
            });
        }

        var caja = document.getElementById('export-lista-ferli');
        if (caja) {
            caja.addEventListener('click', function (e) {
                var a = e.target.closest('a');
                if (!a || !a.href) {
                    return;
                }
                e.preventDefault();
                var formato = /CSV/i.test(a.href) ? 'CSV' : (/PDF/i.test(a.href) ? 'PDF' : 'Excel');
                mostrar('Generando lista…', 'Armando ' + formato + '. Puede demorar. Pulse Esc para cerrar este aviso.');
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
                            throw new Error('No se pudo generar la lista (HTTP ' + res.status + ').');
                        }
                        var fallback = formato === 'PDF' ? 'lista_precios_ferli.pdf' : (formato === 'CSV' ? 'lista_precios_ferli.csv' : 'lista_precios_ferli.xlsx');
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
                        alert((err && err.message) || 'No se pudo generar la lista.');
                    });
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !overlay || overlay.classList.contains('d-none')) {
                return;
            }
            ocultar();
        });
        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
    });
})();
