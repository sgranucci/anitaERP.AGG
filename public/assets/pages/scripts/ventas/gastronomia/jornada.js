(function () {
    'use strict';

    var app = document.getElementById('jornada-gastronomia-app');
    if (!app) {
        return;
    }

    var apiEstadoBase = app.getAttribute('data-api-estado') || '';
    var apiAbrir = app.getAttribute('data-api-abrir') || '';
    var apiCerrar = app.getAttribute('data-api-cerrar') || '';
    var apiEliminar = app.getAttribute('data-api-eliminar') || '';
    var puedeAbrir = app.getAttribute('data-puede-abrir') === '1';
    var puedeCerrar = app.getAttribute('data-puede-cerrar') === '1';
    var puedeEliminar = app.getAttribute('data-puede-eliminar') === '1';
    var cierreTotemHabilitado = app.getAttribute('data-cierre-totem-habilitado') === '1';

    var selectEmpresa = document.getElementById('empresa_id');
    var btnAbrir = document.getElementById('btn-abrir-jornada');
    var btnCerrar = document.getElementById('btn-cerrar-jornada');

    function empresaId() {
        return selectEmpresa ? parseInt(selectEmpresa.value, 10) || 0 : 0;
    }

    function csrfToken() {
        if (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.csrf) {
            return String(window.JORNADA_GASTRONOMIA.csrf);
        }
        if (app && app.getAttribute('data-csrf')) {
            return app.getAttribute('data-csrf');
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        if (typeof window.jQuery !== 'undefined') {
            var jq = window.jQuery;
            var fromInput = jq('input[name="_token"]').val();
            if (fromInput) {
                return String(fromInput);
            }
            var fromHidden = jq('#csrf_token').val();
            if (fromHidden) {
                return String(fromHidden);
            }
        }
        return '';
    }

    function postJson(url, body) {
        var token = csrfToken();
        if (!token) {
            return Promise.resolve({
                ok: false,
                status: 0,
                data: {
                    error: 'No se encontró el token CSRF en la página. Recargue (F5) e intente de nuevo.',
                    motivo: 'csrf',
                },
            });
        }

        var payload = Object.assign({}, body, { _token: token });

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    data = { error: text && text.length < 500 ? text : null };
                }
                return { ok: r.ok, status: r.status, data: data };
            });
        });
    }

    function extraerMensajeError(res, fallback) {
        var d = res && res.data ? res.data : {};
        if (d.error && String(d.error).trim() !== '') {
            return String(d.error);
        }
        if (d.message && String(d.message).trim() !== '') {
            return String(d.message);
        }
        if (d.errors && typeof d.errors === 'object') {
            var partes = [];
            Object.keys(d.errors).forEach(function (k) {
                var v = d.errors[k];
                if (Array.isArray(v)) {
                    partes = partes.concat(v);
                } else if (v) {
                    partes.push(String(v));
                }
            });
            if (partes.length) {
                return partes.join(' ');
            }
        }
        if (res.status === 403) {
            return 'Acceso denegado (403). Verifique permisos de jornada.';
        }
        if (res.status === 419) {
            var raw = (d.message || d.error || '').toString();
            if (/csrf/i.test(raw)) {
                return 'Token de seguridad vencido o inválido (CSRF). Recargue la página con F5 e intente abrir la jornada de nuevo.';
            }
            return 'Sesión expirada (419). Recargue la página e inicie sesión de nuevo.';
        }
        if (res.status >= 500) {
            return 'Error del servidor (HTTP ' + res.status + ').';
        }
        return fallback;
    }

    function alertar(msg, esError) {
        if (typeof toastr !== 'undefined') {
            if (esError) {
                toastr.error(msg);
            } else {
                toastr.success(msg);
            }
        } else {
            window.alert(msg);
        }
    }

    var iframeImpresion = null;

    function obtenerIframeImpresion() {
        if (iframeImpresion && document.body.contains(iframeImpresion)) {
            return iframeImpresion;
        }
        iframeImpresion = document.createElement('iframe');
        iframeImpresion.setAttribute('title', 'Impresión comprobante cierre tótem');
        iframeImpresion.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;border:0;';
        document.body.appendChild(iframeImpresion);
        return iframeImpresion;
    }

    function limpiarIframeImpresion() {
        if (!iframeImpresion) {
            return;
        }
        if (iframeImpresion._jornadaBlobUrl) {
            URL.revokeObjectURL(iframeImpresion._jornadaBlobUrl);
            iframeImpresion._jornadaBlobUrl = null;
        }
        iframeImpresion.removeAttribute('src');
    }

    function urlComprobanteCierreTotem(jornadaId) {
        var base = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlComprobanteTotemBase) || '';
        if (!base) {
            return '';
        }
        return base.replace('__JORNADA_ID__', String(jornadaId));
    }

    /**
     * Abre el diálogo de impresión del PDF (mismo patrón que facturación gastronomía).
     */
    function imprimirComprobanteCierreTotem(jornadaId) {
        var url = typeof jornadaId === 'string' && jornadaId.indexOf('http') === 0
            ? jornadaId
            : urlComprobanteCierreTotem(jornadaId);
        if (!url) {
            return Promise.resolve(false);
        }

        var iframe = obtenerIframeImpresion();
        limpiarIframeImpresion();

        return fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('No se pudo obtener el comprobante PDF.');
                }
                return res.blob();
            })
            .then(function (blob) {
                var blobUrl = URL.createObjectURL(blob);
                iframe._jornadaBlobUrl = blobUrl;

                return new Promise(function (resolve, reject) {
                    function onLoad() {
                        iframe.removeEventListener('load', onLoad);
                        iframe.removeEventListener('error', onError);
                        resolve();
                    }
                    function onError() {
                        iframe.removeEventListener('load', onLoad);
                        iframe.removeEventListener('error', onError);
                        reject(new Error('No se pudo cargar el PDF para impresión.'));
                    }
                    iframe.addEventListener('load', onLoad);
                    iframe.addEventListener('error', onError);
                    iframe.src = blobUrl;
                });
            })
            .then(function () {
                var win = iframe.contentWindow;
                if (win) {
                    win.focus();
                    win.print();
                }
                return true;
            })
            .catch(function (e) {
                limpiarIframeImpresion();
                alertar(e.message || 'Error al imprimir el comprobante.', true);
                return false;
            });
    }

    if (puedeAbrir && btnAbrir) {
        btnAbrir.addEventListener('click', function () {
            var fechaInput = document.getElementById('fecha_jornada_abrir');
            var obsInput = document.getElementById('observacion_abrir');
            btnAbrir.disabled = true;

            postJson(apiAbrir, {
                empresa_id: empresaId(),
                fecha_jornada: fechaInput ? fechaInput.value : '',
                observacion: obsInput ? obsInput.value : '',
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Jornada abierta.', false);
                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo abrir la jornada.'), true);
                btnAbrir.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al abrir la jornada (sin respuesta del servidor).', true);
                btnAbrir.disabled = false;
            });
        });
    }

    if (puedeCerrar && btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            if (!window.confirm('¿Confirma el cierre de la jornada? No podrá facturar hasta abrir una nueva.')) {
                return;
            }

            var obsInput = document.getElementById('observacion_cerrar');
            btnCerrar.disabled = true;

            postJson(apiCerrar, {
                empresa_id: empresaId(),
                observacion: obsInput ? obsInput.value : '',
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    var msg = res.data.mensaje || 'Jornada cerrada.';
                    var ct = res.data.jornada && res.data.jornada.cierre_totem;
                    var jornadaId = res.data.jornada && res.data.jornada.id;

                    if (ct) {
                        if (ct.cantidad_lineas) {
                            msg += ' Órdenes Waitry: ' + ct.cantidad_lineas + '.';
                        }
                        if (ct.total_ingreso_totem != null && ct.total_ingreso_totem > 0) {
                            msg += ' Ingreso tótem: $' + Number(ct.total_ingreso_totem).toFixed(2) + '.';
                        }
                    }

                    alertar(msg, false);

                    if (cierreTotemHabilitado && jornadaId > 0) {
                        abrirModalInformeZ(jornadaId, { ofrecerImpresion: true });
                        btnCerrar.disabled = false;
                        return;
                    }

                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo cerrar la jornada.'), true);
                btnCerrar.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al cerrar la jornada (sin respuesta del servidor).', true);
                btnCerrar.disabled = false;
            });
        });
    }

    if (puedeEliminar && apiEliminar) {
        app.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.js-eliminar-jornada');
            if (!btn) {
                return;
            }

            var jornadaId = parseInt(btn.getAttribute('data-jornada-id'), 10) || 0;
            var fechaJornada = btn.getAttribute('data-fecha-jornada') || '';
            if (jornadaId <= 0) {
                return;
            }

            var msg = '¿Eliminar la jornada del ' + fechaJornada + ' (ID ' + jornadaId + ')?'
                + '\n\nSolo se permite si no tiene turnos ni comprobantes.';
            if (!window.confirm(msg)) {
                return;
            }

            btn.disabled = true;

            postJson(apiEliminar, {
                jornada_id: jornadaId,
            }).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Jornada eliminada.', false);
                    window.location.reload();
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo eliminar la jornada.'), true);
                btn.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al eliminar la jornada (sin respuesta del servidor).', true);
                btn.disabled = false;
            });
        });
    }

    function urlInformeZDatos(jornadaId) {
        var base = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlInformeZDatosBase) || '';
        if (!base) {
            return '';
        }
        return base.replace('__JORNADA_ID__', String(jornadaId));
    }

    function toleranciaInformeZ() {
        var t = window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.toleranciaInformeZ;
        return typeof t === 'number' && !isNaN(t) ? t : 0.02;
    }

    function formatearMonto(n) {
        return Number(n || 0).toFixed(2);
    }

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var modalInformeZ = document.getElementById('modal-informe-z-totem');
    var contenedorInformeZ = document.getElementById('informe-z-contenido');
    var resultadoInformeZ = document.getElementById('informe-z-resultado');
    var subtituloInformeZ = document.getElementById('informe-z-subtitulo');
    var btnGuardarInformeZ = document.getElementById('btn-informe-z-guardar');
    var btnOmitirImprimir = document.getElementById('btn-informe-z-omitir-imprimir');
    var estadoModalInformeZ = {
        jornadaId: 0,
        totems: [],
        ofrecerImpresion: false,
    };

    function renderResultadoConciliacion(conciliacion) {
        if (!resultadoInformeZ || !conciliacion) {
            return;
        }
        var ok = !!conciliacion.ok;
        var cls = ok ? 'alert alert-success' : 'alert alert-warning';
        var txt = ok
            ? 'Cuadra: Informe Z coincide con el sistema.'
            : 'Atención: hay diferencias entre Informe Z y el sistema.';
        resultadoInformeZ.className = 'mb-3 ' + cls;
        resultadoInformeZ.textContent = txt;
        resultadoInformeZ.classList.remove('d-none');
    }

    function renderTablasInformeZ(datos) {
        if (!contenedorInformeZ) {
            return;
        }
        var totems = datos.totems || [];
        estadoModalInformeZ.totems = totems;

        if (totems.length === 0) {
            contenedorInformeZ.innerHTML = '<p class="text-muted">No hay tótems configurados para esta empresa.</p>';
            if (btnGuardarInformeZ) {
                btnGuardarInformeZ.disabled = true;
            }
            return;
        }

        var html = '';
        totems.forEach(function (bloque, idxTotem) {
            var titulo = escHtml(bloque.ubicacion_nombre || 'Tótem');
            if (bloque.detalle) {
                titulo += ' — ' + escHtml(bloque.detalle);
            }
            if (bloque.waitry_table_id) {
                titulo += ' <span class="text-muted">(tableId ' + parseInt(bloque.waitry_table_id, 10) + ')</span>';
            }
            html += '<div class="card mb-3" data-totem-idx="' + idxTotem + '">';
            html += '<div class="card-header py-2"><strong>' + titulo + '</strong>';
            html += ' <span class="float-right text-muted small">Sistema: $' + formatearMonto(bloque.total_ingreso_sistema) + '</span></div>';
            html += '<div class="card-body p-0"><table class="table table-sm table-bordered mb-0">';
            html += '<thead><tr><th>Medio</th><th class="text-right">Sistema</th><th class="text-right" style="width:140px">Informe Z</th><th class="text-right">Diferencia</th></tr></thead><tbody>';

            (bloque.lineas || []).forEach(function (ln, idxLn) {
                var mSis = Number(ln.monto_sistema || 0);
                var mZ = ln.monto_informe_z != null ? Number(ln.monto_informe_z) : '';
                var inputVal = mZ === '' ? '' : formatearMonto(mZ);
                html += '<tr data-linea-idx="' + idxLn + '">';
                html += '<td>' + escHtml(ln.etiqueta || ln.tipo_waitry || '—') + '</td>';
                html += '<td class="text-right">$' + formatearMonto(mSis) + '</td>';
                html += '<td class="text-right"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right js-monto-informe-z"';
                html += ' data-totem-idx="' + idxTotem + '" data-linea-idx="' + idxLn + '"';
                html += ' data-tipo="' + escHtml(ln.tipo_waitry || 'totem') + '"';
                html += ' data-monto-sistema="' + formatearMonto(mSis) + '"';
                html += ' value="' + inputVal + '" placeholder="0.00"></td>';
                html += '<td class="text-right js-diff-cell">—</td></tr>';
            });

            html += '</tbody></table></div></div>';
        });

        contenedorInformeZ.innerHTML = html;
        actualizarDiferenciasInformeZ();

        if (btnGuardarInformeZ) {
            btnGuardarInformeZ.disabled = false;
        }
    }

    function actualizarDiferenciasInformeZ() {
        if (!contenedorInformeZ) {
            return;
        }
        var tol = toleranciaInformeZ();
        contenedorInformeZ.querySelectorAll('.js-monto-informe-z').forEach(function (inp) {
            var tr = inp.closest('tr');
            var cell = tr ? tr.querySelector('.js-diff-cell') : null;
            if (!cell) {
                return;
            }
            var mSis = parseFloat(inp.getAttribute('data-monto-sistema') || '0') || 0;
            var mZ = parseFloat(inp.value || '0') || 0;
            var diff = Math.round((mZ - mSis) * 100) / 100;
            var ok = Math.abs(diff) <= tol || (mSis <= tol && mZ <= tol);
            cell.textContent = (diff >= 0 ? '+' : '') + formatearMonto(diff);
            cell.className = 'text-right js-diff-cell ' + (ok ? 'text-success' : 'text-danger font-weight-bold');
        });
    }

    function recolectarPayloadInformeZ() {
        var totemsOut = [];
        (estadoModalInformeZ.totems || []).forEach(function (bloque, idxTotem) {
            var lineas = [];
            (bloque.lineas || []).forEach(function (ln, idxLn) {
                var inp = contenedorInformeZ
                    ? contenedorInformeZ.querySelector(
                        '.js-monto-informe-z[data-totem-idx="' + idxTotem + '"][data-linea-idx="' + idxLn + '"]'
                    )
                    : null;
                lineas.push({
                    tipo_waitry: ln.tipo_waitry || 'totem',
                    monto: parseFloat(inp && inp.value ? inp.value : '0') || 0,
                });
            });
            totemsOut.push({
                totem_id: bloque.totem_id,
                lineas: lineas,
            });
        });
        return { jornada_id: estadoModalInformeZ.jornadaId, totems: totemsOut };
    }

    function abrirModalInformeZ(jornadaId, opts) {
        opts = opts || {};
        if (!modalInformeZ || !cierreTotemHabilitado || jornadaId <= 0) {
            if (opts.ofrecerImpresion) {
                imprimirComprobanteCierreTotem(jornadaId).finally(function () {
                    window.location.reload();
                });
            }
            return;
        }

        estadoModalInformeZ.jornadaId = jornadaId;
        estadoModalInformeZ.ofrecerImpresion = !!opts.ofrecerImpresion;

        if (contenedorInformeZ) {
            contenedorInformeZ.innerHTML = '<p class="text-muted">Cargando totales del sistema…</p>';
        }
        if (resultadoInformeZ) {
            resultadoInformeZ.classList.add('d-none');
            resultadoInformeZ.textContent = '';
        }
        if (btnGuardarInformeZ) {
            btnGuardarInformeZ.disabled = true;
        }
        if (btnOmitirImprimir) {
            if (opts.ofrecerImpresion) {
                btnOmitirImprimir.classList.remove('d-none');
            } else {
                btnOmitirImprimir.classList.add('d-none');
            }
        }

        var url = urlInformeZDatos(jornadaId);
        if (!url) {
            return;
        }

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.data.ok) {
                    throw new Error(extraerMensajeError(res, 'No se pudieron cargar los datos del Informe Z.'));
                }
                var d = res.data;
                if (subtituloInformeZ) {
                    var sub = 'Jornada ' + (d.fecha_jornada || '') + ' — ingrese los montos del Informe Z de cada tótem.';
                    if (d.informe_z_cargado && d.informe_z_en) {
                        sub += ' Última carga: ' + d.informe_z_en;
                        if (d.usuario_informe_z) {
                            sub += ' (' + d.usuario_informe_z + ')';
                        }
                    }
                    subtituloInformeZ.textContent = sub;
                }
                renderTablasInformeZ(d);
                if (d.conciliacion) {
                    renderResultadoConciliacion(d.conciliacion);
                }
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(modalInformeZ).modal('show');
                } else {
                    modalInformeZ.style.display = 'block';
                }
            })
            .catch(function (e) {
                alertar(e.message || 'Error al cargar Informe Z.', true);
                if (opts.ofrecerImpresion) {
                    imprimirComprobanteCierreTotem(jornadaId).finally(function () {
                        window.location.reload();
                    });
                }
            });
    }

    if (contenedorInformeZ) {
        contenedorInformeZ.addEventListener('input', function (ev) {
            if (ev.target && ev.target.classList.contains('js-monto-informe-z')) {
                actualizarDiferenciasInformeZ();
            }
        });
    }

    if (btnGuardarInformeZ) {
        btnGuardarInformeZ.addEventListener('click', function () {
            var jid = estadoModalInformeZ.jornadaId;
            var urlGuardar = (window.JORNADA_GASTRONOMIA && window.JORNADA_GASTRONOMIA.urlInformeZGuardar) || '';
            if (jid <= 0 || !urlGuardar) {
                return;
            }
            btnGuardarInformeZ.disabled = true;
            postJson(urlGuardar, recolectarPayloadInformeZ()).then(function (res) {
                if (res.ok && res.data.ok) {
                    alertar(res.data.mensaje || 'Informe Z guardado.', false);
                    if (res.data.conciliacion) {
                        renderResultadoConciliacion(res.data.conciliacion);
                    }
                    var imprimir = estadoModalInformeZ.ofrecerImpresion;
                    if (typeof window.jQuery !== 'undefined') {
                        window.jQuery(modalInformeZ).modal('hide');
                    }
                    if (imprimir) {
                        imprimirComprobanteCierreTotem(jid).finally(function () {
                            window.location.reload();
                        });
                    } else {
                        window.location.reload();
                    }
                    return;
                }
                alertar(extraerMensajeError(res, 'No se pudo guardar el Informe Z.'), true);
                btnGuardarInformeZ.disabled = false;
            }).catch(function () {
                alertar('Error de comunicación al guardar el Informe Z.', true);
                btnGuardarInformeZ.disabled = false;
            });
        });
    }

    if (btnOmitirImprimir) {
        btnOmitirImprimir.addEventListener('click', function () {
            var jid = estadoModalInformeZ.jornadaId;
            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(modalInformeZ).modal('hide');
            }
            if (jid > 0) {
                imprimirComprobanteCierreTotem(jid).finally(function () {
                    window.location.reload();
                });
            } else {
                window.location.reload();
            }
        });
    }

    app.addEventListener('click', function (ev) {
        var btnInformeZ = ev.target.closest('.js-informe-z');
        if (btnInformeZ) {
            ev.preventDefault();
            var jidZ = parseInt(btnInformeZ.getAttribute('data-jornada-id'), 10) || 0;
            if (jidZ > 0) {
                abrirModalInformeZ(jidZ, { ofrecerImpresion: false });
            }
            return;
        }

        var btnImprimir = ev.target.closest('.js-imprimir-cierre-totem');
        if (!btnImprimir) {
            return;
        }
        ev.preventDefault();
        var jid = parseInt(btnImprimir.getAttribute('data-jornada-id'), 10) || 0;
        if (jid > 0) {
            imprimirComprobanteCierreTotem(jid);
        }
    });
})();
