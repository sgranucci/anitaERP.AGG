(function () {
    var overlay = document.getElementById('kilos-articulo-surmar-overlay');
    var form = document.getElementById('form-kilos-articulo-surmar');
    var exportAbort = null;
    var campoArticuloActivo = null;
    var articuloElegidoEnModal = false;

    function mostrar(titulo, subtitulo) {
        if (!overlay) {
            return;
        }
        var t = document.getElementById('kilos-articulo-surmar-titulo');
        var s = document.getElementById('kilos-articulo-surmar-subtitulo');
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
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

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

    function enfocarSiguiente(campo) {
        if (!campo) {
            return;
        }
        var sel = campo.getAttribute('data-next-focus');
        var next = sel ? document.querySelector(sel) : null;
        if (!next) {
            return;
        }
        next.focus();
        if (typeof next.select === 'function') {
            next.select();
        }
    }

    function asignarArticulo(campo, data) {
        var sku = String((data && data.sku) || '').trim();
        var idInput = campo.querySelector('.articulo_id');
        var skuInput = campo.querySelector('.codigoarticulo');
        var descInput = campo.querySelector('.descripcionarticulo');
        if (idInput) {
            idInput.value = data && data.id ? data.id : '';
        }
        if (skuInput) {
            skuInput.value = sku;
            skuInput.setAttribute('data-kilos-resuelto', sku);
        }
        if (descInput) {
            descInput.value = (data && data.descripcion) || '';
        }
        if (window.jQuery && typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo(window.jQuery(campo), data && data.id ? data.id : '');
        }
    }

    function limpiarArticulo(campo, conservarSku) {
        var idInput = campo.querySelector('.articulo_id');
        var skuInput = campo.querySelector('.codigoarticulo');
        var descInput = campo.querySelector('.descripcionarticulo');
        if (idInput) {
            idInput.value = '';
        }
        if (descInput) {
            descInput.value = '';
        }
        if (skuInput && !conservarSku) {
            skuInput.value = '';
        }
        if (skuInput) {
            skuInput.removeAttribute('data-kilos-resuelto');
        }
        if (window.jQuery && typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo(window.jQuery(campo), '');
        }
    }

    function resolverArticulo(campo, alertar, alResolver) {
        if (!campo || !window.jQuery) {
            return;
        }
        var skuInput = campo.querySelector('.codigoarticulo');
        if (!skuInput) {
            return;
        }
        var sku = String(skuInput.value || '').trim();
        if (sku === '') {
            limpiarArticulo(campo, false);
            if (alResolver) {
                alResolver(true);
            }
            return;
        }
        if (skuInput.getAttribute('data-kilos-resuelto') === sku) {
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
                asignarArticulo(campo, data);
                if (alResolver) {
                    alResolver(true);
                }
                return;
            }
            limpiarArticulo(campo, true);
            if (alertar) {
                window.alert('No se encontró artículo con ese SKU.');
                window.setTimeout(function () { skuInput.focus(); }, 0);
            }
            if (alResolver) {
                alResolver(false);
            }
        }).fail(function () {
            limpiarArticulo(campo, true);
            if (alertar) {
                window.alert('No se encontró artículo con ese SKU.');
            }
            if (alResolver) {
                alResolver(false);
            }
        });
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
            campoArticuloActivo = campoArticuloDesde(btn);
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
                campoArticuloActivo = campo;
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
            resolverArticulo(campo, true, function (ok) {
                if (ok) {
                    enfocarSiguiente(campo);
                }
            });
        }, true);

        form.addEventListener('focusout', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            if (modalArticuloAbierto()) {
                return;
            }
            resolverArticulo(campoArticuloDesde(target), false);
        });

        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrar('Consultando kilos…', 'Puede demorar según el período. No cierre la página.');
        });
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

    document.querySelectorAll('a[href*="listar-kilos-articulo-surmar"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var href = a.href;
            var lower = href.toLowerCase();
            var formato = 'archivo';
            var fallback = 'kilos_articulo_surmar';
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
                    return { blob: blob, filename: nombreArchivo(res.headers.get('Content-Disposition'), fallback) };
                });
            }).then(function (pack) {
                var url = window.URL.createObjectURL(pack.blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = pack.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1500);
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

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && exportAbort) {
            exportAbort.abort();
            ocultar();
        }
    });
    window.addEventListener('pageshow', ocultar);

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.eligeconsultaarticulo') : null;
        if (btn && campoArticuloActivo && form && form.contains(campoArticuloActivo)) {
            articuloElegidoEnModal = true;
        }
    }, true);

    if (window.jQuery) {
        window.jQuery('#consultaarticuloModal').on('hidden.bs.modal.kilosArt', function () {
            if (!articuloElegidoEnModal || !campoArticuloActivo) {
                articuloElegidoEnModal = false;
                return;
            }
            articuloElegidoEnModal = false;
            var campo = campoArticuloActivo;
            var skuInput = campo.querySelector('.codigoarticulo');
            if (skuInput) {
                skuInput.setAttribute('data-kilos-resuelto', String(skuInput.value || '').trim());
            }
            window.setTimeout(function () { enfocarSiguiente(campo); }, 0);
        });
    }
})();
