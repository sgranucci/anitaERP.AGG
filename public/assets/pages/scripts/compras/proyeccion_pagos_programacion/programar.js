(function () {
    'use strict';

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }
    function $$(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function fmt(n) {
        var v = Math.round((Number(n) || 0) * 100) / 100;
        var parts = v.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(',');
    }

    function filas() {
        return $$('#pp-tabla tbody tr[data-id]');
    }

    function marcadas() {
        return $$('.pp-check:checked');
    }

    function filaDe(el) {
        return el ? el.closest('tr') : null;
    }

    function importeDe(tr) {
        return parseFloat(tr.getAttribute('data-importe') || '0') || 0;
    }

    function setResumen(idCount, idTotal, count, total) {
        var c = $(idCount);
        var t = $(idTotal);
        if (c) c.textContent = String(count);
        if (t) t.textContent = fmt(total);
    }

    function actualizarTotales() {
        var selN = 0, selT = 0;
        var urgN = 0, urgT = 0;
        var fechaN = 0, fechaT = 0;
        var progN = 0, progT = 0;
        var dirty = false;

        filas().forEach(function (tr) {
            if (tr.classList.contains('d-none')) return;
            var importe = importeDe(tr);
            var chk = tr.querySelector('.pp-check');
            var urg = tr.querySelector('.pp-urgente');
            var fecha = tr.querySelector('.pp-fecha');
            var esUrg = !!(urg && urg.checked);
            var tieneFecha = !!(fecha && fecha.value);
            var origUrg = tr.getAttribute('data-urgente-orig') === '1';
            var origFecha = tr.getAttribute('data-fecha-orig') || '';

            if (chk && chk.checked) {
                selN++;
                selT += importe;
            }
            if (esUrg) {
                urgN++;
                urgT += importe;
            }
            if (tieneFecha) {
                fechaN++;
                fechaT += importe;
            }
            if (esUrg || tieneFecha) {
                progN++;
                progT += importe;
            }
            if (esUrg !== origUrg || (fecha ? fecha.value : '') !== origFecha) {
                dirty = true;
            }
        });

        setResumen('#pp-sel-n', '#pp-sel-t', selN, selT);
        setResumen('#pp-urg-n', '#pp-urg-t', urgN, urgT);
        setResumen('#pp-fecha-n', '#pp-fecha-t', fechaN, fechaT);
        setResumen('#pp-prog-n', '#pp-prog-t', progN, progT);

        var aviso = $('#pp-cambios-pendientes');
        if (aviso) {
            aviso.classList.toggle('d-none', !dirty);
        }
        var btnGrabar = $('#pp-btn-grabar');
        if (btnGrabar) {
            btnGrabar.classList.toggle('btn-warning', dirty);
            btnGrabar.classList.toggle('btn-success', !dirty);
        }
    }

    function aplicarFecha() {
        var fecha = $('#pp-fecha-masiva');
        if (!fecha || !fecha.value) return;
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            var input = tr ? tr.querySelector('.pp-fecha') : null;
            var urg = tr ? tr.querySelector('.pp-urgente') : null;
            if (input && !input.disabled) input.value = fecha.value;
            if (urg && !urg.disabled) urg.checked = false;
        });
        actualizarTotales();
    }

    function copiarVencimiento() {
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            var input = tr ? tr.querySelector('.pp-fecha') : null;
            var urg = tr ? tr.querySelector('.pp-urgente') : null;
            if (input && !input.disabled && tr.dataset.vto) input.value = tr.dataset.vto;
            if (urg && !urg.disabled) urg.checked = false;
        });
        actualizarTotales();
    }

    function marcarUrgentes() {
        var aplicadas = 0;
        var ignoradas = 0;
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            if (!tr) return;
            if (tr.dataset.familia !== 'TRF') {
                ignoradas++;
                return;
            }
            var urg = tr.querySelector('.pp-urgente');
            var input = tr.querySelector('.pp-fecha');
            if (urg && !urg.disabled) {
                urg.checked = true;
                aplicadas++;
            }
            if (input && !input.disabled) input.value = '';
        });
        actualizarTotales();
        var hint = $('#pp-urgente-hint');
        if (hint) {
            if (aplicadas === 0 && ignoradas > 0) {
                hint.textContent = 'Urgente solo aplica a transferencias. Las filas marcadas son cheques u otro medio.';
                hint.classList.remove('d-none');
            } else if (aplicadas > 0) {
                hint.textContent = 'Marcadas ' + aplicadas + ' transferencia(s) como urgentes. Recordá Grabar.';
                hint.classList.remove('d-none');
            } else {
                hint.classList.add('d-none');
            }
        }
    }

    function quitarProgramacion() {
        marcadas().forEach(function (chk) {
            var tr = filaDe(chk);
            var input = tr ? tr.querySelector('.pp-fecha') : null;
            var urg = tr ? tr.querySelector('.pp-urgente') : null;
            if (input && !input.disabled) input.value = '';
            if (urg && !urg.disabled) urg.checked = false;
        });
        actualizarTotales();
    }

    function filtrarVista(modo) {
        filas().forEach(function (tr) {
            var show = true;
            var urg = tr.querySelector('.pp-urgente');
            var fecha = tr.querySelector('.pp-fecha');
            var esUrg = !!(urg && urg.checked);
            var tieneFecha = !!(fecha && fecha.value);
            if (modo === 'vencidos') show = tr.dataset.estadoVto === 'vencido';
            else if (modo === 'a_vencer') show = tr.dataset.estadoVto !== 'vencido';
            else if (modo === 'urgentes') show = esUrg;
            else if (modo === 'con_fecha') show = tieneFecha;
            else if (modo === 'sin_programar') show = !esUrg && !tieneFecha;
            tr.classList.toggle('d-none', !show);
        });
        $$('.pp-chip-filtro').forEach(function (chip) {
            chip.classList.toggle('active', chip.getAttribute('data-filtro-vista') === modo);
        });
        actualizarTotales();
    }

    function wireExport() {
        var overlay = $('#programar-pagos-overlay');
        function mostrar(titulo) {
            if (!overlay) return;
            var t = $('#programar-pagos-titulo');
            if (t && titulo) t.textContent = titulo;
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
        }
        function ocultar() {
            if (!overlay) return;
            overlay.classList.add('d-none');
            overlay.style.display = '';
        }
        $$('a[href*="listar-programar-pagos"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                mostrar('Exportando…');
                fetch(a.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) {
                        if (!res.ok) throw new Error('No se pudo exportar');
                        var nombre = 'programar_pagos';
                        var disp = res.headers.get('Content-Disposition') || '';
                        var m = /filename=\"?([^\";]+)\"?/i.exec(disp);
                        if (m) nombre = m[1];
                        return res.blob().then(function (blob) { return { blob: blob, nombre: nombre }; });
                    })
                    .then(function (pack) {
                        var url = URL.createObjectURL(pack.blob);
                        var link = document.createElement('a');
                        link.href = url;
                        link.download = pack.nombre;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(url);
                        ocultar();
                    })
                    .catch(function () { ocultar(); });
            });
        });
        window.addEventListener('pageshow', ocultar);
    }

    function init() {
        var todas = $('#pp-check-todas');
        if (todas) {
            todas.addEventListener('change', function () {
                filas().forEach(function (tr) {
                    if (tr.classList.contains('d-none')) return;
                    var c = tr.querySelector('.pp-check');
                    if (c && !c.disabled) c.checked = todas.checked;
                });
                actualizarTotales();
            });
        }

        var tabla = $('#pp-tabla');
        if (tabla) {
            tabla.addEventListener('change', function (e) {
                var t = e.target;
                if (!t) return;
                if (t.classList.contains('pp-check') || t.classList.contains('pp-urgente') || t.classList.contains('pp-fecha')) {
                    if (t.classList.contains('pp-urgente') && t.checked) {
                        var tr = filaDe(t);
                        var fecha = tr ? tr.querySelector('.pp-fecha') : null;
                        if (fecha && !fecha.disabled) fecha.value = '';
                    }
                    if (t.classList.contains('pp-fecha') && t.value) {
                        var tr2 = filaDe(t);
                        var urg = tr2 ? tr2.querySelector('.pp-urgente') : null;
                        if (urg && !urg.disabled) urg.checked = false;
                    }
                    actualizarTotales();
                }
            });
            tabla.addEventListener('input', function (e) {
                if (e.target && e.target.classList.contains('pp-fecha')) actualizarTotales();
            });
        }

        var bFecha = $('#pp-aplicar-fecha');
        var bVto = $('#pp-copiar-vto');
        var bUrg = $('#pp-marcar-urgente');
        var bQuitar = $('#pp-quitar');
        if (bFecha) bFecha.addEventListener('click', aplicarFecha);
        if (bVto) bVto.addEventListener('click', copiarVencimiento);
        if (bUrg) bUrg.addEventListener('click', marcarUrgentes);
        if (bQuitar) bQuitar.addEventListener('click', quitarProgramacion);

        $$('.pp-chip-filtro').forEach(function (chip) {
            chip.addEventListener('click', function () {
                filtrarVista(chip.getAttribute('data-filtro-vista') || 'todas');
            });
        });

        var toggleDetalle = $('#pp-toggle-detalle');
        if (toggleDetalle) {
            toggleDetalle.addEventListener('change', function () {
                document.body.classList.toggle('pp-detalle-on', toggleDetalle.checked);
            });
            document.body.classList.toggle('pp-detalle-on', toggleDetalle.checked);
        }

        wireExport();
        actualizarTotales();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
