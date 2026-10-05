(function ($) {
    'use strict';

    if (!$('#tabla-comprobantes-iva-ie').length) {
        return;
    }

    var comprobantesIva = [];
    var conceptosMeta = {};
    var cuentasDetalleMeta = {};
    var tiposCompraMeta = {};
    var ptrFilaCuentaConcepto = null;
    window.ptrIeCpFilaCuentaConcepto = null;
    var previewTimer = null;
    var debitosGastoTocados = false;
    var debitosGastoSemilla = null;
    var ultimoPreview = null;
    var cuentasGastoManual = {};
    var ieCpCcAbrirCuentaId = 0;
    var precargaTipoSeq = 0;
    var iaDecisionId = null;
    var iaSugerenciaHash = null;
    // true solo mientras la sugerencia IA está en el modal y aún no se aceptó a la grilla
    var iaDecisionPendienteModal = false;
    var bancoGastoActual = null;
    var bancoGastoSeq = 0;

    function parseJsonEl(id, fallback) {
        try {
            return JSON.parse($(id).text() || 'null') || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function descartarDecisionPendiente() {
        var decisionId = parseInt(iaDecisionId || '0', 10);
        var url = $('#modal-ie-comprobante-iva').data('descartar-url');
        if (!iaDecisionPendienteModal || !decisionId || !url) {
            return;
        }
        iaDecisionPendienteModal = false;
        try {
            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
                    decision_id: decisionId,
                },
                dataType: 'json',
                async: true,
            });
        } catch (e) {
            // ignore
        }
    }

    function init() {
        conceptosMeta = parseJsonEl('#ie-conceptos-cuenta-meta', {});
        cuentasDetalleMeta = parseJsonEl('#ie-cuentas-detalle-meta', {});
        tiposCompraMeta = {};
        (parseJsonEl('#ie-tipos-compra-meta', []) || []).forEach(function (tipo) {
            var id = parseInt(tipo && tipo.id ? tipo.id : '0', 10) || 0;
            if (id > 0) {
                tiposCompraMeta[String(id)] = tipo;
            }
        });
        comprobantesIva = parseJsonEl('#ie-comprobantes-iva-inicial', []);
        renderGrilla();
        syncHidden();
        if (typeof activa_eventos_consultatipotransaccioncompra === 'function') {
            activa_eventos_consultatipotransaccioncompra();
        }
    }

    function formatearCuit(valor) {
        var digits = String(valor || '').replace(/\D/g, '').substring(0, 11);
        if (digits.length > 10) {
            return digits.substring(0, 2) + '-' + digits.substring(2, 10) + '-' + digits.substring(10);
        }
        if (digits.length > 2) {
            return digits.substring(0, 2) + '-' + digits.substring(2);
        }
        return digits;
    }

    function aplicarCuitEventual(valor) {
        $('#ie-cp-eventual-documento').val(formatearCuit(valor));
    }

    function actualizarFilaAutorizacion() {
        var cae = $.trim($('#ie-cp-cae').val() || '');
        var tipo = $.trim($('#ie-cp-tipo-autorizacion').val() || '');
        $('#ie-cp-fila-autorizacion').toggleClass('d-none', !(cae || tipo));
    }

    function aplicarTipoComprobanteEnModal(tipoId, dispararConceptos) {
        var id = parseInt(tipoId || '0', 10) || 0;
        var meta = tiposCompraMeta[String(id)] || {};
        var abrev = String(meta.abreviatura || '');
        var nombre = String(meta.nombre || '');
        $('#ie-cp-tipotransaccion-compra-id').val(id > 0 ? String(id) : '');
        var $abrev = $('#ie-cp-tipo-abreviatura');
        $abrev.val(abrev);
        if (id > 0 && abrev) {
            $abrev.data('ultima-valida', abrev);
        } else {
            $abrev.removeData('ultima-valida');
        }
        $('#ie-cp-tipo-nombre').val(nombre);
        actualizarModoNumeracion();
        if (dispararConceptos) {
            precargarConceptosPorTipo(id);
        }
    }

    function focoElementoIe(el) {
        if (!el || el.disabled || !$(el).is(':visible')) {
            return;
        }
        setTimeout(function () {
            try {
                el.focus();
                if (typeof el.select === 'function' && el.type !== 'date') {
                    el.select();
                }
            } catch (err) {
                // ignore
            }
        }, 0);
    }

    function focoCampoIe(id) {
        focoElementoIe(document.getElementById(id));
    }

    function focoPrimerImporteConcepto() {
        var $filas = $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto');
        var $importe = $();
        $filas.each(function () {
            var id = parseInt($(this).find('.concepto_ivacompra_id').val() || '0', 10) || 0;
            if (id > 0) {
                $importe = $(this).find('.ie-cp-monto');
                return false;
            }
        });
        if ($importe.length) {
            focoElementoIe($importe.get(0));
            return;
        }
        var $codigo = $filas.first().find('.codigo_concepto_ivacompra');
        if ($codigo.length) {
            focoElementoIe($codigo.get(0));
        }
    }

    function focoSiguienteImporteConcepto(actual) {
        var $filas = $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto');
        var $actual = $(actual).closest('.ie-cp-fila-concepto');
        var idx = $filas.index($actual);
        for (var i = idx + 1; i < $filas.length; i++) {
            var $fila = $filas.eq(i);
            var id = parseInt($fila.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
            if (id > 0) {
                focoElementoIe($fila.find('.ie-cp-monto').get(0));
                return;
            }
            focoElementoIe($fila.find('.codigo_concepto_ivacompra').get(0));
            return;
        }
    }

    function syncHidden() {
        $('#comprobantes_ivacompra_json').val(JSON.stringify(comprobantesIva));
        if (typeof window.ieComprobantesIvaCambiaron === 'function') {
            window.ieComprobantesIvaCambiaron(comprobantesIva);
        }
    }

    function formatoNumero(n) {
        return (parseFloat(n) || 0).toFixed(2);
    }

    var TIPOS_NUMERO_AUTOMATICO = { ICO: true, IDO: true };

    function esNumeracionAutomatica() {
        return !!TIPOS_NUMERO_AUTOMATICO[abreviaturaTipoActual()];
    }

    function esGastoBanco() {
        return String($('#ie-cp-tipo-tesoreria').val() || '') === 'GASTO_BANCO';
    }

    function parseMontoCaja(val) {
        var n = parseFloat(String(val || '').replace(/\./g, '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function lineasCajaDelFormulario() {
        var lineas = [];
        $('#tbody-cuenta-table tr.item-cuenta').each(function () {
            var id = parseInt($(this).find('.cuentacaja_id').val() || '0', 10) || 0;
            if (id <= 0) {
                return;
            }
            lineas.push({
                cuentacaja_id: id,
                monto: parseMontoCaja($(this).find('.monto').val()),
            });
        });
        return lineas;
    }

    function pintarBancoGasto(data) {
        bancoGastoActual = data || { ok: false, ambiguo: false, mensaje: 'No se pudo tomar el banco de la cuenta de caja.' };
        data = bancoGastoActual;
        var $texto = $('#ie-cp-banco-auto-texto');
        var $sel = $('#ie-cp-banco-cuenta');
        var $ayuda = $('#ie-cp-banco-auto-ayuda');
        if (data.ambiguo && (data.candidatos || []).length) {
            $texto.text(data.mensaje || 'Elegí la cuenta del banco.');
            var html = '<option value="">Elegí la cuenta</option>';
            data.candidatos.forEach(function (c) {
                var label = $.trim((c.cuenta_codigo ? c.cuenta_codigo + ' · ' : '') + (c.cuenta_nombre || c.banco_nombre || ''));
                html += '<option value="' + c.cuentacaja_id + '">' + $('<div>').text(label).html() + '</option>';
            });
            $sel.removeClass('d-none').html(html);
            $ayuda.text('Solo las cuentas que ya cargaste en el movimiento.');
            return;
        }
        if (!data.ok) {
            $sel.addClass('d-none').empty();
            $texto.text(data.mensaje || 'No se pudo tomar el banco de la cuenta de caja.');
            $ayuda.text('No se carga un proveedor: es un egreso, no una orden de pago.');
            return;
        }
        if ($sel.find('option').length > 1 && data.cuentacaja_id) {
            $sel.removeClass('d-none').val(String(data.cuentacaja_id));
        } else {
            $sel.addClass('d-none').empty();
        }
        $texto.text(data.etiqueta || data.banco_nombre || '');
        if ((parseInt(data.proveedor_id, 10) || 0) > 0) {
            $ayuda.text('Queda vinculado el proveedor ' + $.trim((data.proveedor_codigo || '') + ' ' + (data.proveedor_nombre || '')) + ', por el CUIT del banco.');
        } else {
            $ayuda.text('El banco no está en el maestro de proveedores. El IVA queda a nombre del banco.');
        }
    }

    function resolverBancoGasto(cuentacajaId) {
        var url = $('#modal-ie-comprobante-iva').data('banco-url');
        var seq = ++bancoGastoSeq;
        if (!url) {
            pintarBancoGasto({ ok: false, ambiguo: false, mensaje: 'No se pudo consultar el banco de la cuenta de caja.' });
            return;
        }
        $.post(url, {
            _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
            lineas_json: JSON.stringify(lineasCajaDelFormulario()),
            cuentacaja_id: cuentacajaId || 0,
        }).done(function (data) {
            if (seq !== bancoGastoSeq || !esGastoBanco()) {
                return;
            }
            pintarBancoGasto(data);
        }).fail(function () {
            if (seq !== bancoGastoSeq) {
                return;
            }
            pintarBancoGasto({ ok: false, ambiguo: false, mensaje: 'No se pudo consultar el banco de la cuenta de caja.' });
        });
    }

    function aplicarModoProveedor() {
        var gasto = esGastoBanco();
        $('#ie-cp-banco-auto').toggleClass('d-none', !gasto);
        $('#ie-cp-div-proveedor, #ie-cp-eventual-bloque').toggleClass('d-none', gasto);
        $('#ie-cp-proveedor-titulo').text(gasto ? 'Banco' : 'Proveedor');
        if (!gasto) {
            bancoGastoActual = null;
            $('#ie-cp-banco-cuenta').addClass('d-none').empty();
            return;
        }
        var elegida = 0;
        var idx = parseInt($('#ie-cp-edit-index').val(), 10);
        if (!isNaN(idx) && comprobantesIva[idx] && comprobantesIva[idx].cuentacaja_id) {
            elegida = parseInt(comprobantesIva[idx].cuentacaja_id, 10) || 0;
        }
        resolverBancoGasto(elegida);
    }

    function actualizarModoNumeracion() {
        var auto = esNumeracionAutomatica();
        $('#ie-cp-numero-manual').toggleClass('d-none', auto);
        $('#ie-cp-numero-auto').toggleClass('d-none', !auto);
        if (!auto) {
            return;
        }
        marcarSucursalInvalida(false);
        var nro = 0;
        var idx = parseInt($('#ie-cp-edit-index').val(), 10);
        var previo = (!isNaN(idx) && comprobantesIva[idx]) ? comprobantesIva[idx] : null;
        var abrevPrevio = previo
            ? String((tiposCompraMeta[String(previo.tipotransaccion_compra_id || '')] || {}).abreviatura || '').trim().toUpperCase()
            : '';
        if (previo && previo.id && abrevPrevio === abreviaturaTipoActual()) {
            nro = parseInt(previo.numerocomprobante || '0', 10) || 0;
        }
        $('#ie-cp-letra').val(nro > 0 ? 'A' : '');
        $('#ie-cp-sucursal').val(nro > 0 ? '0' : '');
        $('#ie-cp-numero').val(nro > 0 ? String(nro) : '');
        $('#ie-cp-numero-auto-texto').text(nro > 0
            ? ('Número ' + nro + ' (letra A, punto de venta 0).')
            : 'El número se asigna solo al grabar. No hace falta letra, punto de venta ni número.');
    }

    function etiquetaComprobante(c) {
        var meta = tiposCompraMeta[String(c.tipotransaccion_compra_id || '')] || {};
        var abrev = String(meta.abreviatura || '').trim().toUpperCase();
        if (TIPOS_NUMERO_AUTOMATICO[abrev]) {
            var nro = parseInt(c.numerocomprobante || '0', 10) || 0;
            return nro > 0 ? ('A 0-' + nro) : 'Automático';
        }
        return (c.letra || '') + ' ' + (c.sucursal || '') + '-' + (c.numerocomprobante || '');
    }

    function etiquetaTipoTesoreria(codigo) {
        var cod = codigo || 'FONDO_FIJO';
        var texto = $('#ie-cp-tipo-tesoreria option').filter(function () {
            return $(this).val() === cod;
        }).first().text();
        texto = $.trim(texto || '');
        return texto !== '' ? texto : cod;
    }

    function renderGrilla() {
        var $tbody = $('#tbody-comprobantes-iva-ie');
        $tbody.empty();
        var total = 0;

        comprobantesIva.forEach(function (c, idx) {
            total += parseFloat(c.total) || 0;
            var tipoLabel = etiquetaTipoTesoreria(c.tipo_tesoreria);
            var prov = c.proveedor_nombre || c.proveedor_nombre_eventual || '—';
            var pdfBadge = (c.tiene_pdf || c.pdf_temp_id) ? ' <i class="fa fa-file-pdf text-danger" title="PDF adjunto"></i>' : '';
            $tbody.append(
                '<tr>' +
                '<td>' + tipoLabel + '</td>' +
                '<td>' + etiquetaComprobante(c) + pdfBadge + '</td>' +
                '<td>' + $('<div>').text(prov).html() + '</td>' +
                '<td>' + (c.fechaiva || '') + '</td>' +
                '<td class="text-right">' + formatoNumero(c.total) + '</td>' +
                '<td class="text-center text-nowrap">' +
                '<button type="button" class="btn btn-warning btn-sm ie-edit-comprobante" data-idx="' + idx + '">Editar</button> ' +
                '<button type="button" class="btn btn-danger btn-sm ie-del-comprobante" data-idx="' + idx + '"><i class="fa fa-trash"></i></button>' +
                '</td></tr>'
            );
        });

        $('#ie-total-comprobantes-iva').text(formatoNumero(total));
        syncHidden();
    }

    function setCuentaFila($row, cuentaId, codigo, nombre) {
        var id = parseInt(cuentaId || '0', 10) || 0;
        var cod = codigo || '';
        var nom = nombre || '';
        $row.find('.ie-cp-cuenta-id, .cuentacontable_id').val(id > 0 ? String(id) : '');
        $row.find('.ie-cp-cuenta-codigo, .codigocuentacontable').val(cod);
        $row.find('.codigo_previo').val(cod);
        $row.find('.ie-cp-cuenta-nombre, .nombrecuentacontable').val(
            id > 0 ? (nom || (cod ? '' : ('Cuenta #' + id))) : ''
        );
        $row.removeClass('table-warning');
        if (id <= 0) {
            $row.find('.ie-cp-cuenta-nombre, .nombrecuentacontable').attr('placeholder', 'En el asiento');
        }
    }

    function detalleCuentaMeta(meta, cuentaId) {
        var id = parseInt(cuentaId || '0', 10) || 0;
        if (id <= 0) {
            return { id: 0, codigo: '', nombre: '' };
        }
        var det = cuentasDetalleMeta[String(id)]
            || (meta && meta.cuentas_detalle && meta.cuentas_detalle[String(id)])
            || {};
        return {
            id: id,
            codigo: det.codigo || (meta && meta.cuenta_debe_codigo) || '',
            nombre: det.nombre || (meta && meta.cuenta_debe_nombre) || '',
        };
    }

    function agregarFilaConcepto(data) {
        var $tpl = $($('#ie-cp-template-concepto').html());
        if (data) {
            var conceptoId = parseInt(data.concepto_ivacompra_id || '0', 10) || 0;
            var meta = conceptosMeta[String(conceptoId)] || {};
            var codigo = data.concepto_codigo || meta.codigo || '';
            var nombre = data.concepto_nombre || meta.nombre || '';
            $tpl.find('.concepto_ivacompra_id').val(conceptoId > 0 ? String(conceptoId) : '');
            $tpl.find('.codigo_concepto_ivacompra').val(codigo);
            if (codigo) {
                $tpl.find('.codigo_concepto_ivacompra').data('codigo-resuelto', codigo);
            }
            $tpl.find('.nombre_concepto_ivacompra').val(nombre);
            if (data.monto === '' || data.monto === null) {
                $tpl.find('.ie-cp-monto').val('');
            } else {
                $tpl.find('.ie-cp-monto').val(data.monto || 0);
            }
            var cuentaGuardada = parseInt(data.cuentacontabledebe_id || '0', 10) || 0;
            if (conceptoId > 0 && cuentaGuardada > 0) {
                var detData = detalleCuentaMeta(meta, cuentaGuardada);
                cuentasGastoManual[String(conceptoId)] = {
                    id: cuentaGuardada,
                    codigo: data.cuenta_codigo || detData.codigo || '',
                    nombre: data.cuenta_nombre || detData.nombre || '',
                };
            }
        }
        $('#ie-cp-tbody-conceptos').append($tpl);
        return $tpl;
    }

    function refrescarCuentaFila($row) {
        var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
        var meta = conceptosMeta[String(conceptoId)] || {};
        var cuentaId = parseInt($row.find('.ie-cp-cuenta-id').val() || '0', 10);
        var codigoActual = $.trim($row.find('.codigocuentacontable').val() || '');
        if (cuentaId <= 0) {
            var empresaIdForm = parseInt($('#empresa_id').val() || '0', 10) || 0;
            if (meta.cuentas_por_empresa && empresaIdForm > 0 && meta.cuentas_por_empresa[empresaIdForm]) {
                cuentaId = parseInt(meta.cuentas_por_empresa[empresaIdForm], 10) || 0;
            } else if (meta.cuenta_debe_id) {
                cuentaId = parseInt(meta.cuenta_debe_id, 10) || 0;
            }
            if (cuentaId > 0) {
                var det = detalleCuentaMeta(meta, cuentaId);
                setCuentaFila($row, cuentaId, det.codigo, det.nombre);
                return;
            }
        }
        if (cuentaId <= 0) {
            setCuentaFila($row, 0, '', '');
            return;
        }
        // Ya hay ID: completar código/nombre desde meta si el input está vacío
        if (!codigoActual) {
            var detExistente = detalleCuentaMeta(meta, cuentaId);
            if (detExistente.codigo || detExistente.nombre) {
                setCuentaFila($row, cuentaId, detExistente.codigo, detExistente.nombre);
                return;
            }
        }
        $row.removeClass('table-warning');
    }

    function limpiarModal() {
        descartarDecisionPendiente();
        iaDecisionId = null;
        iaSugerenciaHash = null;
        iaDecisionPendienteModal = false;
        $('#ie-cp-edit-index').val('');
        $('#ie-cp-tbody-conceptos').empty();
        agregarFilaConcepto(null);
        $('#ie-cp-tipo-tesoreria').val('FONDO_FIJO');
        aplicarTipoComprobanteEnModal(0, false);
        $('#ie-cp-letra, #ie-cp-sucursal, #ie-cp-numero, #ie-cp-total, #ie-cp-cae').val('');
        $('#ie-cp-tipo-autorizacion').val('');
        actualizarFilaAutorizacion();
        $('#ie-cp-proveedor-id, #ie-cp-proveedor-codigo, #ie-cp-proveedor-nombre').val('');
        $('#ie-cp-eventual-nombre, #ie-cp-eventual-documento').val('');
        $('#ie-cp-eventual-condicioniva').val('');
        var hoy = new Date().toISOString().slice(0, 10);
        $('#ie-cp-fecha-comprobante, #ie-cp-fecha-iva').val(hoy);
        $('#ie-cp-preview-asiento').empty();
        $('#ie-cp-preview-total-debe, #ie-cp-preview-total-haber').text('0.00');
        $('#ie-cp-preview-error, #ie-cp-asiento-avisos').addClass('d-none').empty();
        debitosGastoTocados = false;
        debitosGastoSemilla = null;
        ultimoPreview = null;
        cuentasGastoManual = {};
        $('#ie-cp-debe-gasto-barra').addClass('d-none');
        $('#ie-cp-debe-gasto-aviso').text('');
        marcarSucursalInvalida(false);
        actualizarEventualSegunProveedor();
        $('#ie-cp-conceptos-coherencia-error, #ie-cp-conceptos-coherencia-aviso').addClass('d-none').empty();
        $('#ie-cp-conceptos-tipo-aviso').addClass('d-none').empty();
        $('#ie-cp-fecha-iva').data('seguir-comprobante', '1');
        $('#ie-cp-pdf-temp-id').val('');
        actualizarSumaConceptos();
    }

    function mostrarAvisoTipoConceptos(html, clase) {
        var $aviso = $('#ie-cp-conceptos-tipo-aviso');
        if (!$aviso.length) {
            return;
        }
        $aviso.removeClass('d-none alert-info alert-warning alert-success alert-danger')
            .addClass(clase || 'alert-info')
            .html(html);
    }

    function abreviaturaTipoActual() {
        return String($('#ie-cp-tipo-abreviatura').val() || '').trim().toUpperCase();
    }

    function conceptosTienenMontos() {
        var hay = false;
        $('#ie-cp-tbody-conceptos .ie-cp-monto').each(function () {
            if (Math.abs(parseFloat($(this).val() || '0') || 0) >= 0.0001) {
                hay = true;
                return false;
            }
        });
        return hay;
    }

    /**
     * Anita deja gravado e IVA como tipo N. La fórmula con(2)*0.21 marca el IVA;
     * el neto base (COMPRAS 21%) hay que marcarlo como gravado de esa alícuota.
     */
    function enriquecerMetaGravadosDesdeFormulas() {
        var porCodigo = {};
        Object.keys(conceptosMeta || {}).forEach(function (id) {
            var meta = conceptosMeta[id] || {};
            var cod = String(meta.codigo || '').trim();
            if (cod) {
                porCodigo[cod] = id;
            }
        });
        Object.keys(conceptosMeta || {}).forEach(function (id) {
            var meta = conceptosMeta[id] || {};
            var base = String(meta.formula_codigo_base || '').trim();
            var coef = parseFloat(meta.formula_coeficiente || 0) || 0;
            if (!base || !(coef > 0)) {
                return;
            }
            var tipoI = String(meta.tipoconcepto || '').toUpperCase();
            if (tipoI !== 'I' && tipoI !== 'G' && tipoI !== 'E' && tipoI !== 'T' && tipoI !== 'P' && tipoI !== 'B' && tipoI !== 'M' && tipoI !== 'S' && tipoI !== 'A' && tipoI !== 'V') {
                meta.tipoconcepto = 'I';
            }
            if (!(parseFloat(meta.impuesto_tasa || 0) > 0)) {
                meta.impuesto_tasa = Math.round(coef * 100000) / 1000;
            }
            conceptosMeta[id] = meta;

            var gravadoId = porCodigo[base];
            if (!gravadoId || !conceptosMeta[gravadoId]) {
                return;
            }
            var gMeta = conceptosMeta[gravadoId];
            var tipoG = String(gMeta.tipoconcepto || '').toUpperCase();
            if (tipoG !== 'G' && tipoG !== 'E') {
                gMeta.tipoconcepto = 'G';
            }
            if (!(parseFloat(gMeta.impuesto_tasa || 0) > 0)) {
                gMeta.impuesto_tasa = Math.round(coef * 100000) / 1000;
            }
            conceptosMeta[gravadoId] = gMeta;
        });
    }

    function incorporarMetaConceptoTipo(c) {
        var id = parseInt(c && c.id ? c.id : '0', 10) || 0;
        if (id <= 0) {
            return;
        }
        var prev = conceptosMeta[String(id)] || {};
        conceptosMeta[String(id)] = $.extend({}, prev, {
            tipoconcepto: String(c.tipoconcepto || prev.tipoconcepto || ''),
            nombre: String(c.nombre || prev.nombre || ''),
            codigo: String(c.codigo || prev.codigo || ''),
            impuesto_tasa: parseFloat(c.impuesto_tasa || prev.impuesto_tasa || 0) || 0,
            formula: String(c.formula || prev.formula || ''),
            formula_codigo_base: String(c.formula_codigo_base || prev.formula_codigo_base || ''),
            formula_coeficiente: parseFloat(c.formula_coeficiente || prev.formula_coeficiente || 0) || 0,
            cuenta_debe_id: parseInt(c.cuenta_debe_id || prev.cuenta_debe_id || '0', 10) || 0,
            cuenta_debe_codigo: String(c.cuenta_debe_codigo || prev.cuenta_debe_codigo || ''),
            cuenta_debe_nombre: String(c.cuenta_debe_nombre || prev.cuenta_debe_nombre || ''),
            cuentas_por_empresa: c.cuentas_por_empresa || prev.cuentas_por_empresa || {},
        });
        var cuentaMetaId = parseInt(c.cuenta_debe_id || '0', 10) || 0;
        if (cuentaMetaId > 0 && (c.cuenta_debe_codigo || c.cuenta_debe_nombre)) {
            cuentasDetalleMeta[String(cuentaMetaId)] = {
                codigo: String(c.cuenta_debe_codigo || ''),
                nombre: String(c.cuenta_debe_nombre || ''),
            };
        }
        var porEmpresa = c.cuentas_detalle_por_empresa || {};
        Object.keys(porEmpresa).forEach(function (emp) {
            var det = porEmpresa[emp] || {};
            var detId = parseInt(det.id || '0', 10) || 0;
            if (detId > 0) {
                cuentasDetalleMeta[String(detId)] = {
                    codigo: String(det.codigo || ''),
                    nombre: String(det.nombre || ''),
                };
            }
        });
    }

    function urlConceptosPorTipo(tipoId) {
        var base = (typeof window.carpetaBase === 'string') ? window.carpetaBase.replace(/\/$/, '') : '';
        return base + '/compras/tipotransaccion_compra/' + tipoId + '/conceptos-iva';
    }

    function precargarConceptosPorTipo(tipoId) {
        var id = parseInt(tipoId || '0', 10) || 0;
        var seq = ++precargaTipoSeq;
        if (id <= 0) {
            if (!conceptosTienenMontos()) {
                $('#ie-cp-tbody-conceptos').empty();
                agregarFilaConcepto(null);
                $('#ie-cp-conceptos-tipo-aviso').addClass('d-none').empty();
            }
            return;
        }
        if (conceptosTienenMontos()) {
            mostrarAvisoTipoConceptos(
                '<i class="fa fa-info-circle"></i> Se conservaron los conceptos y montos. El asiento se recalcula con el tipo nuevo.',
                'alert-info'
            );
            programarPreview();
            return;
        }

        mostrarAvisoTipoConceptos('<i class="fa fa-spinner fa-spin"></i> Cargando conceptos del tipo…', 'alert-info');
        $.getJSON(urlConceptosPorTipo(id))
            .done(function (res) {
                if (seq !== precargaTipoSeq) {
                    return;
                }
                var lista = (res && res.conceptos) || [];
                debitosGastoTocados = false;
                debitosGastoSemilla = null;
                cuentasGastoManual = {};
                $('#ie-cp-tbody-conceptos').empty();
                if (!lista.length) {
                    agregarFilaConcepto(null);
                    var abrevVacio = abreviaturaTipoActual();
                    mostrarAvisoTipoConceptos(
                        '<i class="fa fa-info-circle"></i> '
                        + (abrevVacio ? ('El tipo ' + $('<div>').text(abrevVacio).html() + ' ') : 'Este tipo ')
                        + 'no tiene conceptos IVA en el maestro. Elegí uno que los tenga (FIB, FIS, FGA, …) o cargalos en el ABM del tipo.',
                        'alert-warning'
                    );
                    programarPreview();
                    return;
                }
                var vistos = {};
                var agregados = 0;
                lista.forEach(function (c) {
                    var cid = parseInt(c && c.id ? c.id : '0', 10) || 0;
                    var codigo = String((c && c.codigo) || '').trim();
                    var clave = cid > 0 ? ('id:' + cid) : (codigo ? ('cod:' + codigo) : '');
                    if (clave && vistos[clave]) {
                        return;
                    }
                    if (clave) {
                        vistos[clave] = true;
                    }
                    incorporarMetaConceptoTipo(c);
                    agregarFilaConcepto({
                        concepto_ivacompra_id: cid,
                        concepto_codigo: codigo,
                        concepto_nombre: String((c && c.nombre) || ''),
                        monto: '',
                        cuenta_debe_id: c && c.cuenta_debe_id,
                        cuenta_debe_codigo: c && c.cuenta_debe_codigo,
                        cuenta_debe_nombre: c && c.cuenta_debe_nombre,
                    });
                    agregados++;
                });
                enriquecerMetaGravadosDesdeFormulas();
                mostrarAvisoTipoConceptos(
                    '<i class="fa fa-check-circle"></i> Conceptos del tipo de comprobante. Complete los importes.'
                    + (agregados ? ' (' + agregados + ')' : ''),
                    'alert-success'
                );
                programarPreview();
            })
            .fail(function (xhr) {
                if (seq !== precargaTipoSeq) {
                    return;
                }
                var msg = 'No se pudieron cargar los conceptos del tipo.';
                if (xhr && xhr.status === 403) {
                    msg = 'No tiene permiso para leer los conceptos de este tipo de comprobante.';
                }
                mostrarAvisoTipoConceptos('<i class="fa fa-exclamation-triangle"></i> ' + msg, 'alert-danger');
            });
    }

    function actualizarSumaConceptos() {
        var suma = 0;
        $('#ie-cp-tbody-conceptos .ie-cp-monto').each(function () {
            suma += parseFloat($(this).val() || '0') || 0;
        });
        $('#ie-cp-suma-conceptos').text(formatoNumero(suma));
        var total = parseFloat($('#ie-cp-total').val() || '0') || 0;
        var $dif = $('#ie-cp-dif-conceptos');
        if (!$dif.length) {
            return;
        }
        if (Math.abs(total) < 0.001 && Math.abs(suma) < 0.001) {
            $dif.addClass('d-none').text('');
            return;
        }
        var dif = Math.round((total - suma) * 100) / 100;
        if (Math.abs(dif) < 0.02) {
            $dif.removeClass('d-none text-danger text-info').addClass('text-success').text('Coincide con el total');
            return;
        }
        if (dif > 0 && Math.abs(suma) > 0.001) {
            $dif.removeClass('d-none text-danger text-success').addClass('text-info')
                .text('Factura menor que el monto: la diferencia (' + formatoNumero(dif) + ') va al concepto de gasto');
            return;
        }
        $dif.removeClass('d-none text-success text-info').addClass('text-danger')
            .text('Los conceptos superan el total: ' + formatoNumero(Math.abs(dif)));
    }

    function sumaImportesConceptosModal() {
        var suma = 0;
        $('#ie-cp-tbody-conceptos .ie-cp-monto').each(function () {
            var monto = parseFloat($(this).val() || '0');
            if (!monto) {
                return;
            }
            var conceptoId = parseInt($(this).closest('.ie-cp-fila-concepto').find('.concepto_ivacompra_id').val() || '0', 10);
            if (conceptoId <= 0) {
                return;
            }
            suma += monto;
        });
        return Math.round(suma * 100) / 100;
    }

    function abrirModal(idx) {
        limpiarModal();
        if (idx !== null && idx !== undefined && comprobantesIva[idx]) {
            var c = comprobantesIva[idx];
            $('#ie-cp-edit-index').val(String(idx));
            $('#modal-ie-comprobante-iva-titulo').text('Editar comprobante IVA');
            $('#ie-cp-tipo-tesoreria').val(c.tipo_tesoreria || 'FONDO_FIJO');
            aplicarTipoComprobanteEnModal(c.tipotransaccion_compra_id || 0, false);
            $('#ie-cp-letra').val(c.letra || '');
            $('#ie-cp-sucursal').val(c.sucursal || '');
            $('#ie-cp-numero').val(c.numerocomprobante || '');
            actualizarModoNumeracion();
            $('#ie-cp-proveedor-id').val(c.proveedor_id || '');
            $('#ie-cp-proveedor-codigo').val(c.proveedor_codigo || '');
            $('#ie-cp-proveedor-nombre').val(c.proveedor_nombre || '');
            $('#ie-cp-eventual-nombre').val(c.proveedor_nombre_eventual || '');
            aplicarCuitEventual(c.proveedor_documento_eventual || '');
            $('#ie-cp-eventual-condicioniva').val(c.proveedor_condicioniva_id_eventual || '');
            $('#ie-cp-fecha-comprobante').val(c.fechacomprobante || '');
            $('#ie-cp-fecha-iva').val(c.fechaiva || '');
            $('#ie-cp-total').val(c.total || 0);
            $('#ie-cp-moneda-id').val(c.moneda_id || 1);
            $('#ie-cp-cae').val(c.numerocae || '');
            $('#ie-cp-tipo-autorizacion').val(c.tipo_autorizacion || (c.numerocae ? 'CAE' : ''));
            actualizarFilaAutorizacion();
            $('#ie-cp-pdf-temp-id').val(c.pdf_temp_id || '');
            // Ya estaba en grilla: no descartar al cerrar sin re-aceptar.
            iaDecisionId = c.ai_decision_id || null;
            iaSugerenciaHash = c.ai_sugerencia_hash || null;
            iaDecisionPendienteModal = false;
            $('#ie-cp-tbody-conceptos').empty();
            (c.conceptos || []).forEach(function (concepto) {
                agregarFilaConcepto(concepto);
            });
            if ((c.conceptos || []).length === 0) {
                agregarFilaConcepto(null);
            }
            var fechaComp = ($('#ie-cp-fecha-comprobante').val() || '').slice(0, 10);
            var fechaIva = ($('#ie-cp-fecha-iva').val() || '').slice(0, 10);
            $('#ie-cp-fecha-iva').data('seguir-comprobante', fechaComp && fechaComp === fechaIva ? '1' : '0');
            actualizarEventualSegunProveedor();
            if (Array.isArray(c.debitos_gasto) && c.debitos_gasto.length) {
                debitosGastoTocados = true;
                debitosGastoSemilla = c.debitos_gasto;
            }
        } else {
            $('#modal-ie-comprobante-iva-titulo').text('Nuevo comprobante IVA');
        }
        $('#modal-ie-comprobante-iva')
            .off('shown.bs.modal.ieCpFocoTipo')
            .one('shown.bs.modal.ieCpFocoTipo', function () {
                focoCampoIe('ie-cp-tipo-abreviatura');
            });
        aplicarModoProveedor();
        $('#modal-ie-comprobante-iva').modal('show');
        programarPreview();
    }

    function lineasConceptosDesdeModal() {
        var conceptos = [];
        $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseFloat($row.find('.ie-cp-monto').val() || '0');
            if (conceptoId <= 0 || monto === 0) {
                return;
            }
            conceptos.push({
                concepto_ivacompra_id: conceptoId,
                monto: monto,
            });
        });
        return conceptos;
    }

    function validarCoherenciaConceptosModal() {
        if (typeof window.ConceptosIvacompraCoherencia === 'undefined') {
            return { valido: true, errores: [], advertencias: [] };
        }
        enriquecerMetaGravadosDesdeFormulas();
        return window.ConceptosIvacompraCoherencia.validar(lineasConceptosDesdeModal(), conceptosMeta);
    }

    function renderCoherenciaConceptosModal(result) {
        var $err = $('#ie-cp-conceptos-coherencia-error');
        var $aviso = $('#ie-cp-conceptos-coherencia-aviso');
        if (!$err.length) {
            return;
        }

        if (result.errores && result.errores.length) {
            var htmlErr = '<strong>Coherencia IVA:</strong><ul class="mb-0 pl-3">';
            result.errores.forEach(function (msg) {
                htmlErr += '<li>' + $('<div>').text(msg).html() + '</li>';
            });
            htmlErr += '</ul>';
            $err.removeClass('d-none').html(htmlErr);
        } else {
            $err.addClass('d-none').empty();
        }

        var avisosCoherencia = (result.advertencias || []).filter(function (msg) {
            var texto = String(msg || '');
            return texto.indexOf('se usa gravado = IVA') === -1
                && texto.indexOf('Al guardar se abrirá el neto') === -1
                && texto.indexOf('Al guardar se abrira el neto') === -1;
        });
        if (avisosCoherencia.length) {
            $aviso.removeClass('d-none').text(avisosCoherencia[0]);
        } else {
            $aviso.addClass('d-none').empty();
        }
    }

    function escHtml(valor) {
        return $('<div>').text(valor == null ? '' : String(valor)).html();
    }

    function actualizarEventualSegunProveedor() {
        var id = parseInt($('#ie-cp-proveedor-id').val() || '0', 10) || 0;
        var $bloque = $('#ie-cp-eventual-bloque');
        if (id > 0) {
            $bloque.addClass('d-none');
            $('#ie-cp-eventual-nombre, #ie-cp-eventual-documento').val('');
            $('#ie-cp-eventual-condicioniva').val('');
            return;
        }
        $bloque.removeClass('d-none');
    }

    function htmlCampoCuentaGasto(cuentaId, codigo, nombre) {
        var id = parseInt(cuentaId || '0', 10) || 0;
        return '<div class="tm-cuentacontable-campo ie-cp-debito-cuenta d-flex flex-nowrap align-items-center" style="gap:4px;">' +
            '<input type="hidden" class="cuentacontable_id ie-cp-debito-cuenta-id" value="' + (id > 0 ? id : '') + '">' +
            '<input type="hidden" class="codigo_previo" value="' + escHtml(codigo || '') + '">' +
            '<button type="button" title="Elegir cuenta de gasto (F1)" class="btn-accion-tabla consultacuentacontable flex-shrink-0">' +
            '<i class="fa fa-search text-primary"></i></button>' +
            '<input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5rem;flex-shrink:0;" value="' + escHtml(codigo || '') + '" placeholder="Cód." autocomplete="off">' +
            '<input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly value="' + escHtml(nombre || '') + '" placeholder="Cuenta de gasto" style="min-width:0;flex:1 1 auto;">' +
            '</div>';
    }

    function htmlSelectCentroCosto(linea) {
        var cuentaId = parseInt(linea.cuentacontable_id || '0', 10) || 0;
        var cc = parseInt(linea.centrocosto_id || '0', 10) || 0;
        if (cuentaId <= 0) {
            return '—';
        }
        var opt = cc > 0
            ? '<option value="' + cc + '" selected>' + escHtml(linea.centrocosto_codigo || String(cc)) + '</option>'
            : '<option value="">—</option>';
        return '<select class="form-control form-control-sm ie-cp-centrocosto" data-cuenta-id="' + cuentaId + '" data-cc-actual="' + cc + '">' + opt + '</select>';
    }

    function filaGastoHtml(linea, quitar) {
        var origen = linea.origen || 'debe_gasto';
        var esReparto = origen === 'debe_gasto';
        var cuentaId = parseInt(linea.cuentacontable_id || '0', 10) || 0;
        var importe = parseFloat(linea.importe != null ? linea.importe : linea.debe) || 0;
        var conceptoId = parseInt(linea.concepto_ivacompra_id || '0', 10) || 0;
        var debeHtml = esReparto
            ? '<input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm text-right ie-cp-debito-importe" value="' + importe.toFixed(2) + '">'
            : formatoNumero(importe);
        var quitarHtml = (esReparto && quitar)
            ? '<button type="button" class="btn-accion-tabla ie-cp-debito-quitar" title="Quitar cuenta de gasto"><i class="fa fa-times-circle text-danger"></i></button>'
            : '';
        return '<tr class="ie-cp-linea-gasto' + (esReparto ? ' ie-cp-debito-gasto' : ' ie-cp-neto-manual') + '" data-importe="' + importe + '" data-concepto-id="' + conceptoId + '">' +
            '<td>' + htmlCampoCuentaGasto(cuentaId, linea.codigo, linea.nombre) + '</td>' +
            '<td class="ie-cp-cc">' + htmlSelectCentroCosto(linea) + '</td>' +
            '<td class="text-right">' + debeHtml + '</td>' +
            '<td class="text-right">0.00</td>' +
            '<td class="text-center">' + quitarHtml + '</td></tr>';
    }

    function filaFijaHtml(linea) {
        var texto = $.trim((linea.codigo || '') + ' ' + (linea.nombre || ''));
        if (!texto) {
            texto = linea.observacion || 'Sin cuenta';
        }
        return '<tr class="ie-cp-linea-fija"><td>' + escHtml(texto) +
            (linea.observacion && texto.indexOf(linea.observacion) === -1
                ? '<span class="d-block small text-muted">' + escHtml(linea.observacion) + '</span>'
                : '') +
            '</td><td class="text-muted">—</td><td class="text-right">' + formatoNumero(linea.debe) + '</td>' +
            '<td class="text-right">' + formatoNumero(linea.haber) + '</td><td></td></tr>';
    }

    function leerDebitosDesdeDom() {
        var lineas = [];
        $('#ie-cp-preview-asiento tr.ie-cp-linea-gasto').each(function () {
            var $tr = $(this);
            var importe = $tr.hasClass('ie-cp-debito-gasto')
                ? (parseFloat($tr.find('.ie-cp-debito-importe').val() || '0') || 0)
                : (parseFloat($tr.attr('data-importe') || '0') || 0);
            if (Math.abs(importe) < 0.0001) {
                return;
            }
            lineas.push({
                cuentacontable_id: parseInt($tr.find('.cuentacontable_id').val() || '0', 10) || 0,
                importe: Math.round(Math.abs(importe) * 100) / 100,
                centrocosto_id: parseInt($tr.find('.ie-cp-centrocosto').val() || '0', 10) || 0,
                codigo: $tr.find('.codigocuentacontable').val() || '',
                nombre: $tr.find('.nombrecuentacontable').val() || '',
            });
        });
        return lineas;
    }

    function leerDebitosGastoParaPayload() {
        if (!debitosGastoTocados) {
            return [];
        }
        var delDom = leerDebitosDesdeDom();
        if (delDom.length) {
            return delDom;
        }
        if (Array.isArray(debitosGastoSemilla) && debitosGastoSemilla.length) {
            return debitosGastoSemilla.map(function (linea) {
                return {
                    cuentacontable_id: parseInt(linea.cuentacontable_id || '0', 10) || 0,
                    importe: Math.round(Math.abs(parseFloat(linea.importe || '0') || 0) * 100) / 100,
                };
            }).filter(function (linea) {
                return Math.abs(linea.importe) >= 0.0001;
            });
        }
        return [];
    }

    function actualizarAvisoSumaGasto() {
        var $aviso = $('#ie-cp-debe-gasto-aviso');
        if (!debitosGastoTocados || !ultimoPreview || !ultimoPreview.permite_reparto_gasto) {
            $aviso.text('');
            return;
        }
        var neto = parseFloat(ultimoPreview.neto_imputable_gasto || '0') || 0;
        var suma = 0;
        $('#ie-cp-preview-asiento tr.ie-cp-debito-gasto .ie-cp-debito-importe').each(function () {
            suma += parseFloat($(this).val() || '0') || 0;
        });
        suma = Math.round(suma * 100) / 100;
        var dif = Math.round((neto - suma) * 100) / 100;
        if (Math.abs(dif) <= 0.05) {
            $aviso.removeClass('text-danger').addClass('text-success').text('Suma igual al neto (' + formatoNumero(neto) + ').');
            return;
        }
        $aviso.removeClass('text-success').addClass('text-danger')
            .text('Suma ' + formatoNumero(suma) + ' / neto ' + formatoNumero(neto) + '.');
    }

    function pintarAsientoPreview(data) {
        ultimoPreview = data || {};
        var $tbody = $('#ie-cp-preview-asiento');
        var lineas = data.lineas || [];
        var permite = !!data.permite_reparto_gasto;
        $('#ie-cp-debe-gasto-barra').toggleClass('d-none', !permite);

        function htmlFijas() {
            var html = '';
            lineas.forEach(function (linea) {
                if (linea.origen === 'debe_gasto' || linea.origen === 'neto_manual') {
                    return;
                }
                html += filaFijaHtml(linea);
            });
            return html;
        }

        if (debitosGastoTocados && $tbody.find('tr.ie-cp-debito-gasto').length) {
            $tbody.find('tr.ie-cp-linea-fija, tr.ie-cp-neto-manual').remove();
            $tbody.prepend(htmlFijas());
        } else if (debitosGastoTocados) {
            var gastos = lineas.filter(function (linea) {
                return linea.origen === 'debe_gasto';
            });
            if (!gastos.length && Array.isArray(debitosGastoSemilla)) {
                gastos = debitosGastoSemilla.map(function (linea) {
                    return {
                        origen: 'debe_gasto',
                        cuentacontable_id: linea.cuentacontable_id,
                        centrocosto_id: linea.centrocosto_id || 0,
                        codigo: linea.codigo,
                        nombre: linea.nombre,
                        importe: linea.importe,
                        debe: linea.importe,
                    };
                });
            }
            var quitar = gastos.length > 1;
            var htmlGasto = '';
            gastos.forEach(function (linea) {
                htmlGasto += filaGastoHtml(linea, quitar);
            });
            $tbody.empty().append(htmlFijas() + htmlGasto);
            debitosGastoSemilla = null;
        } else {
            var quitarNeto = false;
            var html = '';
            lineas.forEach(function (linea) {
                if (linea.origen === 'neto_manual' || linea.origen === 'debe_gasto') {
                    html += filaGastoHtml(linea, quitarNeto);
                } else {
                    html += filaFijaHtml(linea);
                }
            });
            $tbody.empty().append(html);
        }

        $('#ie-cp-preview-total-debe').text(formatoNumero(data.total_debe));
        $('#ie-cp-preview-total-haber').text(formatoNumero(data.total_haber));
        actualizarAvisoSumaGasto();
        aplicarCentrosCostoIeCp();
    }

    function aplicarCentrosCostoIeCp() {
        if (typeof window.cargarCentrosCostoEnSelect !== 'function') {
            return;
        }
        var abrirCuenta = ieCpCcAbrirCuentaId;
        $('#ie-cp-preview-asiento .ie-cp-centrocosto').each(function () {
            var $sel = $(this);
            var cuentaId = parseInt($sel.attr('data-cuenta-id') || '0', 10) || 0;
            var actual = parseInt($sel.attr('data-cc-actual') || $sel.val() || '0', 10) || 0;
            window.cargarCentrosCostoEnSelect($sel, cuentaId, actual).done(function (res) {
                if (!res || !res.maneja) {
                    $sel.closest('td').text('—');
                    return;
                }
                if (abrirCuenta > 0 && cuentaId === abrirCuenta && res.cantidad > 1 && res.id <= 0) {
                    ieCpCcAbrirCuentaId = 0;
                    if (typeof window.abrirListaCentroCosto === 'function') {
                        window.abrirListaCentroCosto($sel.get(0));
                    }
                }
            });
        });
    }

    function copiarCuentaGastoAlConcepto($tr) {
        if (!$tr || !$tr.hasClass('ie-cp-neto-manual')) {
            return;
        }
        var conceptoId = parseInt($tr.attr('data-concepto-id') || '0', 10) || 0;
        if (conceptoId <= 0) {
            return;
        }
        var cuentaId = parseInt($tr.find('.cuentacontable_id').val() || '0', 10) || 0;
        if (cuentaId <= 0) {
            delete cuentasGastoManual[String(conceptoId)];
            return;
        }
        cuentasGastoManual[String(conceptoId)] = {
            id: cuentaId,
            codigo: $tr.find('.codigocuentacontable').val() || '',
            nombre: $tr.find('.nombrecuentacontable').val() || '',
        };
    }

    function tasaKeyConcepto(tasa) {
        var n = Math.round((parseFloat(tasa) || 0) * 1000) / 1000;
        return n.toFixed(3);
    }

    function aplicarIvaDesdeGravados() {
        enriquecerMetaGravadosDesdeFormulas();
        var gravados = [];
        var ivas = [];
        var codigosEnGrilla = {};
        $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto').each(function () {
            var $row = $(this);
            var id = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
            var meta = conceptosMeta[String(id)] || {};
            var tipo = String(meta.tipoconcepto || '').toUpperCase();
            var codigo = String($row.find('.codigo_concepto_ivacompra').val() || '').trim();
            var tasa = parseFloat(meta.impuesto_tasa || 0) || 0;
            var monto = parseFloat($row.find('.ie-cp-monto').val() || '0') || 0;
            if (codigo) {
                codigosEnGrilla[codigo] = true;
            }
            if (id <= 0) {
                return;
            }
            if (tipo === 'G' && tasa > 0) {
                gravados.push({ codigo: codigo, tasa: tasa, monto: monto });
            } else if (tipo === 'I' && tasa > 0) {
                ivas.push({
                    tasa: tasa,
                    codigoFormula: String(meta.formula_codigo_base || '').trim(),
                    coef: parseFloat(meta.formula_coeficiente || 0) || 0,
                    $monto: $row.find('.ie-cp-monto'),
                });
            }
        });

        ivas.forEach(function (iva) {
            var base = 0;
            var coef = iva.coef;
            var usaFormula = iva.codigoFormula && codigosEnGrilla[iva.codigoFormula] && coef > 0;
            if (usaFormula) {
                gravados.forEach(function (g) {
                    if (g.codigo === iva.codigoFormula) {
                        base += g.monto;
                    }
                });
            } else {
                coef = iva.tasa / 100;
                gravados.forEach(function (g) {
                    if (tasaKeyConcepto(g.tasa) === tasaKeyConcepto(iva.tasa)) {
                        base += g.monto;
                    }
                });
            }
            var montoIva = Math.round(base * coef * 100) / 100;
            if (Math.abs(base) < 0.0001) {
                if (String(iva.$monto.val() || '') !== '') {
                    iva.$monto.val('');
                }
                return;
            }
            iva.$monto.val(montoIva.toFixed(2));
        });
    }

    function marcarSucursalInvalida(invalida) {
        $('#ie-cp-sucursal').toggleClass('is-invalid', !!invalida);
        $('#ie-cp-aviso-sucursal').toggleClass('d-none', !invalida);
    }

    function repartirImportesIguales(lineas, neto) {
        neto = Math.round(Math.abs(parseFloat(neto) || 0) * 100) / 100;
        var n = lineas.length;
        if (n <= 0) {
            return lineas;
        }
        if (n === 1) {
            lineas[0].importe = neto;
            lineas[0].debe = neto;
            return lineas;
        }
        var base = Math.floor((neto / n) * 100) / 100;
        var asignado = 0;
        for (var i = 0; i < n; i++) {
            var importe = i === n - 1
                ? Math.round((neto - asignado) * 100) / 100
                : base;
            if (i !== n - 1) {
                asignado = Math.round((asignado + base) * 100) / 100;
            }
            lineas[i].importe = importe;
            lineas[i].debe = importe;
        }
        return lineas;
    }

    function pintarLineasGasto(lineas) {
        var $tbody = $('#ie-cp-preview-asiento');
        $tbody.find('tr.ie-cp-linea-gasto').remove();
        var quitar = lineas.length > 1;
        var html = '';
        lineas.forEach(function (linea) {
            html += filaGastoHtml(linea, quitar);
        });
        $tbody.append(html);
        actualizarAvisoSumaGasto();
    }

    function completarResidualGasto($editado) {
        var $inputs = $('#ie-cp-preview-asiento tr.ie-cp-debito-gasto .ie-cp-debito-importe');
        if (!$editado || !$editado.length || $inputs.length < 2) {
            return;
        }
        var neto = ultimoPreview ? (parseFloat(ultimoPreview.neto_imputable_gasto || '0') || 0) : 0;
        neto = Math.round(Math.abs(neto) * 100) / 100;
        var editado = Math.round((parseFloat($editado.val() || '0') || 0) * 100) / 100;
        var $otros = $inputs.filter(function () {
            return this !== $editado.get(0);
        });
        if (!$otros.length) {
            return;
        }
        var sumaFijos = editado;
        $otros.each(function (i) {
            if (i === $otros.length - 1) {
                return;
            }
            sumaFijos += Math.round((parseFloat($(this).val() || '0') || 0) * 100) / 100;
        });
        var residual = Math.round((neto - sumaFijos) * 100) / 100;
        if (residual < 0) {
            residual = 0;
        }
        $otros.last().val(residual.toFixed(2));
        actualizarAvisoSumaGasto();
    }

    function agregarCuentaGasto() {
        var lineas = [];
        $('#ie-cp-preview-asiento tr.ie-cp-linea-gasto').each(function () {
            var $tr = $(this);
            var importe = $tr.hasClass('ie-cp-debito-gasto')
                ? (parseFloat($tr.find('.ie-cp-debito-importe').val() || '0') || 0)
                : (parseFloat($tr.attr('data-importe') || '0') || 0);
            lineas.push({
                origen: 'debe_gasto',
                cuentacontable_id: parseInt($tr.find('.cuentacontable_id').val() || '0', 10) || 0,
                centrocosto_id: parseInt($tr.find('.ie-cp-centrocosto').val() || '0', 10) || 0,
                codigo: $tr.find('.codigocuentacontable').val() || '',
                nombre: $tr.find('.nombrecuentacontable').val() || '',
                importe: Math.round(importe * 100) / 100,
                debe: Math.round(importe * 100) / 100,
            });
        });
        var neto = ultimoPreview ? (parseFloat(ultimoPreview.neto_imputable_gasto || '0') || 0) : 0;
        lineas.push({
            origen: 'debe_gasto',
            cuentacontable_id: 0,
            codigo: '',
            nombre: '',
            importe: 0,
            debe: 0,
        });
        repartirImportesIguales(lineas, neto);
        debitosGastoTocados = true;
        debitosGastoSemilla = null;
        pintarLineasGasto(lineas);
        programarPreview();
        var $codigoNuevo = $('#ie-cp-preview-asiento tr.ie-cp-debito-gasto').last().find('.codigocuentacontable');
        if ($codigoNuevo.length) {
            setTimeout(function () {
                try {
                    $codigoNuevo.trigger('focus').select();
                } catch (errFoco) {
                    // ignore
                }
            }, 0);
        }
    }

    function validarGastosAbiertos() {
        var $gastos = $('#ie-cp-preview-asiento tr.ie-cp-linea-gasto');
        if (!$gastos.length) {
            return null;
        }
        var faltaCuenta = false;
        $gastos.each(function () {
            var id = parseInt($(this).find('.cuentacontable_id').val() || '0', 10) || 0;
            var importe = $(this).hasClass('ie-cp-debito-gasto')
                ? (parseFloat($(this).find('.ie-cp-debito-importe').val() || '0') || 0)
                : (parseFloat($(this).attr('data-importe') || '0') || 0);
            if (Math.abs(importe) >= 0.0001 && id <= 0) {
                faltaCuenta = true;
            }
        });
        if (faltaCuenta) {
            return 'Indique la cuenta de cada débito de gasto en la vista previa del asiento.';
        }
        if (debitosGastoTocados && ultimoPreview && ultimoPreview.permite_reparto_gasto) {
            var neto = parseFloat(ultimoPreview.neto_imputable_gasto || '0') || 0;
            var suma = 0;
            $('#ie-cp-preview-asiento tr.ie-cp-debito-gasto .ie-cp-debito-importe').each(function () {
                suma += parseFloat($(this).val() || '0') || 0;
            });
            if (Math.abs(Math.round((suma - neto) * 100) / 100) > 0.05) {
                return 'La suma de las cuentas de gasto (' + formatoNumero(suma) + ') no coincide con el neto (' + formatoNumero(neto) + ').';
            }
        }
        return null;
    }

    function serializarModal() {
        var conceptos = [];
        $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseFloat($row.find('.ie-cp-monto').val() || '0');
            if (conceptoId <= 0 || monto === 0) {
                return;
            }
            var cuentaManual = cuentasGastoManual[String(conceptoId)];
            var $filaAsiento = $('#ie-cp-preview-asiento tr.ie-cp-neto-manual[data-concepto-id="' + conceptoId + '"]');
            var ccConcepto = parseInt($filaAsiento.find('.ie-cp-centrocosto').val() || '0', 10) || 0;
            conceptos.push({
                concepto_ivacompra_id: conceptoId,
                monto: monto,
                cuentacontabledebe_id: cuentaManual && cuentaManual.id ? cuentaManual.id : null,
                centrocosto_id: ccConcepto > 0 ? ccConcepto : null,
            });
        });

        var gastoBanco = esGastoBanco() && bancoGastoActual && bancoGastoActual.ok;
        var proveedorId = gastoBanco
            ? (parseInt(bancoGastoActual.proveedor_id || '0', 10) || 0)
            : parseInt($('#ie-cp-proveedor-id').val() || '0', 10);
        var editIdx = parseInt($('#ie-cp-edit-index').val(), 10);
        var previo = (editIdx >= 0 && comprobantesIva[editIdx]) ? comprobantesIva[editIdx] : {};
        return {
            id: previo.id || null,
            tipo_tesoreria: $('#ie-cp-tipo-tesoreria').val(),
            tipotransaccion_compra_id: parseInt($('#ie-cp-tipotransaccion-compra-id').val() || '0', 10),
            cuentacaja_id: gastoBanco ? (parseInt(bancoGastoActual.cuentacaja_id || '0', 10) || null) : null,
            proveedor_id: proveedorId,
            proveedor_codigo: gastoBanco ? (bancoGastoActual.proveedor_codigo || '') : $('#ie-cp-proveedor-codigo').val(),
            proveedor_nombre: gastoBanco ? (bancoGastoActual.etiqueta || bancoGastoActual.banco_nombre || '') : $('#ie-cp-proveedor-nombre').val(),
            proveedor_nombre_eventual: gastoBanco
                ? (proveedorId > 0 ? '' : (bancoGastoActual.banco_nombre || ''))
                : (proveedorId > 0 ? '' : $('#ie-cp-eventual-nombre').val()),
            proveedor_documento_eventual: gastoBanco
                ? (proveedorId > 0 ? '' : (bancoGastoActual.cuit || ''))
                : (proveedorId > 0 ? '' : $('#ie-cp-eventual-documento').val()),
            proveedor_condicioniva_id_eventual: gastoBanco
                ? (proveedorId > 0 ? null : (parseInt(bancoGastoActual.condicioniva_id || '0', 10) || null))
                : (proveedorId > 0 ? null : (parseInt($('#ie-cp-eventual-condicioniva').val() || '0', 10) || null)),
            letra: esNumeracionAutomatica() ? 'A' : ($('#ie-cp-letra').val() || 'B').toUpperCase(),
            sucursal: esNumeracionAutomatica() ? 0 : parseInt($('#ie-cp-sucursal').val() || '0', 10),
            numerocomprobante: parseInt($('#ie-cp-numero').val() || '0', 10),
            fechacomprobante: $('#ie-cp-fecha-comprobante').val(),
            fechaiva: $('#ie-cp-fecha-iva').val(),
            total: parseFloat($('#ie-cp-total').val() || '0'),
            moneda_id: parseInt($('#ie-cp-moneda-id').val() || '1', 10),
            cotizacion: 1,
            numerocae: $('#ie-cp-cae').val() || null,
            tipo_autorizacion: $('#ie-cp-tipo-autorizacion').val() || null,
            pdf_temp_id: ($('#ie-cp-pdf-temp-id').val() || '').trim() || null,
            ai_decision_id: iaDecisionId,
            ai_sugerencia_hash: iaSugerenciaHash,
            tiene_pdf: previo.tiene_pdf || false,
            conceptos: conceptos,
            debitos_gasto: leerDebitosGastoParaPayload(),
        };
    }

    function programarPreview() {
        actualizarSumaConceptos();
        renderCoherenciaConceptosModal(validarCoherenciaConceptosModal());
        clearTimeout(previewTimer);
        previewTimer = setTimeout(recargarPreviewAsiento, 400);
    }

    function recargarPreviewAsiento() {
        var $modal = $('#modal-ie-comprobante-iva');
        var url = $modal.data('preview-url');
        var empresaId = $('#empresa_id').val();
        if (!url || !empresaId) {
            return;
        }

        var payload = serializarModal();
        $.post(url, {
            _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
            empresa_id: empresaId,
            comprobante_json: JSON.stringify(payload),
        }).done(function (data) {
            if (data.mensaje !== 'ok') {
                return;
            }
            pintarAsientoPreview(data);

            var $err = $('#ie-cp-preview-error');
            var coherencia = validarCoherenciaConceptosModal();
            if (data.error) {
                $err.removeClass('d-none').text(data.error);
            } else if (!coherencia.valido) {
                $err.removeClass('d-none').text(coherencia.errores.join(' '));
            } else {
                $err.addClass('d-none').empty();
            }

            var avisos = data.avisos || [];
            var $banner = $('#ie-cp-asiento-avisos');
            if (avisos.length) {
                var html = '<ul class="mb-0 pl-3">';
                avisos.forEach(function (a) {
                    html += '<li>' + $('<div>').text(a.mensaje || '').html() + '</li>';
                });
                html += '</ul>';
                $banner.removeClass('d-none').html(html);
            } else {
                $banner.addClass('d-none').empty();
            }
        });
    }

    function guardarDesdeModal() {
        var payload = serializarModal();
        if (!payload.tipotransaccion_compra_id) {
            alert('Seleccione tipo de comprobante.');
            return;
        }
        if (!esNumeracionAutomatica() && (parseInt(payload.sucursal, 10) || 0) <= 0) {
            marcarSucursalInvalida(true);
            focoCampoIe('ie-cp-sucursal');
            return;
        }
        marcarSucursalInvalida(false);
        var sumaConceptos = sumaImportesConceptosModal();
        if (sumaConceptos > 0.001 && payload.total - sumaConceptos > 0.05) {
            payload.total = sumaConceptos;
        }
        if (!(parseFloat(payload.total) > 0)) {
            alert('Indique el total de la factura.');
            focoCampoIe('ie-cp-total');
            return;
        }
        if (sumaConceptos - payload.total > 0.05) {
            alert('Los conceptos (' + formatoNumero(sumaConceptos) + ') superan el total de la factura (' + formatoNumero(payload.total) + ').');
            return;
        }
        if ((payload.conceptos || []).length === 0) {
            alert('Agregue al menos un concepto con importe.');
            return;
        }
        var coherencia = validarCoherenciaConceptosModal();
        renderCoherenciaConceptosModal(coherencia);
        if (!coherencia.valido) {
            alert(coherencia.errores.join('\n'));
            return;
        }
        var errorGasto = validarGastosAbiertos();
        if (errorGasto) {
            alert(errorGasto);
            return;
        }
        if (esGastoBanco()) {
            if (!bancoGastoActual || !bancoGastoActual.ok) {
                alert((bancoGastoActual && bancoGastoActual.mensaje) || 'Falta el banco de la cuenta de caja.');
                return;
            }
        } else if (payload.proveedor_id <= 0 && !payload.proveedor_nombre_eventual) {
            alert('Indique proveedor del maestro o datos de proveedor eventual.');
            return;
        } else if (payload.proveedor_id <= 0 && !payload.proveedor_documento_eventual) {
            alert('El proveedor eventual debe tener CUIT (11 dígitos).');
            return;
        }

        var urlDup = $('#modal-ie-comprobante-iva').data('duplicado-url');
        var empresaId = $('#empresa_id').val();

        function persistirEnGrilla() {
            iaDecisionPendienteModal = false;
            var idx = $('#ie-cp-edit-index').val();
            if (idx !== '') {
                comprobantesIva[parseInt(idx, 10)] = payload;
            } else {
                comprobantesIva.push(payload);
            }
            renderGrilla();
            if (typeof flModificaAsiento !== 'undefined') {
                flModificaAsiento = true;
            }
            $('#modal-ie-comprobante-iva').modal('hide');
        }

        if (!urlDup || !empresaId) {
            persistirEnGrilla();
            return;
        }

        $.post(urlDup, {
            _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
            empresa_id: empresaId,
            comprobante_json: JSON.stringify(payload),
        }).done(function (data) {
            if (data.mensaje !== 'ok' || !data.valido) {
                alert(data.error || 'Comprobante duplicado para este CUIT.');
                return;
            }
            persistirEnGrilla();
        }).fail(function (xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'No se pudo validar duplicado.';
            alert(msg);
        });
    }

    function procesarPdf() {
        var file = $('#ie-cp-pdf-archivo')[0].files[0];
        var empresaId = $('#empresa_id').val();
        var url = $('#modal-ie-comprobante-iva').data('pdf-ia-url');
        if (!file || !empresaId || !url) {
            return;
        }

        var fd = new FormData();
        fd.append('pdf', file);
        fd.append('empresa_id', empresaId);
        fd.append('_token', $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val());

        $('#ie-cp-pdf-procesar').prop('disabled', true);
        $.ajax({
            url: url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        }).done(function (data) {
            if (data.mensaje !== 'ok' || !data.ok) {
                $('#ie-cp-pdf-error').removeClass('d-none').text(data.error || 'Error al procesar PDF');
                return;
            }
            $('#modal-ie-comprobante-iva-pdf').modal('hide');
            abrirModal(null);
            if (data.pdf_temp_id) {
                $('#ie-cp-pdf-temp-id').val(data.pdf_temp_id);
            }
            iaDecisionId = data.ai_decision_id || null;
            iaSugerenciaHash = data.ai_sugerencia_hash || null;
            iaDecisionPendienteModal = !!iaDecisionId;
            var cab = data.cabecera || {};
            if (cab.proveedor_id) {
                $('#ie-cp-proveedor-id').val(cab.proveedor_id);
                $('#ie-cp-proveedor-codigo').val(cab.proveedor_codigo || '');
                $('#ie-cp-proveedor-nombre').val(cab.proveedor_nombre || '');
            } else {
                $('#ie-cp-eventual-nombre').val(cab.proveedor_nombre || '');
                aplicarCuitEventual(cab.proveedor_documento_eventual || '');
            }
            if (cab.tipotransaccion_compra_id) {
                aplicarTipoComprobanteEnModal(cab.tipotransaccion_compra_id, !(data.conceptos || []).length);
            }
            $('#ie-cp-letra').val(cab.letra || 'B');
            $('#ie-cp-sucursal').val(cab.sucursal || '');
            $('#ie-cp-numero').val(cab.numerocomprobante || '');
            actualizarModoNumeracion();
            $('#ie-cp-fecha-comprobante').val((cab.fechacomprobante || '').slice(0, 10));
            $('#ie-cp-fecha-iva').val((cab.fechaiva || '').slice(0, 10));
            $('#ie-cp-fecha-iva').data(
                'seguir-comprobante',
                ($('#ie-cp-fecha-comprobante').val() || '') === ($('#ie-cp-fecha-iva').val() || '') ? '1' : '0'
            );
            $('#ie-cp-total').val(cab.total || 0);
            $('#ie-cp-cae').val(cab.numerocae || '');
            $('#ie-cp-tipo-autorizacion').val(cab.tipo_autorizacion || (cab.numerocae ? 'CAE' : ''));
            actualizarFilaAutorizacion();
            $('#ie-cp-tbody-conceptos').empty();
            (data.conceptos || []).forEach(function (c) {
                agregarFilaConcepto(c);
            });
            if ((data.conceptos || []).length === 0) {
                agregarFilaConcepto(null);
            }
            var adv = data.advertencias || [];
            if (adv.length) {
                $('#ie-cp-asiento-avisos').removeClass('d-none').html('<ul><li>' + adv.join('</li><li>') + '</li></ul>');
            }
            programarPreview();
        }).fail(function (xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al procesar PDF';
            $('#ie-cp-pdf-error').removeClass('d-none').text(msg);
        }).always(function () {
            $('#ie-cp-pdf-procesar').prop('disabled', false);
        });
    }

    $(function () {
        init();

        $('#ie-btn-nuevo-comprobante-iva').on('click', function () {
            abrirModal(null);
        });

        $('#ie-btn-pdf-ia-comprobante').on('click', function () {
            $('#ie-cp-pdf-archivo').val('');
            $('#ie-cp-pdf-error, #ie-cp-pdf-advertencias').addClass('d-none').empty();
            $('#modal-ie-comprobante-iva-pdf').modal('show');
        });

        $('#ie-cp-pdf-procesar').on('click', procesarPdf);
        $('#ie-cp-agregar-concepto').on('click', function () {
            agregarFilaConcepto(null);
        });

        $('#ie-cp-tipo-tesoreria').on('change', aplicarModoProveedor);
        $('#ie-cp-banco-cuenta').on('change', function () {
            var id = parseInt($(this).val() || '0', 10) || 0;
            if (id > 0) {
                resolverBancoGasto(id);
            }
        });

        $('#ie-cp-tipotransaccion-compra-id').on('change', function () {
            precargarConceptosPorTipo($(this).val());
        });

        $(document).on('cp:tipotransaccion-compra-elegido.ieCp', function (e, tipoId) {
            if (!$('#modal-ie-comprobante-iva').hasClass('show')) {
                return;
            }
            var $origen = $('#ie-cp-tipotransaccion-compra-id');
            if (!$origen.length || String($origen.val() || '') !== String(tipoId || '')) {
                return;
            }
            precargarConceptosPorTipo(tipoId);
            actualizarModoNumeracion();
        });

        window.afterTipotransaccionCompraEnterOk = function (data, target) {
            if (!target || !$(target).closest('#modal-ie-comprobante-iva').length) {
                return;
            }
            if (data && data.id) {
                actualizarModoNumeracion();
                focoCampoIe(esNumeracionAutomatica() ? 'ie-cp-fecha-comprobante' : 'ie-cp-letra');
            }
        };

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.which !== 13) {
                return;
            }
            if (e.target && e.target.classList && e.target.classList.contains('ie-cp-debito-importe')) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            if (e.target && e.target.classList && e.target.classList.contains('ie-cp-monto')
                && $(e.target).closest('#modal-ie-comprobante-iva').length) {
                e.preventDefault();
                e.stopPropagation();
                focoSiguienteImporteConcepto(e.target);
                return;
            }
            var id = e.target && e.target.id;
            var siguiente = {
                'ie-cp-letra': 'ie-cp-sucursal',
                'ie-cp-sucursal': 'ie-cp-numero',
                'ie-cp-numero': 'ie-cp-fecha-comprobante',
                'ie-cp-fecha-comprobante': 'ie-cp-fecha-iva',
                'ie-cp-fecha-iva': 'ie-cp-total',
            };
            if (id === 'ie-cp-total') {
                e.preventDefault();
                e.stopPropagation();
                var totalFactura = parseFloat($('#ie-cp-total').val() || '0') || 0;
                if (!(totalFactura > 0)) {
                    alert('Indique el total de la factura.');
                    focoCampoIe('ie-cp-total');
                    return;
                }
                focoPrimerImporteConcepto();
                return;
            }
            if (!siguiente[id]) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (id === 'ie-cp-sucursal') {
                var sucursal = parseInt($('#ie-cp-sucursal').val() || '0', 10) || 0;
                if (sucursal <= 0) {
                    marcarSucursalInvalida(true);
                    focoCampoIe('ie-cp-sucursal');
                    return;
                }
                marcarSucursalInvalida(false);
            }
            if (id === 'ie-cp-fecha-comprobante') {
                var $iva = $('#ie-cp-fecha-iva');
                if (String($iva.data('seguir-comprobante') || '1') !== '0') {
                    $iva.val($('#ie-cp-fecha-comprobante').val());
                }
            }
            focoCampoIe(siguiente[id]);
        }, true);

        $('#ie-cp-fecha-comprobante').on('change', function () {
            var $iva = $('#ie-cp-fecha-iva');
            if (String($iva.data('seguir-comprobante') || '1') !== '0') {
                $iva.val($(this).val());
            }
        });

        $('#ie-cp-fecha-iva').on('input change', function () {
            var comp = $('#ie-cp-fecha-comprobante').val() || '';
            $(this).data('seguir-comprobante', ($(this).val() || '') === comp ? '1' : '0');
        });

        $(document).on('click', '.ie-del-comprobante', function () {
            var idx = parseInt($(this).data('idx'), 10);
            if (confirm('¿Quitar este comprobante de la grilla?')) {
                comprobantesIva.splice(idx, 1);
                renderGrilla();
                if (typeof flModificaAsiento !== 'undefined') {
                    flModificaAsiento = true;
                }
            }
        });

        $(document).on('click', '.ie-edit-comprobante', function () {
            abrirModal(parseInt($(this).data('idx'), 10));
        });

        $('#ie-cp-guardar-modal').on('click', guardarDesdeModal);

        $('#modal-ie-comprobante-iva').on('hidden.bs.modal', function () {
            descartarDecisionPendiente();
            iaDecisionId = null;
            iaSugerenciaHash = null;
            iaDecisionPendienteModal = false;
        });

        $(document).on('change input', '#ie-cp-tbody-conceptos .concepto_ivacompra_id, #ie-cp-tbody-conceptos .ie-cp-monto, #ie-cp-total', function () {
            var $row = $(this).closest('.ie-cp-fila-concepto');
            if ($row.length && $(this).hasClass('ie-cp-monto')) {
                var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
                var tipo = String((conceptosMeta[String(conceptoId)] || {}).tipoconcepto || '').toUpperCase();
                if (tipo === 'G') {
                    aplicarIvaDesdeGravados();
                }
            }
            programarPreview();
        });

        $(document).on('cp:concepto-ivacompra-elegido', function (e, data) {
            var $row = null;
            if (data && data.id && ptrConceptoIvacompraId && ptrConceptoIvacompraId.length) {
                $row = ptrConceptoIvacompraId.closest('.ie-cp-fila-concepto');
            }
            if (!$row || !$row.length) {
                $row = $('#ie-cp-tbody-conceptos .ie-cp-fila-concepto').filter(function () {
                    return parseInt($(this).find('.concepto_ivacompra_id').val() || '0', 10) > 0;
                }).last();
            }
            if ($row && $row.length) {
                aplicarIvaDesdeGravados();
            }
            programarPreview();
        });

        $(document).on('click', '.ie-cp-quitar-concepto', function () {
            $(this).closest('.ie-cp-fila-concepto').remove();
            aplicarIvaDesdeGravados();
            programarPreview();
        });

        // Lupa / F1 usan .consultacuentacontable (consulta.js → abrirModalConsultaCuentaContableDesdeContexto).
        // Al abrir desde el modal IVA, apilar z-index y marcar fila para el hook de Elegir.
        $(document).on('click', '#ie-cp-tbody-conceptos .consultacuentacontable', function () {
            ptrFilaCuentaConcepto = $(this).closest('.ie-cp-fila-concepto');
            window.ptrIeCpFilaCuentaConcepto = ptrFilaCuentaConcepto;
            apilarModalCuentaSobreComprobanteIva();
        });

        $(document).on('keydown', '#ie-cp-tbody-conceptos .codigocuentacontable', function (e) {
            if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
                ptrFilaCuentaConcepto = $(this).closest('.ie-cp-fila-concepto');
                window.ptrIeCpFilaCuentaConcepto = ptrFilaCuentaConcepto;
                apilarModalCuentaSobreComprobanteIva();
            }
        });

        $('#consultacuentaModal')
            .off('shown.bs.modal.ieCpCuenta hidden.bs.modal.ieCpCuenta')
            .on('shown.bs.modal.ieCpCuenta', function () {
                if ($('#modal-ie-comprobante-iva').hasClass('show')) {
                    apilarModalCuentaSobreComprobanteIva();
                }
            })
            .on('hidden.bs.modal.ieCpCuenta', function () {
                desapilarModalCuentaSobreComprobanteIva();
                if ($('#modal-ie-comprobante-iva').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });

        // Tras elegir cuenta (consulta.js escribe en .tm-cuentacontable-campo), refrescar preview.
        $(document).on('click', '#ie-cp-debe-gasto-agregar', function (e) {
            e.preventDefault();
            agregarCuentaGasto();
        });

        $(document).on('click', '#ie-cp-preview-asiento .ie-cp-debito-quitar', function (e) {
            e.preventDefault();
            var $quitar = $(this).closest('tr');
            var lineas = [];
            $('#ie-cp-preview-asiento tr.ie-cp-debito-gasto').each(function () {
                if (this === $quitar[0]) {
                    return;
                }
                var $tr = $(this);
                lineas.push({
                    origen: 'debe_gasto',
                    cuentacontable_id: parseInt($tr.find('.cuentacontable_id').val() || '0', 10) || 0,
                    centrocosto_id: parseInt($tr.find('.ie-cp-centrocosto').val() || '0', 10) || 0,
                    codigo: $tr.find('.codigocuentacontable').val() || '',
                    nombre: $tr.find('.nombrecuentacontable').val() || '',
                    importe: parseFloat($tr.find('.ie-cp-debito-importe').val() || '0') || 0,
                    debe: parseFloat($tr.find('.ie-cp-debito-importe').val() || '0') || 0,
                });
            });
            if (!lineas.length) {
                return;
            }
            var neto = ultimoPreview ? (parseFloat(ultimoPreview.neto_imputable_gasto || '0') || 0) : 0;
            repartirImportesIguales(lineas, neto);
            debitosGastoTocados = true;
            pintarLineasGasto(lineas);
            programarPreview();
        });

        $(document).on('input', '#ie-cp-preview-asiento .ie-cp-debito-importe', function () {
            completarResidualGasto($(this));
        });

        $(document).on('change', '#ie-cp-preview-asiento .ie-cp-centrocosto', function () {
            ieCpCcAbrirCuentaId = 0;
            if ($(this).closest('tr').hasClass('ie-cp-debito-gasto')) {
                debitosGastoTocados = true;
            }
            programarPreview();
        });

        $(document).on('change', '#ie-cp-preview-asiento .ie-cp-debito-importe', function () {
            completarResidualGasto($(this));
            programarPreview();
        });

        $('#ie-cp-sucursal').on('input', function () {
            var sucursal = parseInt($(this).val() || '0', 10) || 0;
            if (sucursal > 0) {
                marcarSucursalInvalida(false);
            }
        });

        $(document).on('change', '#ie-cp-preview-asiento .cuentacontable_id', function () {
            var $tr = $(this).closest('tr');
            ieCpCcAbrirCuentaId = parseInt($(this).val() || '0', 10) || 0;
            if ($tr.hasClass('ie-cp-neto-manual')) {
                copiarCuentaGastoAlConcepto($tr);
                programarPreview();
                return;
            }
            if ($tr.hasClass('ie-cp-debito-gasto')) {
                debitosGastoTocados = true;
                programarPreview();
            }
        });

        $(document).on('change', '#ie-cp-tbody-conceptos .cuentacontable_id', function () {
            var $row = $(this).closest('.ie-cp-fila-concepto');
            if ($row.length) {
                var id = parseInt($(this).val() || '0', 10) || 0;
                if (id > 0) {
                    $row.removeClass('table-warning');
                } else {
                    $row.addClass('table-warning');
                }
            }
            programarPreview();
        });

        window.ieComprobanteIvaAplicarCuenta = function (cuentaId, codigo, nombre) {
            if (!ptrFilaCuentaConcepto || !ptrFilaCuentaConcepto.length) {
                return;
            }
            setCuentaFila(ptrFilaCuentaConcepto, cuentaId, codigo, nombre);
            ptrFilaCuentaConcepto = null;
            window.ptrIeCpFilaCuentaConcepto = null;
            programarPreview();
        };

        function apilarModalCuentaSobreComprobanteIva() {
            if (!$('#modal-ie-comprobante-iva').hasClass('show')) {
                return;
            }
            var $cta = $('#consultacuentaModal');
            var zParent = parseInt($('#modal-ie-comprobante-iva').css('z-index'), 10) || 1050;
            $cta.css('z-index', zParent + 20);
            setTimeout(function () {
                $('.modal-backdrop').last().css('z-index', zParent + 10);
            }, 0);
        }

        function desapilarModalCuentaSobreComprobanteIva() {
            $('#consultacuentaModal').css('z-index', '');
        }

        window.afterProveedorConsultaOk = function (data, $input) {
            if (!$input || !$input.closest('#ie-cp-div-proveedor').length) {
                return;
            }
            if (!$input.data('cp-avanzar-tras-ok')) {
                return;
            }
            $input.removeData('cp-avanzar-tras-ok');
            actualizarEventualSegunProveedor();
            var idProveedor = parseInt($('#ie-cp-proveedor-id').val() || '0', 10) || 0;
            if (idProveedor > 0) {
                focoPrimerImporteConcepto();
                return;
            }
            focoCampoIe('ie-cp-eventual-nombre');
        };

        window.ieComprobanteIvaAplicarProveedor = function (id, nombre, codigo) {
            if (!$('#modal-ie-comprobante-iva').hasClass('show')) {
                return;
            }
            var idNum = parseInt(id || '0', 10) || 0;
            $('#ie-cp-proveedor-id').val(idNum > 0 ? String(idNum) : '');
            $('#ie-cp-proveedor-nombre').val(nombre || '');
            if (codigo !== undefined) {
                $('#ie-cp-proveedor-codigo').val(codigo || '');
            }
            actualizarEventualSegunProveedor();
        };

        function apilarModalSobreComprobanteIva(selector) {
            if (!$('#modal-ie-comprobante-iva').hasClass('show')) {
                return;
            }
            var $hijo = $(selector);
            var zParent = parseInt($('#modal-ie-comprobante-iva').css('z-index'), 10) || 1050;
            $hijo.css('z-index', zParent + 20);
            setTimeout(function () {
                $('.modal-backdrop').last().css('z-index', zParent + 10);
            }, 0);
        }

        function alCerrarModalHijoSobreComprobanteIva(selector) {
            $(selector).css('z-index', '');
            if ($('#modal-ie-comprobante-iva').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        }

        $('#consultaproveedorModal, #consultatipotransaccioncompraModal')
            .off('shown.bs.modal.ieCpApilar hidden.bs.modal.ieCpApilar')
            .on('shown.bs.modal.ieCpApilar', function () {
                apilarModalSobreComprobanteIva(this);
            })
            .on('hidden.bs.modal.ieCpApilar', function () {
                alCerrarModalHijoSobreComprobanteIva(this);
            });

        function parseMontoCajaIe(val) {
            if (window.AsientoMontosFormato && typeof AsientoMontosFormato.parseDecimal === 'function') {
                return AsientoMontosFormato.parseDecimal(val);
            }
            var n = parseFloat(String(val || '').replace(/\./g, '').replace(',', '.'));
            return isNaN(n) ? 0 : n;
        }

        window.obtenerComprobantesIvaIngresoEgreso = function () {
            return comprobantesIva;
        };

        window.validarComprobantesIvaContraCaja = function (callback) {
            var url = $('#tabla-comprobantes-iva-ie').data('validar-url');
            if (!url || comprobantesIva.length === 0) {
                if (typeof callback === 'function') {
                    callback(true);
                }
                return;
            }

            var montos = [];
            var monedaIds = [];
            var cotizaciones = [];
            $('#tbody-cuenta-table .item-cuenta').each(function () {
                montos.push(parseMontoCajaIe($(this).find('.monto').val()));
                monedaIds.push($(this).find('.moneda').val() || 1);
                var cotTxt = $.trim($(this).find('.cotizacion').val() || '');
                cotizaciones.push(cotTxt === '' ? 1 : parseMontoCajaIe(cotTxt));
            });

            $.post(url, {
                _token: $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val(),
                comprobantes_ivacompra_json: JSON.stringify(comprobantesIva),
                montos: montos,
                moneda_ids: monedaIds,
                cotizaciones: cotizaciones,
                conceptogasto_id: $('#conceptogasto_id').val() || '',
            }).done(function (data) {
                if (data.mensaje !== 'ok' || !data.valido) {
                    alert(data.error || 'La suma de comprobantes IVA no coincide con el total del pago.');
                    if (typeof callback === 'function') {
                        callback(false);
                    }
                    return;
                }
                if (typeof callback === 'function') {
                    callback(true);
                }
            }).fail(function () {
                alert('No se pudo validar los comprobantes IVA contra el pago.');
                if (typeof callback === 'function') {
                    callback(false);
                }
            });
        };
    });
})(jQuery);
