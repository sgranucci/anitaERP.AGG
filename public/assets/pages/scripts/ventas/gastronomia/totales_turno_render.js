(function () {
    'use strict';

    var CLS_PANEL = 'gastro-totales-panel';

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Resumen: comprobantes emitidos vs cobranzas registradas en esas facturas.
     * @param {object} t totales_turno / totales_dia
     */
    function renderConciliacionHtml(t) {
        if (!t || t.total_ventas === undefined) {
            return '';
        }
        var ok = !!t.conciliacion_ok;
        var diff = Number(t.diferencia_cobranza || 0);
        var estado = ok
            ? 'Cuadra'
            : '$' + fmt(Math.abs(diff)) + (diff > 0 ? ' (sobra)' : ' (falta)');
        var badgeCls = ok ? 'badge-success' : 'badge-warning';

        var html = '<div class="' + CLS_PANEL + ' gastro-conciliacion border rounded p-3 mb-3 bg-white">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">';
        html += '<span class="font-weight-bold h6 mb-0">Comprobantes y cobranzas</span>';
        html += '<span class="badge ' + badgeCls + ' px-2 py-1">' + estado + '</span>';
        html += '</div>';
        html += '<p class="text-muted mb-2 gastro-totales-leyenda">Las cobranzas se obtienen de cada factura emitida en el período.</p>';
        html += '<div class="row text-center">';
        html += '<div class="col-6 col-md-3 border-right mb-2 mb-md-0">';
        html += '<span class="text-muted d-block">Facturado</span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_ventas) + '</span>';
        html += '<span class="text-muted d-block">' + (t.cantidad_comprobantes || 0) + ' comp.</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-3 border-right mb-2 mb-md-0">';
        html += '<span class="text-muted d-block">Invit. $0,01</span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_invitaciones) + '</span>';
        html += '<span class="text-muted d-block">' + (t.cantidad_invitaciones || 0) + ' comp.</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-3 border-right">';
        html += '<span class="text-muted d-block">Esperado en caja</span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_ventas_cobrables) + '</span>';
        html += '</div>';
        html += '<div class="col-6 col-md-3">';
        html += '<span class="text-muted d-block">Cobrado (facturas)</span>';
        html += '<span class="gastro-totales-monto font-weight-bold">$' + fmt(t.total_cobrado) + '</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        return html;
    }

    function renderMediosPagoTabla(medios, vacio, conTotalFinal, totalCobradoRef, opcionesConciliar) {
        if (!medios || !medios.length) {
            return '<p class="text-muted mb-0 pl-2">' + esc(vacio || 'Sin cobranzas en comprobantes.') + '</p>';
        }
        var suma = 0;
        medios.forEach(function (p) {
            suma += Number(p.total || 0);
        });
        var totalFinal = totalCobradoRef != null ? Number(totalCobradoRef) : suma;
        var conciliar = opcionesConciliar && opcionesConciliar.habilitar;

        var html = '<table class="table table-bordered mb-0 gastro-totales-tabla">';
        html += '<thead class="thead-light"><tr><th>Medio de pago</th><th class="text-right">Cobrado</th>';
        if (conciliar) {
            html += '<th class="text-center" style="width:110px;">Conciliar</th>';
        }
        html += '</tr></thead><tbody>';
        medios.forEach(function (p) {
            var ccId = p.cuentacaja_id || 0;
            html += '<tr><td>' + esc(p.nombre || p.codigo || '—') + '</td>';
            html += '<td class="text-right font-weight-bold">$' + fmt(p.total) + '</td>';
            if (conciliar && ccId > 0) {
                html += '<td class="text-center">';
                html += '<button type="button" class="btn btn-xs btn-outline-info js-conciliar-medio" data-cuentacaja-id="' + ccId + '" ';
                html += 'data-medio-nombre="' + esc(p.nombre || p.codigo || '') + '" title="Ver facturas de este medio">';
                html += '<i class="fa fa-search"></i> Facturas</button></td>';
            } else if (conciliar) {
                html += '<td></td>';
            }
            html += '</tr>';
        });
        html += '</tbody>';
        if (conTotalFinal) {
            html += '<tfoot class="thead-light"><tr>';
            html += '<th class="text-right">Total</th>';
            html += '<th class="text-right gastro-totales-monto font-weight-bold">$' + fmt(totalFinal) + '</th>';
            if (conciliar) {
                html += '<th></th>';
            }
            html += '</tr></tfoot>';
        }
        html += '</table>';
        return html;
    }

    function renderTotalMediosPagoFinalHtml(totales, opcionesConciliar) {
        var medios = totales.por_medio_pago || [];
        if (!medios.length) {
            return '';
        }
        var html = '<div class="mt-3 pt-3 border-top gastro-totales-medios-final">';
        html += '<h6 class="font-weight-bold mb-2">Total final por medio de pago</h6>';
        html += renderMediosPagoTabla(
            medios,
            'Sin cobranzas en comprobantes del turno.',
            true,
            totales.total_cobrado,
            opcionesConciliar
        );
        html += '</div>';
        return html;
    }

    function renderPorMozoHtml(porMozo, opcionesConciliar) {
        if (!porMozo || !porMozo.length) {
            return '<p class="text-muted mb-0">Sin comprobantes por mozo.</p>';
        }
        var html = '<div class="gastro-mozos-lista">';
        porMozo.forEach(function (m) {
            var medios = m.por_medio_pago || [];
            html += '<div class="card mb-2 border">';
            html += '<div class="card-header py-2 bg-light">';
            html += '<div class="d-flex flex-wrap justify-content-between align-items-center">';
            html += '<strong class="gastro-mozo-nombre">' + esc(m.mozo_nombre || 'Sin mozo') + '</strong>';
            html += '<span>';
            html += (m.cantidad || 0) + ' comp. · Facturado <strong>$' + fmt(m.total) + '</strong>';
            html += ' · Cobrado <strong>$' + fmt(m.total_cobrado != null ? m.total_cobrado : 0) + '</strong>';
            html += '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="card-body py-2">';
            html += '<div class="font-weight-bold mb-2">Cobranzas por medio de pago</div>';
            html += renderMediosPagoTabla(medios, 'Sin cobranzas en los comprobantes de este mozo.', false, null, opcionesConciliar);
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function etiquetaMedioColumna(c) {
        var nombre = trim((c && c.nombre) || '');
        var codigo = trim((c && c.codigo) || '');
        if (nombre !== '') {
            return { etiqueta: nombre, titulo: codigo ? codigo + ' — ' + nombre : nombre };
        }
        return { etiqueta: codigo || '—', titulo: codigo || '' };
    }

    function trim(s) {
        return String(s == null ? '' : s).trim();
    }

    function renderGrillaPlaceholderHtml(grilla) {
        if (!grilla) {
            return '<p class="text-muted p-3 mb-0 small">Sin datos del turno.</p>';
        }
        var total = Number(grilla.total_filas || 0);
        var conDiff = Number(grilla.total_con_diferencia || 0);
        var html = '<div class="p-3">';
        html += '<p class="mb-2 small text-muted">';
        html += 'El turno tiene <strong>' + total + '</strong> comprobante(s)';
        if (conDiff > 0) {
            html += ', <strong>' + conDiff + '</strong> con diferencia entre facturado y cobrado';
        }
        html += '. La grilla no se carga automáticamente para no saturar el navegador.';
        html += '</p>';
        html += '<button type="button" class="btn btn-sm btn-primary js-cargar-grilla-comprobantes" data-grilla-target="">';
        html += '<i class="fa fa-table"></i> Ver comprobantes (paginado)</button>';
        html += ' <span class="small text-muted ml-1">Recomendado: marque «Solo con diferencia» si el turno es muy grande.</span>';
        html += '</div>';
        return html;
    }

    function renderGrillaConciliacionHtml(grilla) {
        if (!grilla) {
            return '<p class="text-muted p-3 mb-0">Sin datos.</p>';
        }
        var columnas = grilla.columnas_medios || [];
        var filas = grilla.filas || [];
        var pag = grilla.paginacion;

        if (!pag || pag.page < 1) {
            return renderGrillaPlaceholderHtml(grilla);
        }

        if (!filas.length) {
            var soloDif = pag && pag.solo_diferencias;
            return '<p class="text-muted p-3 mb-0">' + (soloDif
                ? 'No hay comprobantes con diferencia de cobranza.'
                : 'No hay comprobantes en esta página.') + '</p>';
        }

        var html = '';
        if (pag) {
            html += '<div class="d-flex flex-wrap justify-content-between align-items-center px-2 py-1 border-bottom bg-light small">';
            html += '<span>Página ' + pag.page + ' de ' + (pag.total_pages || 1);
            html += ' · ' + pag.total + ' comprobante(s)';
            if (pag.solo_diferencias) {
                html += ' <span class="badge badge-warning">solo con diferencia</span>';
            }
            html += '</span>';
            html += '<div class="gastro-grilla-paginacion" data-container=""></div>';
            html += '</div>';
        }

        html += '<table class="table table-bordered table-sm mb-0 gastro-totales-tabla">';
        html += '<thead><tr>';
        html += '<th>Comprobante</th><th>Hora</th><th>Cliente</th><th>Mozo</th>';
        html += '<th class="text-right">Facturado</th><th class="text-right">Cobrado</th><th class="text-right">Dif.</th>';
        columnas.forEach(function (c) {
            var med = etiquetaMedioColumna(c);
            html += '<th class="text-right" title="' + esc(med.titulo) + '">' + esc(med.etiqueta) + '</th>';
        });
        html += '<th></th></tr></thead><tbody>';

        filas.forEach(function (f) {
            var diff = Number(f.diferencia || 0);
            var diffCls = Math.abs(diff) < 0.02 ? '' : ' class="table-warning"';
            html += '<tr' + diffCls + '>';
            html += '<td>' + esc(f.codigo) + (f.es_invitacion ? ' <span class="badge badge-secondary">Inv.</span>' : '') + '</td>';
            html += '<td>' + esc(f.hora) + '</td>';
            html += '<td>' + esc(f.cliente) + '</td>';
            html += '<td>' + esc(f.mozo_nombre) + '</td>';
            html += '<td class="text-right">$' + fmt(f.total_facturado) + '</td>';
            html += '<td class="text-right">$' + fmt(f.total_cobrado) + '</td>';
            html += '<td class="text-right">$' + fmt(diff) + '</td>';
            columnas.forEach(function (c) {
                var m = (f.medios && f.medios[c.cuentacaja_id]) ? f.medios[c.cuentacaja_id] : 0;
                html += '<td class="text-right">' + (m > 0.001 ? '$' + fmt(m) : '—') + '</td>';
            });
            html += '<td class="text-nowrap">';
            if (f.venta_id) {
                var baseVer = (typeof window !== 'undefined' && window.GASTRONOMIA_FACTURA_VER_BASE) || '';
                if (baseVer) {
                    var verUrl = baseVer.replace(/\/$/, '') + '/' + f.venta_id + '/ver';
                    html += '<a href="' + esc(verUrl) + '" class="btn btn-xs btn-outline-primary" target="_blank" rel="noopener" title="Ver factura">';
                    html += '<i class="fa fa-eye"></i> Ver</a>';
                } else {
                    html += '<a href="#" class="btn btn-xs btn-outline-primary js-ver-factura-detalle" data-venta-id="' + f.venta_id + '" title="Ver detalle">';
                    html += '<i class="fa fa-eye"></i></a>';
                }
            }
            html += '</td></tr>';
        });

        html += '</tbody></table>';

        if (pag && pag.total_pages > 1) {
            html += '<div class="d-flex justify-content-center py-2 border-top bg-light gastro-grilla-paginacion-footer" data-container=""></div>';
        }

        return html;
    }

    function renderPaginacionGrillaHtml(pag, containerId) {
        if (!pag || pag.total_pages <= 1) {
            return '';
        }
        var html = '<nav class="btn-group btn-group-sm" role="navigation" aria-label="Paginación grilla" data-grilla-container="' + esc(containerId) + '">';
        if (pag.page > 1) {
            html += '<button type="button" class="btn btn-outline-secondary js-grilla-pagina" data-page="' + (pag.page - 1) + '" data-container="' + esc(containerId) + '">« Ant.</button>';
        }
        html += '<span class="btn btn-light disabled">' + pag.page + ' / ' + pag.total_pages + '</span>';
        if (pag.page < pag.total_pages) {
            html += '<button type="button" class="btn btn-outline-secondary js-grilla-pagina" data-page="' + (pag.page + 1) + '" data-container="' + esc(containerId) + '">Sig. »</button>';
        }
        html += '</nav>';
        return html;
    }

    /**
     * Alertas de control: conciliación, cuentas pendientes, enlaces útiles.
     */
    function renderAlertasControlHtml(estado) {
        if (!estado || !estado.turno_habilitado) {
            return '';
        }
        var html = '';
        var t = estado.totales_turno;
        if (t && !t.conciliacion_ok) {
            var diff = Number(t.diferencia_cobranza || 0);
            html += '<div class="alert alert-warning py-2 mb-2">';
            html += '<strong><i class="fa fa-exclamation-triangle"></i> Conciliación pendiente</strong> — ';
            html += 'Diferencia $' + fmt(Math.abs(diff)) + (diff > 0 ? ' (sobra en caja)' : ' (falta en caja)');
            html += '. Revise la grilla o concilie por medio de pago.</div>';
        } else if (t && t.conciliacion_ok) {
            html += '<div class="alert alert-success py-2 mb-2"><i class="fa fa-check"></i> ';
            html += 'Comprobantes y cobranzas del turno <strong>cuadran</strong>.</div>';
        }
        var cuentas = Number(estado.cuentas_sin_facturar || 0);
        if (cuentas > 0) {
            if (estado.es_ultimo_turno_dia) {
                html += '<div class="alert alert-danger py-2 mb-2">';
                html += '<strong>' + cuentas + ' cuenta(s) o mesa(s)</strong> pendientes en esta terminal. ';
                html += 'Al cerrar el <strong>último turno del día</strong> deben quedar todas facturadas o cerradas.</div>';
            } else {
                html += '<div class="alert alert-info py-2 mb-2">';
                html += '<strong>' + cuentas + ' cuenta(s)</strong> pendientes: pueden continuar en el próximo turno del día. ';
                html += 'Solo el último turno exige dejarlas resueltas.</div>';
            }
        }
        if (estado.url_facturas_dia) {
            html += '<div class="mb-2">';
            html += '<a href="' + esc(estado.url_facturas_dia) + '" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">';
            html += '<i class="fa fa-list"></i> Facturas del día (esta empresa)</a>';
            html += '</div>';
        }
        return html;
    }

    function renderListaParcialesHtml(lista, urlPdfParcialBase) {
        if (!lista || !lista.length) {
            return '<p class="text-muted small mb-0">Sin cierres parciales registrados.</p>';
        }
        var base = urlPdfParcialBase || '';
        var html = '<ul class="list-group list-group-flush">';
        lista.forEach(function (p) {
            html += '<li class="list-group-item d-flex justify-content-between align-items-center py-2">';
            html += '<span><strong>#' + esc(p.numero_parcial) + '</strong> — ' + esc(p.fecha || '');
            if (p.solo_totales_mozo) {
                html += ' <span class="badge badge-info">Solo mozo</span>';
            }
            html += ' · $' + fmt(p.total) + '</span>';
            if (p.id && base) {
                html += '<a href="' + esc(base + '/' + p.id + '/comprobante?inline=1') + '" class="btn btn-xs btn-outline-secondary" target="_blank" rel="noopener">';
                html += '<i class="fa fa-file-pdf"></i> PDF</a>';
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    function renderTotalesHtml(totales, titulo, opciones) {
        if (!totales) {
            return '';
        }
        var totalVentas = totales.total_ventas != null ? totales.total_ventas : totales.total_general;
        var html = '<div class="' + CLS_PANEL + ' gastro-totales-bloque border rounded p-3 mb-3 bg-white">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">';
        html += '<span class="h6 font-weight-bold mb-0">' + esc(titulo) + '</span>';
        html += '<span>Total facturado: <strong class="gastro-totales-monto">$' + fmt(totalVentas) + '</strong></span>';
        html += '</div>';
        var optsConc = (opciones && opciones.conciliarMedios) ? { habilitar: true } : null;
        var compacto = opciones && opciones.soloMozo;
        var ocultarMozo = opciones && opciones.ocultarDetalleMozo;

        if (!compacto) {
            html += renderConciliacionHtml(totales);
        }
        if (!ocultarMozo) {
            html += '<h6 class="font-weight-bold mt-1 mb-2">Detalle por mozo</h6>';
            html += renderPorMozoHtml(totales.por_mozo, optsConc);
        }
        if (!compacto) {
            html += renderTotalMediosPagoFinalHtml(totales, optsConc);
        }
        html += '</div>';

        return html;
    }

    function renderTotalesResumenHtml(bloques) {
        if (!bloques || !bloques.length) {
            return '';
        }
        if (bloques.length === 1) {
            return bloques[0].html;
        }
        var html = '<div class="row gastro-totales-resumen">';
        bloques.forEach(function (b) {
            html += '<div class="col-lg-6 mb-3 mb-lg-0">' + b.html + '</div>';
        });
        html += '</div>';
        return html;
    }

    window.GastronomiaTotalesTurnoRender = {
        fmt: fmt,
        esc: esc,
        renderConciliacionHtml: renderConciliacionHtml,
        renderTotalesHtml: renderTotalesHtml,
        renderTotalesResumenHtml: renderTotalesResumenHtml,
        renderGrillaConciliacionHtml: renderGrillaConciliacionHtml,
        renderGrillaPlaceholderHtml: renderGrillaPlaceholderHtml,
        renderPaginacionGrillaHtml: renderPaginacionGrillaHtml,
        renderListaParcialesHtml: renderListaParcialesHtml,
        renderAlertasControlHtml: renderAlertasControlHtml,
    };
})();
