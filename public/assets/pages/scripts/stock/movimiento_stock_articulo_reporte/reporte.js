(function () {
    var overlay = document.getElementById('stkmov-articulo-overlay');
    var exportAbort = null;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('stkmov-articulo-titulo');
        var s = document.getElementById('stkmov-articulo-subtitulo');
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
            exportAbort.abort();
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

    var form = document.getElementById('form-stkmov-articulo');
    var campoArticuloActivo = null;
    var articuloElegidoEnModal = false;

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function esTeclaEnter(e) {
        return e.key === 'Enter' || e.keyCode === 13 || e.which === 13;
    }

    function modalArticuloAbierto() {
        var modal = document.getElementById('consultaarticuloModal');
        return !!(modal && modal.classList.contains('show'));
    }

    function campoArticuloDesde(el) {
        return el && el.closest ? el.closest('.tm-articulo-campo') : null;
    }

    function enfocarSiguienteArticulo(campo) {
        if (!campo) {
            return;
        }
        var sel = campo.getAttribute('data-next-focus');
        if (!sel) {
            return;
        }
        var next = document.querySelector(sel);
        if (!next) {
            return;
        }
        next.focus();
        if (typeof next.select === 'function') {
            next.select();
        }
    }

    function asignarArticuloCampo(campo, data) {
        var sku = String((data && data.sku) || '').trim();
        var idInput = campo.querySelector('.articulo_id');
        var skuInput = campo.querySelector('.codigoarticulo');
        var descInput = campo.querySelector('.descripcionarticulo');
        if (idInput) {
            idInput.value = data && data.id ? data.id : '';
        }
        if (skuInput) {
            skuInput.value = sku;
            skuInput.setAttribute('data-stkmov-resuelto', sku);
            skuInput.removeAttribute('data-stkmov-invalido');
            skuInput.removeAttribute('data-stkmov-avisado');
        }
        if (descInput) {
            descInput.value = (data && data.descripcion) || '';
        }
        if (window.jQuery && typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo(window.jQuery(campo), data && data.id ? data.id : '');
        }
    }

    function limpiarArticuloCampo(campo, conservarSku) {
        var idInput = campo.querySelector('.articulo_id');
        var skuInput = campo.querySelector('.codigoarticulo');
        var descInput = campo.querySelector('.descripcionarticulo');
        if (idInput) {
            idInput.value = '';
        }
        if (descInput) {
            descInput.value = '';
        }
        if (skuInput) {
            if (!conservarSku) {
                skuInput.value = '';
            }
            skuInput.removeAttribute('data-stkmov-resuelto');
        }
        if (window.jQuery && typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo(window.jQuery(campo), '');
        }
    }

    function resolverArticuloCampo(campo, alertar, alResolver) {
        if (!campo || !window.jQuery) {
            return;
        }
        var skuInput = campo.querySelector('.codigoarticulo');
        if (!skuInput) {
            return;
        }
        var sku = String(skuInput.value || '').trim();
        var idInput = campo.querySelector('.articulo_id');
        if (sku === '') {
            limpiarArticuloCampo(campo, false);
            if (alResolver) {
                alResolver(true);
            }
            return;
        }
        if (skuInput.getAttribute('data-stkmov-resuelto') === sku && idInput && String(idInput.value || '') !== '') {
            if (alResolver) {
                alResolver(true);
            }
            return;
        }
        var url = typeof urlLeerArticuloPorSku === 'function'
            ? urlLeerArticuloPorSku(sku)
            : (window.carpetaBase || '') + '/stock/leerunarticuloporsku/' + encodeURIComponent(sku);
        window.jQuery.get(url).done(function (data) {
            if (String(skuInput.value || '').trim() !== sku) {
                return;
            }
            if (data && data.id) {
                asignarArticuloCampo(campo, data);
                if (alResolver) {
                    alResolver(true);
                }
                return;
            }
            limpiarArticuloCampo(campo, true);
            skuInput.setAttribute('data-stkmov-invalido', sku);
            if (alertar && skuInput.getAttribute('data-stkmov-avisado') !== sku) {
                skuInput.setAttribute('data-stkmov-avisado', sku);
                window.alert('No se encontró artículo con ese SKU.');
                window.setTimeout(function () {
                    skuInput.focus();
                    if (typeof skuInput.select === 'function') {
                        skuInput.select();
                    }
                }, 0);
            }
            if (alResolver) {
                alResolver(false);
            }
        }).fail(function () {
            if (String(skuInput.value || '').trim() !== sku) {
                return;
            }
            limpiarArticuloCampo(campo, true);
            skuInput.setAttribute('data-stkmov-invalido', sku);
            if (alertar && skuInput.getAttribute('data-stkmov-avisado') !== sku) {
                skuInput.setAttribute('data-stkmov-avisado', sku);
                window.alert('No se encontró artículo con ese SKU.');
                window.setTimeout(function () {
                    skuInput.focus();
                }, 0);
            }
            if (alResolver) {
                alResolver(false);
            }
        });
    }

    function marcarOmitirBlurArticulo(campo) {
        campoArticuloActivo = campo;
        var skuInput = campo ? campo.querySelector('.codigoarticulo') : null;
        if (skuInput) {
            skuInput.setAttribute('data-stkmov-omitir-blur', '1');
        }
    }

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    if (form) {
        form.addEventListener('mousedown', function (e) {
            var btn = e.target.closest && e.target.closest('.consultaarticulo');
            if (!btn || !form.contains(btn)) {
                return;
            }
            marcarOmitirBlurArticulo(campoArticuloDesde(btn));
        }, true);

        form.addEventListener('keydown', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            var campo = campoArticuloDesde(target);
            if (esTeclaF1(e)) {
                e.preventDefault();
                e.stopPropagation();
                marcarOmitirBlurArticulo(campo);
                var btn = campo ? campo.querySelector('.consultaarticulo') : null;
                if (btn) {
                    btn.click();
                }
                return;
            }
            if (!esTeclaEnter(e)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            resolverArticuloCampo(campo, true, function (ok) {
                if (ok) {
                    enfocarSiguienteArticulo(campo);
                }
            });
        }, true);

        form.addEventListener('blur', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            if (target.getAttribute('data-stkmov-omitir-blur') === '1' || modalArticuloAbierto()) {
                target.removeAttribute('data-stkmov-omitir-blur');
                return;
            }
            resolverArticuloCampo(campoArticuloDesde(target), false);
        }, true);

        form.addEventListener('input', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            var campo = campoArticuloDesde(target);
            var idInput = campo ? campo.querySelector('.articulo_id') : null;
            var descInput = campo ? campo.querySelector('.descripcionarticulo') : null;
            if (idInput) {
                idInput.value = '';
            }
            if (descInput) {
                descInput.value = '';
            }
            target.removeAttribute('data-stkmov-resuelto');
            target.removeAttribute('data-stkmov-invalido');
            target.removeAttribute('data-stkmov-avisado');
            if (window.jQuery && campo && typeof actualizarLinkEditarArticulo === 'function') {
                actualizarLinkEditarArticulo(window.jQuery(campo), '');
            }
        }, true);

        // El change compartido de consulta.js avisa en el blur. Acá el aviso queda solo en Enter.
        form.addEventListener('change', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            e.stopImmediatePropagation();
        }, true);

        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrar('Consultando movimientos…', 'Puede demorar según el período. No cierre la página.');
        });
    }

    document.querySelectorAll('a[href*="listar-reporte-movimientos-stock-articulo"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var href = a.href;
            var lower = href.toLowerCase();
            var formato = 'archivo';
            var fallback = 'movimientos_stock_articulo';
            if (lower.indexOf('excel') !== -1) {
                formato = 'Excel';
                fallback += '.xlsx';
            } else if (lower.indexOf('pdf') !== -1) {
                formato = 'PDF';
                fallback += '.pdf';
            } else if (lower.indexOf('csv') !== -1) {
                formato = 'CSV';
                fallback += '.csv';
            }
            mostrar('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');
            exportAbort = new AbortController();
            fetch(href, {
                credentials: 'same-origin',
                signal: exportAbort.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('No se pudo exportar');
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback)
                    };
                });
            }).then(function (pack) {
                var url = window.URL.createObjectURL(pack.blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = pack.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.setTimeout(function () {
                    window.URL.revokeObjectURL(url);
                }, 1500);
                exportAbort = null;
                ocultar();
            }).catch(function (err) {
                exportAbort = null;
                ocultar();
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert((err && err.message) || 'No se pudo exportar');
            });
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.eligeconsultaarticulo') : null;
        if (!btn || !campoArticuloActivo || !form || !form.contains(campoArticuloActivo)) {
            return;
        }
        articuloElegidoEnModal = true;
    }, true);

    document.addEventListener('keydown', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        var target = e.target;
        if (!target || target.id !== 'consulta' || !modalArticuloAbierto()) {
            return;
        }
        if (!campoArticuloActivo || !form || !form.contains(campoArticuloActivo)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var elegir = document.querySelector('#datos .eligeconsultaarticulo');
        if (elegir) {
            elegir.click();
        }
    }, true);

    if (window.jQuery) {
        window.jQuery('#consultaarticuloModal').on('hidden.bs.modal.stkmovArt', function () {
            if (!articuloElegidoEnModal || !campoArticuloActivo || !form || !form.contains(campoArticuloActivo)) {
                articuloElegidoEnModal = false;
                return;
            }
            articuloElegidoEnModal = false;
            var skuInput = campoArticuloActivo.querySelector('.codigoarticulo');
            var idInput = campoArticuloActivo.querySelector('.articulo_id');
            var sku = skuInput ? String(skuInput.value || '').trim() : '';
            if (skuInput && idInput && String(idInput.value || '') !== '' && sku !== '') {
                skuInput.setAttribute('data-stkmov-resuelto', sku);
                skuInput.removeAttribute('data-stkmov-invalido');
                skuInput.removeAttribute('data-stkmov-avisado');
            }
            var campo = campoArticuloActivo;
            window.setTimeout(function () {
                enfocarSiguienteArticulo(campo);
            }, 0);
        });
    }

    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);
})();
