/**
 * Stock por OT: overlay de descarga.
 * El POST descarga attachment sin navegar: se usa fetch+blob para ocultar el aviso al terminar.
 */
(function () {
    'use strict';

    var OVERLAY_ID = 'repstockot-overlay';
    var TITULO_ID = 'repstockot-overlay-titulo';
    var SUBTITULO_ID = 'repstockot-overlay-subtitulo';
    var hintTimer = null;
    var abortController = null;

    function overlay() {
        return document.getElementById(OVERLAY_ID);
    }

    function mostrar(titulo, subtitulo) {
        var ov = overlay();
        if (!ov) {
            return;
        }
        var t = document.getElementById(TITULO_ID);
        var s = document.getElementById(SUBTITULO_ID);
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
        ov.classList.remove('d-none');
        ov.style.setProperty('display', 'flex', 'important');
        ov.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (hintTimer) {
            clearTimeout(hintTimer);
        }
        hintTimer = setTimeout(function () {
            var sub = document.getElementById(SUBTITULO_ID);
            if (sub && ov.getAttribute('aria-hidden') === 'false') {
                sub.textContent = 'Sigue en curso… Pulse Esc para cerrar el aviso (no cancela la descarga).';
            }
        }, 90000);
    }

    function ocultar() {
        if (hintTimer) {
            clearTimeout(hintTimer);
            hintTimer = null;
        }
        var ov = overlay();
        if (!ov) {
            document.body.style.overflow = '';
            return;
        }
        ov.classList.add('d-none');
        ov.style.setProperty('display', 'none', 'important');
        ov.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function overlayVisible() {
        var ov = overlay();
        return ov && !ov.classList.contains('d-none');
    }

    function nombreDesdeContentDisposition(header) {
        if (!header) {
            return null;
        }
        var star = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header);
        if (star && star[1]) {
            try {
                return decodeURIComponent(star[1].trim().replace(/"/g, ''));
            } catch (e) {
                return star[1].trim().replace(/"/g, '');
            }
        }
        var plain = /filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i.exec(header);
        if (plain) {
            return (plain[1] || plain[2] || '').trim();
        }
        return null;
    }

    function extensionPorBoton(valor) {
        if (!valor) {
            return 'xlsx';
        }
        if (valor.indexOf('PDF') !== -1) {
            return 'pdf';
        }
        if (valor.indexOf('CSV') !== -1) {
            return 'csv';
        }
        return 'xlsx';
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'stockot.xlsx';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            window.URL.revokeObjectURL(url);
            if (a.parentNode) {
                a.parentNode.removeChild(a);
            }
        }, 1500);
    }

    function descargarReporte(form, submitter) {
        if (abortController) {
            try {
                abortController.abort();
            } catch (e) {}
        }
        abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        var fd = new FormData(form);
        var extensionValor = 'Genera Reporte en Excel';
        if (submitter && submitter.name) {
            fd.set(submitter.name, submitter.value);
            extensionValor = submitter.value || extensionValor;
        } else if (!fd.has('extension')) {
            fd.set('extension', extensionValor);
        } else {
            extensionValor = fd.get('extension') || extensionValor;
        }

        var ext = extensionPorBoton(String(extensionValor));
        mostrar(
            'Generando reporte…',
            'Puede demorar según los filtros. Pulse Esc si la descarga ya terminó.'
        );

        var opts = {
            method: (form.getAttribute('method') || 'POST').toUpperCase(),
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/octet-stream, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, */*',
            },
        };
        if (abortController) {
            opts.signal = abortController.signal;
        }

        return fetch(form.action, opts)
            .then(function (response) {
                var ctype = (response.headers.get('Content-Type') || '').toLowerCase();
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                if (ctype.indexOf('text/html') !== -1) {
                    throw new Error('La respuesta no es un archivo (posible error de sesión o permisos).');
                }
                var filename = nombreDesdeContentDisposition(response.headers.get('Content-Disposition'))
                    || ('stockot.' + ext);
                return response.blob().then(function (blob) {
                    if (!blob || blob.size === 0) {
                        throw new Error('El archivo llegó vacío.');
                    }
                    dispararDescargaBlob(blob, filename);
                });
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert(err && err.message ? err.message : 'No se pudo generar el reporte.');
            })
            .finally(function () {
                abortController = null;
                ocultar();
            });
    }

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function esTeclaEnter(e) {
        return e.key === 'Enter' || e.keyCode === 13 || e.which === 13;
    }

    function enfocar(el) {
        if (!el) {
            return;
        }
        el.focus();
        if (typeof el.select === 'function' && el.tagName === 'INPUT') {
            el.select();
        }
    }

    function campoArticuloDesde(el) {
        return el && el.closest ? el.closest('.tm-articulo-campo') : null;
    }

    function modalArticuloAbierto() {
        var modal = document.getElementById('consultaarticuloModal');
        return !!(modal && modal.classList.contains('show'));
    }

    function enfocarSiguienteArticulo(campo) {
        if (!campo) {
            return;
        }
        var sel = campo.getAttribute('data-next-focus');
        if (!sel) {
            return;
        }
        enfocar(document.querySelector(sel));
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
            skuInput.setAttribute('data-repstockot-resuelto', sku);
            skuInput.removeAttribute('data-repstockot-invalido');
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
        if (skuInput && !conservarSku) {
            skuInput.value = '';
        }
        if (skuInput) {
            skuInput.removeAttribute('data-repstockot-resuelto');
        }
        if (window.jQuery && typeof actualizarLinkEditarArticulo === 'function') {
            actualizarLinkEditarArticulo(window.jQuery(campo), '');
        }
    }

    function resolverArticuloCampo(campo, alertar, alResolver) {
        if (!campo || !window.jQuery) {
            if (alResolver) {
                alResolver(false);
            }
            return;
        }
        var skuInput = campo.querySelector('.codigoarticulo');
        if (!skuInput) {
            if (alResolver) {
                alResolver(true);
            }
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
        if (skuInput.getAttribute('data-repstockot-resuelto') === sku && idInput && String(idInput.value || '') !== '') {
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
            skuInput.setAttribute('data-repstockot-invalido', sku);
            if (alertar) {
                window.alert('No se encontró artículo con ese SKU.');
                window.setTimeout(function () {
                    enfocar(skuInput);
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
            skuInput.setAttribute('data-repstockot-invalido', sku);
            if (alertar) {
                window.alert('No se pudo validar el artículo.');
                window.setTimeout(function () {
                    enfocar(skuInput);
                }, 0);
            }
            if (alResolver) {
                alResolver(false);
            }
        });
    }

    function validarCodigosConsulta(form, listo) {
        if (!window.jQuery) {
            listo(true);
            return;
        }
        var $ = window.jQuery;
        var tareas = [];

        form.querySelectorAll('.codigodeposito').forEach(function (input) {
            tareas.push(function (done) {
                var cod = String(input.value || '').trim();
                leerDepositoPorCodigo(cod, input, function (data) {
                    done(cod === '' || !!(data && data.id), input);
                }, { silencioso: true });
            });
        });
        form.querySelectorAll('.tm-articulo-campo').forEach(function (campo) {
            tareas.push(function (done) {
                resolverArticuloCampo(campo, false, function (ok) {
                    var input = campo.querySelector('.codigoarticulo');
                    done(ok, input);
                });
            });
        });
        form.querySelectorAll('.codigolinea').forEach(function (input) {
            tareas.push(function (done) {
                var cod = String(input.value || '').trim();
                resolverPorCodigoLinea(cod, $(input).closest('.tm-linea-campo'), {
                    silencioso: true,
                    onDone: function (data) {
                        done(cod === '' || !!(data && data.id), input);
                    }
                });
            });
        });
        form.querySelectorAll('.codigocategoria').forEach(function (input) {
            tareas.push(function (done) {
                var cod = String(input.value || '').trim();
                resolverPorCodigoCategoria(cod, $(input).closest('.tm-categoria-campo'), {
                    silencioso: true,
                    onDone: function (data) {
                        done(cod === '' || !!(data && data.id), input);
                    }
                });
            });
        });

        var indice = 0;
        var fallo = null;
        function siguiente() {
            if (fallo || indice >= tareas.length) {
                if (fallo) {
                    window.alert('El código no es válido. Corríjalo o déjelo vacío.');
                    enfocar(fallo);
                    listo(false);
                    return;
                }
                listo(true);
                return;
            }
            tareas[indice](function (ok, input) {
                indice += 1;
                if (!ok && !fallo) {
                    fallo = input;
                }
                siguiente();
            });
        }
        siguiente();
    }

    function activarFichasMarca(form) {
        var grupo = form.querySelector('#repstockot-marcas');
        var hidden = form.querySelector('#mventa_id');
        if (!grupo || !hidden) {
            return;
        }
        var fichas = Array.prototype.slice.call(grupo.querySelectorAll('.repstockot-marca'));
        if (!fichas.length) {
            return;
        }

        function elegir(ficha, avanzar) {
            fichas.forEach(function (item) {
                var activa = item === ficha;
                item.classList.toggle('is-activa', activa);
                item.setAttribute('aria-pressed', activa ? 'true' : 'false');
                item.tabIndex = activa ? 0 : -1;
            });
            hidden.value = ficha.getAttribute('data-id') || '';
            if (avanzar) {
                var destino = document.querySelector('#repstockot-ambitos .repstockot-ambito.is-activa')
                    || document.querySelector('#repstockot-ambitos .repstockot-ambito');
                enfocar(destino);
            }
        }

        grupo.addEventListener('click', function (e) {
            var ficha = e.target.closest ? e.target.closest('.repstockot-marca') : null;
            if (!ficha || !grupo.contains(ficha)) {
                return;
            }
            elegir(ficha, false);
        });

        grupo.addEventListener('keydown', function (e) {
            var ficha = e.target.closest ? e.target.closest('.repstockot-marca') : null;
            if (!ficha || !grupo.contains(ficha)) {
                return;
            }
            var idx = fichas.indexOf(ficha);
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                var siguiente = fichas[(idx + 1) % fichas.length];
                elegir(siguiente, false);
                siguiente.focus();
                return;
            }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                var anterior = fichas[(idx - 1 + fichas.length) % fichas.length];
                elegir(anterior, false);
                anterior.focus();
                return;
            }
            if (esTeclaEnter(e)) {
                e.preventDefault();
                e.stopPropagation();
                elegir(ficha, true);
            }
        });
    }

    function limpiarDepositoReporte() {
        var campo = document.getElementById('tm_deposito_repstockot');
        if (!campo) {
            return;
        }
        var hiddenDep = campo.querySelector('.deposito_id');
        var codigo = campo.querySelector('.codigodeposito');
        var descripcion = campo.querySelector('.descripciondeposito');
        if (hiddenDep) {
            hiddenDep.value = '';
        }
        if (codigo) {
            codigo.value = '';
        }
        if (descripcion) {
            descripcion.value = '';
        }
        if (window.jQuery && typeof actualizarLinkEditarDeposito === 'function') {
            actualizarLinkEditarDeposito(window.jQuery(campo), 0);
        }
    }

    function activarFichasAmbito(form) {
        var grupo = form.querySelector('#repstockot-ambitos');
        var hidden = form.querySelector('#ambito_deposito');
        if (!grupo || !hidden) {
            return;
        }
        var fichas = Array.prototype.slice.call(grupo.querySelectorAll('.repstockot-ambito'));
        if (!fichas.length) {
            return;
        }

        function elegir(ficha, avanzar) {
            var anterior = hidden.value;
            fichas.forEach(function (item) {
                var activa = item === ficha;
                item.classList.toggle('is-activa', activa);
                item.setAttribute('aria-pressed', activa ? 'true' : 'false');
                item.tabIndex = activa ? 0 : -1;
            });
            hidden.value = ficha.getAttribute('data-id') || 'TODOS';
            if (anterior !== hidden.value) {
                limpiarDepositoReporte();
            }
            if (avanzar) {
                enfocar(document.getElementById('deposito_repstockot_id_codigo'));
            }
        }

        grupo.addEventListener('click', function (e) {
            var ficha = e.target.closest ? e.target.closest('.repstockot-ambito') : null;
            if (!ficha || !grupo.contains(ficha)) {
                return;
            }
            elegir(ficha, false);
        });

        grupo.addEventListener('keydown', function (e) {
            var ficha = e.target.closest ? e.target.closest('.repstockot-ambito') : null;
            if (!ficha || !grupo.contains(ficha)) {
                return;
            }
            var idx = fichas.indexOf(ficha);
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                var siguiente = fichas[(idx + 1) % fichas.length];
                elegir(siguiente, false);
                siguiente.focus();
                return;
            }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                var anterior = fichas[(idx - 1 + fichas.length) % fichas.length];
                elegir(anterior, false);
                anterior.focus();
                return;
            }
            if (esTeclaEnter(e)) {
                e.preventDefault();
                e.stopPropagation();
                elegir(ficha, true);
            }
        });
    }

    function activarNavegacionConsulta(form) {
        var campoArticuloActivo = null;
        var articuloElegidoEnModal = false;

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        form.addEventListener('mousedown', function (e) {
            var btn = e.target.closest && e.target.closest('.consultaarticulo');
            if (!btn || !form.contains(btn)) {
                return;
            }
            campoArticuloActivo = campoArticuloDesde(btn);
            var skuInput = campoArticuloActivo ? campoArticuloActivo.querySelector('.codigoarticulo') : null;
            if (skuInput) {
                skuInput.setAttribute('data-repstockot-omitir-blur', '1');
            }
        }, true);

        form.addEventListener('keydown', function (e) {
            var target = e.target;
            if (!target || !form.contains(target)) {
                return;
            }
            if (target.classList && target.classList.contains('codigoarticulo')) {
                var campo = campoArticuloDesde(target);
                if (esTeclaF1(e)) {
                    e.preventDefault();
                    e.stopPropagation();
                    campoArticuloActivo = campo;
                    target.setAttribute('data-repstockot-omitir-blur', '1');
                    var btnArt = campo ? campo.querySelector('.consultaarticulo') : null;
                    if (btnArt) {
                        btnArt.click();
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
                return;
            }

            if (!esTeclaEnter(e)) {
                return;
            }
            var siguientePorId = {
                desdelote: 'hastalote',
                hastalote: 'estado',
                estado: 'estadoOt',
                estadoOt: 'imprimefoto',
                imprimefoto: 'apertura'
            };
            if (siguientePorId[target.id]) {
                e.preventDefault();
                e.stopPropagation();
                enfocar(document.getElementById(siguientePorId[target.id]));
                return;
            }
            if (target.id === 'apertura') {
                e.preventDefault();
                e.stopPropagation();
                enfocar(form.querySelector('input[type="submit"]'));
            }
        }, true);

        form.addEventListener('blur', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            if (target.getAttribute('data-repstockot-omitir-blur') === '1' || modalArticuloAbierto()) {
                target.removeAttribute('data-repstockot-omitir-blur');
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
            target.removeAttribute('data-repstockot-resuelto');
            target.removeAttribute('data-repstockot-invalido');
        }, true);

        form.addEventListener('change', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigoarticulo') || !form.contains(target)) {
                return;
            }
            e.stopImmediatePropagation();
        }, true);

        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('.eligeconsultaarticulo') : null;
            if (!btn || !campoArticuloActivo || !form.contains(campoArticuloActivo)) {
                return;
            }
            articuloElegidoEnModal = true;
        }, true);

        if (window.jQuery) {
            window.jQuery('#consultaarticuloModal').on('hidden.bs.modal.repstockotArt', function () {
                if (!articuloElegidoEnModal || !campoArticuloActivo || !form.contains(campoArticuloActivo)) {
                    articuloElegidoEnModal = false;
                    return;
                }
                articuloElegidoEnModal = false;
                var skuInput = campoArticuloActivo.querySelector('.codigoarticulo');
                var idInput = campoArticuloActivo.querySelector('.articulo_id');
                var sku = skuInput ? String(skuInput.value || '').trim() : '';
                if (skuInput && idInput && String(idInput.value || '') !== '' && sku !== '') {
                    skuInput.setAttribute('data-repstockot-resuelto', sku);
                }
                var campo = campoArticuloActivo;
                window.setTimeout(function () {
                    enfocarSiguienteArticulo(campo);
                }, 0);
            });
        }
    }

    function init() {
        ocultar();

        window.payloadExtraConsultaDeposito = function () {
            var hidden = document.getElementById('ambito_deposito');
            var ambito = hidden ? String(hidden.value || '').trim() : '';
            if (ambito === '') {
                return {};
            }

            return { ambito_deposito: ambito };
        };

        var form = document.getElementById('form-general');
        if (form) {
            activarFichasMarca(form);
            activarFichasAmbito(form);
            activarNavegacionConsulta(form);
            form.addEventListener('submit', function (e) {
                if (form.getAttribute('data-enviando') === '1') {
                    return;
                }
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                validarCodigosConsulta(form, function (ok) {
                    if (!ok) {
                        return;
                    }
                    mostrar(
                        'Consultando stock por OT…',
                        'Puede demorar según los filtros. No cierre la página.'
                    );
                    form.setAttribute('data-enviando', '1');
                    form.submit();
                });
            });
        }

        var bloqueExport = document.getElementById('bloque-export-repstockot');
        if (bloqueExport) {
            bloqueExport.querySelectorAll('a').forEach(function (enlace) {
                enlace.addEventListener('click', function (e) {
                    e.preventDefault();
                    var href = enlace.getAttribute('href');
                    if (!href) {
                        return;
                    }
                    if (abortController) {
                        try {
                            abortController.abort();
                        } catch (err) {}
                    }
                    abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
                    var esPdf = href.indexOf('/PDF') !== -1 || href.indexOf('formato=PDF') !== -1;
                    mostrar(
                        esPdf ? 'Generando PDF…' : 'Generando Excel…',
                        'Sale con el resultado filtrado. Pulse Esc para cerrar este aviso.'
                    );
                    var opts = {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: '*/*' },
                    };
                    if (abortController) {
                        opts.signal = abortController.signal;
                    }
                    fetch(href, opts)
                        .then(function (response) {
                            var ctype = (response.headers.get('Content-Type') || '').toLowerCase();
                            if (!response.ok || ctype.indexOf('text/html') !== -1) {
                                throw new Error('No se pudo generar el archivo.');
                            }
                            var filename = nombreDesdeContentDisposition(response.headers.get('Content-Disposition'))
                                || (esPdf ? 'stockot.pdf' : 'stockot.xlsx');
                            return response.blob().then(function (blob) {
                                dispararDescargaBlob(blob, filename);
                            });
                        })
                        .catch(function (err) {
                            if (err && err.name === 'AbortError') {
                                return;
                            }
                            window.alert(err && err.message ? err.message : 'No se pudo generar el reporte.');
                        })
                        .finally(function () {
                            abortController = null;
                            ocultar();
                        });
                });
            });
        }

        window.addEventListener('pageshow', ocultar);
        window.addEventListener('pagehide', ocultar);
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && overlayVisible()) {
                if (abortController) {
                    try {
                        abortController.abort();
                    } catch (err) {}
                }
                ocultar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
