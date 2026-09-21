/**
 * Remito interno FL: líneas artículo + combinación/color + talle (como POS).
 */
(function () {
    'use strict';

    var CFG = window.RI_CFG || {};
    var buscaTimers = new WeakMap();

    function get(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CFG.csrf || ''
            }
        }).then(function (r) {
            return r.json().then(function (j) {
                return { status: r.status, body: j };
            });
        });
    }

    function reindexLineas() {
        var rows = document.querySelectorAll('#ri-lineas-tbody .ri-linea-row');
        rows.forEach(function (row, idx) {
            row.querySelectorAll('[name^="lineas["]').forEach(function (el) {
                el.name = el.name.replace(/lineas\[\d+]/, 'lineas[' + idx + ']');
            });
        });
    }

    function limpiarVariantes(row) {
        var comb = row.querySelector('.ri-combinacion-id');
        var color = row.querySelector('.ri-color-id');
        var talle = row.querySelector('.ri-talle-id');
        var selV = row.querySelector('.ri-variante-select');
        var selT = row.querySelector('.ri-talle-select');
        if (comb) comb.value = '';
        if (color) color.value = '';
        if (talle) talle.value = '';
        if (selV) selV.innerHTML = '<option value="">—</option>';
        if (selT) selT.innerHTML = '<option value="">—</option>';
    }

    function aplicarArticulo(row, articulo) {
        var idEl = row.querySelector('.ri-articulo-id');
        var codEl = row.querySelector('.ri-articulo-codigo');
        var descEl = row.querySelector('.ri-descripcion');
        if (idEl) idEl.value = articulo.id;
        if (codEl) codEl.value = articulo.sku || '';
        if (descEl) descEl.value = articulo.descripcion || '';
        limpiarVariantes(row);
        cargarVariantes(row, articulo.id);
    }

    function cargarVariantes(row, articuloId) {
        if (!articuloId || !CFG.urls || !CFG.urls.variantes) return;
        get(CFG.urls.variantes + '/' + articuloId).then(function (res) {
            if (res.status !== 200 || !res.body) return;
            var modo = res.body.modo || 'combinacion';
            var selV = row.querySelector('.ri-variante-select');
            var selT = row.querySelector('.ri-talle-select');
            if (!selV || !selT) return;

            selV.innerHTML = '<option value="">Seleccione…</option>';
            if (modo === 'color_talle') {
                (res.body.colores || []).forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = 'col:' + c.id;
                    opt.textContent = (c.codigo ? c.codigo + ' — ' : '') + (c.nombre || '');
                    selV.appendChild(opt);
                });
            } else {
                (res.body.combinaciones || []).forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = 'c:' + c.id;
                    opt.textContent = (c.codigo ? c.codigo + ' — ' : '') + (c.nombre || '');
                    selV.appendChild(opt);
                });
            }

            selT.innerHTML = '<option value="">Seleccione…</option>';
            (res.body.talles || []).forEach(function (t) {
                var opt = document.createElement('option');
                opt.value = String(t.id);
                opt.textContent = t.nombre || t.codigo || t.id;
                selT.appendChild(opt);
            });

            // Restaurar si había valores
            var combId = row.querySelector('.ri-combinacion-id');
            var colorId = row.querySelector('.ri-color-id');
            var talleId = row.querySelector('.ri-talle-id');
            if (combId && combId.value) {
                selV.value = 'c:' + combId.value;
            } else if (colorId && colorId.value) {
                selV.value = 'col:' + colorId.value;
            }
            if (talleId && talleId.value) {
                selT.value = String(talleId.value);
            }
        }).catch(function () { /* silencioso */ });
    }

    function sincronizarVarianteHidden(row) {
        var selV = row.querySelector('.ri-variante-select');
        var selT = row.querySelector('.ri-talle-select');
        var comb = row.querySelector('.ri-combinacion-id');
        var color = row.querySelector('.ri-color-id');
        var talle = row.querySelector('.ri-talle-id');
        if (!selV || !comb || !color) return;
        var v = selV.value || '';
        comb.value = '';
        color.value = '';
        if (v.indexOf('c:') === 0) {
            comb.value = v.slice(2);
        } else if (v.indexOf('col:') === 0) {
            color.value = v.slice(4);
        }
        if (talle && selT) {
            talle.value = selT.value || '';
        }
    }

    function ocultarSugerencias(row) {
        var box = row.querySelector('.ri-sugerencias');
        if (box) {
            box.classList.add('d-none');
            box.innerHTML = '';
        }
    }

    function mostrarSugerencias(row, items) {
        var box = row.querySelector('.ri-sugerencias');
        if (!box) return;
        if (!items || !items.length) {
            ocultarSugerencias(row);
            return;
        }
        box.innerHTML = '';
        items.forEach(function (a) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action py-1 px-2';
            btn.textContent = (a.sku || '') + ' — ' + (a.descripcion || '');
            btn.addEventListener('click', function () {
                aplicarArticulo(row, a);
                ocultarSugerencias(row);
                var cant = row.querySelector('.ri-cantidad');
                if (cant) cant.focus();
            });
            box.appendChild(btn);
        });
        box.classList.remove('d-none');
    }

    function buscarArticulo(row, q, autoElegir) {
        if (!CFG.urls || !CFG.urls.buscar || q.length < 2) {
            ocultarSugerencias(row);
            return;
        }
        get(CFG.urls.buscar + '?q=' + encodeURIComponent(q)).then(function (res) {
            var data = (res.body && res.body.data) || [];
            if (autoElegir && data.length === 1) {
                aplicarArticulo(row, data[0]);
                ocultarSugerencias(row);
                return;
            }
            mostrarSugerencias(row, data);
        }).catch(function () {
            ocultarSugerencias(row);
        });
    }

    function bindRow(row) {
        if (!CFG.editable) return;
        var codigo = row.querySelector('.ri-articulo-codigo');
        var selV = row.querySelector('.ri-variante-select');
        var selT = row.querySelector('.ri-talle-select');

        if (codigo) {
            codigo.addEventListener('input', function () {
                var idEl = row.querySelector('.ri-articulo-id');
                if (idEl) idEl.value = '';
                limpiarVariantes(row);
                var q = codigo.value.trim();
                clearTimeout(buscaTimers.get(row));
                var t = setTimeout(function () {
                    buscarArticulo(row, q, false);
                }, 280);
                buscaTimers.set(row, t);
            });
            codigo.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarArticulo(row, codigo.value.trim(), true);
                }
            });
            codigo.addEventListener('blur', function () {
                setTimeout(function () { ocultarSugerencias(row); }, 200);
            });
        }
        if (selV) {
            selV.addEventListener('change', function () {
                sincronizarVarianteHidden(row);
            });
        }
        if (selT) {
            selT.addEventListener('change', function () {
                sincronizarVarianteHidden(row);
            });
        }

        // Cargar variantes si ya hay artículo (edición)
        var artId = row.querySelector('.ri-articulo-id');
        if (artId && artId.value) {
            cargarVariantes(row, artId.value);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tbody = document.getElementById('ri-lineas-tbody');
        var btnAdd = document.getElementById('ri-agregar-linea');
        var tpl = document.getElementById('ri-template-linea');

        if (tbody) {
            tbody.querySelectorAll('.ri-linea-row').forEach(bindRow);
            tbody.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.ri-quitar-linea');
                if (!btn) return;
                var rows = tbody.querySelectorAll('.ri-linea-row');
                var row = btn.closest('tr');
                if (!row) return;
                if (rows.length <= 1) {
                    row.querySelectorAll('input').forEach(function (inp) {
                        if (inp.classList.contains('ri-cantidad')) {
                            inp.value = '1';
                        } else if (!inp.type || inp.type === 'text' || inp.type === 'hidden' || inp.type === 'number') {
                            if (!inp.classList.contains('ri-cantidad')) inp.value = '';
                        }
                    });
                    limpiarVariantes(row);
                    return;
                }
                row.remove();
                reindexLineas();
            });
        }

        if (btnAdd && tbody && tpl) {
            btnAdd.addEventListener('click', function () {
                var idx = tbody.querySelectorAll('.ri-linea-row').length;
                var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
                tbody.insertAdjacentHTML('beforeend', html);
                var rows = tbody.querySelectorAll('.ri-linea-row');
                bindRow(rows[rows.length - 1]);
            });
        }

        var form = document.getElementById('form-remito-interno');
        if (form) {
            form.addEventListener('submit', function () {
                if (tbody) {
                    tbody.querySelectorAll('.ri-linea-row').forEach(sincronizarVarianteHidden);
                }
            });
        }
    });
})();
