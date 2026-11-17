<script>
window.cdmLegajoId = @json($data->exists ? (int) $data->id : 0);
window.cdmEditable = @json((bool) ($editable ?? false));
window.cdmPuedeCorregirReemplazo = @json((bool) ($puedeCorregirReemplazo ?? false));
window.cdmPuedeSumarReemplazo = @json((bool) ($puedeSumarReemplazo ?? false));
window.cdmBuscarVentaUrl = @json(route('api_buscar_venta_cambio_devolucion_marketplace'));
window.cdmArticulosFacturaUrl = @json(route('api_articulos_factura_cambio_devolucion_marketplace', ['ventaId' => '__ID__']));
window.cdmAgregarDevolucionesUrl = @json($data->exists ? route('agregar_devoluciones_cambio_devolucion_marketplace', $data->id) : '');
window.cdmVariantesUrl = @json(route('api_variantes_cambio_devolucion_marketplace', ['articuloId' => '__ID__']));
window.cdmPrecioUrl = @json(route('api_precio_cambio_devolucion_marketplace'));
window.cdmPreviewFac = @json($previewFac ?? null);
window.cdmPreviewNcReemplazo = @json($previewNcReemplazo ?? null);
window.cdmPreviewNcDevolver = @json($previewNcDevolver ?? null);
window.cdmPesos = function (n) {
    return '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
window.cdmCantidadTxt = function (n) {
    var v = Number(n || 0);
    if (Math.abs(v - Math.round(v)) < 0.0001) {
        return String(Math.round(v));
    }
    return v.toLocaleString('es-AR', { maximumFractionDigits: 4 });
};
window.cdmIdsSeleccionados = function (selector) {
    var ids = [];
    document.querySelectorAll(selector + ':checked').forEach(function (el) {
        var id = parseInt(el.value, 10);
        if (id > 0) {
            ids.push(id);
        }
    });
    return ids;
};
/** Modo de precio de la NC de lo devuelto: form de emisión, o el select de preview previo. */
window.cdmModoPrecioDevolucion = function () {
    var sel = document.querySelector('.cdm-form-emitir-nc [name=modo_precio]')
        || document.querySelector('.cdm-modo-precio-devolucion');
    return sel && sel.value ? sel.value : 'grilla';
};
window.cdmSincronizarLineaIdsEnForm = function (form, ids) {
    if (!form) {
        return;
    }
    form.querySelectorAll('input[name="linea_ids[]"]').forEach(function (el) {
        el.remove();
    });
    (ids || []).forEach(function (id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'linea_ids[]';
        input.value = String(id);
        form.appendChild(input);
    });
};
window.cdmFilaPreviewModo = function (preview, modo) {
    if (!preview || !preview.modos) {
        return null;
    }
    return preview.modos[modo] || null;
};
window.cdmFiltrarLineasPorIds = function (lineas, ids) {
    if (!ids || !ids.length) {
        return [];
    }
    var set = {};
    ids.forEach(function (id) { set[id] = true; });
    return (lineas || []).filter(function (l) {
        return set[l.linea_id];
    });
};
window.cdmSumarLineas = function (lineas) {
    var pares = 0;
    var total = 0;
    (lineas || []).forEach(function (l) {
        pares += Number(l.cantidad || 0);
        total += Number(l.subtotal != null ? l.subtotal : (Number(l.cantidad || 0) * Number(l.precio != null ? l.precio : l.precio_unitario || 0)));
    });
    return { pares: Math.round(pares * 10000) / 10000, total: Math.round(total * 100) / 100 };
};

window.cdmRenderTablaSeleccion = function (opts) {
    var tbody = document.getElementById(opts.tbodyId);
    if (!tbody) {
        return;
    }
    var pendientes = opts.pendientes || [];
    var preciosPorLinea = opts.preciosPorLinea || {};
    var selected = opts.selectedIds || [];
    var html = '';
    if (!pendientes.length) {
        html = '<tr><td colspan="6" class="text-muted text-center">No hay líneas.</td></tr>';
    } else {
        pendientes.forEach(function (l) {
            var id = l.linea_id;
            var checked = selected.indexOf(id) >= 0 ? ' checked' : '';
            var precio = preciosPorLinea[id] != null
                ? preciosPorLinea[id]
                : (l.precio != null ? l.precio : l.precio_unitario);
            var sub = Number(l.cantidad || 0) * Number(precio || 0);
            html += '<tr data-linea-id="' + id + '">'
                + '<td class="text-center"><input type="checkbox" class="' + opts.checkClass + '" value="' + id + '"' + checked + '></td>'
                + '<td>' + (l.sku || '') + '</td>'
                + '<td>' + (l.descripcion || '') + '</td>'
                + '<td class="text-right">' + window.cdmCantidadTxt(l.cantidad) + '</td>'
                + '<td class="text-right">' + window.cdmPesos(precio) + '</td>'
                + '<td class="text-right">' + window.cdmPesos(sub) + '</td>'
                + '</tr>';
        });
    }
    tbody.innerHTML = html;
};

window.cdmRenderPreviewFac = function (modo) {
    var preview = window.cdmPreviewFac;
    if (!preview) {
        return;
    }
    var fila = window.cdmFilaPreviewModo(preview, modo);
    var precios = {};
    if (fila && fila.ok && fila.lineas) {
        fila.lineas.forEach(function (l) {
            precios[l.linea_id] = l.precio;
        });
    }
    var selected = window.cdmIdsSeleccionados('.cdm-fac-linea-check');
    // Primera vez: si hay una sola pendiente, la marcamos; si hay varias, vacío (no auto-todo).
    if (!document.querySelector('.cdm-fac-linea-check') && selected.length === 0) {
        var pend = preview.pendientes || [];
        selected = pend.length === 1 && pend[0].linea_id ? [pend[0].linea_id] : [];
    }
    window.cdmRenderTablaSeleccion({
        tbodyId: 'cdm-preview-fac-tbody',
        checkClass: 'cdm-fac-linea-check',
        pendientes: preview.pendientes || [],
        preciosPorLinea: precios,
        selectedIds: selected,
    });
    selected = window.cdmIdsSeleccionados('.cdm-fac-linea-check');
    var lineasSel = [];
    if (fila && fila.ok && fila.lineas) {
        lineasSel = window.cdmFiltrarLineasPorIds(fila.lineas, selected);
    } else {
        lineasSel = window.cdmFiltrarLineasPorIds(preview.pendientes || [], selected).map(function (l) {
            return {
                linea_id: l.linea_id,
                cantidad: l.cantidad,
                precio: l.precio_unitario,
                subtotal: l.subtotal,
                sku: l.sku,
                descripcion: l.descripcion
            };
        });
    }
    var suma = window.cdmSumarLineas(lineasSel);
    var alerta = document.getElementById('cdm-preview-fac-alerta');
    if (alerta) {
        if (selected.length === 0) {
            alerta.textContent = 'Marcá al menos una línea para facturar. No se emite sola con todo lo pendiente.';
            alerta.classList.remove('d-none');
        } else if (fila && !fila.ok) {
            alerta.textContent = fila.error || 'No se puede emitir con este modo de precio.';
            alerta.classList.remove('d-none');
        } else if (suma.pares > 1.0001) {
            alerta.textContent = 'Atención: hay ' + window.cdmCantidadTxt(suma.pares)
                + ' pares seleccionados. Un cambio es par por par.';
            alerta.classList.remove('d-none');
        } else {
            alerta.textContent = '';
            alerta.classList.add('d-none');
        }
    }
    var paresEl = document.getElementById('cdm-preview-fac-pares');
    var totalEl = document.getElementById('cdm-preview-fac-total');
    if (paresEl) {
        paresEl.textContent = selected.length ? window.cdmCantidadTxt(suma.pares) : '—';
    }
    if (totalEl) {
        totalEl.textContent = (fila && fila.ok && selected.length) ? window.cdmPesos(suma.total) : '—';
    }
    var resumen = document.getElementById('cdm-preview-fac-resumen');
    if (resumen) {
        if (fila && fila.ok && selected.length) {
            var facturado = Number(preview.vigente || 0) + Number(suma.total || 0);
            var diferencia = Math.round((facturado - Number(preview.devolver || 0)) * 100) / 100;
            var txt = 'A devolver' + (preview.devolver_estimado ? ' (estimado)' : '') + ': ' + window.cdmPesos(preview.devolver);
            if (Number(preview.vigente || 0) > 0) {
                txt += ' · FAC vigentes: ' + window.cdmPesos(preview.vigente);
            }
            if (Math.abs(diferencia) < 0.01) {
                txt += ' · Diferencia: sin diferencia';
            } else if (diferencia > 0) {
                txt += ' · Diferencia: el cliente debe ' + window.cdmPesos(diferencia);
            } else {
                txt += ' · Diferencia: Ferli acredita ' + window.cdmPesos(-diferencia);
            }
            resumen.textContent = txt;
        } else {
            resumen.textContent = '';
        }
    }
    var avisosEl = document.getElementById('cdm-preview-fac-avisos');
    if (avisosEl) {
        avisosEl.textContent = (fila && fila.ok && fila.avisos && fila.avisos.length) ? fila.avisos.join(' ') : '';
    }
};

window.cdmRenderPreviewNcReemplazo = function () {
    var preview = window.cdmPreviewNcReemplazo;
    if (!preview) {
        return;
    }
    var selected = window.cdmIdsSeleccionados('.cdm-nc-reemplazo-linea-check');
    var precios = {};
    (preview.lineas || []).forEach(function (l) { precios[l.linea_id] = l.precio; });
    var pendNcR = preview.pendientes || preview.lineas || [];
    if (!document.querySelector('.cdm-nc-reemplazo-linea-check') && selected.length === 0) {
        selected = pendNcR.length === 1 && pendNcR[0].linea_id ? [pendNcR[0].linea_id] : [];
    }
    window.cdmRenderTablaSeleccion({
        tbodyId: 'cdm-preview-nc-reemplazo-tbody',
        checkClass: 'cdm-nc-reemplazo-linea-check',
        pendientes: pendNcR,
        preciosPorLinea: precios,
        selectedIds: selected,
    });
    selected = window.cdmIdsSeleccionados('.cdm-nc-reemplazo-linea-check');
    var lineasSel = window.cdmFiltrarLineasPorIds(preview.lineas || preview.pendientes || [], selected);
    var suma = window.cdmSumarLineas(lineasSel.map(function (l) {
        return {
            cantidad: l.cantidad,
            precio: l.precio != null ? l.precio : l.precio_unitario,
            subtotal: l.subtotal
        };
    }));
    var alerta = document.getElementById('cdm-preview-nc-reemplazo-alerta');
    if (alerta) {
        if (selected.length === 0) {
            alerta.textContent = 'Marcá al menos una línea del reemplazo a anular.';
            alerta.classList.remove('d-none');
        } else if (preview.ok === false) {
            alerta.textContent = preview.error || 'No se puede emitir la NC del reemplazo.';
            alerta.classList.remove('d-none');
        } else {
            alerta.textContent = '';
            alerta.classList.add('d-none');
        }
    }
    var paresEl = document.getElementById('cdm-preview-nc-reemplazo-pares');
    var totalEl = document.getElementById('cdm-preview-nc-reemplazo-total');
    if (paresEl) {
        paresEl.textContent = selected.length ? window.cdmCantidadTxt(suma.pares) : '—';
    }
    if (totalEl) {
        totalEl.textContent = selected.length ? window.cdmPesos(suma.total) : '—';
    }
};

window.cdmRenderPreviewNcDevolver = function (modo) {
    var preview = window.cdmPreviewNcDevolver;
    if (!preview) {
        return;
    }
    var fila = window.cdmFilaPreviewModo(preview, modo);
    var precios = {};
    if (fila && fila.ok && fila.lineas) {
        fila.lineas.forEach(function (l) { precios[l.linea_id] = l.precio; });
    }
    // Precios cargados en inputs del form NC (modo grilla)
    document.querySelectorAll('.cdm-nc-precio').forEach(function (inp) {
        var m = (inp.id || '').match(/cdm-nc-precio-(\d+)/);
        if (m && inp.value !== '') {
            precios[parseInt(m[1], 10)] = Number(inp.value);
        }
    });
    var selected = window.cdmIdsSeleccionados('.cdm-nc-devolver-linea-check');
    var pendNcD = preview.pendientes || [];
    if (!document.querySelector('.cdm-nc-devolver-linea-check') && selected.length === 0) {
        selected = pendNcD.length === 1 && pendNcD[0].linea_id ? [pendNcD[0].linea_id] : [];
    }
    window.cdmRenderTablaSeleccion({
        tbodyId: 'cdm-preview-nc-devolver-tbody',
        checkClass: 'cdm-nc-devolver-linea-check',
        pendientes: pendNcD,
        preciosPorLinea: precios,
        selectedIds: selected,
    });
    selected = window.cdmIdsSeleccionados('.cdm-nc-devolver-linea-check');
    var lineasBase = (fila && fila.ok && fila.lineas) ? fila.lineas : (preview.pendientes || []);
    var lineasSel = window.cdmFiltrarLineasPorIds(lineasBase, selected).map(function (l) {
        var precio = precios[l.linea_id] != null ? precios[l.linea_id] : (l.precio != null ? l.precio : l.precio_unitario);
        return {
            linea_id: l.linea_id,
            sku: l.sku,
            descripcion: l.descripcion,
            cantidad: l.cantidad,
            precio: precio,
            subtotal: Math.round(Number(l.cantidad || 0) * Number(precio || 0) * 100) / 100
        };
    });
    var suma = window.cdmSumarLineas(lineasSel);
    var alerta = document.getElementById('cdm-preview-nc-devolver-alerta');
    if (alerta) {
        if (selected.length === 0) {
            alerta.textContent = 'Marcá al menos una línea a acreditar. No se emite sola con todo lo pendiente.';
            alerta.classList.remove('d-none');
        } else if (fila && !fila.ok) {
            alerta.textContent = fila.error || 'No se puede emitir con este modo de precio.';
            alerta.classList.remove('d-none');
        } else {
            alerta.textContent = '';
            alerta.classList.add('d-none');
        }
    }
    var paresEl = document.getElementById('cdm-preview-nc-devolver-pares');
    var totalEl = document.getElementById('cdm-preview-nc-devolver-total');
    if (paresEl) {
        paresEl.textContent = selected.length ? window.cdmCantidadTxt(suma.pares) : '—';
    }
    if (totalEl) {
        totalEl.textContent = selected.length ? window.cdmPesos(suma.total) : '—';
    }
    var resumen = document.getElementById('cdm-preview-nc-devolver-resumen');
    if (resumen) {
        resumen.textContent = selected.length
            ? ('Total a acreditar en esta NC: ' + window.cdmPesos(suma.total))
            : '';
    }
    var avisosEl = document.getElementById('cdm-preview-nc-devolver-avisos');
    if (avisosEl) {
        avisosEl.textContent = (fila && fila.ok && fila.avisos && fila.avisos.length) ? fila.avisos.join(' ') : '';
    }
};

window.cdmConfirmarFac = function (form, pregunta) {
    var select = form.querySelector('[name=modo_precio]');
    var modo = select ? select.value : 'grilla';
    var etiqueta = select ? select.options[select.selectedIndex].text.trim() : '';
    var preview = window.cdmPreviewFac;
    window.cdmRenderPreviewFac(modo);
    var ids = window.cdmIdsSeleccionados('.cdm-fac-linea-check');
    if (!ids.length) {
        var panelFac = document.getElementById('cdm-preview-fac-panel');
        if (panelFac) {
            panelFac.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        alert('Indicá qué línea(s) de reemplazo facturar en el preview de arriba (columna Incluir). No se emite sola con todo lo pendiente.');
        return false;
    }
    var fila = window.cdmFilaPreviewModo(preview, modo);
    if (fila && !fila.ok) {
        alert('No se puede facturar con «' + etiqueta + '»:\n' + fila.error);
        return false;
    }
    window.cdmSincronizarLineaIdsEnForm(form, ids);
    var lineas = window.cdmFiltrarLineasPorIds((fila && fila.lineas) ? fila.lineas : (preview.pendientes || []), ids);
    var suma = window.cdmSumarLineas(lineas.map(function (l) {
        return {
            cantidad: l.cantidad,
            precio: l.precio != null ? l.precio : l.precio_unitario,
            subtotal: l.subtotal
        };
    }));
    var texto = pregunta + '\n\nPrecio: ' + etiqueta;
    texto += '\nPares en esta factura: ' + window.cdmCantidadTxt(suma.pares);
    texto += '\nDetalle a grabar:';
    lineas.forEach(function (l) {
        var precio = l.precio != null ? l.precio : l.precio_unitario;
        var sub = l.subtotal != null ? l.subtotal : (Number(l.cantidad || 0) * Number(precio || 0));
        texto += '\n· ' + (l.sku || '') + ' ' + (l.descripcion || '')
            + ' × ' + window.cdmCantidadTxt(l.cantidad)
            + ' @ ' + window.cdmPesos(precio)
            + ' = ' + window.cdmPesos(sub);
    });
    if (fila && fila.ok) {
        var facturado = Number(preview.vigente || 0) + Number(suma.total || 0);
        var diferencia = Math.round((facturado - Number(preview.devolver || 0)) * 100) / 100;
        texto += '\nTotal de esta factura: ' + window.cdmPesos(suma.total);
        if (Number(preview.vigente || 0) > 0) {
            texto += '\nFacturas de reemplazo vigentes: ' + window.cdmPesos(preview.vigente);
        }
        texto += '\nA devolver' + (preview.devolver_estimado ? ' (estimado con la grilla)' : '') + ': ' + window.cdmPesos(preview.devolver);
        if (Math.abs(diferencia) < 0.01) {
            texto += '\nDiferencia: sin diferencia';
        } else if (diferencia > 0) {
            texto += '\nDiferencia: el cliente debe ' + window.cdmPesos(diferencia);
        } else {
            texto += '\nDiferencia: Ferli acredita ' + window.cdmPesos(-diferencia);
        }
        if (suma.pares > 1.0001 || Math.abs(diferencia) >= 0.01) {
            texto += '\n\nRevisá que no haya un par de más ni un precio mal cargado.';
        }
    }
    return confirm(texto);
};

window.cdmConfirmarNcReemplazo = function (form) {
    window.cdmRenderPreviewNcReemplazo();
    var ids = window.cdmIdsSeleccionados('.cdm-nc-reemplazo-linea-check');
    if (!ids.length) {
        var panelNcR = document.getElementById('cdm-preview-nc-reemplazo-panel');
        if (panelNcR) {
            panelNcR.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        alert('Indicá qué línea(s) del reemplazo anular en el preview de arriba (columna Incluir).');
        return false;
    }
    window.cdmSincronizarLineaIdsEnForm(form, ids);
    var preview = window.cdmPreviewNcReemplazo || {};
    var lineas = window.cdmFiltrarLineasPorIds(preview.lineas || preview.pendientes || [], ids);
    var suma = window.cdmSumarLineas(lineas.map(function (l) {
        return {
            cantidad: l.cantidad,
            precio: l.precio != null ? l.precio : l.precio_unitario,
            subtotal: l.subtotal
        };
    }));
    var texto = '¿Emitir nota de crédito del reemplazo por error de carga?\nEl par vuelve al stock y después se puede facturar de nuevo.\n\nDetalle a grabar:';
    lineas.forEach(function (l) {
        var precio = l.precio != null ? l.precio : l.precio_unitario;
        var sub = l.subtotal != null ? l.subtotal : (Number(l.cantidad || 0) * Number(precio || 0));
        texto += '\n· ' + (l.sku || '') + ' ' + (l.descripcion || '')
            + ' × ' + window.cdmCantidadTxt(l.cantidad)
            + ' @ ' + window.cdmPesos(precio)
            + ' = ' + window.cdmPesos(sub);
    });
    texto += '\nTotal NC: ' + window.cdmPesos(suma.total);
    return confirm(texto);
};

window.cdmConfirmarNcDevolver = function (form) {
    var select = form.querySelector('[name=modo_precio]');
    var modo = select ? select.value : 'grilla';
    var etiqueta = select ? select.options[select.selectedIndex].text.trim() : '';
    window.cdmRenderPreviewNcDevolver(modo);
    var ids = window.cdmIdsSeleccionados('.cdm-nc-devolver-linea-check');
    if (!ids.length) {
        var panelNcD = document.getElementById('cdm-preview-nc-devolver-panel');
        if (panelNcD) {
            panelNcD.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        alert('Indicá qué línea(s) a devolver en el preview de arriba (columna Incluir).');
        return false;
    }
    var preview = window.cdmPreviewNcDevolver;
    var fila = window.cdmFilaPreviewModo(preview, modo);
    if (fila && !fila.ok) {
        alert('No se puede emitir la NC con «' + etiqueta + '»:\n' + fila.error);
        return false;
    }
    window.cdmSincronizarLineaIdsEnForm(form, ids);
    var precios = {};
    document.querySelectorAll('.cdm-nc-precio').forEach(function (inp) {
        var m = (inp.id || '').match(/cdm-nc-precio-(\d+)/);
        if (m && inp.value !== '') {
            precios[parseInt(m[1], 10)] = Number(inp.value);
        }
    });
    var base = (fila && fila.ok && fila.lineas) ? fila.lineas : (preview.pendientes || []);
    var lineas = window.cdmFiltrarLineasPorIds(base, ids).map(function (l) {
        var precio = precios[l.linea_id] != null ? precios[l.linea_id] : (l.precio != null ? l.precio : l.precio_unitario);
        return {
            sku: l.sku,
            descripcion: l.descripcion,
            cantidad: l.cantidad,
            precio: precio,
            subtotal: Math.round(Number(l.cantidad || 0) * Number(precio || 0) * 100) / 100
        };
    });
    var suma = window.cdmSumarLineas(lineas);
    var texto = '¿Emitir la nota de crédito de lo devuelto?\nPrecio: ' + etiqueta + '\nDetalle a grabar:';
    lineas.forEach(function (l) {
        texto += '\n· ' + (l.sku || '') + ' ' + (l.descripcion || '')
            + ' × ' + window.cdmCantidadTxt(l.cantidad)
            + ' @ ' + window.cdmPesos(l.precio)
            + ' = ' + window.cdmPesos(l.subtotal);
    });
    texto += '\nTotal NC: ' + window.cdmPesos(suma.total);
    texto += '\nEl motivo define si ese par ingresa al stock.';
    return confirm(texto);
};

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.classList && ev.target.classList.contains('cdm-fac-linea-check')) {
            var formFac = document.querySelector('.cdm-form-emitir-fac [name=modo_precio]');
            window.cdmRenderPreviewFac(formFac ? formFac.value : 'grilla');
        }
        if (ev.target && ev.target.classList && ev.target.classList.contains('cdm-nc-reemplazo-linea-check')) {
            window.cdmRenderPreviewNcReemplazo();
        }
        if (ev.target && ev.target.classList && ev.target.classList.contains('cdm-nc-devolver-linea-check')) {
            window.cdmRenderPreviewNcDevolver(window.cdmModoPrecioDevolucion());
        }
    });

    document.querySelectorAll('.cdm-form-emitir-fac').forEach(function (form) {
        var select = form.querySelector('[name=modo_precio]');
        if (select) {
            select.addEventListener('change', function () {
                window.cdmRenderPreviewFac(select.value);
            });
        }
    });
    document.querySelectorAll('.cdm-form-emitir-nc').forEach(function (form) {
        var select = form.querySelector('[name=modo_precio]');
        if (select) {
            select.addEventListener('change', function () {
                window.cdmRenderPreviewNcDevolver(select.value);
            });
        }
        form.querySelectorAll('.cdm-nc-precio').forEach(function (inp) {
            inp.addEventListener('input', function () {
                window.cdmRenderPreviewNcDevolver(window.cdmModoPrecioDevolucion());
            });
        });
    });
    // Select de precio sin name (preview previo a RECIBIDO): mismo modo que verá el operador.
    document.querySelectorAll('.cdm-modo-precio-devolucion').forEach(function (select) {
        select.addEventListener('change', function () {
            window.cdmRenderPreviewNcDevolver(select.value);
        });
    });

    if (window.cdmPreviewFac) {
        var primer = document.querySelector('.cdm-form-emitir-fac [name=modo_precio]');
        window.cdmRenderPreviewFac(primer ? primer.value : 'grilla');
    }
    if (window.cdmPreviewNcReemplazo) {
        window.cdmRenderPreviewNcReemplazo();
    }
    if (window.cdmPreviewNcDevolver) {
        window.cdmRenderPreviewNcDevolver(window.cdmModoPrecioDevolucion());
    }
});
</script>
@if ($lineaInteractiva ?? $editable ?? false)
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/talle/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/talle/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/color/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/color/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/combinacion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/combinacion/consulta.js')) ?: time() }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js')) ?: time() }}"></script>
