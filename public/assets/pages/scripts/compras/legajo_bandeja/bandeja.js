(function ($) {
    'use strict';

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '';
    }

    function abrirPdf(url) {
        if (!url) {
            return;
        }
        window.open(url, '_blank', 'noopener');
    }

    function renderHistoria(rows) {
        var $tb = $('#tablaBandejaHistoria tbody').empty();
        if (!rows || !rows.length) {
            $tb.append('<tr><td colspan="5" class="text-center text-muted">Sin movimientos de sector.</td></tr>');
            return;
        }
        rows.forEach(function (r) {
            var sec = (r.sector_legajocompras && r.sector_legajocompras.nombre) ? r.sector_legajocompras.nombre : '';
            var usr = (r.usuarios && r.usuarios.nombre) ? r.usuarios.nombre : '';
            var f = r.fecha ? String(r.fecha).replace('T', ' ').substring(0, 19) : '';
            $tb.append(
                '<tr><td>' + esc(f) + '</td><td>' + esc(sec) + '</td><td>' + esc(r.observacion || '') +
                '</td><td>' + esc(r.leyenda || '') + '</td><td>' + esc(usr) + '</td></tr>'
            );
        });
    }

    function mostrarPdf($iframe, url) {
        if (!$iframe.length) {
            return;
        }
        $iframe.removeAttr('srcdoc');
        $iframe.attr('src', url || 'about:blank');
    }

    function mostrarSinPdf($iframe, urlCxp) {
        if (!$iframe.length) {
            return;
        }
        var html = '<div style="font-family:sans-serif;padding:24px;color:#333;">'
            + '<p style="margin:0 0 12px 0;">Este comprobante no tiene PDF en el legajo (ingresó por importación a CxP).</p>';
        if (urlCxp) {
            html += '<p style="margin:0;"><a href="' + esc(urlCxp) + '" target="_blank" rel="noopener">Abrir en Cuentas a pagar</a></p>';
        }
        html += '</div>';
        $iframe.attr('srcdoc', html);
        $iframe.removeAttr('src');
    }

    function mapaComAsignadaA(paquete) {
        var facs = {};
        ((paquete && paquete.facturas) || []).forEach(function (f) {
            facs[String(f.id)] = f.etiqueta || ('#' + f.id);
        });
        ((paquete && paquete.comprobantes) || []).forEach(function (c) {
            facs['cp-' + c.id] = c.etiqueta || ('CP #' + c.id);
        });
        var out = {};
        var asignadas = (paquete && paquete.asignadas) || {};
        Object.keys(asignadas).forEach(function (preId) {
            var label = facs[String(preId)] || ('#' + preId);
            (asignadas[preId] || []).forEach(function (id) {
                if (id > 0) {
                    out[String(id)] = label;
                }
            });
        });
        return out;
    }

    function chipComFactura(f) {
        var asig = (f && f.coms_asignadas) || [];
        if (asig.length) {
            var labels = asig.map(function (c) {
                return c.numerorecepcion ? ('COM ' + c.numerorecepcion) : (c.documento || ('#' + c.id));
            });
            return '<span class="badge badge-primary" title="' + esc(labels.join(', ')) + '">'
                + esc(labels.length === 1 ? labels[0] : ('COM ×' + labels.length))
                + '</span>';
        }
        var sug = f && f.com_sugerida;
        if (sug) {
            var lab = sug.numerorecepcion ? ('COM ' + sug.numerorecepcion) : (sug.documento || ('#' + sug.id));
            return '<span class="badge badge-info" title="Sugerida: ' + esc(sug.motivo_label || '') + '">'
                + esc(lab) + ' ¿?</span>';
        }
        var tipo = String((f && f.tipo) || 'FC').toUpperCase();
        var exige = !f || (f.exige_com !== false && tipo !== 'NC' && tipo !== 'ND');
        if (!exige) {
            return '<span class="badge badge-light border text-muted">no exige</span>';
        }
        if (f && f.pendiente_entrega) {
            return '<span class="badge badge-secondary" title="Mercadería pendiente de entrega">pend. entrega</span>';
        }
        return '<span class="badge badge-warning">sin COM</span>';
    }

    function celdaAsignacionCom(c) {
        if (c && c.asignada_a && c.asignada_a.etiqueta) {
            return '<span class="badge badge-primary">' + esc(c.asignada_a.etiqueta) + '</span>';
        }
        if (c && c.sugerida_para && c.sugerida_para.etiqueta) {
            return '<span class="badge badge-info" title="' + esc(c.sugerida_para.motivo_label || '') + '">'
                + esc(c.sugerida_para.etiqueta) + ' ¿?</span>'
                + (c.numerofactura
                    ? '<br><small class="text-muted">anotada ' + esc(c.numerofactura) + '</small>'
                    : '');
        }
        if (c && c.numerofactura) {
            return '<span class="text-muted">—</span><br><small class="text-muted">anotada '
                + esc(c.numerofactura) + '</small>';
        }
        return '—';
    }

    function fmtNetoCom(c) {
        if (c == null || c.neto == null || c.neto === '') {
            return '—';
        }
        return fmtMonto(c.neto);
    }

    function renderComs(paquete) {
        var $tb = $('#tablaBandejaComs tbody').empty();
        var $pdf = $('#bandejaComPdf');
        var coms = (paquete && paquete.coms) || [];
        mostrarPdf($pdf, '');
        if (!coms.length) {
            $tb.append('<tr><td colspan="4" class="text-center text-muted">No hay COM en este legajo.</td></tr>');
            return;
        }
        coms.forEach(function (c, i) {
            var $tr = $('<tr class="js-bandeja-pdf-row" style="cursor:pointer;"></tr>');
            $tr.attr('data-url-pdf', c.url_pdf || '');
            $tr.append('<td>' + esc(c.documento || ('#' + c.id))
                + (c.fecha ? '<br><small class="text-muted">' + esc(c.fecha) + '</small>' : '')
                + '</td>');
            $tr.append('<td class="text-right">' + fmtNetoCom(c) + '</td>');
            $tr.append('<td>' + esc(c.estado || '') + '</td>');
            $tr.append('<td>' + celdaAsignacionCom(c) + '</td>');
            if (i === 0) {
                $tr.addClass('table-info');
            }
            $tb.append($tr);
        });
        if (coms[0] && coms[0].url_pdf) {
            mostrarPdf($pdf, coms[0].url_pdf);
        }
    }

    function fmtMonto(n, moneda) {
        if (n == null || n === '') {
            return '—';
        }
        var num = Number(n);
        if (isNaN(num)) {
            return '—';
        }
        var parts = num.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var s = parts[0] + ',' + parts[1];
        return moneda ? (s + ' ' + moneda) : s;
    }

    function claseEstadoPago(estado) {
        var e = String(estado || '').toLowerCase().replace(/\s+/g, '-');
        if (!e) {
            return 'bandeja-pagos-estado';
        }
        return 'bandeja-pagos-estado bandeja-pagos-estado-' + e;
    }

    function chipPagoFactura(f) {
        if (f && f.tiene_pagos) {
            var n = ((f.pagos) || []).length;
            return '<span class="bandeja-pagos-chip bandeja-pagos-chip-ok">' + n + ' OP</span>';
        }
        if (f && f.cargado_cxp) {
            return '<span class="bandeja-pagos-chip bandeja-pagos-chip-warn">sin pago</span>';
        }
        return '<span class="bandeja-pagos-chip bandeja-pagos-chip-mute">—</span>';
    }

    function renderFacturas(paquete) {
        var $tb = $('#tablaBandejaFacturas tbody').empty();
        var $pdf = $('#bandejaFacturaPdf');
        var facs = (paquete && paquete.facturas) || [];
        mostrarPdf($pdf, '');
        if (!facs.length) {
            $tb.append('<tr><td colspan="6" class="text-center text-muted">No hay facturas ni comprobantes en este legajo.</td></tr>');
            return;
        }
        facs.forEach(function (f, i) {
            var origen = f.origen_label || ((f.origen === 'anita') ? 'Scan Anita (manual, no IA)' : (f.estado || 'Precarga'));
            var estadoHtml = f.cargado_cxp
                ? '<span class="badge badge-info">cargada</span>'
                : (f.pendiente_entrega
                    ? '<span class="badge badge-secondary" title="Retenida hasta que llegue la mercadería">pend. entrega</span>'
                    : '<span class="badge badge-warning">pendiente</span>');
            if (f.cargado_cxp_fuera_legajo) {
                estadoHtml = '<span class="badge badge-danger" title="' + esc(f.cargado_cxp_detalle || '')
                    + '">cargada fuera de este legajo</span>';
            }
            if (f.url_comprobante) {
                estadoHtml += ' <a href="' + esc(f.url_comprobante) + '" target="_blank" rel="noopener" class="js-bandeja-abrir-cxp" title="Abrir en CxP">CxP</a>';
            }
            var etiqueta = esc(f.etiqueta || ('#' + f.id));
            var etiquetaHtml = f.url_pdf
                ? '<a href="' + esc(f.url_pdf) + '" class="text-primary" target="_blank" rel="noopener" title="Abrir PDF en pantalla completa">' + etiqueta + '</a>'
                : etiqueta;
            var pagoHtml = chipPagoFactura(f);
            if (f.tiene_pagos) {
                pagoHtml += ' <button type="button" class="bandeja-fac-ver-pagos js-bandeja-ir-pagos" data-fac-key="' + esc(String(f.id)) + '">ver</button>';
            }
            var $tr = $('<tr class="js-bandeja-pdf-row" style="cursor:pointer;"></tr>');
            $tr.attr('data-url-pdf', f.url_pdf || '');
            $tr.attr('data-url-cxp', f.url_comprobante || '');
            $tr.attr('data-fac-key', String(f.id));
            $tr.append('<td>' + etiquetaHtml + escaneosDeFactura(f) + '</td>');
            $tr.append('<td>' + esc(f.fecha || '') + '</td>');
            $tr.append('<td>' + esc(origen) + '</td>');
            $tr.append('<td>' + chipComFactura(f) + '</td>');
            $tr.append('<td>' + estadoHtml + '</td>');
            $tr.append('<td>' + pagoHtml + '</td>');
            if (i === 0) {
                $tr.addClass('table-info');
            }
            $tb.append($tr);
        });
        var primero = facs[0] || {};
        if (primero.url_pdf) {
            mostrarPdf($pdf, primero.url_pdf);
        } else {
            mostrarSinPdf($pdf, primero.url_comprobante || '');
        }
    }

    var pagosToolEstado = {
        paquete: null,
        activo: null
    };

    function facturasConContextoPago(paquete) {
        var facs = (paquete && paquete.facturas) || [];
        return facs.filter(function (f) {
            return !!(f.cargado_cxp || f.tiene_pagos || f.comprobante_proveedor_id);
        });
    }

    function renderPagosDetalle(fac) {
        var $panel = $('#bandejaPagosPanelBody');
        if (!$panel.length) {
            return;
        }
        if (!fac) {
            $panel.html(
                '<div class="bandeja-pagos-empty">' +
                '<div class="empty-ico"><i class="fa fa-hand-pointer-o"></i></div>' +
                '<p class="mb-0">Seleccioná una factura para ver sus órdenes de pago.</p></div>'
            );
            return;
        }
        var pagos = fac.pagos || [];
        var head =
            '<div class="mb-3">' +
            '<div class="font-weight-bold" style="font-size:1rem;">' + esc(fac.etiqueta || ('#' + fac.id)) + '</div>' +
            '<div class="text-muted small mt-1">' +
            (fac.fecha ? esc(fac.fecha) + ' · ' : '') +
            'Total ' + fmtMonto(fac.total) +
            (fac.total_pagado != null ? ' · Pagado ' + fmtMonto(fac.total_pagado) : '') +
            (fac.saldo != null ? ' · Saldo ' + fmtMonto(fac.saldo) : '') +
            '</div></div>';

        if (!pagos.length) {
            $panel.html(
                head +
                '<div class="bandeja-pagos-empty" style="padding-top:1.25rem;">' +
                '<div class="empty-ico"><i class="fa fa-inbox"></i></div>' +
                '<p class="mb-1 font-weight-bold" style="color:#1a2332;">Sin órdenes de pago</p>' +
                '<p class="mb-0 small">Esta factura todavía no tiene aplicaciones de pago registradas.</p></div>'
            );
            return;
        }

        var html = head;
        pagos.forEach(function (op) {
            var estado = esc(op.estado || '');
            html +=
                '<div class="bandeja-pagos-op">' +
                '<div class="bandeja-pagos-op-top">' +
                '<div>' +
                '<a class="bandeja-pagos-op-etiqueta" href="' + esc(op.url || '#') + '" target="_blank" rel="noopener">' +
                esc(op.etiqueta || ('OP #' + op.id)) + '</a>' +
                (estado ? ' <span class="' + claseEstadoPago(op.estado) + '">' + estado + '</span>' : '') +
                '<div class="bandeja-pagos-op-meta">' +
                (op.fecha ? '<span><i class="fa fa-calendar-o"></i> ' + esc(op.fecha) + '</span>' : '') +
                (op.monto_aplicado != null
                    ? '<span><i class="fa fa-check-circle-o"></i> Aplicado ' + fmtMonto(op.monto_aplicado, op.moneda) + '</span>'
                    : (op.monto != null ? '<span><i class="fa fa-money"></i> OP ' + fmtMonto(op.monto, op.moneda) + '</span>' : '')) +
                '</div></div>' +
                '<div class="bandeja-pagos-op-actions">' +
                (op.url_pdf
                    ? '<a class="btn btn-sm btn-outline-danger" href="' + esc(op.url_pdf) + '" target="_blank" rel="noopener" title="PDF de la OP"><i class="fa fa-file-pdf-o"></i></a>'
                    : '') +
                (op.url
                    ? '<a class="btn btn-sm btn-outline-success" href="' + esc(op.url) + '" target="_blank" rel="noopener" title="Abrir orden de pago"><i class="fa fa-external-link"></i> Abrir</a>'
                    : '') +
                '</div></div></div>';
        });
        $panel.html(html);
    }

    function renderPagosLista(facs, activoKey) {
        var $lista = $('#bandejaPagosListaBody');
        if (!$lista.length) {
            return;
        }
        $lista.empty();
        if (!facs.length) {
            $lista.append(
                '<div class="bandeja-pagos-empty">' +
                '<div class="empty-ico"><i class="fa fa-folder-open-o"></i></div>' +
                '<p class="mb-0">No hay facturas cargadas en CxP en este legajo.</p></div>'
            );
            return;
        }
        facs.forEach(function (f) {
            var key = String(f.id);
            var $btn = $('<button type="button" class="bandeja-pagos-fac js-bandeja-pago-fac"></button>');
            $btn.attr('data-fac-key', key);
            if (key === String(activoKey)) {
                $btn.addClass('is-active');
            }
            $btn.append(
                '<div class="bandeja-pagos-fac-top">' +
                '<div class="bandeja-pagos-fac-titulo">' + esc(f.etiqueta || ('#' + f.id)) + '</div>' +
                chipPagoFactura(f) +
                '</div>' +
                '<div class="bandeja-pagos-fac-meta">' +
                esc(f.fecha || 'Sin fecha') +
                (f.total != null ? ' · ' + fmtMonto(f.total) : '') +
                (f.total_pagado != null ? ' · pagado ' + fmtMonto(f.total_pagado) : '') +
                '</div>'
            );
            $lista.append($btn);
        });
    }

    function seleccionarFacturaPago(facKey) {
        var paquete = pagosToolEstado.paquete;
        var facs = facturasConContextoPago(paquete);
        var activo = facs.find(function (f) { return String(f.id) === String(facKey); }) || facs[0] || null;
        pagosToolEstado.activo = activo ? String(activo.id) : null;
        renderPagosLista(facs, pagosToolEstado.activo);
        renderPagosDetalle(activo);
    }

    function renderPagos(paquete) {
        var $box = $('#bandejaLegajoPagos').empty();
        var pagos = (paquete && paquete.pagos) || [];
        var facs = facturasConContextoPago(paquete);
        var nOp = pagos.length;
        var nFacConPago = facs.filter(function (f) { return f.tiene_pagos; }).length;
        var totalAplicado = 0;
        var tieneAplicado = false;
        pagos.forEach(function (op) {
            if (op.monto_aplicado_legajo != null) {
                totalAplicado += Number(op.monto_aplicado_legajo) || 0;
                tieneAplicado = true;
            }
        });
        $('#bandejaLegajoNPago').text(nOp);
        $('#bandeja-tab-pagos-item').show();

        pagosToolEstado.paquete = paquete;

        if (!facs.length && !nOp) {
            $box.append(
                '<div class="bandeja-pagos-empty">' +
                '<div class="empty-ico"><i class="fa fa-money"></i></div>' +
                '<p class="mb-1 font-weight-bold" style="color:#1a2332;">Todavía no hay pagos</p>' +
                '<p class="mb-0">Cuando las facturas del legajo se paguen, las órdenes de pago aparecerán acá con monto aplicado y PDF.</p></div>'
            );
            return;
        }

        var html =
            '<div class="bandeja-pagos-kpis">' +
            '<div class="bandeja-pagos-kpi"><span class="kpi-label">Órdenes de pago</span>' +
            '<div class="kpi-valor">' + nOp + '</div>' +
            '<div class="kpi-hint">' + nFacConPago + ' factura' + (nFacConPago === 1 ? '' : 's') + ' con pago</div></div>' +
            '<div class="bandeja-pagos-kpi"><span class="kpi-label">Aplicado en el legajo</span>' +
            '<div class="kpi-valor">' + (tieneAplicado ? fmtMonto(totalAplicado) : '—') + '</div>' +
            '<div class="kpi-hint">Suma de aplicaciones a facturas</div></div>' +
            '<div class="bandeja-pagos-kpi"><span class="kpi-label">Facturas en CxP</span>' +
            '<div class="kpi-valor">' + facs.length + '</div>' +
            '<div class="kpi-hint">Con seguimiento de pago</div></div>' +
            '</div>' +
            '<div class="bandeja-pagos-layout">' +
            '<div class="bandeja-pagos-lista">' +
            '<div class="bandeja-pagos-lista-head"><i class="fa fa-files-o"></i> Facturas del legajo</div>' +
            '<div class="bandeja-pagos-lista-body" id="bandejaPagosListaBody"></div></div>' +
            '<div class="bandeja-pagos-panel">' +
            '<div class="bandeja-pagos-panel-head"><i class="fa fa-credit-card"></i> Detalle de pagos</div>' +
            '<div class="bandeja-pagos-panel-body" id="bandejaPagosPanelBody"></div></div>' +
            '</div>';
        $box.html(html);

        var preferida = facs.find(function (f) { return f.tiene_pagos; }) || facs[0] || null;
        if (pagosToolEstado.activo) {
            var keep = facs.find(function (f) { return String(f.id) === String(pagosToolEstado.activo); });
            if (keep) {
                preferida = keep;
            }
        }
        seleccionarFacturaPago(preferida ? preferida.id : null);
    }

    function activarTabLegajo(tab) {
        var id = tab === 'coms' ? '#bandeja-tab-coms' : (tab === 'pagos' ? '#bandeja-tab-pagos' : '#bandeja-tab-facturas');
        $(id).tab('show');
    }

    function renderLegajo(paquete, tab) {
        $('#bandejaLegajoNFac').text(((paquete && paquete.facturas) || []).length);
        $('#bandejaLegajoNCom').text(((paquete && paquete.coms) || []).length);
        var $oc = $('#bandejaLegajoOc');
        if (paquete && paquete.url_oc) {
            $oc.attr('href', paquete.url_oc).show();
        } else {
            $oc.hide();
        }
        pagosToolEstado.activo = null;
        renderFacturas(paquete);
        renderComs(paquete);
        renderPagos(paquete);
        activarTabLegajo(tab || 'facturas');
    }

    var asignarEstado = {
        mapa: {},
        activo: null,
        coms: [],
        facs: [],
        urlCorregirTipo: '',
        tiposOpciones: [],
        permiteAsignarCom: true,
        mensajeBloqueaCom: ''
    };

    function badgeComDoc(fac) {
        if (fac && fac.cargado_cxp) {
            return '<span class="badge badge-success">en CxP</span>';
        }
        var tipo = String(fac.tipo || 'FC').toUpperCase();
        var exige = fac.exige_com !== false && tipo !== 'NC' && tipo !== 'ND';
        if (!exige) {
            var label = tipo === 'ND' ? 'ND sin COM' : (tipo === 'NC' ? 'NC sin COM' : 'no exige COM');
            return '<span class="badge badge-light border text-muted">' + label + '</span>';
        }
        var ids = asignarEstado.mapa[String(fac.id)] || [];
        if (ids.length) {
            return '<span class="badge bandeja-asig-badge-ok">COM ×' + ids.length + '</span>';
        }
        if (fac.com_sugerida && fac.com_sugerida.id) {
            return '<span class="badge bandeja-asig-badge-sug">sugerida</span>';
        }
        if (fac.pendiente_entrega) {
            return '<span class="badge badge-secondary">pend. entrega</span>';
        }
        return '<span class="badge bandeja-asig-badge-sin">sin COM</span>';
    }

    function renderAsignarComsActivo() {
        var $coms = $('#bandejaAsignarComs').empty();
        var activo = asignarEstado.activo;
        if (!activo) {
            $coms.append('<p class="text-muted mb-0 p-3 small">Seleccioná un comprobante a la izquierda.</p>');
            return;
        }
        var fac = asignarEstado.facs.find(function (f) { return String(f.id) === String(activo); });
        var $head = $('<div class="bandeja-asig-panel-head"></div>');
        var tipoActual = String((fac && (fac.tipo_abrev || fac.tipo_label || fac.tipo)) || 'FC').toUpperCase();
        if (fac && (fac.origen || 'precarga') === 'precarga' && /^\d+$/.test(String(fac.id))) {
            var opciones = (asignarEstado.tiposOpciones && asignarEstado.tiposOpciones.length)
                ? asignarEstado.tiposOpciones.slice()
                : [
                    { value: 'FC', label: 'FC — Factura' },
                    { value: 'NC', label: 'NC — Nota de crédito (no exige COM)' },
                    { value: 'ND', label: 'ND — Nota de débito (no exige COM)' }
                ];
            var tieneActual = opciones.some(function (opt) {
                return String(opt.value || '').toUpperCase() === tipoActual;
            });
            if (tipoActual && !tieneActual) {
                opciones.unshift({ value: tipoActual, label: tipoActual });
            }
            var optsHtml = opciones.map(function (opt) {
                var val = String(opt.value || '').toUpperCase();
                var lab = opt.label || val;
                return '<option value="' + esc(val) + '"' + (val === tipoActual ? ' selected' : '') + '>' + esc(lab) + '</option>';
            }).join('');
            $head.append(
                '<div class="form-group">' +
                '<label class="small mb-1" for="bandejaAsigTipoDoc">Tipo</label>' +
                '<select id="bandejaAsigTipoDoc" class="form-control form-control-sm js-bandeja-asig-tipo" data-precarga-id="' + esc(String(fac.id)) + '">' +
                optsHtml +
                '</select></div>'
            );
        }
        if (fac && fac.cargado_cxp) {
            $head.append('<p class="text-success small mb-2 mb-md-1">Ya está cargado en CxP.</p>');
        }
        if (fac && fac.pendiente_entrega) {
            $head.append(
                '<p class="text-muted small mb-2">' +
                'Retenida por mercadería pendiente. Liberála para asignar COM y enviarla a CxP.' +
                '</p>' +
                '<button type="button" class="btn btn-sm btn-outline-warning mb-2 js-bandeja-liberar-pendiente-entrega" data-precarga-id="' +
                esc(String(fac.id)) + '">Liberar pendiente de entrega</button>'
            );
        }
        var tipoFac = String((fac && fac.tipo) || 'FC').toUpperCase();
        var exige = !fac || (fac.exige_com !== false && tipoFac !== 'NC' && tipoFac !== 'ND');
        if (!exige) {
            var textoTipo = tipoFac === 'ND' ? 'nota de débito' : (tipoFac === 'NC' ? 'nota de crédito' : 'este tipo');
            $head.append('<p class="text-muted mb-1 small">Es ' + textoTipo + ': no exige COM.</p>');
        }
        if (asignarEstado.permiteAsignarCom === false && asignarEstado.mensajeBloqueaCom) {
            $head.append(
                '<div class="alert alert-warning py-2 small mb-2">' +
                esc(asignarEstado.mensajeBloqueaCom) +
                '</div>'
            );
        }
        if (fac && (fac.subtotal != null || fac.total != null)) {
            var imp = '<div class="bandeja-asig-importes">';
            if (fac.subtotal != null) {
                imp += '<span>Neto <strong>' + esc(fmtMonto(fac.subtotal)) + '</strong></span>';
            }
            if (fac.total != null && (fac.subtotal == null || Math.abs(Number(fac.subtotal) - Number(fac.total)) > 0.01)) {
                imp += '<span>Total <strong>' + esc(fmtMonto(fac.total)) + '</strong></span>';
            }
            imp += '</div>';
            $head.append(imp);
        }
        $coms.append($head);

        if (!asignarEstado.coms.length) {
            $coms.append('<p class="text-muted mb-0 p-3 small">No hay COM confirmada para asignar.</p>');
            return;
        }

        var idsAsig = asignarEstado.mapa[String(activo)] || [];
        var ocupadasPorOtra = comIdsOcupadasPorOtraFactura(activo);
        var sugeridaId = fac && fac.com_sugerida && fac.com_sugerida.id ? Number(fac.com_sugerida.id) : 0;
        var sugeridaMotivo = (fac && fac.com_sugerida && fac.com_sugerida.motivo_label)
            || (fac && fac.com_sugerida && fac.com_sugerida.motivo)
            || '';

        var filas = asignarEstado.coms.map(function (c) {
            var checked = idsAsig.indexOf(c.id) !== -1 || idsAsig.indexOf(String(c.id)) !== -1
                || idsAsig.indexOf(Number(c.id)) !== -1;
            var ocupada = ocupadasPorOtra[String(c.id)];
            var esSugerida = sugeridaId > 0 && Number(c.id) === sugeridaId;
            var sugeridaOtra = !checked && !ocupada && c.sugerida_para
                && String(c.sugerida_para.id) !== String(activo)
                ? c.sugerida_para.etiqueta
                : '';
            var bloqueadaAnticipada = asignarEstado.permiteAsignarCom === false;
            var bloqueada = !!(!checked && (ocupada || c.facturada_en_cxp)) || bloqueadaAnticipada;
            var orden = checked ? 0 : (esSugerida ? 1 : (bloqueada ? 3 : 2));
            return {
                c: c,
                checked: checked && !bloqueadaAnticipada,
                ocupada: ocupada,
                esSugerida: esSugerida && !bloqueadaAnticipada,
                sugeridaOtra: sugeridaOtra,
                bloqueada: bloqueada,
                orden: orden
            };
        });
        filas.sort(function (a, b) { return a.orden - b.orden; });

        var disponibles = filas.filter(function (r) { return !r.bloqueada; });
        var bloqueadas = filas.filter(function (r) { return r.bloqueada; });
        var sugeridaRow = disponibles.find(function (r) { return r.esSugerida; });

        var $list = $('<div class="bandeja-asig-com-list"></div>');

        if (sugeridaRow) {
            var matchTxt = 'Coincide con esta factura';
            if (sugeridaRow.c.numerofactura) {
                matchTxt = 'Coincide por nº de factura <strong>' + esc(sugeridaRow.c.numerofactura) + '</strong>';
            } else if (sugeridaMotivo) {
                matchTxt = esc(sugeridaMotivo);
            }
            $list.append('<div class="bandeja-asig-match">' + matchTxt + '</div>');
        }

        if (disponibles.length) {
            $list.append('<div class="bandeja-asig-grupo">Para esta factura</div>');
            disponibles.forEach(function (row) {
                $list.append(htmlFilaComAsignacion(row));
            });
        }

        if (bloqueadas.length) {
            $list.append('<div class="bandeja-asig-grupo" style="margin-top:0.85rem">Ya tomadas por otra factura</div>');
            bloqueadas.forEach(function (row) {
                $list.append(htmlFilaComAsignacion(row));
            });
        }

        $coms.append($list);
        if (!disponibles.length) {
            $coms.append('<p class="text-muted mb-0 px-3 pb-2 small">Las COM del legajo ya están asignadas a otras facturas.</p>');
        }
    }

    function htmlFilaComAsignacion(row) {
        var c = row.c;
        var badge = '';
        if (row.esSugerida) {
            badge = '<span class="badge bandeja-asig-badge-sug">sugerida</span>';
        } else if (row.checked) {
            badge = '<span class="badge bandeja-asig-badge-ok">asignada</span>';
        }

        var meta = [];
        if (!row.bloqueada) {
            if (c.fecha) {
                meta.push('<span><b>Fecha</b>' + esc(c.fecha) + '</span>');
            }
            if (c.neto != null) {
                meta.push('<span><b>Neto</b>' + esc(fmtMonto(c.neto)) + '</span>');
            }
            if (c.numerofactura) {
                meta.push('<span><b>Factura</b>' + esc(c.numerofactura) + '</span>');
            }
        }

        var tomada = '';
        if (row.ocupada) {
            tomada = '<span class="bandeja-asig-tomada">· ' + esc(row.ocupada) + '</span>';
        } else if (row.sugeridaOtra) {
            tomada = '<span class="bandeja-asig-tomada">· sugerida p/ ' + esc(row.sugeridaOtra) + '</span>';
        } else if (c.facturada_en_cxp) {
            tomada = '<span class="bandeja-asig-tomada">· en CxP</span>';
        }

        var clases = 'bandeja-asig-com-row';
        if (row.bloqueada) {
            clases += ' is-bloqueada';
        }
        if (row.checked) {
            clases += ' is-checked';
        }
        if (row.esSugerida) {
            clases += ' is-sugerida';
        }

        return '<div class="' + clases + '">' +
            '<input class="form-check-input js-bandeja-asig-com" type="checkbox" data-com-id="' + c.id + '" id="ban_com_' + c.id + '"' +
            (row.checked ? ' checked' : '') + (row.bloqueada ? ' disabled' : '') + '>' +
            '<label class="bandeja-asig-com-body mb-0" for="ban_com_' + c.id + '">' +
            '<span class="bandeja-asig-com-title">' + esc(c.documento) + badge + tomada + '</span>' +
            (meta.length ? '<span class="bandeja-asig-com-meta">' + meta.join('') + '</span>' : '') +
            '</label></div>';
    }

    function etiquetaFacturaAsignacion(facId) {
        var fac = asignarEstado.facs.find(function (f) { return String(f.id) === String(facId); });
        return fac ? (fac.etiqueta || ('#' + facId)) : ('#' + facId);
    }

    function comIdsOcupadasPorOtraFactura(facActivaId) {
        var ocupadas = {};
        Object.keys(asignarEstado.mapa).forEach(function (preId) {
            if (String(preId) === String(facActivaId)) {
                return;
            }
            var ids = asignarEstado.mapa[preId] || [];
            ids.forEach(function (id) {
                if (id > 0) {
                    ocupadas[String(id)] = etiquetaFacturaAsignacion(preId);
                }
            });
        });
        return ocupadas;
    }

    function itemAsignarDoc(f) {
        var activo = String(asignarEstado.activo) === String(f.id);
        var tipoRaw = String(f.tipo_label || f.tipo_abrev || f.tipo || '').trim();
        var etiqueta = String(f.etiqueta || ('#' + f.id));
        // Evitar "FGA" + "FGA A 0004-…" cuando la etiqueta ya arranca con el tipo.
        var tipoYaEnEtiqueta = tipoRaw && etiqueta.toUpperCase().indexOf(tipoRaw.toUpperCase()) === 0;
        var tipoBadge = (!tipoYaEnEtiqueta && tipoRaw)
            ? '<span class="badge badge-light border text-dark mr-1">' + esc(tipoRaw) + '</span>'
            : '';
        var extra = f.cargado_cxp ? ' text-muted' : '';
        var meta = [];
        if (f.fecha) {
            meta.push(esc(f.fecha));
        }
        if (f.origen_label) {
            meta.push(esc(f.origen_label));
        }
        return '<a href="#" class="list-group-item list-group-item-action js-bandeja-asig-doc' + (activo ? ' active' : '') + extra + '" data-doc-id="' + esc(String(f.id)) + '">' +
            '<div class="d-flex justify-content-between align-items-start">' +
            '<div class="pr-2 min-w-0">' +
            '<div class="bandeja-asig-doc-main">' + tipoBadge + esc(etiqueta) + '</div>' +
            (meta.length ? '<div class="bandeja-asig-doc-meta">' + meta.join(' · ') + '</div>' : '') +
            '</div><div class="ml-1 text-right flex-shrink-0">' + badgeComDoc(f) + botonDescartarScan(f) + '</div></div></a>';
    }

    // Solo los scans de Anita se pueden descartar del legajo: una precarga cargada a mano o un
    // comprobante ya en CxP se corrigen por su propio camino.
    function documentoAnitaDeFactura(f) {
        if (!f || f.cargado_cxp) {
            return 0;
        }
        var m = /^anita-(\d+)$/i.exec(String(f.id));
        return m ? parseInt(m[1], 10) : 0;
    }

    function botonDescartarScan(f) {
        var docId = documentoAnitaDeFactura(f);
        if (!docId) {
            return '';
        }
        return '<button type="button" class="btn btn-link btn-sm p-0 ml-2 text-danger js-bandeja-descartar-scan"'
            + ' data-documento-id="' + docId + '" data-etiqueta="' + esc(f.etiqueta || ('#' + f.id)) + '"'
            + ' title="Descartar este escaneo del legajo"><i class="fa fa-times-circle"></i></button>';
    }

    // Los escaneos de Anita de esta factura. Si hay más de uno hay que verlo: uno de los dos suele
    // ser un escaneo repetido o de otra factura, y hasta ahora quedaba tapado por el primero.
    function escaneosDeFactura(f) {
        var scans = (f && f.scans_anita) || [];
        if (!scans.length) {
            return '';
        }
        var varios = scans.length > 1;
        var html = '<div class="bandeja-fac-scans small mt-1">';
        html += varios
            ? '<span class="badge badge-warning mr-1" title="Esta factura tiene más de un escaneo en Anita. Revise cuál corresponde y descarte el que no.">'
                + '<i class="fa fa-clone"></i> ' + scans.length + ' escaneos</span>'
            : '<span class="text-muted mr-1">escaneo:</span>';
        scans.forEach(function (s, idx) {
            var titulo = 'Ver el escaneo Anita #' + s.documento_id + (s.fecha ? ' del ' + s.fecha : '');
            html += '<span class="mr-2 text-nowrap">';
            html += '<a href="#" class="js-bandeja-ver-scan" data-url-pdf="' + esc(s.url_pdf || '') + '"'
                + ' title="' + esc(titulo) + '">' + (varios ? ('#' + (idx + 1)) : 'ver') + '</a>';
            if (!f.cargado_cxp) {
                html += '<button type="button" class="btn btn-link btn-sm p-0 ml-1 text-danger js-bandeja-descartar-scan"'
                    + ' data-documento-id="' + esc(String(s.documento_id)) + '"'
                    + ' data-etiqueta="' + esc((f.etiqueta || '') + ' (escaneo #' + s.documento_id + ')') + '"'
                    + ' title="Descartar este escaneo del legajo"><i class="fa fa-times-circle"></i></button>';
            }
            html += '</span>';
        });

        return html + '</div>';
    }

    function renderScansDescartados(paquete) {
        var $wrap = $('#bandejaScansDescartados');
        if (!$wrap.length) {
            return;
        }
        var lista = (paquete && paquete.scans_descartados) || [];
        if (!lista.length) {
            $wrap.empty().hide();
            return;
        }
        var html = '<div class="alert alert-warning py-2 mb-2">'
            + '<div class="font-weight-bold small mb-1">'
            + '<i class="fa fa-eye-slash"></i> Escaneos descartados de este legajo (' + lista.length + ')</div>';
        lista.forEach(function (d) {
            html += '<div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1">'
                + '<div class="small"><strong>' + esc(d.etiqueta) + '</strong>'
                + (d.motivo ? '<br><span class="text-muted">' + esc(d.motivo) + '</span>' : '')
                + '<br><span class="text-muted">'
                + esc([d.usuario, d.fecha].filter(function (v) { return !!v; }).join(' · '))
                + '</span></div>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary js-bandeja-revertir-descarte"'
                + ' data-documento-id="' + parseInt(d.documento_id, 10) + '"'
                + ' data-etiqueta="' + esc(d.etiqueta) + '">'
                + '<i class="fa fa-undo"></i> Deshacer</button></div>';
        });
        html += '</div>';
        $wrap.html(html).show();
    }

    function renderAsignarListaDocs() {
        var $fac = $('#bandejaAsignarPrecarga').empty();
        if (!asignarEstado.facs.length) {
            $fac.append('<div class="p-2 text-muted small">No hay factura precargada. Adjuntela al enviar el legajo o desde la OC.</div>');
            return;
        }
        var pendientes = [];
        var cargados = [];
        asignarEstado.facs.forEach(function (f) {
            if (f.cargado_cxp) {
                cargados.push(f);
            } else {
                pendientes.push(f);
            }
        });
        if (pendientes.length) {
            $fac.append('<div class="bandeja-asig-sec">Este envío · ' + pendientes.length + '</div>');
            pendientes.forEach(function (f) { $fac.append(itemAsignarDoc(f)); });
        } else {
            $fac.append('<div class="p-3 text-muted small">No hay comprobantes pendientes: todos ya están en CxP.</div>');
        }
        if (cargados.length) {
            $fac.append(
                '<a href="#" class="bandeja-asig-sec d-block text-decoration-none js-bandeja-toggle-cargados" style="cursor:pointer;">'
                + '▸ Ya en CxP · ' + cargados.length + '</a>'
            );
            var $wrap = $('<div class="js-bandeja-cargados-wrap" style="display:none"></div>');
            cargados.forEach(function (f) { $wrap.append(itemAsignarDoc(f)); });
            $fac.append($wrap);
        }
    }

    function esFacturaEditableAsignacion(f) {
        if (!f || f.cargado_cxp) {
            return false;
        }
        var idStr = String(f.id);
        return /^\d+$/.test(idStr) || /^anita-\d+$/i.test(idStr);
    }

    function idsRecepcionAsignadas(raw) {
        return (raw || []).map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; });
    }

    function renderAsignar(paquete) {
        var facs = (paquete && paquete.facturas) || [];
        var coms = ((paquete && paquete.coms) || []).filter(function (c) { return c.confirmada; });
        var asignadas = (paquete && paquete.asignadas) || {};
        asignarEstado.facs = facs;
        asignarEstado.coms = coms;
        asignarEstado.tiposOpciones = (paquete && paquete.tipos_opciones) || [];
        asignarEstado.permiteAsignarCom = paquete ? paquete.permite_asignar_com !== false : true;
        asignarEstado.mensajeBloqueaCom = (paquete && paquete.mensaje_bloquea_com_anticipada) || '';
        asignarEstado.mapa = {};
        // Incluir también asignaciones de facturas ya en CxP / no editables:
        // si no, esas COM aparecen libres y el guardado falla en servidor.
        Object.keys(asignadas).forEach(function (key) {
            asignarEstado.mapa[String(key)] = idsRecepcionAsignadas(asignadas[key]);
        });
        facs.forEach(function (f) {
            if (!esFacturaEditableAsignacion(f)) {
                return;
            }
            var key = String(f.id);
            if (!Object.prototype.hasOwnProperty.call(asignarEstado.mapa, key)) {
                asignarEstado.mapa[key] = idsRecepcionAsignadas(asignadas[key] || asignadas[f.id]);
            }
            // Anticipada 1ª factura: no pre-tildar COM (debe ir como anticipo).
            if (!asignarEstado.permiteAsignarCom) {
                return;
            }
            // Si todavía no hay asignación guardada, pre-tildar la sugerencia (número/neto).
            // El operador puede destildar antes de guardar.
            if ((!asignarEstado.mapa[key] || !asignarEstado.mapa[key].length)
                && f.com_sugerida && f.com_sugerida.id) {
                var sid = parseInt(f.com_sugerida.id, 10);
                if (sid > 0) {
                    var yaTomada = false;
                    Object.keys(asignarEstado.mapa).forEach(function (otra) {
                        if (otra === key) {
                            return;
                        }
                        (asignarEstado.mapa[otra] || []).forEach(function (id) {
                            if (Number(id) === sid) {
                                yaTomada = true;
                            }
                        });
                    });
                    if (!yaTomada) {
                        asignarEstado.mapa[key] = [sid];
                    }
                }
            }
        });
        // 1ª factura anticipada: limpiar tildes locales (la asignación incorrecta se rechaza al guardar).
        if (!asignarEstado.permiteAsignarCom) {
            facs.forEach(function (f) {
                if (!esFacturaEditableAsignacion(f)) {
                    return;
                }
                asignarEstado.mapa[String(f.id)] = [];
            });
        }
        var pendientes = facs.filter(function (f) { return !f.cargado_cxp; });
        var prefer = pendientes.find(function (f) {
            var tipo = String(f.tipo || 'FC').toUpperCase();
            var exige = f.exige_com !== false && tipo !== 'NC' && tipo !== 'ND';
            return exige && !(f.coms_asignadas && f.coms_asignadas.length) && f.com_sugerida;
        }) || pendientes.find(function (f) {
            var tipo = String(f.tipo || 'FC').toUpperCase();
            return f.exige_com !== false && tipo !== 'NC' && tipo !== 'ND';
        }) || pendientes[0] || facs[0] || null;
        asignarEstado.activo = prefer ? String(prefer.id) : null;
        renderAsignarListaDocs();
        renderAsignarComsActivo();
        renderScansDescartados(paquete);

        var $atajos = $('#bandejaAsignarAtajos').empty();
        var pendientesFac = facs.filter(function (f) { return !f.cargado_cxp; });
        if (paquete && paquete.url_cargar_cxp) {
            var etqSig = (paquete.siguiente_pendiente && paquete.siguiente_pendiente.etiqueta)
                ? paquete.siguiente_pendiente.etiqueta
                : 'siguiente pendiente';
            $atajos.append('<a href="' + esc(paquete.url_cargar_cxp) + '" class="btn btn-sm btn-primary mr-1 mb-1"><i class="fa fa-plus"></i> Cargar ' + esc(etqSig) + '</a>');
            pendientesFac.forEach(function (f) {
                if (!f.url_cargar_cxp) {
                    return;
                }
                var etq = String(f.etiqueta || '');
                if (etq === etqSig) {
                    return;
                }
                $atajos.append('<a href="' + esc(f.url_cargar_cxp) + '" class="btn btn-sm btn-outline-primary mr-1 mb-1"><i class="fa fa-plus"></i> Cargar ' + esc(etq) + '</a>');
            });
        }
        var nCp = (paquete && paquete.comprobantes) ? paquete.comprobantes.length : 0;
        if (nCp > 0) {
            $atajos.append('<span class="text-muted small align-middle">Ya hay ' + nCp + ' CP de esta OC anual; no se vuelven a cargar.</span>');
        }
        if (paquete && paquete.pagos) {
            paquete.pagos.forEach(function (op) {
                $atajos.append('<a href="' + esc(op.url) + '" class="btn btn-sm btn-outline-success mr-1">OP ' + esc(op.etiqueta) + '</a>');
            });
        }
    }

    function enviarAccionScan(url, documentoId, motivo, $btn) {
        if (!url) {
            alert('No se pudo resolver la acción sobre el escaneo.');
            return;
        }
        var textoOriginal = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: url,
            method: 'POST',
            data: { _token: csrf(), documento_id: parseInt(documentoId, 10), motivo: motivo },
            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
        }).done(function (resp) {
            if (resp && resp.mensaje) {
                alert(resp.mensaje);
            }
            if (resp && resp.paquete) {
                renderAsignar(resp.paquete);
            } else if (asignarEstado.urlPaquete) {
                cargarPaquete(asignarEstado.urlPaquete, renderAsignar);
            }
        }).fail(function (xhr) {
            var msg = 'No se pudo completar la acción sobre el escaneo.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.keys(xhr.responseJSON.errors).map(function (k) {
                    return xhr.responseJSON.errors[k].join(' ');
                }).join(' ');
            }
            alert(msg);
        }).always(function () {
            $btn.prop('disabled', false).html(textoOriginal);
        });
    }

    function cargarPaquete(url, done) {
        $.get(url).done(done).fail(function () {
            alert('No se pudo leer el paquete del legajo.');
        });
    }

    $(function () {
        $('#modalBandejaAsignarCom').on('click', '.js-bandeja-toggle-cargados', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $wrap = $btn.next('.js-bandeja-cargados-wrap');
            $wrap.toggle();
            var n = $wrap.find('.js-bandeja-asig-doc').length;
            $btn.text(($wrap.is(':visible') ? '▾ ' : '▸ ') + 'Ya en CxP — no se vuelven a mandar (' + n + ')');
        });

        if (window.OcCambiarSectorLegajo) {
            window.OcCambiarSectorLegajo.initForm($('#formBandejaEnviarGastro'), { forzarPaquete: true });
            window.OcCambiarSectorLegajo.initForm($('#formBandejaEnviarCxp'), { forzarPaquete: true });
        }

        $('.js-bandeja-enviar-gastro').on('click', function () {
            var $form = $('#formBandejaEnviarGastro');
            var ocId = $(this).data('ordencompra-id') || '';
            $form.attr('action', $(this).data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $form.find('input[type=file]').val('');
            $form.find('input[name=destinatario_usuario_id]').val('');
            $form.data('ordencompra-id', ocId);
            $form.attr('data-ordencompra-id', ocId);
            if (window.OcCambiarSectorLegajo) {
                window.OcCambiarSectorLegajo.initForm($form, { forzarPaquete: true });
            }
            if (window.OcEnviarGastronomiaFirmante) {
                var base = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
                window.OcEnviarGastronomiaFirmante.setOrdencompraId(
                    $form,
                    ocId,
                    ocId ? (base + '/compras/ordencompra/' + ocId + '/firmantes-gastronomia-arbol') : ''
                );
            }
            $('#modalBandejaEnviarGastro').modal('show');
        });

        $('.js-bandeja-enviar-pagos').on('click', function () {
            var $form = $('#formBandejaEnviarPagos');
            $form.attr('action', $(this).data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $('#modalBandejaEnviarPagos').modal('show');
        });

        $('.js-bandeja-devolver-cxp').on('click', function () {
            var $form = $('#formBandejaDevolverCxp');
            $form.attr('action', $(this).data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $('#modalBandejaDevolverCxp').modal('show');
        });

        $('.js-bandeja-devolver-compras').on('click', function () {
            var $form = $('#formBandejaDevolverCompras');
            $form.attr('action', $(this).data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $('#modalBandejaDevolverCompras').modal('show');
        });

        $('.js-bandeja-enviar-cxp').on('click', function () {
            var $btn = $(this);
            var ocId = $btn.data('ordencompra-id') || '';
            var $form = $('#formBandejaEnviarCxp');
            var opts = { forzarPaquete: true, forzarCxp: true };
            var base = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
            var urlPaquete = $btn.data('url-paquete') || '';
            var urlAsignar = $btn.data('url-asignar') || '';
            var numero = $btn.data('numero') || '';

            $form.attr('action', $btn.data('url'));
            $form.find('input[name=observacion]').val('');
            $form.find('textarea[name=leyenda]').val('');
            $form.find('input[type=file]').val('');
            $form.data('ordencompra-id', ocId);
            if (window.OcCambiarSectorLegajo) {
                window.OcCambiarSectorLegajo.initForm($form, opts);
            }

            if (!ocId) {
                alert('No se pudo identificar el legajo.');
                return;
            }

            $btn.prop('disabled', true);
            $.getJSON(base + '/compras/ordencompra/' + ocId + '/gate-cuentas-a-pagar?preflight=1')
                .done(function (gate) {
                    if (!gate || !gate.ok) {
                        var faltan = (gate && gate.faltan_com) || [];
                        var retenibles = faltan.filter(function (d) { return d && d.puede_retener && d.precarga_id; });
                        if (retenibles.length) {
                            mostrarModalPendienteEntrega({
                                ocId: ocId,
                                numero: numero,
                                urlPaquete: urlPaquete,
                                urlAsignar: urlAsignar,
                                faltan: faltan,
                                gate: gate,
                                $btnEnviar: $btn,
                                opts: opts
                            });
                            return;
                        }
                        var errs = (gate && gate.errores && gate.errores.length)
                            ? gate.errores.join('\n')
                            : 'El legajo no cumple los requisitos para enviar a Cuentas a pagar.';
                        alert(errs);
                        return;
                    }
                    abrirModalEnviarCxp($form, ocId, opts);
                })
                .fail(function () {
                    alert('No se pudo validar el legajo antes del envío.');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });

        var pendienteEntregaEstado = {};

        function abrirModalEnviarCxp($form, ocId, opts) {
            if (window.OcCambiarSectorLegajo) {
                window.OcCambiarSectorLegajo.setOrdencompraId($form, ocId, opts);
            }
            $('#modalBandejaEnviarCxp').modal('show');
        }

        function mostrarModalPendienteEntrega(ctx) {
            pendienteEntregaEstado = ctx || {};
            var $lista = $('#bandejaPendienteEntregaLista').empty();
            var faltan = ctx.faltan || [];
            var retenibles = 0;
            faltan.forEach(function (d) {
                var id = d.precarga_id ? Number(d.precarga_id) : 0;
                var puede = !!(d.puede_retener && id > 0);
                if (puede) {
                    retenibles += 1;
                }
                var $row = $('<div class="custom-control custom-checkbox mb-1"></div>');
                var cid = 'pe_fac_' + (id || ('a' + String(d.anita_id || Math.random()).replace(/\W/g, '')));
                $row.append(
                    '<input type="checkbox" class="custom-control-input js-bandeja-pe-check" id="' + cid + '"' +
                    ' data-precarga-id="' + esc(String(id)) + '"' +
                    (puede ? ' checked' : ' disabled') + '>' +
                    '<label class="custom-control-label" for="' + cid + '">' +
                    esc(d.etiqueta || ('#' + id)) +
                    (puede ? '' : ' <span class="text-muted">(sin precarga: asigná COM o materializá el PDF)</span>') +
                    '</label>'
                );
                $lista.append($row);
            });
            $('#bandejaPendienteEntregaHint').text(
                retenibles
                    ? 'Marcá las que aún no tienen mercadería y continuá. El resto debe tener COM asignada.'
                    : 'Ninguna se puede retener automáticamente. Asigná COM a las facturas listadas.'
            );
            $('#btnBandejaMarcarPendienteEntrega').prop('disabled', retenibles === 0);
            $('#modalBandejaPendienteEntrega .modal-title').text('Facturas sin COM — OC ' + (ctx.numero || ''));
            $('#modalBandejaPendienteEntrega').modal('show');
        }

        $('#btnBandejaAbrirAsignarCom').on('click', function () {
            var ctx = pendienteEntregaEstado;
            $('#modalBandejaPendienteEntrega').modal('hide');
            if (!ctx.urlAsignar && !ctx.urlPaquete) {
                alert('Abrí Asignar COM desde la fila del legajo.');
                return;
            }
            var $fake = $('<button type="button" class="js-bandeja-asignar-com"></button>');
            $fake.attr('data-url-asignar', ctx.urlAsignar || String(ctx.urlPaquete).replace(/\/paquete\/?(\?.*)?$/, '/asignar-com'));
            $fake.attr('data-url-paquete', ctx.urlPaquete || '');
            $fake.attr('data-numero', ctx.numero || '');
            $fake.trigger('click');
        });

        $('#btnBandejaMarcarPendienteEntrega').on('click', function () {
            var ctx = pendienteEntregaEstado;
            var ids = [];
            $('#bandejaPendienteEntregaLista .js-bandeja-pe-check:checked').each(function () {
                var id = parseInt($(this).data('precarga-id'), 10) || 0;
                if (id > 0) {
                    ids.push(id);
                }
            });
            if (!ids.length) {
                alert('Seleccioná al menos una factura para marcar como pendiente de entrega.');
                return;
            }
            var url = (ctx.urlPaquete || '').replace(/\/paquete\/?(\?.*)?$/, '/marcar-pendiente-entrega');
            if (!url) {
                alert('No se pudo armar la URL de retención.');
                return;
            }
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url: url,
                method: 'POST',
                data: { precarga_ids: ids },
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (resp) {
                $('#modalBandejaPendienteEntrega').modal('hide');
                var gate = resp && resp.gate;
                if (gate && gate.ok) {
                    var $form = $('#formBandejaEnviarCxp');
                    abrirModalEnviarCxp($form, ctx.ocId, ctx.opts || { forzarPaquete: true, forzarCxp: true });
                    return;
                }
                var faltan = (gate && gate.faltan_com) || [];
                if (faltan.length) {
                    alert((gate.errores && gate.errores.join('\n')) || 'Todavía faltan COM. Asigná las que no retuviste.');
                    mostrarModalPendienteEntrega($.extend({}, ctx, { faltan: faltan, gate: gate }));
                    return;
                }
                alert((resp && resp.mensaje) || 'Facturas retenidas.');
            }).fail(function (xhr) {
                var msg = 'No se pudo marcar como pendiente de entrega.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = $.map(xhr.responseJSON.errors, function (v) {
                        return $.isArray(v) ? v.join(' ') : String(v);
                    }).join(' ');
                }
                alert(msg);
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $(document).on('click', '.js-bandeja-liberar-pendiente-entrega', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var precargaId = parseInt($(this).data('precarga-id'), 10) || 0;
            if (!precargaId || !asignarEstado.urlPaquete) {
                return;
            }
            var url = String(asignarEstado.urlPaquete).replace(/\/paquete\/?(\?.*)?$/, '/liberar-pendiente-entrega');
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url: url,
                method: 'POST',
                data: { precarga_ids: [precargaId] },
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (resp) {
                if (resp && resp.paquete) {
                    renderAsignar(resp.paquete);
                }
                if (resp && resp.mensaje) {
                    alert(resp.mensaje);
                }
            }).fail(function (xhr) {
                var msg = 'No se pudo liberar la factura.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = $.map(xhr.responseJSON.errors, function (v) {
                        return $.isArray(v) ? v.join(' ') : String(v);
                    }).join(' ');
                }
                alert(msg);
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $('.js-bandeja-historia').on('click', function () {
            var numero = $(this).data('numero') || '';
            $('#modalBandejaHistoria .modal-title').text('Historia del legajo OC ' + numero);
            $('#tablaBandejaHistoria tbody').html('<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>');
            $('#modalBandejaHistoria').modal('show');
            $.get($(this).data('url')).done(renderHistoria).fail(function () {
                $('#tablaBandejaHistoria tbody').html('<tr><td colspan="5" class="text-center text-danger">No se pudo leer la historia.</td></tr>');
            });
        });

        $('.js-bandeja-nota').on('click', function () {
            var $btn = $(this);
            var numero = $btn.data('numero') || '';
            var $form = $('#formBandejaNota');
            $form.attr('action', $btn.data('url'));
            $form.data('btn', $btn);
            $('#modalBandejaNota .modal-title').text('Nota del legajo OC ' + numero);
            $('#bandeja_nota_texto').val($btn.attr('data-nota') || '');
            $('#modalBandejaNota').modal('show');
        });

        $('#formBandejaNota').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.data('btn');
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (resp) {
                $('#modalBandejaNota').modal('hide');
                var nota = (resp && resp.nota_legajo) ? String(resp.nota_legajo) : '';
                var tiene = !!(resp && resp.tiene_nota);
                if ($btn && $btn.length) {
                    $btn.attr('data-nota', nota);
                    $btn.attr('title', tiene ? ('Nota: ' + nota) : 'Agregar nota al legajo');
                    $btn.toggleClass('btn-warning', tiene)
                        .toggleClass('btn-outline-secondary', !tiene);
                    $btn.find('i').attr('class', tiene ? 'fa fa-sticky-note' : 'fa fa-sticky-note-o');
                }
                if (resp && resp.mensaje) {
                    alert(resp.mensaje);
                }
            }).fail(function (xhr) {
                var msg = 'No se pudo guardar la nota.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join(' ');
                }
                alert(msg);
            });
        });

        $(document).on('click', '.js-bandeja-pdf-row', function (e) {
            // Los links y botones de la fila (ver escaneo, descartar) manejan su propio click.
            if ($(e.target).closest('a, button').length) {
                return;
            }
            var url = $(this).data('url-pdf');
            var urlCxp = $(this).data('url-cxp');
            var $modal = $(this).closest('.modal');
            $(this).addClass('table-info').siblings().removeClass('table-info');
            var $iframe = $modal.find('.tab-pane.active iframe');
            if (!$iframe.length) {
                $iframe = $modal.find('iframe').first();
            }
            if (url) {
                mostrarPdf($iframe, url);
            } else {
                mostrarSinPdf($iframe, urlCxp || '');
            }
        });

        $(document).on('click', '.js-bandeja-ver-legajo, .js-bandeja-ver-factura, .js-bandeja-ver-com', function () {
            var urlPaquete = $(this).data('url-paquete');
            var numero = $(this).data('numero') || '';
            var tab = $(this).data('tab') || ($(this).hasClass('js-bandeja-ver-com') ? 'coms' : 'facturas');
            $('#bandejaLegajoTitulo').text('Legajo OC ' + numero);
            $('#tablaBandejaFacturas tbody').html('<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>');
            $('#tablaBandejaComs tbody').html('<tr><td colspan="3" class="text-center text-muted">Cargando…</td></tr>');
            mostrarPdf($('#bandejaFacturaPdf'), '');
            mostrarPdf($('#bandejaComPdf'), '');
            $('#bandejaLegajoPagos').html('<div class="bandeja-pagos-empty"><p class="text-muted mb-0">Cargando pagos…</p></div>');
            $('#modalBandejaLegajo').modal('show');
            cargarPaquete(urlPaquete, function (paquete) {
                renderLegajo(paquete, tab);
            });
        });

        $(document).on('click', '.js-bandeja-pago-fac', function () {
            seleccionarFacturaPago($(this).data('fac-key'));
        });

        $(document).on('click', '.js-bandeja-ir-pagos', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var key = $(this).data('fac-key');
            pagosToolEstado.activo = key != null ? String(key) : null;
            activarTabLegajo('pagos');
            if (pagosToolEstado.paquete) {
                seleccionarFacturaPago(pagosToolEstado.activo);
            }
        });

        $('.js-bandeja-asignar-com').on('click', function () {
            var $btn = $(this);
            var numero = $btn.data('numero') || '';
            var urlPaquete = $btn.data('url-paquete') || '';
            $('#formBandejaAsignarCom').attr('action', $btn.data('url-asignar'));
            $('#modalBandejaAsignarCom .modal-title').text('Asignar COM — OC ' + numero);
            $('#bandejaAsignarPrecarga, #bandejaAsignarComs').html('<p class="text-muted p-2 mb-0">Cargando…</p>');
            $('#bandejaAsignarAtajos').empty();
            asignarEstado.urlCorregirTipo = String(urlPaquete).replace(/\/paquete\/?(\?.*)?$/, '/corregir-tipo-documento');
            asignarEstado.urlPaquete = urlPaquete;
            asignarEstado.urlDescartarScan = String(urlPaquete).replace(/\/paquete\/?(\?.*)?$/, '/descartar-scan-anita');
            asignarEstado.urlRevertirDescarte = String(urlPaquete).replace(/\/paquete\/?(\?.*)?$/, '/revertir-descarte-scan-anita');
            $('#modalBandejaAsignarCom').modal('show');
            cargarPaquete(urlPaquete, renderAsignar);
        });

        // Ver un escaneo puntual de la factura en el visor del modal (sin abrir otra pestaña).
        $(document).on('click', '.js-bandeja-ver-scan', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var url = String($(this).data('url-pdf') || '');
            var $fila = $(this).closest('tr');
            $fila.addClass('table-info').siblings().removeClass('table-info');
            var $modal = $(this).closest('.modal');
            var $iframe = $modal.find('.tab-pane.active iframe');
            if (!$iframe.length) {
                $iframe = $modal.find('iframe').first();
            }
            if (url) {
                mostrarPdf($iframe, url);
            } else {
                mostrarSinPdf($iframe, '');
            }
        });

        // Descarte de un escaneo mal vinculado: motivo obligatorio y reversible desde el mismo panel.
        $(document).on('click', '.js-bandeja-descartar-scan', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var etiqueta = String($btn.data('etiqueta') || '');
            var motivo = window.prompt(
                'Descartar el escaneo ' + etiqueta + ' de este legajo.\n\n'
                + 'Indique el motivo (queda registrado y se puede deshacer):'
            );
            if (motivo === null) {
                return;
            }
            motivo = String(motivo).trim();
            if (motivo.length < 5) {
                alert('El motivo es obligatorio (mínimo 5 caracteres).');
                return;
            }
            enviarAccionScan(asignarEstado.urlDescartarScan, $btn.data('documento-id'), motivo, $btn);
        });

        $(document).on('click', '.js-bandeja-revertir-descarte', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var etiqueta = String($btn.data('etiqueta') || '');
            var motivo = window.prompt(
                'Deshacer el descarte de ' + etiqueta + ': la factura vuelve al legajo.\n\n'
                + 'Indique el motivo:'
            );
            if (motivo === null) {
                return;
            }
            motivo = String(motivo).trim();
            if (motivo.length < 5) {
                alert('El motivo es obligatorio (mínimo 5 caracteres).');
                return;
            }
            enviarAccionScan(asignarEstado.urlRevertirDescarte, $btn.data('documento-id'), motivo, $btn);
        });

        $(document).on('click', '.js-bandeja-asig-doc', function (e) {
            e.preventDefault();
            asignarEstado.activo = String($(this).data('doc-id'));
            renderAsignarListaDocs();
            renderAsignarComsActivo();
        });

        $(document).on('change', '.js-bandeja-asig-tipo', function () {
            var $sel = $(this);
            var precargaId = parseInt($sel.data('precarga-id'), 10) || 0;
            var tipo = String($sel.val() || '').toUpperCase();
            var url = asignarEstado.urlCorregirTipo || '';
            if (!url || precargaId <= 0 || !tipo) {
                return;
            }
            $sel.prop('disabled', true);
            $.ajax({
                url: url,
                method: 'POST',
                data: { precarga_id: precargaId, tipo: tipo },
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (resp) {
                if (resp && resp.paquete) {
                    var mapa = asignarEstado.mapa;
                    var activo = asignarEstado.activo;
                    renderAsignar(resp.paquete);
                    asignarEstado.mapa = mapa;
                    asignarEstado.activo = activo || asignarEstado.activo;
                    renderAsignarListaDocs();
                    renderAsignarComsActivo();
                }
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.tipo && xhr.responseJSON.errors.tipo[0]))) || 'No se pudo corregir el tipo.';
                alert(msg);
            }).always(function () {
                $sel.prop('disabled', false);
            });
        });

        $(document).on('change', '.js-bandeja-asig-com', function () {
            var activo = String(asignarEstado.activo || '');
            if (!activo) {
                return;
            }
            var ids = [];
            $('#bandejaAsignarComs .js-bandeja-asig-com:checked:not(:disabled)').each(function () {
                ids.push(parseInt($(this).data('com-id'), 10));
            });
            ids = ids.filter(function (id) { return id > 0; });
            var ocupadas = comIdsOcupadasPorOtraFactura(activo);
            var conflicto = ids.filter(function (id) { return !!ocupadas[String(id)]; });
            if (conflicto.length) {
                alert('La COM ya está asignada a otra factura del legajo. Cada recepción solo puede vincularse a un comprobante.');
                $(this).prop('checked', false);
                return;
            }
            asignarEstado.mapa[activo] = ids;
            renderAsignarListaDocs();
            renderAsignarComsActivo();
        });

        $('#formBandejaAsignarCom').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var asignaciones = [];
            var vistas = {};
            var dup = null;
            Object.keys(asignarEstado.mapa).forEach(function (preId) {
                var idStr = String(preId);
                if (!/^\d+$/.test(idStr) && !/^anita-\d+$/i.test(idStr)) {
                    return;
                }
                var fac = asignarEstado.facs.find(function (f) { return String(f.id) === idStr; });
                // No reenviar vínculos de facturas ya en CxP (solo sirven para ocupación en UI).
                if (fac && fac.cargado_cxp) {
                    return;
                }
                if (!fac && !/^\d+$/.test(idStr)) {
                    return;
                }
                // Precarga del legajo no listada / anulada: conservar ocupación, no pisar en save.
                if (!fac) {
                    return;
                }
                if (!esFacturaEditableAsignacion(fac)) {
                    return;
                }
                var recepcionIds = asignarEstado.mapa[preId] || [];
                recepcionIds.forEach(function (rid) {
                    if (rid > 0 && vistas[rid] && String(vistas[rid]) !== idStr) {
                        dup = rid;
                    }
                    if (rid > 0) {
                        vistas[rid] = idStr;
                    }
                });
                asignaciones.push({
                    precarga_id: preId,
                    recepcion_ids: recepcionIds
                });
            });
            // Conflictos con COM ya tomadas por facturas no editables (CxP / fuera de lista).
            Object.keys(asignarEstado.mapa).forEach(function (preId) {
                var idStr = String(preId);
                var fac = asignarEstado.facs.find(function (f) { return String(f.id) === idStr; });
                if (fac ? esFacturaEditableAsignacion(fac) : false) {
                    return;
                }
                (asignarEstado.mapa[preId] || []).forEach(function (rid) {
                    if (rid > 0 && vistas[rid]) {
                        dup = rid;
                    }
                });
            });
            if (dup) {
                alert('La misma COM quedó asignada a más de una factura. Corrija antes de guardar.');
                return;
            }
            // Doble clic = dos POST concurrentes que se pisaban y duplicaban la asignación.
            if ($form.data('enviando')) {
                return;
            }
            var $btnGuardar = $form.find('button[type="submit"]');
            var textoOriginal = $btnGuardar.html();
            $form.data('enviando', true);
            $btnGuardar.prop('disabled', true).html('Guardando...');
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: {
                    _token: csrf(),
                    asignaciones: asignaciones
                },
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (resp) {
                $('#modalBandejaAsignarCom').modal('hide');
                if (resp && resp.mensaje) {
                    alert(resp.mensaje);
                }
                window.location.reload();
            }).fail(function (xhr) {
                var msg = 'No se pudo asignar la COM.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).join(' ');
                }
                alert(msg);
                $form.data('enviando', false);
                $btnGuardar.prop('disabled', false).html(textoOriginal);
            });
        });
    });
})(jQuery);
