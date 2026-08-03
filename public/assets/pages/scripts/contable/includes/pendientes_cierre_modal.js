(function () {
    'use strict';

    var cfg = window.CIERRE_REND_PENDIENTES || {};
    var pendientesCargando = false;
    var pendientesResumen = null;
    var pendientesTimer = null;

    function formatoNumero(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatoFechaIso(iso) {
        if (!iso || iso.length !== 10) {
            return iso || '';
        }
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function metaPendientes() {
        return document.getElementById('pendientes-url-listado-base');
    }

    function flagMeta(name) {
        var el = metaPendientes();
        return el && el.getAttribute(name) === '1';
    }

    function empresaIdPendientes() {
        return parseInt((document.getElementById('pendientes-empresa-id') || {}).value || '0', 10);
    }

    function leerRangoCierrePendientes() {
        var desde = (document.getElementById('pendientes-fecha-desde') || {}).value || '';
        var hasta = (document.getElementById('pendientes-fecha-hasta') || {}).value || '';
        if (desde && hasta && desde > hasta) {
            return { fecha_desde: hasta, fecha_hasta: desde };
        }
        return { fecha_desde: desde, fecha_hasta: hasta };
    }

    function setRangoCierrePendientes(desde, hasta) {
        var inputDesde = document.getElementById('pendientes-fecha-desde');
        var inputHasta = document.getElementById('pendientes-fecha-hasta');
        var min = (pendientesResumen && pendientesResumen.fecha_desde) || '';
        var max = (pendientesResumen && (pendientesResumen.fecha_hasta || pendientesResumen.fecha_desde)) || '';

        if (flagMeta('data-exige-correlatividad') && pendientesResumen && pendientesResumen.fecha_desde) {
            desde = pendientesResumen.fecha_desde;
        }

        if (inputDesde) {
            inputDesde.value = desde || '';
            if (min) {
                inputDesde.min = min;
                inputDesde.max = max || min;
            }
        }
        if (inputHasta) {
            inputHasta.value = hasta || '';
            if (min) {
                inputHasta.min = min;
                inputHasta.max = max || min;
            }
        }
    }

    function filtrarResumenPorRango(resumen, desde, hasta) {
        var grupos = (resumen.grupos || []).filter(function (g) {
            var f = g.fecha_dia || '';
            if (!f) {
                return false;
            }
            if (desde && f < desde) {
                return false;
            }
            if (hasta && f > hasta) {
                return false;
            }
            return true;
        });
        var porDia = (resumen.por_dia || []).filter(function (d) {
            var f = d.fecha_jornada || '';
            if (!f) {
                return false;
            }
            if (desde && f < desde) {
                return false;
            }
            if (hasta && f > hasta) {
                return false;
            }
            return true;
        });
        var turnos = 0;
        var cobrado = 0;
        var jornadas = {};
        grupos.forEach(function (g) {
            turnos += parseInt(g.cantidad_rendiciones || 0, 10);
            cobrado = Math.round((cobrado + Number(g.total_cobrado || 0)) * 100) / 100;
            if (g.fecha_dia) {
                jornadas[g.fecha_dia] = true;
            }
        });

        return {
            empresa_id: resumen.empresa_id,
            empresa_nombre: resumen.empresa_nombre,
            cantidad_grupos: grupos.length,
            cantidad_rendiciones: turnos,
            cantidad_jornadas: Object.keys(jornadas).length,
            fecha_desde: desde || null,
            fecha_hasta: hasta || null,
            fecha_desde_fmt: desde ? formatoFechaIso(desde) : null,
            fecha_hasta_fmt: hasta ? formatoFechaIso(hasta) : null,
            total_cobrado: cobrado,
            grupos: grupos,
            por_dia: porDia,
            generado_en_fmt: resumen.generado_en_fmt,
        };
    }

    function actualizarBadgePendientes(cantidad) {
        var badge = document.getElementById('badge-pendientes-cierre');
        if (!badge) {
            return;
        }
        var n = parseInt(cantidad || 0, 10);
        if (n > 0) {
            badge.textContent = String(n);
            badge.classList.remove('d-none');
        } else {
            badge.textContent = '0';
            badge.classList.add('d-none');
        }
    }

    function setPendientesLoading(activo) {
        var loading = document.getElementById('pendientes-loading');
        var contenido = document.getElementById('pendientes-contenido');
        if (loading) {
            loading.classList.toggle('d-none', !activo);
        }
        if (contenido && activo) {
            contenido.classList.add('d-none');
        }
        var btn = document.getElementById('btn-pendientes-actualizar');
        if (btn) {
            btn.disabled = !!activo;
        }
    }

    function mostrarErrorPendientes(mensaje) {
        var err = document.getElementById('pendientes-error-box');
        var contenido = document.getElementById('pendientes-contenido');
        if (err) {
            err.textContent = mensaje || 'No se pudo consultar pendientes.';
            err.classList.remove('d-none');
        }
        if (contenido) {
            contenido.classList.add('d-none');
        }
    }

    function limpiarErrorPendientes() {
        var err = document.getElementById('pendientes-error-box');
        if (err) {
            err.classList.add('d-none');
            err.textContent = '';
        }
    }

    function urlListadoPendientes(empresaId, desde, hasta) {
        var baseEl = metaPendientes();
        if (!baseEl) {
            return '#';
        }
        var url = new URL(baseEl.value, window.location.origin);
        url.searchParams.set('empresa_id', String(empresaId || ''));
        url.searchParams.set('estado_cierre', baseEl.getAttribute('data-estado-pendiente') || 'pendiente');
        if (desde) {
            url.searchParams.set('fecha_jornada_desde', desde);
        }
        if (hasta) {
            url.searchParams.set('fecha_jornada_hasta', hasta);
        }
        return url.pathname + url.search;
    }

    function validarCorrelatividad(rango) {
        if (!flagMeta('data-exige-correlatividad') || !pendientesResumen) {
            return { ok: true, mensaje: '' };
        }
        var proxima = pendientesResumen.fecha_desde || '';
        if (!proxima) {
            return { ok: true, mensaje: '' };
        }
        if (!rango.fecha_desde || rango.fecha_desde !== proxima) {
            return {
                ok: false,
                mensaje: 'El cierre debe comenzar en la jornada pendiente más antigua ('
                    + formatoFechaIso(proxima) + ').',
            };
        }
        return { ok: true, mensaje: '' };
    }

    function aplicarVistaPendientes() {
        if (!pendientesResumen) {
            return;
        }
        var contenido = document.getElementById('pendientes-contenido');
        var vacio = document.getElementById('pendientes-vacio');
        var vacioRango = document.getElementById('pendientes-vacio-rango');
        var tablas = document.getElementById('pendientes-tablas');
        var tbody = document.getElementById('pendientes-grupos-tbody');
        var porDiaBox = document.getElementById('pendientes-por-dia-box');
        var porDiaTbody = document.getElementById('pendientes-por-dia-tbody');
        var btnListado = document.getElementById('btn-pendientes-ver-listado');
        var btnCerrar = document.getElementById('btn-pendientes-cerrar-rango');
        var rangoCierreBox = document.getElementById('pendientes-rango-cierre-box');
        var ayuda = document.getElementById('pendientes-rango-cierre-ayuda');
        var avisoCorrel = document.getElementById('pendientes-correl-aviso');
        var rangoTexto = document.getElementById('pendientes-rango-texto');
        var totalGrupos = pendientesResumen.cantidad_grupos || 0;
        var mostrarPv = flagMeta('data-mostrar-puntoventa');
        var mostrarFac = flagMeta('data-mostrar-facturado');
        var labelMonto = (metaPendientes() && metaPendientes().getAttribute('data-label-monto')) || 'Total';

        if (!contenido) {
            return;
        }

        if (rangoCierreBox) {
            rangoCierreBox.classList.toggle('d-none', totalGrupos <= 0);
        }

        if (rangoTexto) {
            if (totalGrupos > 0 && pendientesResumen.fecha_desde_fmt) {
                rangoTexto.textContent = (pendientesResumen.empresa_nombre || 'Empresa')
                    + ': hay pendientes desde ' + pendientesResumen.fecha_desde_fmt
                    + ' hasta ' + (pendientesResumen.fecha_hasta_fmt || pendientesResumen.fecha_desde_fmt)
                    + '. Elegí abajo el tramo a cerrar.';
            } else {
                rangoTexto.textContent = (pendientesResumen.empresa_nombre || 'Empresa') + ': sin pendientes.';
            }
        }

        var rango = leerRangoCierrePendientes();
        if (flagMeta('data-exige-correlatividad') && pendientesResumen.fecha_desde) {
            rango.fecha_desde = pendientesResumen.fecha_desde;
            var inputDesde = document.getElementById('pendientes-fecha-desde');
            if (inputDesde && inputDesde.value !== pendientesResumen.fecha_desde) {
                inputDesde.value = pendientesResumen.fecha_desde;
            }
        }

        var filtrado = totalGrupos > 0
            ? filtrarResumenPorRango(pendientesResumen, rango.fecha_desde, rango.fecha_hasta)
            : {
                cantidad_grupos: 0,
                cantidad_rendiciones: 0,
                cantidad_jornadas: 0,
                total_cobrado: 0,
                grupos: [],
                por_dia: [],
            };

        document.getElementById('pendientes-kpi-grupos').textContent = String(filtrado.cantidad_grupos || 0);
        document.getElementById('pendientes-kpi-turnos').textContent = String(filtrado.cantidad_rendiciones || 0);
        document.getElementById('pendientes-kpi-jornadas').textContent = String(filtrado.cantidad_jornadas || 0);
        document.getElementById('pendientes-kpi-cobrado').textContent = formatoNumero(filtrado.total_cobrado || 0);

        var correl = validarCorrelatividad(rango);
        if (avisoCorrel) {
            if (!correl.ok) {
                avisoCorrel.textContent = correl.mensaje;
                avisoCorrel.classList.remove('d-none');
            } else {
                avisoCorrel.classList.add('d-none');
                avisoCorrel.textContent = '';
            }
        }

        if (ayuda) {
            if (totalGrupos <= 0) {
                ayuda.textContent = '';
            } else if ((filtrado.cantidad_grupos || 0) <= 0) {
                ayuda.textContent = 'Ningún pendiente cae en este rango.';
            } else {
                ayuda.textContent = 'Se cerrarán '
                    + filtrado.cantidad_grupos + ' asiento(s) / '
                    + filtrado.cantidad_rendiciones + ' registro(s)'
                    + ' del ' + (filtrado.fecha_desde_fmt || '—')
                    + ' al ' + (filtrado.fecha_hasta_fmt || '—')
                    + '. ' + labelMonto + ': $ ' + formatoNumero(filtrado.total_cobrado || 0) + '.';
            }
        }

        var puedeCerrar = (filtrado.cantidad_grupos || 0) > 0 && correl.ok;
        if (btnListado) {
            if ((filtrado.cantidad_grupos || 0) > 0) {
                btnListado.href = urlListadoPendientes(
                    pendientesResumen.empresa_id,
                    rango.fecha_desde,
                    rango.fecha_hasta
                );
                btnListado.classList.remove('d-none');
            } else {
                btnListado.classList.add('d-none');
            }
        }
        if (btnCerrar) {
            btnCerrar.classList.toggle('d-none', !puedeCerrar);
        }

        if (totalGrupos <= 0) {
            if (vacio) {
                vacio.classList.remove('d-none');
            }
            if (vacioRango) {
                vacioRango.classList.add('d-none');
            }
            if (tablas) {
                tablas.classList.add('d-none');
            }
            return;
        }

        if (vacio) {
            vacio.classList.add('d-none');
        }

        if ((filtrado.cantidad_grupos || 0) <= 0) {
            if (vacioRango) {
                vacioRango.classList.remove('d-none');
            }
            if (tablas) {
                tablas.classList.add('d-none');
            }
            return;
        }

        if (vacioRango) {
            vacioRango.classList.add('d-none');
        }
        if (tablas) {
            tablas.classList.remove('d-none');
        }

        if (tbody) {
            tbody.innerHTML = '';
            (filtrado.grupos || []).forEach(function (g) {
                var tr = document.createElement('tr');
                var html = '<td>' + escapeHtml(g.fecha_dia_fmt || formatoFechaIso(g.fecha_dia)) + '</td>';
                if (mostrarPv) {
                    html += '<td><small>' + escapeHtml(g.puntoventa_label || '—') + '</small></td>';
                }
                html += '<td class="text-center">' + (g.cantidad_rendiciones || 0) + '</td>'
                    + '<td class="text-right">' + formatoNumero(g.total_cobrado || 0) + '</td>';
                if (mostrarFac) {
                    html += '<td class="text-right">' + formatoNumero(g.total_factura || 0) + '</td>';
                }
                tr.innerHTML = html;
                tbody.appendChild(tr);
            });
        }

        if (porDiaTbody && porDiaBox) {
            var porDia = filtrado.por_dia || [];
            if (porDia.length > 1) {
                porDiaTbody.innerHTML = '';
                porDia.forEach(function (d) {
                    var trDia = document.createElement('tr');
                    trDia.innerHTML = '<td>' + escapeHtml(d.fecha_jornada_fmt || formatoFechaIso(d.fecha_jornada)) + '</td>'
                        + '<td class="text-center">' + (d.cantidad || 0) + '</td>'
                        + '<td class="text-center">' + (d.cantidad_grupos || 0) + '</td>'
                        + '<td class="text-right">' + formatoNumero(d.total_cobrado || 0) + '</td>';
                    porDiaTbody.appendChild(trDia);
                });
                porDiaBox.classList.remove('d-none');
            } else {
                porDiaBox.classList.add('d-none');
                porDiaTbody.innerHTML = '';
            }
        }
    }

    function renderPendientes(resumen, opciones) {
        opciones = opciones || {};
        var preservarRango = !!opciones.preservarRango;
        var rangoActual = preservarRango ? leerRangoCierrePendientes() : null;
        pendientesResumen = resumen || null;
        var contenido = document.getElementById('pendientes-contenido');
        var actualizado = document.getElementById('pendientes-actualizado-en');

        if (!contenido || !resumen) {
            return;
        }

        limpiarErrorPendientes();
        contenido.classList.remove('d-none');
        actualizarBadgePendientes(resumen.cantidad_grupos || 0);

        if (actualizado) {
            actualizado.textContent = resumen.generado_en_fmt
                ? ('Actualizado ' + resumen.generado_en_fmt)
                : '';
        }

        var desde = resumen.fecha_desde || '';
        var hasta = resumen.fecha_hasta || resumen.fecha_desde || '';
        if (preservarRango && rangoActual && rangoActual.fecha_desde && rangoActual.fecha_hasta) {
            desde = rangoActual.fecha_desde;
            hasta = rangoActual.fecha_hasta;
            if (resumen.fecha_desde && desde < resumen.fecha_desde) {
                desde = resumen.fecha_desde;
            }
            if (resumen.fecha_hasta && hasta > resumen.fecha_hasta) {
                hasta = resumen.fecha_hasta;
            }
            if (desde > hasta) {
                desde = resumen.fecha_desde || desde;
                hasta = resumen.fecha_hasta || hasta;
            }
        }
        if (flagMeta('data-exige-correlatividad') && resumen.fecha_desde) {
            desde = resumen.fecha_desde;
        }
        setRangoCierrePendientes(desde, hasta);
        aplicarVistaPendientes();
    }

    function cargarPendientesCierre(opciones) {
        opciones = opciones || {};
        var silencioso = !!opciones.silencioso;
        var empresaId = empresaIdPendientes();
        if (empresaId <= 0) {
            if (!silencioso) {
                mostrarErrorPendientes('Seleccione una empresa.');
            }
            return;
        }
        if (!cfg.urlPendientes) {
            return;
        }
        if (pendientesCargando) {
            return;
        }
        pendientesCargando = true;
        if (!silencioso) {
            setPendientesLoading(true);
            limpiarErrorPendientes();
        }

        var url = cfg.urlPendientes + (cfg.urlPendientes.indexOf('?') >= 0 ? '&' : '?')
            + 'empresa_id=' + encodeURIComponent(String(empresaId));

        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                pendientesCargando = false;
                setPendientesLoading(false);
                if (!data.ok) {
                    if (!silencioso) {
                        mostrarErrorPendientes(data.mensaje || 'No se pudo consultar pendientes.');
                    }
                    return;
                }
                renderPendientes(data.resumen || {}, { preservarRango: silencioso });
            })
            .catch(function () {
                pendientesCargando = false;
                setPendientesLoading(false);
                if (!silencioso) {
                    mostrarErrorPendientes('Error de comunicación al consultar pendientes.');
                }
            });
    }

    function detenerAutoRefreshPendientes() {
        if (pendientesTimer) {
            clearInterval(pendientesTimer);
            pendientesTimer = null;
        }
    }

    function iniciarAutoRefreshPendientes() {
        detenerAutoRefreshPendientes();
        pendientesTimer = setInterval(function () {
            var modal = document.getElementById('modal-pendientes-cierre');
            if (!modal || !modal.classList.contains('show')) {
                return;
            }
            cargarPendientesCierre({ silencioso: true });
        }, 60000);
    }

    function abrirModalPendientes() {
        var select = document.getElementById('pendientes-empresa-id');
        if (select && (!select.value || select.value === '0') && cfg.empresaIdFiltro > 0) {
            select.value = String(cfg.empresaIdFiltro);
        }
        $('#modal-pendientes-cierre').modal('show');
        cargarPendientesCierre();
        iniciarAutoRefreshPendientes();
    }

    function abrirCierreRangoDesdePendientes() {
        if (!pendientesResumen) {
            return;
        }
        var rango = leerRangoCierrePendientes();
        if (flagMeta('data-exige-correlatividad') && pendientesResumen.fecha_desde) {
            rango.fecha_desde = pendientesResumen.fecha_desde;
        }
        if (!rango.fecha_desde || !rango.fecha_hasta) {
            alert('Indique el rango de jornadas a cerrar.');
            return;
        }
        var correl = validarCorrelatividad(rango);
        if (!correl.ok) {
            alert(correl.mensaje);
            return;
        }
        var filtrado = filtrarResumenPorRango(
            pendientesResumen,
            rango.fecha_desde,
            rango.fecha_hasta
        );
        if ((filtrado.cantidad_grupos || 0) <= 0) {
            alert('No hay pendientes en el rango indicado.');
            return;
        }

        $('#modal-pendientes-cierre').modal('hide');

        if (typeof window.CIERRE_REND_abrirRangoDesdePendientes === 'function') {
            window.CIERRE_REND_abrirRangoDesdePendientes(
                pendientesResumen.empresa_id,
                rango.fecha_desde,
                rango.fecha_hasta
            );
            return;
        }

        var empRango = document.getElementById('rango-empresa-id');
        var desde = document.getElementById('rango-fecha-desde');
        var hasta = document.getElementById('rango-fecha-hasta');
        if (empRango) {
            empRango.value = String(pendientesResumen.empresa_id || '');
        }
        if (desde) {
            desde.value = rango.fecha_desde;
        }
        if (hasta) {
            hasta.value = rango.fecha_hasta;
        }
        var modalRangoId = cfg.modalRangoId || 'modal-cierre-rango';
        $('#' + modalRangoId).modal('show');
        var btnPreview = document.getElementById('btn-rango-preview');
        if (btnPreview) {
            btnPreview.click();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('modal-pendientes-cierre')) {
            return;
        }

        var btnAbrirPendientes = document.getElementById('btn-abrir-pendientes-cierre');
        if (btnAbrirPendientes) {
            btnAbrirPendientes.addEventListener('click', abrirModalPendientes);
        }

        var btnActualizarPendientes = document.getElementById('btn-pendientes-actualizar');
        if (btnActualizarPendientes) {
            btnActualizarPendientes.addEventListener('click', function () {
                this.blur();
                cargarPendientesCierre();
            });
        }

        var selectPendientes = document.getElementById('pendientes-empresa-id');
        if (selectPendientes) {
            selectPendientes.addEventListener('change', function () {
                cargarPendientesCierre();
            });
        }

        var btnCerrarDesdePendientes = document.getElementById('btn-pendientes-cerrar-rango');
        if (btnCerrarDesdePendientes) {
            btnCerrarDesdePendientes.addEventListener('click', abrirCierreRangoDesdePendientes);
        }

        var inputDesdePend = document.getElementById('pendientes-fecha-desde');
        var inputHastaPend = document.getElementById('pendientes-fecha-hasta');
        if (inputDesdePend && !flagMeta('data-exige-correlatividad')) {
            inputDesdePend.addEventListener('change', aplicarVistaPendientes);
        }
        if (inputHastaPend) {
            inputHastaPend.addEventListener('change', aplicarVistaPendientes);
        }

        var btnRangoCompleto = document.getElementById('btn-pendientes-rango-completo');
        if (btnRangoCompleto) {
            btnRangoCompleto.addEventListener('click', function () {
                if (!pendientesResumen) {
                    return;
                }
                setRangoCierrePendientes(
                    pendientesResumen.fecha_desde || '',
                    pendientesResumen.fecha_hasta || pendientesResumen.fecha_desde || ''
                );
                aplicarVistaPendientes();
            });
        }

        $('#modal-pendientes-cierre').on('hidden.bs.modal', function () {
            detenerAutoRefreshPendientes();
        });

        if (cfg.urlPendientes && cfg.empresaIdFiltro > 0 && selectPendientes) {
            if (!selectPendientes.value) {
                selectPendientes.value = String(cfg.empresaIdFiltro);
            }
            cargarPendientesCierre({ silencioso: true });
        }
    });
})();
