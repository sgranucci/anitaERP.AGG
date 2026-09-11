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

    function renderComs(paquete) {
        var $tb = $('#tablaBandejaComs tbody').empty();
        var $pdf = $('#bandejaComPdf');
        var coms = (paquete && paquete.coms) || [];
        mostrarPdf($pdf, '');
        if (!coms.length) {
            $tb.append('<tr><td colspan="3" class="text-center text-muted">No hay COM en este legajo.</td></tr>');
            return;
        }
        coms.forEach(function (c, i) {
            var $tr = $('<tr class="js-bandeja-pdf-row" style="cursor:pointer;"></tr>');
            $tr.attr('data-url-pdf', c.url_pdf || '');
            $tr.append('<td>' + esc(c.documento || ('#' + c.id)) + '</td>');
            $tr.append('<td>' + esc(c.fecha || '') + '</td>');
            $tr.append('<td>' + esc(c.estado || '') + '</td>');
            if (i === 0) {
                $tr.addClass('table-info');
            }
            $tb.append($tr);
        });
        if (coms[0] && coms[0].url_pdf) {
            mostrarPdf($pdf, coms[0].url_pdf);
        }
    }

    function renderFacturas(paquete) {
        var $tb = $('#tablaBandejaFacturas tbody').empty();
        var $pdf = $('#bandejaFacturaPdf');
        var facs = (paquete && paquete.facturas) || [];
        mostrarPdf($pdf, '');
        if (!facs.length) {
            $tb.append('<tr><td colspan="4" class="text-center text-muted">No hay facturas ni comprobantes en este legajo.</td></tr>');
            return;
        }
        facs.forEach(function (f, i) {
            var origen = f.origen_label || ((f.origen === 'anita') ? 'Scan Anita (manual, no IA)' : (f.estado || 'Precarga'));
            var estadoHtml = f.cargado_cxp
                ? '<span class="badge badge-info">cargada</span>'
                : '<span class="badge badge-warning">pendiente</span>';
            if (f.url_comprobante) {
                estadoHtml += ' <a href="' + esc(f.url_comprobante) + '" target="_blank" rel="noopener" class="js-bandeja-abrir-cxp" title="Abrir en CxP">CxP</a>';
            }
            var $tr = $('<tr class="js-bandeja-pdf-row" style="cursor:pointer;"></tr>');
            $tr.attr('data-url-pdf', f.url_pdf || '');
            $tr.attr('data-url-cxp', f.url_comprobante || '');
            $tr.append('<td>' + esc(f.etiqueta || ('#' + f.id)) + '</td>');
            $tr.append('<td>' + esc(f.fecha || '') + '</td>');
            $tr.append('<td>' + esc(origen) + '</td>');
            $tr.append('<td>' + estadoHtml + '</td>');
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

    function renderPagos(paquete) {
        var $box = $('#bandejaLegajoPagos').empty();
        var pagos = (paquete && paquete.pagos) || [];
        var n = pagos.length;
        $('#bandejaLegajoNPago').text(n);
        if (n) {
            $('#bandeja-tab-pagos-item').show();
        } else {
            $('#bandeja-tab-pagos-item').hide();
        }
        if (!pagos.length) {
            $box.append('<p class="text-muted mb-0">No hay órdenes de pago en este legajo.</p>');
            return;
        }
        pagos.forEach(function (op) {
            $box.append(
                '<a href="' + esc(op.url || '#') + '" class="btn btn-outline-success btn-sm mr-1 mb-1" target="_blank" rel="noopener">' +
                esc(op.etiqueta || ('OP #' + op.id)) + '</a>'
            );
        });
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
        tiposOpciones: []
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
            return '<span class="badge badge-primary">COM ×' + ids.length + '</span>';
        }
        return '<span class="badge badge-warning">sin COM</span>';
    }

    function renderAsignarComsActivo() {
        var $coms = $('#bandejaAsignarComs').empty();
        var activo = asignarEstado.activo;
        if (!activo) {
            $coms.append('<p class="text-muted mb-0 small">Seleccioná un comprobante a la izquierda.</p>');
            return;
        }
        var fac = asignarEstado.facs.find(function (f) { return String(f.id) === String(activo); });
        var tipoActual = String((fac && (fac.tipo_abrev || fac.tipo_label || fac.tipo)) || 'FC').toUpperCase();
        if (fac && (fac.origen || 'precarga') === 'precarga' && /^\d+$/.test(String(fac.id))) {
            var opciones = (asignarEstado.tiposOpciones && asignarEstado.tiposOpciones.length)
                ? asignarEstado.tiposOpciones
                : [
                    { value: 'FC', label: 'FC — Factura' },
                    { value: 'NC', label: 'NC — Nota de crédito (no exige COM)' },
                    { value: 'ND', label: 'ND — Nota de débito (no exige COM)' }
                ];
            var optsHtml = opciones.map(function (opt) {
                var val = String(opt.value || '').toUpperCase();
                var lab = opt.label || val;
                return '<option value="' + esc(val) + '"' + (val === tipoActual ? ' selected' : '') + '>' + esc(lab) + '</option>';
            }).join('');
            $coms.append(
                '<div class="form-group mb-2">' +
                '<label class="small mb-1" for="bandejaAsigTipoDoc">Tipo del comprobante</label>' +
                '<select id="bandejaAsigTipoDoc" class="form-control form-control-sm js-bandeja-asig-tipo" data-precarga-id="' + esc(String(fac.id)) + '">' +
                optsHtml +
                '</select>' +
                '<small class="form-text text-muted">En OC con varios centros de costo podés pasar de FIB a FGA (gastronomía).</small>' +
                '</div>'
            );
        }
        if (fac && fac.cargado_cxp) {
            $coms.prepend('<p class="text-success small mb-2">Ya está cargado en CxP. En una OC anual no forma parte de este envío.</p>');
        }
        var tipoFac = String((fac && fac.tipo) || 'FC').toUpperCase();
        var exige = !fac || (fac.exige_com !== false && tipoFac !== 'NC' && tipoFac !== 'ND');
        if (!exige) {
            var textoTipo = tipoFac === 'ND' ? 'nota de débito' : (tipoFac === 'NC' ? 'nota de crédito' : 'este tipo');
            $coms.append('<p class="text-muted mb-2 small">Este comprobante es ' + textoTipo + ': no exige recepción COM.</p>');
        }
        if (!asignarEstado.coms.length) {
            $coms.append('<p class="text-muted mb-0">No hay COM confirmada para asignar.</p>');
            return;
        }
        var idsAsig = asignarEstado.mapa[String(activo)] || [];
        asignarEstado.coms.forEach(function (c) {
            var checked = idsAsig.indexOf(c.id) !== -1 || idsAsig.indexOf(String(c.id)) !== -1;
            $coms.append(
                '<div class="form-check">' +
                '<input class="form-check-input js-bandeja-asig-com" type="checkbox" data-com-id="' + c.id + '" id="ban_com_' + c.id + '"' + (checked ? ' checked' : '') + '>' +
                '<label class="form-check-label" for="ban_com_' + c.id + '">' + esc(c.documento) +
                (c.fecha ? ' <small class="text-muted">' + esc(c.fecha) + '</small>' : '') + '</label></div>'
            );
        });
    }

    function itemAsignarDoc(f) {
        var activo = String(asignarEstado.activo) === String(f.id);
        var tipo = esc(f.tipo_label || f.tipo || 'FC');
        var extra = f.cargado_cxp ? ' text-muted' : '';
        return '<a href="#" class="list-group-item list-group-item-action js-bandeja-asig-doc py-2' + (activo ? ' active' : '') + extra + '" data-doc-id="' + esc(String(f.id)) + '">' +
            '<div class="d-flex justify-content-between align-items-start">' +
            '<div><span class="badge badge-dark mr-1">' + tipo + '</span>' + esc(f.etiqueta || ('#' + f.id)) +
            (f.fecha ? '<br><small class="' + (activo ? 'text-white-50' : 'text-muted') + '">' + esc(f.fecha) + '</small>' : '') +
            (f.origen_label ? '<br><small class="' + (activo ? 'text-white-50' : 'text-muted') + '">' + esc(f.origen_label) + '</small>' : '') +
            '</div><div class="ml-2 text-right">' + badgeComDoc(f) + '</div></div></a>';
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
            $fac.append('<div class="list-group-item bg-light py-1 small font-weight-bold">Este envío (' + pendientes.length + ')</div>');
            pendientes.forEach(function (f) { $fac.append(itemAsignarDoc(f)); });
        } else {
            $fac.append('<div class="p-2 text-muted small">No hay comprobantes pendientes: todos ya están en CxP.</div>');
        }
        if (cargados.length) {
            $fac.append(
                '<a href="#" class="list-group-item bg-light py-1 small text-muted js-bandeja-toggle-cargados">' +
                '▸ Ya en CxP — no se vuelven a mandar (' + cargados.length + ')</a>'
            );
            var $wrap = $('<div class="js-bandeja-cargados-wrap" style="display:none"></div>');
            cargados.forEach(function (f) { $wrap.append(itemAsignarDoc(f)); });
            $fac.append($wrap);
        }
    }

    function renderAsignar(paquete) {
        var facs = (paquete && paquete.facturas) || [];
        var coms = ((paquete && paquete.coms) || []).filter(function (c) { return c.confirmada; });
        var asignadas = (paquete && paquete.asignadas) || {};
        asignarEstado.facs = facs;
        asignarEstado.coms = coms;
        asignarEstado.tiposOpciones = (paquete && paquete.tipos_opciones) || [];
        asignarEstado.mapa = {};
        facs.forEach(function (f) {
            var key = String(f.id);
            var ids = asignadas[key] || asignadas[f.id] || [];
            asignarEstado.mapa[key] = ids.map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; });
        });
        var pendientes = facs.filter(function (f) { return !f.cargado_cxp; });
        var prefer = pendientes.find(function (f) {
            var tipo = String(f.tipo || 'FC').toUpperCase();
            return f.exige_com !== false && tipo !== 'NC' && tipo !== 'ND';
        }) || pendientes[0] || facs[0] || null;
        asignarEstado.activo = prefer ? String(prefer.id) : null;
        renderAsignarListaDocs();
        renderAsignarComsActivo();

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
                        var errs = (gate && gate.errores && gate.errores.length)
                            ? gate.errores.join('\n')
                            : 'El legajo no cumple los requisitos para enviar a Cuentas a pagar.';
                        alert(errs);
                        return;
                    }
                    if (window.OcCambiarSectorLegajo) {
                        window.OcCambiarSectorLegajo.setOrdencompraId($form, ocId, opts);
                    }
                    $('#modalBandejaEnviarCxp').modal('show');
                })
                .fail(function () {
                    alert('No se pudo validar el legajo antes del envío.');
                })
                .always(function () {
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
            if ($(e.target).closest('a').length) {
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
            $('#tablaBandejaFacturas tbody').html('<tr><td colspan="4" class="text-center text-muted">Cargando…</td></tr>');
            $('#tablaBandejaComs tbody').html('<tr><td colspan="3" class="text-center text-muted">Cargando…</td></tr>');
            mostrarPdf($('#bandejaFacturaPdf'), '');
            mostrarPdf($('#bandejaComPdf'), '');
            $('#modalBandejaLegajo').modal('show');
            cargarPaquete(urlPaquete, function (paquete) {
                renderLegajo(paquete, tab);
            });
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
            $('#modalBandejaAsignarCom').modal('show');
            cargarPaquete(urlPaquete, renderAsignar);
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
            $('#bandejaAsignarComs .js-bandeja-asig-com:checked').each(function () {
                ids.push(parseInt($(this).data('com-id'), 10));
            });
            asignarEstado.mapa[activo] = ids.filter(function (id) { return id > 0; });
            renderAsignarListaDocs();
        });

        $('#formBandejaAsignarCom').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var asignaciones = [];
            Object.keys(asignarEstado.mapa).forEach(function (preId) {
                asignaciones.push({
                    precarga_id: preId,
                    recepcion_ids: asignarEstado.mapa[preId] || []
                });
            });
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
            });
        });
    });
})(jQuery);
