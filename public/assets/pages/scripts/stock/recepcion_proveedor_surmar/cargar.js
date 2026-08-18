(function ($) {
    'use strict';

    var cfg = window.SURMAR_RECEPCION || {};
    var lineas = Array.isArray(cfg.lineas) ? cfg.lineas.slice() : [];
    var lineasOc = Array.isArray(cfg.lineasOc) ? cfg.lineasOc.slice() : [];
    var unidades = Array.isArray(cfg.unidadesmedida) ? cfg.unidadesmedida.slice() : [];
    var $tbodyOc = $('#tabla-oc-pendientes-surmar tbody');
    var $msg = $('#surmar-msg-vivo');
    var overlay = document.getElementById('surmar-overlay');
    var ocElegidaId = null;
    var contextoAlta = null;
    /** @type {{total:number, actual:number, locked:boolean}|null} */
    var procesoEtiqueta = null;
    /** @type {string|null} Campo a enfocar cuando el modal termina de abrir. */
    var focoPendienteModalEtiqueta = null;

    function actualizarBadgeProceso() {
        var $b = $('#etiq_proceso_badge');
        if (!procesoEtiqueta || procesoEtiqueta.total < 1) {
            $b.hide().text('');
            return;
        }
        $b.text('Unidad ' + procesoEtiqueta.actual + ' de ' + procesoEtiqueta.total).show();
    }

    function finProcesoEtiqueta() {
        procesoEtiqueta = null;
        actualizarBadgeProceso();
    }

    /**
     * Bootstrap enfoca el propio modal en shown.bs.modal y pisa cualquier focus
     * previo (por eso el foco "se iba" del campo con un setTimeout suelto).
     * Si el modal aún no terminó de abrir, se deja pendiente y lo aplica el handler.
     */
    function enfocarCampoModalEtiqueta(selector) {
        var $modal = $('#modalEtiquetaProveedorSurmar');

        if (!$modal.hasClass('show')) {
            focoPendienteModalEtiqueta = selector;
            return;
        }

        focoPendienteModalEtiqueta = null;
        var $campo = $(selector);
        if ($campo.length) {
            $campo.trigger('focus').select();
        }
    }

    function prepararSiguienteUnidadEnModal(proxima) {
        if (!procesoEtiqueta || !contextoAlta) {
            finProcesoEtiqueta();
            $('#modalEtiquetaProveedorSurmar').modal('hide');
            return;
        }
        procesoEtiqueta.actual = proxima;
        procesoEtiqueta.locked = true;
        $('#etiq_modo').val('alta');
        $('#etiq_linea_id').val('').removeData('etiquetaId');
        $('#etiq_nro_apertura').val(proxima).prop('readonly', true);
        $('#etiq_cant_unid').val(procesoEtiqueta.total).prop('readonly', true);
        $('#etiq_nro_ayuda').text('Cambie pesos/piezas si esta unidad difiere; Imprime guarda e imprime.');
        $('#btn-etiq-guardar-imprimir').prop('disabled', false)
            .html('<i class="fa fa-print"></i> Imprime y siguiente');
        $('#btn-etiq-guardar').prop('disabled', false);
        actualizarBadgeProceso();
        actualizarPreviewLocal();
        enfocarCampoModalEtiqueta('#etiq_piezas');
    }

    function sincronizarPesosAlFormulario() {
        $('#lote_proveedor').val($('#etiq_lote').val());
        $('#fecha_vto').val($('#etiq_vto').val());
        $('#cant_pieza').val($('#etiq_piezas').val());
        $('#peso_bruto').val($('#etiq_bruto').val());
        $('#peso_tara').val($('#etiq_tara').val());
        $('#peso_neto').val($('#etiq_neto').val());
    }

    function limpiarPesosForm() {
        $('#cant_pieza').val('1');
        $('#peso_bruto').val('');
        $('#peso_tara').val('0');
        $('#peso_neto').val('');
    }

    function mostrarOverlay(titulo) {
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('surmar-overlay-titulo');
            if (t) t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function fmt(n) {
        return (Number(n) || 0).toFixed(2);
    }

    function fmtPeso(n) {
        var v = Number(n) || 0;
        if (v === 0) return '—';
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 6 });
    }

    function fmtPrecioConMoneda(precio, monedaAbr) {
        var p = fmt(precio);
        var m = $.trim(monedaAbr || '');
        return m ? (m + ' ' + p) : p;
    }

    function addDaysYmd(ymd, days) {
        if (!ymd || !(Number(days) > 0)) return '';
        var parts = String(ymd).split('-');
        if (parts.length !== 3) return '';
        var d = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
        if (isNaN(d.getTime())) return '';
        d.setUTCDate(d.getUTCDate() + Number(days));
        var mm = String(d.getUTCMonth() + 1).padStart(2, '0');
        var dd = String(d.getUTCDate()).padStart(2, '0');
        return d.getUTCFullYear() + '-' + mm + '-' + dd;
    }

    function calcularFechaVtoDesdeArticulo(vencimientoEnDias) {
        // Misma base que el backend: día de emisión (hoy), no fecha de cabecera/OC.
        return addDaysYmd(fechaHoyYmd(), vencimientoEnDias);
    }

    function fechaHoyYmd() {
        var d = new Date();
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function fechaHoyDmY() {
        var ymd = fechaHoyYmd();
        var p = ymd.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function fechaRecepcionDmY() {
        // Compat: preview de etiqueta usa fecha del día (emisión).
        return fechaHoyDmY();
    }

    function recalcularPesoNetoCampos($bruto, $tara, $neto) {
        var bruto = Number($bruto.val());
        var tara = Number($tara.val());
        if (isNaN(tara) || tara < 0) {
            tara = 0;
            $tara.val('0');
        }
        if (bruto > 0) {
            var neto = Math.round((bruto - tara) * 10000) / 10000;
            $neto.val(neto > 0 ? String(neto) : '');
        }
    }

    function recalcularPesoNeto() {
        recalcularPesoNetoCampos($('#peso_bruto'), $('#peso_tara'), $('#peso_neto'));
    }

    function recalcularPesoNetoModal() {
        recalcularPesoNetoCampos($('#etiq_bruto'), $('#etiq_tara'), $('#etiq_neto'));
        actualizarPreviewLocal();
    }

    function esTeclaEnter(e) {
        if (!e) return false;
        return e.key === 'Enter'
            || e.code === 'Enter'
            || e.which === 13
            || e.keyCode === 13
            || e.keyCode === 10;
    }

    function camposNavegables($scope, selector) {
        var $base = $scope && $scope.length ? $scope.find(selector) : $(selector);
        return $base.filter(function () {
            var $el = $(this);
            if (!$el.is(':visible')) return false;
            if ($el.prop('readonly') || $el.prop('disabled')) return false;
            var t = String($el.attr('type') || '').toLowerCase();
            return t !== 'hidden' && t !== 'submit' && t !== 'button' && t !== 'checkbox'
                && t !== 'radio' && t !== 'file' && t !== 'image';
        });
    }

    function avanzarConEnter($campos, e, onLast) {
        if (!esTeclaEnter(e)) {
            return false;
        }
        var $el = $(e.currentTarget && e.currentTarget.nodeType ? e.currentTarget : e.target);
        if ($el.is('textarea')) {
            return false;
        }
        e.preventDefault();
        e.stopPropagation();
        var $list = $campos;
        if (!$list || !$list.length) {
            return true;
        }
        // Preferir el elemento del handler (this/currentTarget) para el índice.
        var nodo = e.currentTarget && e.currentTarget.nodeType === 1 ? e.currentTarget : e.target;
        var idx = $list.index(nodo);
        if (idx < 0) {
            idx = $list.index($el.get(0));
        }
        if (idx < 0) {
            return true;
        }
        if (idx >= $list.length - 1) {
            if (typeof onLast === 'function') {
                onLast($el);
            }
            return true;
        }
        var $next = $list.eq(idx + 1);
        $next.trigger('focus');
        if ($next.is('input') && !$next.is('[type=date],[type=checkbox],[type=radio],[type=number]')) {
            try { $next.select(); } catch (err) { /* ignore */ }
        } else if ($next.is('input[type=number],input[type=date]')) {
            try { $next.select(); } catch (err2) { /* ignore */ }
        }
        return true;
    }

    function bindEnterEnContenedor(contenedorSelector, campoSelector, onLast) {
        var sel = contenedorSelector + ' ' + campoSelector;
        $(document).off('keydown.surmarNav', sel).on('keydown.surmarNav', sel, function (e) {
            if (!esTeclaEnter(e)) return;
            var $cont = $(this).closest(contenedorSelector);
            var $list = camposNavegables($cont.length ? $cont : $(contenedorSelector), campoSelector);
            avanzarConEnter($list, e, onLast);
        });
    }

    function setSeparaDefault() {
        var defId = cfg.separaDefaultId || 2;
        if ($('#etiq_separa option[value="' + defId + '"]').length) {
            $('#etiq_separa').val(String(defId));
        } else if (unidades.length) {
            $('#etiq_separa').val(String(unidades[0].id));
        }
    }

    function abrevSepara(id) {
        var um = unidades.find(function (u) { return String(u.id) === String(id); });
        return um ? (um.abreviatura || 'UN') : 'UN';
    }

    function loteDesdeCertificadoCabecera() {
        var vivo = $.trim($('#certificado_senasa').val() || '');
        if (vivo) {
            cfg.certificadoSenasa = vivo;
            return vivo;
        }
        return $.trim(cfg.certificadoSenasa || '');
    }

    function aplicarLoteDesdeCabecera() {
        var loteCab = loteDesdeCertificadoCabecera();
        if (loteCab) {
            $('#lote_proveedor').val(loteCab);
        }
        return loteCab;
    }

    function renderOc() {
        if (!$tbodyOc.length) return;
        $tbodyOc.empty();
        if (!lineasOc.length) {
            $tbodyOc.append(
                $('<tr/>').append(
                    $('<td colspan="9" class="text-center text-muted py-3"/>')
                        .text('Sin líneas pendientes en la OC (o ya cubiertas por COM confirmadas).')
                )
            );
            return;
        }
        lineasOc.forEach(function (l) {
            var tr = $('<tr/>').attr('data-oc-art-id', l.ordencompra_articulo_id);
            if (String(ocElegidaId) === String(l.ordencompra_articulo_id)) {
                tr.addClass('js-oc-elegida');
            }
            var $acc = $('<td class="text-nowrap"/>');
            if (cfg.editable) {
                $acc.append(
                    $('<button type="button" class="btn btn-warning btn-xs js-elegir-oc mr-1"/>')
                        .text('Elegir')
                );
            }
            if (cfg.entregaSemanal && (l.tiene_entregas_semanales || (l.entregas_semanales && l.entregas_semanales.length))) {
                $acc.append(
                    $('<button type="button" class="btn btn-outline-info btn-xs js-ver-entregas-oc mr-1"/>')
                        .attr('title', 'Ver entregas semanales de la línea OC')
                        .html('<i class="fa fa-calendar"></i>')
                );
            }
            if (cfg.entregaSemanal && (cfg.ordencompraId || (cfg.urls && cfg.urls.entregasSemanalesOrden))) {
                $acc.append(
                    $('<button type="button" class="btn btn-outline-primary btn-xs js-ver-entregas-oc-orden mr-1"/>')
                        .attr('title', 'Ver entregas semanales de toda la orden')
                        .html('<i class="fa fa-calendar-check-o"></i>')
                );
            }
            if (cfg.puedeConsultarOc && cfg.urls && cfg.urls.consultarOc) {
                $acc.append(
                    $('<a class="btn btn-info btn-xs js-consultar-oc" target="_blank"/>')
                        .attr('href', cfg.urls.consultarOc)
                        .attr('title', 'Ver orden de compra')
                        .html('<i class="fa fa-file-text-o"></i> Ver OC')
                );
            }
            tr.append($acc);
            tr.append($('<td/>').text(l.sku || ''));
            tr.append($('<td/>').text(l.descripcion || ''));
            tr.append($('<td class="text-right"/>').text(fmt(l.cantidad_oc)));
            tr.append($('<td class="text-right"/>').text(fmt(l.cantidad_recibida)));
            tr.append($('<td class="text-right"/>').text(fmt(l.cantidad_pendiente)));
            tr.append($('<td class="text-right"/>').text(fmtPeso(l.peso_unitario)));
            tr.append($('<td class="text-right"/>').text(fmtPeso(l.peso_total)));
            tr.append($('<td class="text-right text-nowrap"/>').text(fmtPrecioConMoneda(l.precio, l.moneda_abreviatura)));
            tr.data('ocLinea', l);
            $tbodyOc.append(tr);
        });
    }

    function actualizarOrigenArticulo(tipo) {
        var $lab = $('#surmar-origen-articulo');
        if (!$lab.length) return;
        var artId = $('#articulo_id').val();
        var ocArt = $('#ordencompra_articulo_id').val();
        if (!artId) {
            $lab.text('Sin artículo');
            return;
        }
        if (tipo === 'OC' || ocArt) {
            $lab.html('<span class="badge badge-info">OC</span> Línea de orden de compra');
        } else {
            $lab.html('<span class="badge badge-warning">EXTRA</span> Fuera de la OC');
        }
    }

    function limpiarVinculoOc() {
        ocElegidaId = null;
        $('#ordencompra_articulo_id').val('');
        $('#precio_oc').val('');
        renderOc();
    }

    function elegirLineaOc(l) {
        if (!l || !cfg.editable) return;
        ocElegidaId = l.ordencompra_articulo_id;
        $('#ordencompra_articulo_id').val(l.ordencompra_articulo_id || '');
        $('#articulo_id').val(l.articulo_id || '');
        $('#codigoarticulo').val(l.sku || '');
        $('#descripcionarticulo').val(l.descripcion || '');
        $('#precio_oc').val(l.precio != null ? l.precio : '');
        var vto = calcularFechaVtoDesdeArticulo(l.vencimientoendia);
        $('#fecha_vto').val(vto || '');
        aplicarLoteDesdeCabecera();
        actualizarOrigenArticulo('OC');
        renderOc();
        $('#lote_proveedor').focus();
        $msg.text(
            vto
                ? ('Línea OC seleccionada — vto ' + vto + ' (' + (Number(l.vencimientoendia) || 0) + ' días)')
                : 'Línea OC seleccionada — complete lote y pesos'
        ).removeClass('text-danger text-success').addClass('text-muted');
    }

    function aplicarArticuloExtra(art) {
        if (!art || !cfg.editable) return;
        limpiarVinculoOc();
        $('#articulo_id').val(art.id || art.articulo_id || '');
        $('#codigoarticulo').val(art.sku || art.codigo || '');
        $('#descripcionarticulo').val(art.descripcion || art.nombre || '');
        var vto = calcularFechaVtoDesdeArticulo(art.vencimientoendia);
        if (vto) {
            $('#fecha_vto').val(vto);
        }
        aplicarLoteDesdeCabecera();
        actualizarOrigenArticulo('EXTRA');
        $msg.text('Artículo EXTRA (fuera de OC) — complete lote y pesos').removeClass('text-danger text-success').addClass('text-muted');
    }

    function resolverArticuloPorSku(sku, cb) {
        sku = $.trim(sku || '');
        if (!sku) {
            if (cb) cb(null);
            return;
        }
        var base = cfg.urls.carpetaBase || '';
        $.getJSON(base + '/stock/leerunarticuloporsku/' + encodeURIComponent(sku))
            .done(function (data) {
                if (!data || !(data.id || data.articulo_id)) {
                    if (cb) cb(null);
                    return;
                }
                if (cb) cb(data);
            })
            .fail(function () {
                if (cb) cb(null);
            });
    }

    /** @type {Object.<string, boolean>} */
    var bloquesColapsados = {};

    function claveGrupoLinea(l) {
        var oc = l.ordencompra_articulo_id ? ('oc-' + l.ordencompra_articulo_id) : 'extra';
        return oc + '-art-' + (l.articulo_id || 0);
    }

    function agruparLineas() {
        var grupos = [];
        var map = {};
        lineas.forEach(function (l) {
            var k = claveGrupoLinea(l);
            if (!map[k]) {
                map[k] = {
                    key: k,
                    articulo_id: l.articulo_id,
                    ordencompra_articulo_id: l.ordencompra_articulo_id || null,
                    tipo_linea: l.tipo_linea || (l.ordencompra_articulo_id ? 'OC' : 'EXTRA'),
                    codigo: l.codigo || '',
                    descripcion: l.descripcion || '',
                    lineas: []
                };
                grupos.push(map[k]);
            }
            map[k].lineas.push(l);
        });
        return grupos;
    }

    function totalesGrupo(g) {
        var t = { piezas: 0, bruto: 0, tara: 0, neto: 0, etiquetas: g.lineas.length };
        g.lineas.forEach(function (l) {
            t.piezas += Number(l.cant_pieza) || 0;
            t.bruto += Number(l.peso_bruto) || 0;
            t.tara += Number(l.peso_tara) || 0;
            t.neto += Number(l.peso_neto) || 0;
        });
        return t;
    }

    function renderFilaEtiqueta(l, idx) {
        var tr = $('<tr/>');
        tr.append($('<td class="text-nowrap"/>').text(l.orden || (idx + 1)));
        tr.append($('<td class="hora-carga text-nowrap"/>').text(l.hora_piqueo || '—'));
        tr.append($('<td class="text-nowrap"/>').text(l.lote_proveedor || ''));
        tr.append($('<td/>').text(l.separa_abreviatura || abrevSepara(l.separa_unidadmedida_id) || '—'));
        tr.append($('<td class="text-right"/>').text(l.cant_unid_separa || 1));
        tr.append($('<td class="text-right"/>').text(l.nro_apertura || 1));
        tr.append($('<td class="text-nowrap"/>').text(l.fecha_vto || ''));
        tr.append($('<td class="text-right"/>').text(fmt(l.cant_pieza)));
        tr.append($('<td class="text-right"/>').text(fmt(l.peso_bruto)));
        tr.append($('<td class="text-right"/>').text(fmt(l.peso_tara)));
        tr.append($('<td class="text-right"/>').text(fmt(l.peso_neto)));
        var $etiq = $('<td class="text-nowrap text-center align-middle"/>');
        if (l.stock_etiqueta_id) {
            $etiq.append(
                $('<button type="button" class="btn btn-xs btn-outline-secondary js-preview-linea"/>')
                    .attr('data-id', l.id)
                    .attr('title', 'Preview / editar etiqueta')
                    .html('<i class="fa fa-eye"></i> #' + l.stock_etiqueta_id)
            );
        } else {
            $etiq.text('—');
        }
        tr.append($etiq);
        var $acc = $('<td class="text-nowrap text-center align-middle"/>');
        if (cfg.editable) {
            $acc.append(
                $('<button type="button" class="btn-accion-tabla js-editar-linea" title="Modificar etiqueta"/>')
                    .attr('data-id', l.id)
                    .html('<i class="fa fa-edit"></i>')
            );
            if (l.stock_etiqueta_id) {
                $acc.append(
                    $('<button type="button" class="btn-accion-tabla js-reimprimir-linea" title="Reimprimir"/>')
                        .attr('data-id', l.id)
                        .html('<i class="fa fa-print"></i>')
                );
            }
            $acc.append(
                $('<button type="button" class="btn-accion-tabla text-danger js-quitar-linea" title="Borrar etiqueta"/>')
                    .attr('data-id', l.id)
                    .html('<i class="fa fa-times-circle"></i>')
            );
        } else if (l.stock_etiqueta_id) {
            $acc.append(
                $('<button type="button" class="btn-accion-tabla js-preview-linea" title="Ver / reimprimir"/>')
                    .attr('data-id', l.id)
                    .html('<i class="fa fa-eye"></i>')
            );
            $acc.append(
                $('<button type="button" class="btn-accion-tabla js-reimprimir-linea" title="Reimprimir"/>')
                    .attr('data-id', l.id)
                    .html('<i class="fa fa-print"></i>')
            );
        }
        tr.append($acc);
        return tr;
    }

    function render() {
        var $tb = $('#tabla-items-recepcion-surmar tbody');
        var $vacio = $('#surmar-lineas-vacio');
        if (!$tb.length) {
            return;
        }
        $tb.empty();
        var totalNeto = 0;
        lineas.forEach(function (l) {
            totalNeto += Number(l.peso_neto) || 0;
        });
        var grupos = agruparLineas();
        if (!grupos.length) {
            $vacio.removeClass('d-none');
        } else {
            $vacio.addClass('d-none');
        }
        grupos.forEach(function (g) {
            var colapsado = !!bloquesColapsados[g.key];
            var tot = totalesGrupo(g);
            var esExtra = String(g.tipo_linea).toUpperCase() === 'EXTRA' || !g.ordencompra_articulo_id;
            var $tr = $('<tr class="surmar-item-principal"/>')
                .attr('data-grupo', g.key)
                .toggleClass('surmar-item-extra', esExtra);
            $tr.append(
                $('<td class="text-center align-middle"/>').append(
                    $('<button type="button" class="btn btn-sm btn-outline-secondary js-toggle-bloque"/>')
                        .attr('data-grupo', g.key)
                        .attr('title', colapsado ? 'Expandir etiquetas' : 'Colapsar etiquetas')
                        .html(colapsado
                            ? '<i class="fa fa-chevron-right"></i>'
                            : '<i class="fa fa-chevron-down"></i>')
                )
            );
            $tr.append(
                $('<td class="align-middle"/>').append(
                    $('<span class="badge"/>')
                        .addClass(esExtra ? 'badge-warning' : 'badge-info')
                        .text(esExtra ? 'EXTRA' : 'OC')
                )
            );
            $tr.append($('<td class="text-nowrap align-middle"/>').text(g.codigo || ''));
            $tr.append($('<td class="align-middle"/>').text(g.descripcion || ''));
            $tr.append(
                $('<td class="text-right align-middle surmar-total-derivado"/>')
                    .attr('title', 'Cantidad de etiquetas')
                    .text(tot.etiquetas)
            );
            $tr.append(
                $('<td class="text-right align-middle surmar-total-derivado"/>')
                    .attr('title', 'Suma de piezas (etiquetas)')
                    .text(fmt(tot.piezas))
            );
            $tr.append(
                $('<td class="text-right align-middle surmar-total-derivado"/>')
                    .attr('title', 'Suma bruto (etiquetas)')
                    .text(fmt(tot.bruto))
            );
            $tr.append(
                $('<td class="text-right align-middle surmar-total-derivado"/>')
                    .attr('title', 'Suma tara (etiquetas)')
                    .text(fmt(tot.tara))
            );
            $tr.append(
                $('<td class="text-right align-middle surmar-total-derivado"/>')
                    .attr('title', 'Suma neto (etiquetas)')
                    .text(fmt(tot.neto))
            );
            var $acc = $('<td class="text-nowrap text-center align-middle"/>');
            if (cfg.entregaSemanal && g.ordencompra_articulo_id) {
                var ocLinEnt = lineasOc.find(function (x) {
                    return String(x.ordencompra_articulo_id) === String(g.ordencompra_articulo_id);
                });
                if (ocLinEnt && (ocLinEnt.tiene_entregas_semanales || (ocLinEnt.entregas_semanales && ocLinEnt.entregas_semanales.length))) {
                    $acc.append(
                        $('<button type="button" class="btn-accion-tabla js-ver-entregas-oc" title="Ver entregas semanales OC"/>')
                            .data('ocLinea', ocLinEnt)
                            .html('<i class="fa fa-calendar text-info"></i>')
                    );
                }
            }
            if (cfg.editable) {
                $acc.append(
                    $('<button type="button" class="btn-accion-tabla js-agregar-en-bloque" title="Agregar etiqueta de este artículo"/>')
                        .attr('data-grupo', g.key)
                        .html('<i class="fa fa-plus text-primary"></i>')
                );
            }
            $tr.append($acc);
            $tr.data('grupoData', g);
            $tb.append($tr);

            var $detail = $('<tr class="surmar-item-etiquetas"/>')
                .attr('data-grupo', g.key)
                .toggleClass('d-none', colapsado);
            var $cell = $('<td colspan="10"/>');
            var $inner = $(
                '<div class="table-responsive">' +
                '<table class="table table-sm table-bordered mb-0 surmar-etiquetas-inner">' +
                '<thead><tr>' +
                '<th>#</th><th>Hora</th><th>Lote</th><th>Separa</th>' +
                '<th class="text-right">Unid</th><th class="text-right">Nro</th><th>Vto</th>' +
                '<th class="text-right">Piezas</th><th class="text-right">Bruto</th>' +
                '<th class="text-right">Tara</th><th class="text-right">Neto</th>' +
                '<th class="text-center">Etiqueta</th><th class="text-center">Acciones</th>' +
                '</tr></thead><tbody></tbody></table></div>'
            );
            var $innerTb = $inner.find('tbody');
            g.lineas.forEach(function (l, idx) {
                $innerTb.append(renderFilaEtiqueta(l, idx));
            });
            $cell.append($inner);
            $detail.append($cell);
            $tb.append($detail);
        });
        $('#surmar-total-items').text(grupos.length + ' ítem(s) / ' + lineas.length + ' etiqueta(s)');
        $('#surmar-total-neto').text(fmt(totalNeto));
    }

    function seleccionarGrupoParaAlta(g) {
        if (!g) return;
        if (g.ordencompra_articulo_id) {
            var oc = lineasOc.find(function (x) {
                return String(x.ordencompra_articulo_id) === String(g.ordencompra_articulo_id);
            });
            if (oc) {
                elegirLineaOc(oc);
                return;
            }
            ocElegidaId = g.ordencompra_articulo_id;
            $('#ordencompra_articulo_id').val(g.ordencompra_articulo_id);
            $('#articulo_id').val(g.articulo_id || '');
            $('#codigoarticulo').val(g.codigo || '');
            $('#descripcionarticulo').val(g.descripcion || '');
            aplicarLoteDesdeCabecera();
            actualizarOrigenArticulo('OC');
            return;
        }
        aplicarArticuloExtra({
            id: g.articulo_id,
            sku: g.codigo,
            descripcion: g.descripcion
        });
    }

    function precargarDesdeLineaCargada(linea) {
        if (!linea) return;
        if (linea.ordencompra_articulo_id) {
            var oc = lineasOc.find(function (x) {
                return String(x.ordencompra_articulo_id) === String(linea.ordencompra_articulo_id);
            });
            if (oc) {
                elegirLineaOc(oc);
                return;
            }
            ocElegidaId = linea.ordencompra_articulo_id;
            $('#ordencompra_articulo_id').val(linea.ordencompra_articulo_id);
            $('#articulo_id').val(linea.articulo_id || '');
            $('#codigoarticulo').val(linea.codigo || '');
            $('#descripcionarticulo').val(linea.descripcion || '');
            aplicarLoteDesdeCabecera();
            actualizarOrigenArticulo('OC');
            return;
        }
        aplicarArticuloExtra({
            id: linea.articulo_id,
            sku: linea.codigo,
            descripcion: linea.descripcion
        });
        if (linea.lote_proveedor) {
            $('#lote_proveedor').val(linea.lote_proveedor);
        }
        if (linea.fecha_vto) {
            $('#fecha_vto').val(linea.fecha_vto);
        }
    }

    function iniciarArticuloExtra() {
        if (!cfg.editable) return;
        limpiarVinculoOc();
        $('#articulo_id').val('');
        $('#codigoarticulo').val('');
        $('#descripcionarticulo').val('');
        aplicarLoteDesdeCabecera();
        actualizarOrigenArticulo('EXTRA');
        $msg.text('Artículo fuera de OC — use la lupa (F1) o escriba el SKU').removeClass('text-danger text-success').addClass('text-muted');
        var $lupa = $('#surmar-nuevo-item-campos .consultaarticulo').first();
        if ($lupa.length) {
            $lupa.trigger('click');
        } else {
            $('#codigoarticulo').trigger('focus');
        }
    }

    function limpiarForm() {
        // Conserva la línea OC elegida para etiquetar más unidades o el mismo artículo.
        aplicarLoteDesdeCabecera();
        limpiarPesosForm();
    }

    function token() {
        return cfg.urls.token || $('input[name="_token"]').val() || '';
    }

    var DESTINO_KEY = 'surmar_etiqueta_destino';

    function destinoEtiqueta() {
        var desdeRadio = $('input[name="etiq_destino"]:checked').val();
        if (desdeRadio === 'pdf' || desdeRadio === 'impresora') {
            return desdeRadio;
        }
        try {
            var ls = localStorage.getItem(DESTINO_KEY);
            if (ls === 'pdf' || ls === 'impresora') return ls;
        } catch (e) { /* ignore */ }
        return cfg.destinoEtiquetaDefault === 'pdf' ? 'pdf' : 'impresora';
    }

    function setDestinoEtiqueta(destino) {
        destino = destino === 'pdf' ? 'pdf' : 'impresora';
        try { localStorage.setItem(DESTINO_KEY, destino); } catch (e) { /* ignore */ }
        $('#etiq_destino_impresora').prop('checked', destino === 'impresora');
        $('#etiq_destino_pdf').prop('checked', destino === 'pdf');
        $('#etiq_destino_lbl_impresora').toggleClass('active', destino === 'impresora');
        $('#etiq_destino_lbl_pdf').toggleClass('active', destino === 'pdf');
    }

    function aplicarDestinoInicial() {
        setDestinoEtiqueta(destinoEtiqueta());
    }

    /**
     * Imprime según destino: impresora de red (server seteosalida) o PDF.
     * opts: { etiquetaId, zpl, zpls, copias }
     */
    function imprimirEtiquetas(opts) {
        opts = opts || {};
        var destino = destinoEtiqueta();
        var copias = Math.max(1, parseInt(opts.copias != null ? opts.copias : $('#etiq_copias').val(), 10) || 1);

        if (destino === 'pdf') {
            var idPdf = opts.etiquetaId || $('#etiq_linea_id').data('etiquetaId');
            if (!idPdf) {
                alert('Guarde la etiqueta antes de generar PDF.');
                return;
            }
            var basePdf = cfg.urls.pdfEtiqueta || cfg.urls.zpl;
            window.open(basePdf + '/' + idPdf + '/pdf', '_blank');
            return;
        }

        // Impresora de red (servidor → Zebra / CUPS)
        if (!cfg.urls.imprimirSalida) {
            alert('Falta ruta de impresión. Recargue la pantalla.');
            return;
        }

        var payload = { _token: token(), copias: copias };
        if (opts.zpls && opts.zpls.length) {
            payload.zpls = opts.zpls;
        } else if (opts.zpl) {
            payload.zpl = opts.zpl;
        } else if (opts.etiquetaId) {
            payload.etiqueta_id = opts.etiquetaId;
        } else {
            var eid = $('#etiq_linea_id').data('etiquetaId');
            if (!eid) {
                alert('No hay etiqueta para imprimir.');
                return;
            }
            payload.etiqueta_id = eid;
        }

        if (!opts.sinOverlay) {
            mostrarOverlay('Enviando a impresora de red…');
        }
        $.ajax({
            url: cfg.urls.imprimirSalida,
            method: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (res) {
            if (res && res.ok) {
                $msg.text(res.mensaje || 'Impreso').removeClass('text-muted text-danger').addClass('text-success');
            } else {
                var m = (res && res.mensaje) ? res.mensaje : 'No se pudo imprimir.';
                alert(m);
                $msg.text(m).removeClass('text-success').addClass('text-danger');
            }
        }).fail(function (xhr) {
            var m = 'No se pudo enviar a la impresora.';
            if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                m = xhr.responseJSON.mensaje;
            }
            alert(m);
        }).always(function () {
            if (!opts.sinOverlay) {
                ocultarOverlay();
            }
        });
    }

    function imprimirZpl(zpl) {
        imprimirEtiquetas({ zpl: zpl });
    }

    function imprimirZpls(zpls) {
        imprimirEtiquetas({ zpls: zpls });
    }

    function descargarZpl(zpl) {
        var blob = new Blob([zpl], { type: 'text/plain' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'etiqueta_surmar.zpl';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            a.remove();
        }, 500);
    }

    function pintarPreview(data) {
        var $box = $('#surmar-preview-label');
        if (!$box.length || !data) return;
        $box.find('[data-k]').each(function () {
            var k = $(this).attr('data-k');
            $(this).text(data[k] != null && data[k] !== '' ? data[k] : '—');
        });
    }

    function actualizarPreviewLocal() {
        var separaId = $('#etiq_separa').val();
        var abrev = abrevSepara(separaId);
        var cantUnid = Math.max(1, parseInt($('#etiq_cant_unid').val(), 10) || 1);
        var nro = Math.max(1, parseInt($('#etiq_nro_apertura').val(), 10) || 1);
        var lote = $.trim($('#etiq_lote').val() || '');
        var vto = $('#etiq_vto').val() || '';
        var vtoFmt = '';
        if (vto && vto.indexOf('-') >= 0) {
            var p = vto.split('-');
            vtoFmt = p[2] + '/' + p[1] + '/' + p[0];
        }
        var neto = parseFloat($('#etiq_neto').val()) || 0;
        var piezas = parseFloat($('#etiq_piezas').val()) || 0;
        var prom = '';
        if (piezas > 0.0000001) {
            prom = fmt(neto / piezas);
        }
        pintarPreview({
            id: $('#etiq_modo').val() === 'edicion' ? ($('#etiq_linea_id').data('etiquetaId') || '—') : '(nuevo)',
            codigo_articulo: $('#etiq_sku').val() || '',
            proveedor: cfg.proveedorNombre || '',
            descripcion: $('#etiq_descripcion').val() || '',
            peso_bruto: fmt($('#etiq_bruto').val()),
            peso_neto: fmt($('#etiq_neto').val()),
            cant_pieza: fmt($('#etiq_piezas').val()),
            peso_promedio: prom || '—',
            linea_separa: abrev + ': ' + cantUnid + ' - Nro.: ' + nro,
            fecha: fechaHoyDmY(),
            fecha_vto: vtoFmt,
            lote: lote ? (lote + '/' + nro) : String(nro)
        });
    }

    function abrirModalAlta() {
        if (!cfg.editable) return;
        recalcularPesoNeto();
        var ocArtId = $('#ordencompra_articulo_id').val();
        var artId = $('#articulo_id').val();
        if (!artId) {
            alert('Elija una línea de la OC o consulte un artículo (fuera de OC).');
            $('#codigoarticulo').focus();
            return;
        }
        var lote = $.trim($('#lote_proveedor').val()) || loteDesdeCertificadoCabecera();
        if (!lote) {
            alert('Ingrese el lote (o certificado SENASA en encabezado).');
            $('#lote_proveedor').focus();
            return;
        }

        contextoAlta = {
            ordencompra_articulo_id: ocArtId || '',
            articulo_id: artId,
            precio: $('#precio_oc').val() || 0
        };

        procesoEtiqueta = { total: 1, actual: 1, locked: false };

        $('#etiq_modo').val('alta');
        $('#etiq_linea_id').val('').removeData('etiquetaId');
        $('#etiq_sku').val($('#codigoarticulo').val());
        $('#etiq_descripcion').val($('#descripcionarticulo').val());
        $('#etiq_lote').val(lote);
        $('#etiq_piezas').val($('#cant_pieza').val() || 1);
        $('#etiq_bruto').val($('#peso_bruto').val() || '');
        $('#etiq_tara').val($('#peso_tara').val() || 0);
        $('#etiq_neto').val($('#peso_neto').val() || '');
        $('#etiq_vto').val($('#fecha_vto').val() || '');
        $('#etiq_cant_unid').val(1).prop('readonly', false);
        $('#etiq_nro_apertura').val(1).prop('readonly', true);
        $('#etiq_nro_ayuda').text('Indique «Cantidad que separa». Cada Imprime guarda una unidad y pasa a la siguiente.');
        $('#etiq_copias').val(1);
        setSeparaDefault();
        $('#btn-etiq-guardar-imprimir').prop('disabled', false)
            .html('<i class="fa fa-print"></i> Imprime y siguiente');
        $('#btn-etiq-guardar').prop('disabled', false);
        actualizarBadgeProceso();
        actualizarPreviewLocal();
        // Alta rápida sin datos previos: foco en piezas (luego Enter → bruto → … → Imprime).
        enfocarCampoModalEtiqueta('#etiq_piezas');
        $('#modalEtiquetaProveedorSurmar').modal('show');
    }

    function abrirModalEdicion(linea, forzarImprimir) {
        if (!linea) return;
        finProcesoEtiqueta();
        contextoAlta = null;
        $('#etiq_modo').val(cfg.editable ? 'edicion' : 'solo_lectura');
        $('#etiq_linea_id').val(linea.id).data('etiquetaId', linea.stock_etiqueta_id || '');
        $('#etiq_sku').val(linea.codigo || '');
        $('#etiq_descripcion').val(linea.descripcion || '');
        $('#etiq_lote').val(linea.lote_proveedor || '');
        $('#etiq_piezas').val(linea.cant_pieza || 1);
        $('#etiq_bruto').val(linea.peso_bruto || '');
        $('#etiq_tara').val(linea.peso_tara || 0);
        $('#etiq_neto').val(linea.peso_neto || '');
        $('#etiq_vto').val(linea.fecha_vto || '');
        $('#etiq_cant_unid').val(linea.cant_unid_separa || 1).prop('readonly', !cfg.editable);
        $('#etiq_nro_apertura').val(linea.nro_apertura || 1).prop('readonly', !cfg.editable);
        $('#etiq_nro_ayuda').text(cfg.editable
            ? 'Modifique y Guarde, o Imprime (guarda e imprime esta etiqueta).'
            : 'Solo lectura — puede reimprimir.');
        $('#etiq_copias').val(1);
        if (linea.separa_unidadmedida_id) {
            $('#etiq_separa').val(String(linea.separa_unidadmedida_id));
        } else {
            setSeparaDefault();
        }
        $('#btn-etiq-guardar-imprimir, #btn-etiq-guardar').prop('disabled', !cfg.editable);
        if (!cfg.editable) {
            $('#btn-etiq-guardar-imprimir').prop('disabled', false).html('<i class="fa fa-print"></i> Reimprimir');
        } else {
            $('#btn-etiq-guardar-imprimir').html('<i class="fa fa-print"></i> Imprime');
        }
        actualizarBadgeProceso();
        actualizarPreviewLocal();
        $('#modalEtiquetaProveedorSurmar').modal('show');

        if (linea.stock_etiqueta_id && cfg.urls.previewEtiqueta) {
            $.getJSON(cfg.urls.previewEtiqueta + '/' + linea.stock_etiqueta_id + '/preview')
                .done(function (res) {
                    if (res && res.preview) {
                        pintarPreview(res.preview);
                    }
                });
        }

        if (forzarImprimir && linea.stock_etiqueta_id) {
            setTimeout(function () {
                imprimirEtiquetas({ etiquetaId: linea.stock_etiqueta_id });
            }, 400);
        }
    }

    function payloadDesdeModal() {
        recalcularPesoNetoModal();
        return {
            lote_proveedor: $.trim($('#etiq_lote').val()),
            certificado: loteDesdeCertificadoCabecera() || $.trim($('#etiq_lote').val()),
            fecha_vto: $('#etiq_vto').val() || null,
            cant_pieza: $('#etiq_piezas').val() || 1,
            peso_bruto: $('#etiq_bruto').val() || '',
            peso_tara: $('#etiq_tara').val() || 0,
            peso_neto: $('#etiq_neto').val(),
            separa_unidadmedida_id: $('#etiq_separa').val(),
            cant_unid_separa: $('#etiq_cant_unid').val() || 1,
            nro_apertura: $('#etiq_nro_apertura').val() || 1,
            copias: $('#etiq_copias').val() || 1
        };
    }

    function validarModal() {
        var p = payloadDesdeModal();
        if (!p.lote_proveedor) {
            alert('Ingrese el lote / certificado.');
            $('#etiq_lote').focus();
            return null;
        }
        if (!p.separa_unidadmedida_id) {
            alert('Indique en qué separa.');
            $('#etiq_separa').focus();
            return null;
        }
        if (!(Number(p.peso_neto) > 0)) {
            alert('Peso neto inválido.');
            return null;
        }
        return p;
    }

    function guardarDesdeModal(imprimir) {
        var p = validarModal();
        if (!p) return;
        p._token = token();
        p.imprimir = imprimir ? 1 : 0;
        actualizarPreviewLocal();

        var modo = $('#etiq_modo').val();
        if (modo === 'solo_lectura') {
            if (!imprimir) return;
            var etiqId = $('#etiq_linea_id').data('etiquetaId');
            if (!etiqId) return;
            imprimirEtiquetas({ etiquetaId: etiqId, copias: $('#etiq_copias').val() || 1 });
            return;
        }

        mostrarOverlay(imprimir ? 'Grabando e imprimiendo…' : 'Grabando etiqueta…');

        if (modo === 'alta') {
            if (!contextoAlta) {
                ocultarOverlay();
                return;
            }
            var totalLote = Math.max(1, Math.min(50, parseInt(p.cant_unid_separa, 10) || 1));
            var nroActual = Math.max(1, parseInt(p.nro_apertura, 10) || 1);
            if (!procesoEtiqueta) {
                procesoEtiqueta = { total: totalLote, actual: nroActual, locked: false };
            }
            if (!procesoEtiqueta.locked) {
                procesoEtiqueta.total = totalLote;
                procesoEtiqueta.actual = nroActual;
            }
            p.cant_unid_separa = procesoEtiqueta.total;
            p.nro_apertura = procesoEtiqueta.actual;
            $.extend(p, contextoAlta);
            if (!p.ordencompra_articulo_id) {
                delete p.ordencompra_articulo_id;
            }
            $.ajax({
                url: cfg.urls.guardarLinea,
                method: 'POST',
                data: p,
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.ok) {
                    $msg.text('Error al grabar').addClass('text-danger');
                    return;
                }
                (res.lineas_creadas || [res.linea]).forEach(function (l) {
                    lineas.push(l);
                });
                if (Array.isArray(res.lineas_oc)) {
                    lineasOc = res.lineas_oc;
                }
                render();
                renderOc();
                sincronizarPesosAlFormulario();
                if (res.preview) pintarPreview(res.preview);
                $msg.text(res.mensaje || 'Grabado').removeClass('text-muted text-danger').addClass('text-success');
                if (imprimir) {
                    var etiqId = res.etiqueta_id || (res.linea && res.linea.stock_etiqueta_id);
                    var printOpts = { etiquetaId: etiqId, sinOverlay: true };
                    if (destinoEtiqueta() === 'pdf') {
                        if (etiqId) imprimirEtiquetas(printOpts);
                    } else if (res.zpls && res.zpls.length) {
                        printOpts.zpls = res.zpls;
                        imprimirEtiquetas(printOpts);
                    } else if (res.zpl) {
                        printOpts.zpl = res.zpl;
                        imprimirEtiquetas(printOpts);
                    } else if (etiqId) {
                        imprimirEtiquetas(printOpts);
                    }
                }
                var total = procesoEtiqueta ? procesoEtiqueta.total : 1;
                var actual = procesoEtiqueta ? procesoEtiqueta.actual : 1;
                if (actual < total) {
                    prepararSiguienteUnidadEnModal(actual + 1);
                } else {
                    // Al terminar el lote, colapsa el renglón del artículo (queda totalizado).
                    if (res.linea) {
                        bloquesColapsados[claveGrupoLinea(res.linea)] = true;
                    }
                    finProcesoEtiqueta();
                    limpiarPesosForm();
                    $('#modalEtiquetaProveedorSurmar').modal('hide');
                }
            }).fail(function (xhr) {
                var msg = 'No se pudo grabar.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errs = xhr.responseJSON.errors;
                    msg = Object.keys(errs).map(function (k) { return errs[k].join(' '); }).join(' ');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }).always(ocultarOverlay);
            return;
        }

        // edición: Guarda / Imprime sobre la misma etiqueta (no avanza secuencia)
        var lineaId = $('#etiq_linea_id').val();
        $.ajax({
            url: cfg.urls.actualizarLinea + '/' + lineaId,
            method: 'POST',
            data: $.extend({}, p, { _method: 'PUT' }),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.ok) return;
            lineas = lineas.map(function (l) {
                return String(l.id) === String(res.linea.id) ? res.linea : l;
            });
            render();
            if (res.preview) pintarPreview(res.preview);
            $msg.text(res.mensaje || 'Actualizado').removeClass('text-muted text-danger').addClass('text-success');
            if (imprimir) {
                var etiqIdEd = res.etiqueta_id || (res.linea && res.linea.stock_etiqueta_id) || $('#etiq_linea_id').data('etiquetaId');
                if (destinoEtiqueta() === 'pdf') {
                    if (etiqIdEd) imprimirEtiquetas({ etiquetaId: etiqIdEd });
                } else if (res.zpls && res.zpls.length) {
                    imprimirEtiquetas({ zpls: res.zpls, etiquetaId: etiqIdEd });
                } else if (res.zpl) {
                    imprimirEtiquetas({ zpl: res.zpl, etiquetaId: etiqIdEd });
                } else if (etiqIdEd) {
                    imprimirEtiquetas({ etiquetaId: etiqIdEd });
                }
            } else {
                $('#modalEtiquetaProveedorSurmar').modal('hide');
            }
        }).fail(function (xhr) {
            var msg = 'No se pudo actualizar.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errs = xhr.responseJSON.errors;
                msg = Object.keys(errs).map(function (k) { return errs[k].join(' '); }).join(' ');
            }
            alert(msg);
        }).always(ocultarOverlay);
    }

    function quitarLinea(lineaId) {
        if (!cfg.editable) return;
        if (!confirm('¿Quitar este ítem y anular su etiqueta?')) return;
        mostrarOverlay('Quitando ítem…');
        $.ajax({
            url: cfg.urls.eliminarLinea + '/' + lineaId,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.ok) return;
            lineas = lineas.filter(function (l) { return String(l.id) !== String(lineaId); });
            if (Array.isArray(res.lineas_oc)) {
                lineasOc = res.lineas_oc;
                renderOc();
            }
            render();
            $msg.text('Ítem quitado').addClass('text-muted');
        }).fail(function () {
            alert('No se pudo quitar el ítem.');
        }).always(ocultarOverlay);
    }

    function lineaPorId(id) {
        return lineas.find(function (l) { return String(l.id) === String(id); });
    }

    $(function () {
        render();
        renderOc();
        actualizarOrigenArticulo();
        $('#btn-agregar-item-surmar').on('click', abrirModalAlta);
        $('#btn-articulo-extra-surmar').on('click', iniciarArticuloExtra);

        var $grid = $('#tabla-items-recepcion-surmar');
        $grid.on('click', '.js-quitar-linea', function () {
            quitarLinea($(this).data('id'));
        });
        $grid.on('click', '.js-editar-linea, .js-preview-linea', function () {
            abrirModalEdicion(lineaPorId($(this).data('id')));
        });
        $grid.on('click', '.js-reimprimir-linea', function () {
            var linea = lineaPorId($(this).data('id'));
            if (!linea || !linea.stock_etiqueta_id) {
                alert('Sin etiqueta para reimprimir.');
                return;
            }
            imprimirEtiquetas({ etiquetaId: linea.stock_etiqueta_id, copias: 1 });
        });
        $grid.on('click', '.js-toggle-bloque', function (e) {
            e.stopPropagation();
            var key = String($(this).data('grupo') || '');
            bloquesColapsados[key] = !bloquesColapsados[key];
            render();
        });
        $grid.on('click', '.js-agregar-en-bloque', function (e) {
            e.stopPropagation();
            var $tr = $(this).closest('tr.surmar-item-principal');
            seleccionarGrupoParaAlta($tr.data('grupoData'));
            // Al agregar, expandimos el bloque para ver la nueva etiqueta
            var key = String($(this).data('grupo') || '');
            if (key) {
                bloquesColapsados[key] = false;
            }
            abrirModalAlta();
        });
        $('#btn-colapsar-todos-bloques').on('click', function () {
            agruparLineas().forEach(function (g) { bloquesColapsados[g.key] = true; });
            render();
        });
        $('#btn-expandir-todos-bloques').on('click', function () {
            bloquesColapsados = {};
            render();
        });

        $('input[name="etiq_destino"]').on('change', function () {
            setDestinoEtiqueta($(this).val());
        });
        aplicarDestinoInicial();
        function fmtFechaEntregaDdMmYyyy(iso) {
            var f = (iso || '').toString();
            if (/^\d{4}-\d{2}-\d{2}$/.test(f)) {
                return f.slice(8, 10) + '/' + f.slice(5, 7) + '/' + f.slice(0, 4);
            }
            return f;
        }

        function fmtCantEntrega(n) {
            var x = Number(n);
            if (!isFinite(x)) {
                return '0';
            }
            return String(parseFloat(x.toFixed(4)));
        }

        function renderEntregasSemanalesOrdenMatrix(payload) {
            var lineas = (payload && payload.lineas) ? payload.lineas : [];
            var fechasSet = {};
            lineas.forEach(function (l) {
                (l.entregas || []).forEach(function (e) {
                    if (e.fecha) {
                        fechasSet[e.fecha] = true;
                    }
                });
            });
            var fechas = Object.keys(fechasSet).sort();
            var $thead = $('#oc-entrega-semanal-resumen-thead').empty();
            var $tbody = $('#oc-entrega-semanal-resumen-tbody').empty();
            var $tfoot = $('#oc-entrega-semanal-resumen-tfoot').empty();
            var $vacio = $('#oc-entrega-semanal-resumen-vacio');

            if (!lineas.length) {
                $vacio.removeClass('d-none').text('No hay artículos en la orden de compra.');
                return;
            }

            var $hr = $('<tr/>');
            $hr.append($('<th class="text-nowrap"/>').text('SKU'));
            $hr.append($('<th/>').text('Descripción'));
            fechas.forEach(function (f) {
                $hr.append(
                    $('<th class="text-right text-nowrap"/>')
                        .attr('title', f)
                        .text(fmtFechaEntregaDdMmYyyy(f))
                );
            });
            $hr.append($('<th class="text-right text-nowrap"/>').text('Total entregas'));
            $hr.append($('<th class="text-right text-nowrap"/>').text('Cant. línea'));
            $thead.append($hr);

            var totPorFecha = {};
            fechas.forEach(function (f) { totPorFecha[f] = 0; });
            var totEntregas = 0;
            var totCantLinea = 0;

            lineas.forEach(function (l) {
                var porFecha = {};
                var totalEnt = 0;
                (l.entregas || []).forEach(function (e) {
                    var fecha = (e.fecha || '').toString();
                    var cant = Number(e.cantidad) || 0;
                    if (!fecha || cant <= 0) {
                        return;
                    }
                    porFecha[fecha] = (porFecha[fecha] || 0) + cant;
                    totalEnt += cant;
                });
                var $tr = $('<tr/>');
                $tr.append($('<td class="text-nowrap"/>').text(l.sku || '—'));
                $tr.append($('<td/>').text(l.descripcion || '—'));
                fechas.forEach(function (f) {
                    var v = porFecha[f] || 0;
                    totPorFecha[f] += v;
                    $tr.append(
                        $('<td class="text-right"/>').text(v > 0 ? fmtCantEntrega(v) : '—')
                    );
                });
                totEntregas += totalEnt;
                totCantLinea += Number(l.cantidad_linea) || 0;
                $tr.append($('<td class="text-right font-weight-bold"/>').text(fmtCantEntrega(totalEnt)));
                $tr.append($('<td class="text-right"/>').text(fmtCantEntrega(l.cantidad_linea)));
                $tbody.append($tr);
            });

            var $fr = $('<tr/>');
            $fr.append($('<th colspan="2" class="text-right"/>').text('Totales'));
            fechas.forEach(function (f) {
                $fr.append($('<th class="text-right"/>').text(fmtCantEntrega(totPorFecha[f])));
            });
            $fr.append($('<th class="text-right"/>').text(fmtCantEntrega(totEntregas)));
            $fr.append($('<th class="text-right"/>').text(fmtCantEntrega(totCantLinea)));
            $tfoot.append($fr);

            if (!fechas.length) {
                $vacio.removeClass('d-none').text('No hay entregas semanales cargadas en esta orden.');
            } else {
                $vacio.addClass('d-none');
            }

            var nOc = (payload && payload.numeroordencompra)
                ? String(payload.numeroordencompra)
                : String(cfg.numeroOrdencompra || '');
            var sub = 'Una fila por artículo; cada columna es una fecha de entrega. Totales por artículo y por fecha.';
            if (nOc && nOc !== '0') {
                sub = 'OC Nº ' + nOc + ' — ' + sub;
            }
            $('#oc-entrega-semanal-resumen-subtitulo').text(sub);
        }

        function mostrarEntregasSemanalesOrden() {
            if (!cfg.entregaSemanal) {
                return;
            }
            var url = (cfg.urls && cfg.urls.entregasSemanalesOrden) || '';
            if (!url && cfg.ordencompraId) {
                var base = (cfg.urls && cfg.urls.carpetaBase) ? cfg.urls.carpetaBase : '';
                url = base + '/compras/ordencompra/' + cfg.ordencompraId + '/entregas-semanales';
            }
            if (!url) {
                alert('No hay orden de compra vinculada.');
                return;
            }
            if (!$('#modalOcEntregaSemanalResumen').length) {
                alert('Modal de entregas semanales no disponible.');
                return;
            }
            $('#oc-entrega-semanal-resumen-thead, #oc-entrega-semanal-resumen-tbody, #oc-entrega-semanal-resumen-tfoot').empty();
            $('#oc-entrega-semanal-resumen-vacio').removeClass('d-none').text('Cargando…');
            $('#modalOcEntregaSemanalResumen').modal('show');
            $.getJSON(url)
                .done(function (res) {
                    if (!res || !res.ok) {
                        $('#oc-entrega-semanal-resumen-vacio').text((res && res.mensaje) || 'No se pudo cargar el resumen.');
                        return;
                    }
                    renderEntregasSemanalesOrdenMatrix(res);
                })
                .fail(function () {
                    $('#oc-entrega-semanal-resumen-vacio').text('Error al consultar entregas semanales de la orden.');
                });
        }

        function mostrarEntregasSemanalesOc(l) {
            if (!l || !cfg.entregaSemanal) {
                return;
            }
            var arr = Array.isArray(l.entregas_semanales) ? l.entregas_semanales : [];
            var sub = ((l.sku || '') + (l.descripcion ? ' — ' + l.descripcion : '')).trim()
                || 'Entregas semanales de la línea OC';
            $('#oc-entrega-semanal-subtitulo').text(sub);
            var $tb = $('#oc-entrega-semanal-tbody');
            $tb.empty();
            var sum = 0;
            if (!arr.length && l.ordencompra_articulo_id && cfg.urls && cfg.urls.entregasSemanales) {
                $.getJSON(cfg.urls.entregasSemanales + '/' + l.ordencompra_articulo_id + '/entregas-semanales')
                    .done(function (res) {
                        if (res && res.ok && Array.isArray(res.entregas)) {
                            l.entregas_semanales = res.entregas;
                            l.tiene_entregas_semanales = res.entregas.length > 0;
                            mostrarEntregasSemanalesOc(l);
                        }
                    });
                return;
            }
            arr.forEach(function (e) {
                var cant = Number(e.cantidad) || 0;
                sum += cant;
                var $tr = $('<tr class="oc-entrega-semanal-renglon"/>');
                $tr.append($('<td/>').append(
                    $('<input type="date" class="form-control form-control-sm oc-entrega-fecha" readonly/>')
                        .val(e.fecha || '')
                ));
                $tr.append($('<td/>').append(
                    $('<input type="number" class="form-control form-control-sm oc-entrega-cantidad text-right" readonly/>')
                        .val(cant)
                ));
                $tr.append($('<td class="text-center text-muted"/>').text('—'));
                $tb.append($tr);
            });
            if (!arr.length) {
                $tb.append(
                    $('<tr/>').append(
                        $('<td colspan="3" class="text-center text-muted py-3"/>')
                            .text('Sin entregas semanales cargadas en esta línea.')
                    )
                );
            }
            $('#oc-entrega-semanal-total').text(String(parseFloat(sum.toFixed(4))));
            $('#modalOcEntregaSemanal').modal('show');
        }

        $tbodyOc.on('click', 'tr', function (e) {
            if ($(e.target).closest('a.js-consultar-oc, .js-consultar-oc, .js-ver-entregas-oc, .js-ver-entregas-oc-orden').length) {
                e.stopPropagation();
                return;
            }
            if (!cfg.editable) return;
            var l = $(this).data('ocLinea');
            if (!l) return;
            if ($(e.target).closest('button, a').length || $(e.target).is('button, a')) {
                e.preventDefault();
            }
            elegirLineaOc(l);
        });
        $tbodyOc.on('click', '.js-ver-entregas-oc', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var l = $(this).closest('tr').data('ocLinea');
            mostrarEntregasSemanalesOc(l);
        });
        $tbodyOc.on('click', '.js-ver-entregas-oc-orden', function (e) {
            e.preventDefault();
            e.stopPropagation();
            mostrarEntregasSemanalesOrden();
        });
        $('#tabla-items-recepcion-surmar').on('click', '.js-ver-entregas-oc', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var l = $(this).data('ocLinea');
            mostrarEntregasSemanalesOc(l);
        });
        $tbodyOc.on('click', 'a.js-consultar-oc', function (e) {
            e.stopPropagation();
        });
        $('#peso_bruto, #peso_tara').on('input change', recalcularPesoNeto);
        $('#etiq_bruto, #etiq_tara').on('input change', recalcularPesoNetoModal);
        $('#etiq_separa, #etiq_cant_unid, #etiq_nro_apertura, #etiq_lote, #etiq_piezas, #etiq_vto, #etiq_neto')
            .on('input change', actualizarPreviewLocal);
        $('#etiq_cant_unid').on('change input', function () {
            if (procesoEtiqueta && !procesoEtiqueta.locked) {
                var t = Math.max(1, Math.min(50, parseInt($(this).val(), 10) || 1));
                procesoEtiqueta.total = t;
                actualizarBadgeProceso();
            }
        });
        $('#btn-etiq-actualizar-preview').on('click', actualizarPreviewLocal);
        $('#btn-etiq-guardar').on('click', function () { guardarDesdeModal(false); });
        $('#btn-etiq-guardar-imprimir').on('click', function () { guardarDesdeModal(true); });
        $('#modalEtiquetaProveedorSurmar').on('shown.bs.modal', function () {
            if (!focoPendienteModalEtiqueta) {
                return;
            }
            var $campo = $(focoPendienteModalEtiqueta);
            focoPendienteModalEtiqueta = null;
            if ($campo.length) {
                $campo.trigger('focus').select();
            }
        });
        $('#modalEtiquetaProveedorSurmar').on('hidden.bs.modal', function () {
            focoPendienteModalEtiqueta = null;
            finProcesoEtiqueta();
        });

        // Enter: encabezado / SENASA / depósito (todo input/select del form, no solo la clase)
        bindEnterEnContenedor(
            '#form-encabezado-surmar',
            'input.surmar-enc-nav, input:not([type=hidden]):not([type=submit]):not([type=button]), select',
            function () {
                $('#form-encabezado-surmar button[type=submit]').trigger('focus');
            }
        );
        // Fallback explícito por si el form aún no tiene clase en algún campo
        $(document).off('keydown.surmarCert', '#certificado_senasa').on('keydown.surmarCert', '#certificado_senasa', function (e) {
            if (!esTeclaEnter(e)) return;
            e.preventDefault();
            e.stopPropagation();
            var $next = $('#tropa');
            if ($next.length) {
                $next.trigger('focus').select();
            }
        });
        // Enter: ítem nuevo
        $(document).off('keydown.surmarItem', '.surmar-item-nav').on('keydown.surmarItem', '.surmar-item-nav', function (e) {
            if (!esTeclaEnter(e)) return;
            if ($(this).is('#codigoarticulo')) {
                e.preventDefault();
                e.stopPropagation();
                resolverArticuloPorSku($('#codigoarticulo').val(), function (art) {
                    if (!art) {
                        alert('Artículo no encontrado.');
                        return;
                    }
                    aplicarArticuloExtra(art);
                    $('#lote_proveedor').trigger('focus').select();
                });
                return;
            }
            avanzarConEnter(camposNavegables($(document), '.surmar-item-nav'), e, function () {
                $('#btn-agregar-item-surmar').trigger('click');
            });
        });
        // Enter: modal etiqueta
        $(document).off('keydown.surmarEtiq', '.surmar-etiq-nav').on('keydown.surmarEtiq', '.surmar-etiq-nav', function (e) {
            if (!esTeclaEnter(e)) return;
            avanzarConEnter(
                camposNavegables($('#modalEtiquetaProveedorSurmar'), '.surmar-etiq-nav'),
                e,
                function () {
                    $('#btn-etiq-guardar-imprimir').trigger('focus');
                }
            );
        });
        // Evitar submit del encabezado con Enter en cualquier input
        $('#form-encabezado-surmar').off('keydown.surmarNoSubmit').on('keydown.surmarNoSubmit', 'input, select', function (e) {
            if (!esTeclaEnter(e)) return;
            if ($(this).is('textarea')) return;
            e.preventDefault();
        });
        $('#codigoarticulo').on('blur', function () {
            var sku = $.trim($(this).val() || '');
            if (!sku) return;
            if ($('#articulo_id').val() && $.trim($('#descripcionarticulo').val() || '')) return;
            resolverArticuloPorSku(sku, function (art) {
                if (art) aplicarArticuloExtra(art);
            });
        });

        $('#aceptaconsultaarticuloModal').on('click', function () {
            setTimeout(function () {
                if ($('#articulo_id').val()) {
                    limpiarVinculoOc();
                    actualizarOrigenArticulo('EXTRA');
                    aplicarLoteDesdeCabecera();
                    $('#lote_proveedor').trigger('focus');
                }
            }, 150);
        });

        if (typeof window.activa_eventos_consultaarticulo === 'function') {
            window.activa_eventos_consultaarticulo();
        }
        if (typeof window.activa_eventos_consultadeposito === 'function') {
            window.activa_eventos_consultadeposito();
        }

        window.addEventListener('pageshow', ocultarOverlay);
        setSeparaDefault();
        aplicarLoteDesdeCabecera();
        $('#certificado_senasa').on('input change', function () {
            cfg.certificadoSenasa = $.trim($(this).val() || '');
            aplicarLoteDesdeCabecera();
        });
        $('a[href="#tab-items"]').on('shown.bs.tab', function () {
            aplicarLoteDesdeCabecera();
        });
        if (cfg.editable && lineasOc.length === 1) {
            elegirLineaOc(lineasOc[0]);
        }
    });
})(jQuery);
