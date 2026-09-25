$(function () {
    var $alertaContabilizar = $('#cp-alerta-contabilizar');
    if ($alertaContabilizar.length && $alertaContabilizar.offset()) {
        $('html, body').animate({ scrollTop: Math.max(0, $alertaContabilizar.offset().top - 80) }, 250);
    }

    if (!$('#form-comprobante-proveedor').length) {
        return;
    }

    var $form = $('#form-comprobante-proveedor');
    var comprobanteId = parseInt($form.attr('data-comprobante-id') || $form.data('comprobanteId') || '0', 10);
    var contabilizado = String($form.attr('data-contabilizado') || $form.data('contabilizado') || '0') === '1';
    // Preferir attr(): jQuery .data() a veces no lee data-preview-url en kebab-case.
    var previewUrl = String($form.attr('data-preview-url') || $form.data('previewUrl') || '').trim();
    var puedeEditarConceptoIva = String($form.attr('data-puede-editar-concepto-iva') || $form.data('puedeEditarConceptoIva') || '0') === '1';
    var urlEditarConceptoIvaTpl = String($form.attr('data-url-editar-concepto-iva') || $form.data('urlEditarConceptoIva') || '');
    var previewTimer = null;
    var previewSeq = 0;
    var previewXhr = null;
    /** @type {Object.<string, {id:number, codigo:string, nombre:string}>} */
    var cuentasAsientoManualPorConcepto = {};
    var conceptosMeta = {};
    /** Reparto multi-cuenta Debe gasto (solapa Asiento). */
    var debeGastoActivo = false;
    /** Líneas pendientes de mostrar (Agregar/Quitar antes del refresh AJAX). */
    var debeGastoPendiente = null;

        try {
            conceptosMeta = JSON.parse($('#cp-conceptos-cuenta-meta').text() || '{}');
        } catch (e) {
            conceptosMeta = {};
        }

    var TIPOS_NETO = ['N', 'G', 'E'];
    var TIPO_IMPUESTO_INTERNO = 'T';
    var CODIGOS_IMPUESTO_INTERNO = ['5', '510'];
    var CODIGOS_EXENTO_NO_GRAVADO = ['1'];
    var CODIGOS_DESCUENTO = ['80', '81'];

    function esExento(tipoconcepto, codigo) {
        if (String(tipoconcepto || '').toUpperCase() === 'E') {
            return true;
        }
        var cod = String(codigo == null ? '' : codigo).trim();
        return cod !== '' && CODIGOS_EXENTO_NO_GRAVADO.indexOf(cod) >= 0;
    }

    function parseMonto(val) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.parseDecimal === 'function') {
            return window.AsientoMontosFormato.parseDecimal(val);
        }
        var n = parseFloat(String(val || '').replace(/\./g, '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function formatearInputMontoEn($root) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.initEnContenedor === 'function') {
            window.AsientoMontosFormato.initEnContenedor($root && $root.length ? $root[0] : document);
        }
    }

    function mostrarSolapa(sel) {
        var paneId = String(sel || '').replace(/^#/, '');
        if (!paneId) {
            return;
        }
        var $link = $('#cp-tabs-comprobante .nav-link[href="#' + paneId + '"]');
        if ($link.length && typeof $link.tab === 'function') {
            $link.tab('show');
            return;
        }
        // Fallback legacy (por si falta Bootstrap tabs)
        $('.cp-solapa').hide().removeClass('show active');
        $('#' + paneId).show().addClass('show active');
    }

    function abrirSolapaCom() {
        if ($('#cp-solapa-recepciones-com').length) {
            mostrarSolapa('#cp-solapa-recepciones-com');
            actualizarUiRecepcionesCom();
            return;
        }
        if ($('#cp-solapa-recepciones-com-inline').length) {
            mostrarSolapa('#cp-solapa-principal');
            $('html, body').animate({
                scrollTop: $('#cp-solapa-recepciones-com-inline').offset().top - 80
            }, 200);
            actualizarUiRecepcionesCom();
        }
    }

    function abrirSolapaOc() {
        if ($('#cp-solapa-ordencompra').length) {
            mostrarSolapa('#cp-solapa-ordencompra');
        }
    }

    function marcarTabActivo(btnDomId) {
        var $b = $('#' + btnDomId);
        if ($b.length && $b.hasClass('nav-link') && typeof $b.tab === 'function') {
            $b.tab('show');
        }
    }

    function esModoAsignaRecepcion() {
        return $('#modo_carga').val() === 'ASIGNA_RECEPCION';
    }

    function normalizarTextoTipo(val) {
        return String(val || '')
            .toUpperCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function tipoGenericoComprobante() {
        var abrev = normalizarTextoTipo($('#tipotransaccion_compra_id_abreviatura').val());
        var nombre = normalizarTextoTipo($('#tipotransaccion_compra_id_descripcion').val());
        if (abrev.indexOf('NC') === 0) {
            return 'NC';
        }
        if (abrev.indexOf('ND') === 0) {
            return 'ND';
        }
        if (abrev.indexOf('REC') === 0) {
            return 'REC';
        }
        if (abrev.length >= 3) {
            var ini = abrev.charAt(0);
            if (ini === 'C' && abrev !== 'COM' && abrev !== 'COV') {
                return 'NC';
            }
            if (ini === 'D') {
                return 'ND';
            }
        }
        if (/NOTA\s+DE\s+DEBITO|\bND\b/.test(nombre)) {
            return 'ND';
        }
        if (/NOTA\s+DE\s+CREDITO|\bNC\b/.test(nombre)) {
            return 'NC';
        }
        if (/\bRECIBO\b/.test(nombre)) {
            return 'REC';
        }
        return 'FC';
    }

    function tipoNoExigeCom() {
        var t = tipoGenericoComprobante();
        return t === 'NC' || t === 'ND' || t === 'REC'
            || String($form.attr('data-sin-com-por-tipo') || '') === '1';
    }

    function mensajeSinComPorTipo(tipo) {
        if (tipo === 'ND') {
            return 'Las notas de débito no requieren recepción COM.';
        }
        if (tipo === 'NC') {
            return 'Las notas de crédito no requieren recepción COM.';
        }
        if (tipo === 'REC') {
            return 'Los recibos no requieren recepción COM.';
        }
        return 'Este tipo de comprobante no requiere recepción COM.';
    }

    function setModoCargaSinRecepcion(mensaje) {
        var $modo = $('#modo_carga');
        if (!$modo.length) {
            return;
        }
        $modo.val('SIN_RECEPCION');
        var $txt = $modo.siblings('input.form-control[readonly]').first();
        if ($txt.length) {
            $txt.val('Gasto sin recepción');
        }
        var $help = $modo.siblings('small.form-text').first();
        if ($help.length && mensaje) {
            $help.text(mensaje);
        }
        if ($modo.is('select')) {
            $modo.trigger('change');
        } else {
            toggleBloqueRecepcionesCom();
        }
    }

    function aplicarExcepcionComPorTipo() {
        var tipo = tipoGenericoComprobante();
        var abrev = normalizarTextoTipo($('#tipotransaccion_compra_id_abreviatura').val());
        var detectado = tipo === 'NC' || tipo === 'ND' || tipo === 'REC';
        if (detectado) {
            $form.attr('data-sin-com-por-tipo', '1');
        } else if (abrev !== '') {
            $form.attr('data-sin-com-por-tipo', '0');
        }
        if (String($form.attr('data-sin-com-por-tipo') || '') !== '1') {
            return;
        }
        setModoCargaSinRecepcion(mensajeSinComPorTipo(tipo));
        $('#cp-boton-recepciones-com').closest('.nav-item').hide();
        $('#cp-bloque-recepciones-com').hide();
    }

    function contratoImputacionManual() {
        return String($form.attr('data-contrato-imputacion') || '') === 'manual'
            && String($form.attr('data-contrato-vigente') || '') === '1'
            && String($form.attr('data-contrato-requiere-recepcion') || '') !== '1';
    }

    function contratoImputacionArticulos() {
        return String($form.attr('data-contrato-imputacion') || '') === 'articulos'
            && String($form.attr('data-contrato-vigente') || '') === '1'
            && String($form.attr('data-contrato-requiere-recepcion') || '') !== '1';
    }

    function contratoCuentaManualDatos() {
        return {
            id: parseInt($form.attr('data-contrato-cuentacontable-id') || '0', 10) || 0,
            codigo: String($form.attr('data-contrato-cuentacontable-codigo') || ''),
            nombre: String($form.attr('data-contrato-cuentacontable-nombre') || '')
        };
    }

    function cuentaDebeDesdeMeta(conceptoId) {
        var meta = conceptosMeta[conceptoId] || {};
        var empresaIdForm = parseInt($('#empresa_id').val() || '0', 10) || 0;
        var cuentaDebe = parseInt(meta.cuenta_debe_id || '0', 10) || 0;
        if (meta.cuentas_por_empresa && empresaIdForm > 0 && meta.cuentas_por_empresa[empresaIdForm]) {
            cuentaDebe = parseInt(meta.cuentas_por_empresa[empresaIdForm], 10) || 0;
        }
        return {
            id: cuentaDebe,
            codigo: String(meta.cuenta_debe_codigo || ''),
            nombre: String(meta.cuenta_debe_nombre || '')
        };
    }

    function setCuentaDebeEnFila($row, datos) {
        var $campo = $row.find('.cp-celda-cuenta-debe');
        if (!$campo.length) {
            return;
        }
        var id = parseInt((datos && datos.id) || '0', 10) || 0;
        $campo.find('.cuentacontable_id').val(id > 0 ? String(id) : '');
        $campo.find('.codigocuentacontable').val(id > 0 ? String((datos && datos.codigo) || '') : '');
        $campo.find('.nombrecuentacontable').val(id > 0 ? String((datos && datos.nombre) || '') : '');
        if (typeof actualizarLinkEditarCuentaContable === 'function') {
            actualizarLinkEditarCuentaContable($campo, id);
        }
        actualizarVisibilidadEditorCuentaDebe($row);
    }

    function tieneOrdenCompra() {
        var fromData = parseInt($form.attr('data-ordencompra-id') || '0', 10) || 0;
        if (fromData > 0) {
            return true;
        }
        return (parseInt($('#ordencompra_id').val() || $('input[name="ordencompra_id"]').val() || '0', 10) || 0) > 0;
    }

    /**
     * La columna Cuenta DEBE solo se muestra si el renglón no está cubierto por COM
     * ni por otra regla con cuenta ya resuelta (maestro, contrato, artículos OC).
     */
    function esImpuestoInterno(tipoConcepto, codigo) {
        if (String(tipoConcepto || '').toUpperCase() === TIPO_IMPUESTO_INTERNO) {
            return true;
        }
        var cod = String(codigo || '').trim();
        return cod !== '' && CODIGOS_IMPUESTO_INTERNO.indexOf(cod) >= 0;
    }

    function comSeleccionadasIncluyenIi() {
        var incluye = false;
        $('.cp-com-check:checked').each(function () {
            if (String($(this).closest('.cp-com-fila').attr('data-incluye-ii') || '') === '1') {
                incluye = true;
            }
        });
        return incluye;
    }

    function revierteProvisionCom(tipoConcepto, codigo) {
        if (esImpuestoInterno(tipoConcepto, codigo)) {
            return comSeleccionadasIncluyenIi();
        }
        var tipo = String(tipoConcepto || '').toUpperCase();
        return TIPOS_NETO.indexOf(tipo) >= 0;
    }

    function reglaCubreCuentaDebeSinEditor(tipoConcepto, codigo) {
        var tipo = String(tipoConcepto || '');
        var esNeto = TIPOS_NETO.indexOf(tipo) >= 0 && !esImpuestoInterno(tipo, codigo);
        if (esModoAsignaRecepcion() && revierteProvisionCom(tipo, codigo)) {
            return true;
        }
        // OC asociada: neto → cuentas de artículos (igual FAC diferencia / contrato).
        if (tieneOrdenCompra() && esNeto && !contratoImputacionManual()) {
            return true;
        }
        return false;
    }

    function cuentaPreasignadaPorRegla($row) {
        var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
        if (conceptoId <= 0) {
            return null;
        }
        var meta = conceptosMeta[conceptoId] || {};
        var tipo = String(meta.tipoconcepto || '');
        var codigo = String(meta.codigo || '');
        var esNeto = TIPOS_NETO.indexOf(tipo) >= 0 && !esImpuestoInterno(tipo, codigo);

        if (reglaCubreCuentaDebeSinEditor(tipo, codigo)) {
            return { id: -1, codigo: '', nombre: '', via: 'regla' };
        }

        if (contratoImputacionManual() && esNeto) {
            var contrato = contratoCuentaManualDatos();
            if (contrato.id > 0) {
                return contrato;
            }
            return null;
        }

        var desdeMeta = cuentaDebeDesdeMeta(conceptoId);
        if (desdeMeta.id > 0) {
            return desdeMeta;
        }

        return null;
    }

    function filaMuestraEditorCuentaDebe($row) {
        var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
        if (conceptoId <= 0) {
            return false;
        }
        var meta = conceptosMeta[conceptoId] || {};
        var tipo = String(meta.tipoconcepto || '');
        var codigo = String(meta.codigo || '');
        if (reglaCubreCuentaDebeSinEditor(tipo, codigo)) {
            return false;
        }
        var esNeto = TIPOS_NETO.indexOf(tipo) >= 0 && !esImpuestoInterno(tipo, codigo);
        // Neto sin OC/COM: la cuenta se elige en la solapa Asiento contable.
        if (esNeto && !contratoImputacionManual()) {
            return false;
        }
        var pre = cuentaPreasignadaPorRegla($row);
        if (pre && pre.id > 0) {
            return false;
        }
        if (pre && pre.id === -1) {
            return false;
        }
        // Impuestos u otros sin cuenta preasignada: editor en conceptos.
        return true;
    }

    function actualizarVisibilidadEditorCuentaDebe($row) {
        var $campo = $row.find('.cp-celda-cuenta-debe');
        if (!$campo.length) {
            return;
        }
        if (filaMuestraEditorCuentaDebe($row)) {
            $campo.removeClass('d-none');
        } else {
            $campo.addClass('d-none');
        }
    }

    function actualizarColumnaCuentaDebe() {
        var algunaVisible = false;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            actualizarVisibilidadEditorCuentaDebe($row);
            if (filaMuestraEditorCuentaDebe($row)) {
                algunaVisible = true;
            }
        });
        $('#concepto-table thead .cp-th-cuenta-debe').toggleClass('d-none', !algunaVisible);
    }

    function aplicarCuentaContratoEnFila($row, forzar) {
        if (!contratoImputacionManual()) {
            return;
        }
        var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
        var meta = conceptosMeta[conceptoId] || {};
        var tipo = String(meta.tipoconcepto || '');
        var codigo = String(meta.codigo || '');
        // Solo neto: IVA/percepciones usan la cuenta del maestro, no la del contrato.
        var esNeto = TIPOS_NETO.indexOf(tipo) >= 0 && !esImpuestoInterno(tipo, codigo);
        if (!esNeto) {
            return;
        }
        var datos = contratoCuentaManualDatos();
        if (datos.id <= 0) {
            actualizarVisibilidadEditorCuentaDebe($row);
            actualizarColumnaCuentaDebe();
            return;
        }
        var actual = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
        if (!forzar && actual > 0) {
            actualizarVisibilidadEditorCuentaDebe($row);
            actualizarColumnaCuentaDebe();
            return;
        }
        setCuentaDebeEnFila($row, datos);
        actualizarColumnaCuentaDebe();
    }

    function aplicarCuentaConceptoEnFila($row, forzar) {
        var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
        var meta = conceptosMeta[conceptoId] || {};
        var tipo = String(meta.tipoconcepto || '');
        var codigo = String(meta.codigo || '');
        var esNeto = TIPOS_NETO.indexOf(tipo) >= 0 && !esImpuestoInterno(tipo, codigo);
        // Contrato manual: solo el neto toma la cuenta del contrato.
        if (contratoImputacionManual() && esNeto) {
            aplicarCuentaContratoEnFila($row, forzar);
            return;
        }
        // Neto cubierto por OC/COM: no precargar la cuenta del maestro (sale de artículos OC;
        // el override solo se setea desde la solapa Asiento contable).
        if (conceptoId > 0 && reglaCubreCuentaDebeSinEditor(tipo, codigo)) {
            if (forzar) {
                setCuentaDebeEnFila($row, { id: 0, codigo: '', nombre: '' });
            } else {
                actualizarVisibilidadEditorCuentaDebe($row);
            }
            actualizarColumnaCuentaDebe();
            return;
        }
        var actual = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
        if (!forzar && actual > 0) {
            actualizarVisibilidadEditorCuentaDebe($row);
            actualizarColumnaCuentaDebe();
            return;
        }
        if (conceptoId <= 0) {
            setCuentaDebeEnFila($row, { id: 0, codigo: '', nombre: '' });
            actualizarColumnaCuentaDebe();
            return;
        }
        setCuentaDebeEnFila($row, cuentaDebeDesdeMeta(conceptoId));
        actualizarColumnaCuentaDebe();
    }

    function conceptoRequiereCuentaDebe(tipoConcepto) {
        var esNeto = TIPOS_NETO.indexOf(String(tipoConcepto || '')) >= 0;
        if (esModoAsignaRecepcion() && esNeto) {
            return false;
        }
        if (tieneOrdenCompra() && esNeto && !contratoImputacionManual()) {
            return false;
        }
        if (contratoImputacionManual() && esNeto) {
            return false;
        }
        // Neto sin referencia: se completa en solapa asiento, no exige cuenta en conceptos.
        if (esNeto) {
            return false;
        }
        return true;
    }

    function actualizarBadgeAsiento(tieneProblema) {
        var $tab = $('#cp-boton-asiento-contable');
        if (!$tab.length) {
            return;
        }
        $tab.find('.cp-badge-asiento-error').remove();
        if (tieneProblema) {
            $tab.find('.badge-success').remove();
            $tab.append('<span class="badge badge-warning ml-1 cp-badge-asiento-error" title="Revise el cuadre antes de contabilizar">!</span>');
        }
    }

    function actualizarBadgeConceptos(tieneProblema) {
        var $tab = $('#cp-boton-conceptos');
        if (!$tab.length) {
            return;
        }
        $tab.find('.cp-badge-conceptos-error').remove();
        if (tieneProblema) {
            $tab.append('<span class="badge badge-warning ml-1 cp-badge-conceptos-error" title="Revise coherencia neto gravado / IVA">!</span>');
        }
    }

    function lineasConceptosDesdeFormulario() {
        var lineas = [];
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            if (conceptoId <= 0 || monto === 0) {
                return;
            }
            lineas.push({ concepto_ivacompra_id: conceptoId, monto: monto });
        });
        return lineas;
    }

    function validarCoherenciaConceptosPantalla() {
        if (typeof window.ConceptosIvacompraCoherencia === 'undefined') {
            return { valido: true, errores: [], advertencias: [] };
        }
        enriquecerMetaGravadosDesdeFormulas();
        return window.ConceptosIvacompraCoherencia.validar(lineasConceptosDesdeFormulario(), conceptosMeta);
    }

    function renderBannerCoherenciaConceptos(result) {
        var $err = $('#cp-conceptos-iva-coherencia-banner');
        var $aviso = $('#cp-conceptos-iva-coherencia-aviso');
        if (!$err.length) {
            return;
        }

        if (result.errores && result.errores.length) {
            var htmlErr = '<strong><i class="fa fa-exclamation-triangle"></i> Coherencia IVA:</strong><ul class="mb-0 mt-1 pl-3">';
            result.errores.forEach(function (msg) {
                htmlErr += '<li>' + $('<div>').text(msg).html() + '</li>';
            });
            htmlErr += '</ul>';
            $err.removeClass('d-none').html(htmlErr);
        } else {
            $err.addClass('d-none').empty();
        }

        if (result.advertencias && result.advertencias.length) {
            var htmlAv = '<strong><i class="fa fa-info-circle"></i> </strong>' + $('<div>').text(result.advertencias[0]).html();
            $aviso.removeClass('d-none').html(htmlAv);
        } else {
            $aviso.addClass('d-none').empty();
        }

        actualizarBadgeConceptos(!result.valido);
    }

    function marcarAvisosConceptosLocales() {
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var $select = $row.find('.concepto_ivacompra_id');
            var $monto = $row.find('.monto');
            var $aviso = $row.find('.cp-aviso-concepto-cuenta');
            var conceptoId = parseInt($select.val() || '0', 10);
            var monto = parseMonto($monto.val() || '0');

            $row.removeClass('table-warning');
            $aviso.removeClass('text-danger text-success text-muted fa fa-exclamation-triangle fa-check')
                .empty()
                .attr('title', '');

            if (conceptoId <= 0 || monto <= 0) {
                return;
            }

            var meta = conceptosMeta[conceptoId] || {};
            var esNeto = TIPOS_NETO.indexOf(String(meta.tipoconcepto || '')) >= 0;
            if ((tieneOrdenCompra() || contratoImputacionArticulos()) && esNeto && !contratoImputacionManual()) {
                $aviso.addClass('text-muted').text('OC').attr('title', 'Neto: cuenta de los artículos de la OC');
                return;
            }
            if (contratoImputacionManual() && esNeto) {
                var cuentaManual = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
                if (cuentaManual <= 0) {
                    cuentaManual = contratoCuentaManualDatos().id;
                }
                if (cuentaManual <= 0) {
                    $row.addClass('table-warning');
                    $aviso.addClass('text-danger fa fa-exclamation-triangle')
                        .attr('title', 'Falta la cuenta DEBE del neto (indicarla en el contrato o en este renglón)');
                } else {
                    $aviso.addClass('text-success fa fa-check').attr('title', 'Cuenta DEBE del contrato / renglón');
                }
                return;
            }
            if (esNeto) {
                var cuentaNeto = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
                if (cuentaNeto > 0) {
                    $aviso.addClass('text-success fa fa-check')
                        .attr('title', 'Cuenta del neto cargada (se puede cambiar en Asiento contable)');
                } else {
                    $aviso.addClass('text-muted').text('Asiento')
                        .attr('title', 'Neto sin OC/COM: indique la cuenta en la solapa Asiento contable');
                }
                return;
            }
            if (!conceptoRequiereCuentaDebe(meta.tipoconcepto)) {
                $aviso.addClass('text-muted').text('—').attr('title', 'Neto: revierte provisión COM');
                return;
            }

            var cuentaDebe = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
            if (cuentaDebe <= 0) {
                cuentaDebe = cuentaDebeDesdeMeta(conceptoId).id;
            }

            if (!cuentaDebe || cuentaDebe <= 0) {
                $row.addClass('table-warning');
                if (puedeEditarConceptoIva && urlEditarConceptoIvaTpl && conceptoId > 0) {
                    var urlEditar = urlEditarConceptoIvaTpl.replace('__ID__', String(conceptoId));
                    $aviso.html(
                        '<a href="' + urlEditar + '" class="text-danger" target="_blank" rel="noopener noreferrer" ' +
                        'title="Falta cuenta DEBE en el maestro del concepto «' + (meta.nombre || '') +
                        '». Configúrela allí' + (filaMuestraEditorCuentaDebe($row) ? ' o en este renglón' : '') + '.">' +
                        '<i class="fa fa-exclamation-triangle"></i></a>'
                    );
                } else {
                    $aviso.addClass('text-danger fa fa-exclamation-triangle')
                        .attr(
                            'title',
                            'Falta cuenta DEBE en el maestro del concepto «' + (meta.nombre || '') + '»'
                            + (filaMuestraEditorCuentaDebe($row) ? ' (o indíquela en este renglón)' : '')
                        );
                }
            } else {
                $aviso.addClass('text-success fa fa-check').attr('title', 'Cuenta DEBE asignada');
            }
        });
        actualizarColumnaCuentaDebe();
    }

    function renderBannerAvisos(avisos, error) {
        var $banner = $('#cp-asiento-avisos-banner');
        if (!$banner.length) {
            return;
        }

        var mensajes = [];
        var vistos = {};
        function pushUnico(msg) {
            var texto = String(msg || '').trim();
            var clave = texto.toLowerCase();
            if (!texto || vistos[clave]) {
                return;
            }
            vistos[clave] = true;
            mensajes.push(texto);
        }
        if (error) {
            pushUnico(error);
        }
        (avisos || []).forEach(function (aviso) {
            if (aviso && aviso.mensaje) {
                pushUnico(aviso.mensaje);
            }
        });

        if (mensajes.length === 0) {
            $banner.addClass('d-none').empty();
            return;
        }

        var html = '<strong><i class="fa fa-exclamation-triangle"></i> Asiento contable:</strong><ul class="mb-0 mt-1 pl-3">';
        mensajes.forEach(function (msg) {
            html += '<li>' + $('<div>').text(msg).html() + '</li>';
        });
        html += '</ul>';
        $banner.removeClass('d-none').html(html);
    }

    function aplicarAvisosProveedor(avisos) {
        var $aviso = $('#cp-aviso-proveedor-cuenta');
        if (!$aviso.length) {
            return;
        }

        var problema = (avisos || []).some(function (a) {
            return a.tipo === 'proveedor_sin_cuenta'
                || a.tipo === 'proveedor_sin_cuenta_me'
                || a.tipo === 'proveedor_sin_seleccionar';
        });

        if (problema) {
            $aviso.removeClass('d-none').text('Sin cuenta contable de proveedores');
        } else {
            $aviso.addClass('d-none').text('');
        }
    }

    function targetsPreviewAsiento() {
        return $('.cp-asiento-preview-target');
    }

    function exentoIntegraTotal(totalDoc, sumaSinExento, sumaExento) {
        if (Math.abs(sumaExento) <= 0.005) {
            return false;
        }
        if (!(totalDoc > 0)) {
            return true;
        }
        var tol = 1;
        // Solo EXENTO: no descartar (tol=1 + totalDoc=1 daba falso positivo y dejaba total=1).
        if (Math.abs(sumaSinExento) <= tol) {
            return true;
        }
        var cierraSin = Math.abs(sumaSinExento - totalDoc) <= tol;
        var cierraCon = Math.abs(sumaSinExento + sumaExento - totalDoc) <= tol;
        if (cierraSin && !cierraCon) {
            return false;
        }
        return true;
    }

    function sincronizarTotalesDesdeConceptos() {
        var total = 0;
        var subtotal = 0;
        var sumaSinExento = 0;
        var exento = 0;
        var netoSinExento = 0;
        var hayLineas = false;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            if (conceptoId <= 0 || Math.abs(monto) < 0.0001) {
                return;
            }
            hayLineas = true;
            total += monto;
            var tip = String((conceptosMeta[conceptoId] || {}).tipoconcepto || '').toUpperCase();
            var codigo = String((conceptosMeta[conceptoId] || {}).codigo || '');
            if (esExento(tip, codigo)) {
                exento += monto;
            } else {
                sumaSinExento += monto;
            }
            if (TIPOS_NETO.indexOf(tip) >= 0) {
                subtotal += monto;
                if (!esExento(tip, codigo)) {
                    netoSinExento += monto;
                }
            }
        });
        if (!hayLineas) {
            var fmtVacio = function (n) {
                if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
                    return window.AsientoMontosFormato.fmt(n);
                }
                return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };
            if ($('#total').length) {
                $('#total').val(fmtVacio(0));
            }
            if ($('#subtotal').length) {
                $('#subtotal').val(fmtVacio(0));
            }
            sincronizarCuotasDesdeTotal(0);
            return;
        }
        var fmt = function (n) {
            if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
                return window.AsientoMontosFormato.fmt(n);
            }
            return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        total = Math.round(total * 100) / 100;
        subtotal = Math.round(subtotal * 100) / 100;
        sumaSinExento = Math.round(sumaSinExento * 100) / 100;
        exento = Math.round(exento * 100) / 100;
        netoSinExento = Math.round(netoSinExento * 100) / 100;
        var totalDoc = parseMonto($('#total').val() || '0');
        if (Math.abs(exento) > 0.005 && !exentoIntegraTotal(totalDoc, sumaSinExento, exento)) {
            total = (totalDoc > 0 && Math.abs(totalDoc - sumaSinExento) <= 1) ? totalDoc : sumaSinExento;
            if (Math.abs(netoSinExento) > 0.0001) {
                subtotal = netoSinExento;
            }
        }
        if ($('#total').length) {
            $('#total').val(fmt(total));
        }
        if ($('#subtotal').length && Math.abs(subtotal) > 0.0001) {
            $('#subtotal').val(fmt(subtotal));
        }
        sincronizarCuotasDesdeTotal(total);
        avisarDesvioVsPrecarga(total);
    }

    /**
     * Si cambian conceptos/total, las cuotas tienen que ir de la mano (misma proporción).
     */
    function sincronizarCuotasDesdeTotal(total) {
        var $rows = $('#tbody-cuotas-table tr.item-cuota');
        if (!$rows.length) {
            return;
        }
        total = Math.round((parseFloat(total) || 0) * 100) / 100;
        var fmt = function (n) {
            if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
                return window.AsientoMontosFormato.fmt(n);
            }
            return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        if (!(total > 0)) {
            $rows.each(function () {
                var $input = $(this).find('[name="cuota_monto[]"]');
                if ($input.length && !$input.prop('readonly')) {
                    $input.val(fmt(0));
                }
            });
            return;
        }

        var montos = [];
        var suma = 0;
        $rows.each(function () {
            var m = Math.abs(parseMonto($(this).find('[name="cuota_monto[]"]').val() || '0'));
            montos.push(m);
            suma += m;
        });
        suma = Math.round(suma * 100) / 100;
        if (Math.abs(suma - total) < 0.005) {
            return;
        }

        var n = $rows.length;
        if (n === 1) {
            var $unico = $rows.first().find('[name="cuota_monto[]"]');
            if ($unico.length && !$unico.prop('readonly')) {
                $unico.val(fmt(total));
            }
            return;
        }

        // Residual de centavos (p. ej. 0,02 USD): última cuota. No reescalar el plan.
        if (Math.abs(suma - total) <= 0.05) {
            var resto = 0;
            $rows.each(function (i) {
                if (i === n - 1) {
                    return;
                }
                resto += Math.abs(parseMonto($(this).find('[name="cuota_monto[]"]').val() || '0'));
            });
            var $last = $rows.last().find('[name="cuota_monto[]"]');
            if ($last.length && !$last.prop('readonly')) {
                $last.val(fmt(Math.round((total - resto) * 100) / 100));
            }
            return;
        }

        var asignado = 0;
        $rows.each(function (i) {
            var $input = $(this).find('[name="cuota_monto[]"]');
            if (!$input.length || $input.prop('readonly')) {
                return;
            }
            var nuevo;
            if (suma < 0.0001) {
                nuevo = i === 0 ? total : 0;
            } else if (i === n - 1) {
                nuevo = Math.round((total - asignado) * 100) / 100;
            } else {
                nuevo = Math.round(total * (montos[i] / suma) * 100) / 100;
                asignado += nuevo;
            }
            $input.val(fmt(nuevo));
        });
    }

    function avisarDesvioVsPrecarga(total) {
        var $form = $('#form-comprobante-proveedor');
        if (!$form.length) {
            $form = $('form[data-precarga-id]').first();
        }
        var precargaId = parseInt($form.attr('data-precarga-id') || '0', 10) || 0;
        var precargaTotal = parseFloat($form.attr('data-precarga-total') || '0') || 0;
        var $banner = $('#cp-banner-desvio-precarga');
        if (!precargaId || !(precargaTotal > 0)) {
            if ($banner.length) {
                $banner.hide().empty();
            }
            return;
        }
        total = Math.round((parseFloat(total) || 0) * 100) / 100;
        var diff = Math.round(Math.abs(total - precargaTotal) * 100) / 100;
        if (diff <= 0.05) {
            if ($banner.length) {
                $banner.hide().empty();
            }
            return;
        }
        if (!$banner.length) {
            $banner = $('<div id="cp-banner-desvio-precarga" class="alert alert-danger mx-3 mt-2"></div>');
            $form.prepend($banner);
        }
        $banner.html(
            '<strong>Total distinto a la precarga #' + precargaId + '.</strong> '
            + 'Factura: ' + formatearMonto(total) + ' · Precarga: ' + formatearMonto(precargaTotal)
            + ' · Diferencia: ' + formatearMonto(diff)
            + '. Si el PDF está mal, corregí la precarga; el grabado será rechazado.'
        ).show();
    }

    /**
     * Hay dos previews del asiento (solapa Conceptos + solapa Asiento) con el mismo HTML.
     * Si se itera en orden DOM, el editor vacío del otro panel pisa la cuenta recién elegida.
     * Agregar por concepto y preferir la que tenga cuenta cargada.
     * Además se conserva un mapa local: el AJAX del preview reemplaza el HTML y, si un
     * request viejo llega sin la cuenta, la selección no debe perderse.
     */
    function recordarCuentaAsientoManual(conceptoId, datos) {
        conceptoId = parseInt(conceptoId, 10) || 0;
        if (conceptoId <= 0) {
            return;
        }
        var id = parseInt((datos && datos.id) || '0', 10) || 0;
        if (id > 0) {
            cuentasAsientoManualPorConcepto[String(conceptoId)] = {
                id: id,
                codigo: String((datos && datos.codigo) || ''),
                nombre: String((datos && datos.nombre) || '')
            };
            return;
        }
        delete cuentasAsientoManualPorConcepto[String(conceptoId)];
    }

    function aplicarCuentasAsientoManualesEnEditores() {
        $.each(cuentasAsientoManualPorConcepto, function (conceptoIdStr, datos) {
            var conceptoId = parseInt(conceptoIdStr, 10) || 0;
            var id = parseInt((datos && datos.id) || '0', 10) || 0;
            if (conceptoId <= 0 || id <= 0) {
                return;
            }
            $('.cp-asiento-cuenta-editable[data-concepto-ivacompra-id="' + conceptoId + '"]').each(function () {
                var $campo = $(this);
                $campo.find('.cuentacontable_id').val(String(id));
                $campo.find('.codigocuentacontable').val(String((datos && datos.codigo) || ''));
                $campo.find('.nombrecuentacontable').val(String((datos && datos.nombre) || ''));
                if (typeof actualizarLinkEditarCuentaContable === 'function') {
                    actualizarLinkEditarCuentaContable($campo, id);
                }
            });
            $('#tbody-concepto-table tr.item-concepto').each(function () {
                var $row = $(this);
                if (parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) !== conceptoId) {
                    return;
                }
                setCuentaDebeEnFila($row, datos);
            });
        });
    }

    function datosCuentaDesdeEditor($campo) {
        return {
            id: parseInt($campo.find('.cuentacontable_id').val() || '0', 10) || 0,
            codigo: String($campo.find('.codigocuentacontable').val() || ''),
            nombre: String($campo.find('.nombrecuentacontable').val() || '')
        };
    }

    function conceptoNetoSinCuentaMaestro(conceptoId) {
        conceptoId = parseInt(conceptoId, 10) || 0;
        if (conceptoId <= 0) {
            return false;
        }
        var meta = conceptosMeta[conceptoId] || {};
        var tipo = String(meta.tipoconcepto || '');
        var codigo = String(meta.codigo || '');
        if (TIPOS_NETO.indexOf(tipo) < 0 || esImpuestoInterno(tipo, codigo)) {
            return false;
        }
        return cuentaDebeDesdeMeta(conceptoId).id <= 0;
    }

    function escribirCuentaEnEditorNeto($campo, datos) {
        var id = parseInt((datos && datos.id) || '0', 10) || 0;
        if (id <= 0) {
            return;
        }
        $campo.find('.cuentacontable_id').val(String(id));
        $campo.find('.codigocuentacontable').val(String((datos && datos.codigo) || ''));
        $campo.find('.nombrecuentacontable').val(String((datos && datos.nombre) || ''));
        if (typeof actualizarLinkEditarCuentaContable === 'function') {
            actualizarLinkEditarCuentaContable($campo, id);
        }
    }

    /**
     * Un neto con cuenta (ej. exento) precarga los otros netos vacíos.
     * No pisa un renglón que ya tiene otra cuenta, ni copia la cuenta de maestro del IVA.
     */
    function precargarCuentaEnNetosVacios() {
        var fuente = null;

        $('.cp-asiento-cuenta-editable').each(function () {
            if (fuente) {
                return;
            }
            var $campo = $(this);
            var conceptoId = parseInt($campo.attr('data-concepto-ivacompra-id') || '0', 10) || 0;
            if (!conceptoNetoSinCuentaMaestro(conceptoId)) {
                return;
            }
            var datos = datosCuentaDesdeEditor($campo);
            if (datos.id > 0) {
                fuente = datos;
            }
        });

        if (!fuente) {
            $('#tbody-concepto-table tr.item-concepto').each(function () {
                if (fuente) {
                    return;
                }
                var $row = $(this);
                var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
                if (!conceptoNetoSinCuentaMaestro(conceptoId)) {
                    return;
                }
                var id = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
                if (id <= 0) {
                    return;
                }
                fuente = {
                    id: id,
                    codigo: String($row.find('.cp-celda-cuenta-debe .codigocuentacontable').val() || ''),
                    nombre: String($row.find('.cp-celda-cuenta-debe .nombrecuentacontable').val() || '')
                };
            });
        }

        if (!fuente || fuente.id <= 0) {
            return;
        }

        $('.cp-asiento-cuenta-editable').each(function () {
            var $campo = $(this);
            var conceptoId = parseInt($campo.attr('data-concepto-ivacompra-id') || '0', 10) || 0;
            if (!conceptoNetoSinCuentaMaestro(conceptoId)) {
                return;
            }
            if ((parseInt($campo.find('.cuentacontable_id').val() || '0', 10) || 0) > 0) {
                return;
            }
            escribirCuentaEnEditorNeto($campo, fuente);
            recordarCuentaAsientoManual(conceptoId, fuente);
        });

        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
            if (!conceptoNetoSinCuentaMaestro(conceptoId)) {
                return;
            }
            if ((parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0) > 0) {
                return;
            }
            setCuentaDebeEnFila($row, fuente);
            recordarCuentaAsientoManual(conceptoId, fuente);
        });
        marcarAvisosConceptosLocales();
    }

    function sincronizarCuentasAsientoAConceptos() {
        precargarCuentaEnNetosVacios();
        var porConcepto = {};
        $('.cp-asiento-cuenta-editable').each(function () {
            var $campo = $(this);
            var conceptoId = parseInt($campo.attr('data-concepto-ivacompra-id') || '0', 10) || 0;
            if (conceptoId <= 0) {
                return;
            }
            var cuentaId = parseInt($campo.find('.cuentacontable_id').val() || '0', 10) || 0;
            var datos = {
                id: cuentaId,
                codigo: String($campo.find('.codigocuentacontable').val() || ''),
                nombre: String($campo.find('.nombrecuentacontable').val() || '')
            };
            var prev = porConcepto[conceptoId];
            if (!prev || (cuentaId > 0 && prev.id <= 0)) {
                porConcepto[conceptoId] = datos;
            }
        });
        $.each(cuentasAsientoManualPorConcepto, function (conceptoIdStr, datos) {
            var conceptoId = parseInt(conceptoIdStr, 10) || 0;
            var id = parseInt((datos && datos.id) || '0', 10) || 0;
            if (conceptoId <= 0 || id <= 0) {
                return;
            }
            var prev = porConcepto[conceptoId];
            if (!prev || prev.id <= 0) {
                porConcepto[conceptoId] = {
                    id: id,
                    codigo: String((datos && datos.codigo) || ''),
                    nombre: String((datos && datos.nombre) || '')
                };
            }
        });
        $.each(porConcepto, function (conceptoIdStr, datos) {
            var conceptoId = parseInt(conceptoIdStr, 10) || 0;
            if (conceptoId <= 0) {
                return;
            }
            // Solo volcar si hay cuenta: un panel vacío no debe borrar la del renglón.
            if (datos.id <= 0) {
                return;
            }
            recordarCuentaAsientoManual(conceptoId, datos);
            $('#tbody-concepto-table tr.item-concepto').each(function () {
                var $row = $(this);
                if (parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) !== conceptoId) {
                    return;
                }
                setCuentaDebeEnFila($row, datos);
            });
        });
    }

    function serializarFormularioPreview() {
        sincronizarCuentasAsientoAConceptos();
        sincronizarTotalesDesdeConceptos();
        sincronizarDebeGastoHiddenDesdeTabla();

        // Armar payload explícito: montos en decimal (evita es-AR y desfase de #total).
        // `_method` (PUT del form de edición) haría que Laravel enrute el POST como PUT → 405.
        var params = $form.serializeArray().filter(function (p) {
            return p.name !== '_method'
                && p.name !== 'montos[]'
                && p.name !== 'concepto_ivacompra_ids[]'
                && p.name !== 'cuentacontabledebe_ids[]'
                && p.name !== 'debe_gasto_cuenta_ids[]'
                && p.name !== 'debe_gasto_importes[]'
                && p.name !== 'debe_gasto_centrocosto_ids[]'
                && p.name !== 'total'
                && p.name !== 'subtotal';
        });

        var total = 0;
        var subtotal = 0;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            params.push({ name: 'concepto_ivacompra_ids[]', value: String(conceptoId > 0 ? conceptoId : '') });
            params.push({ name: 'montos[]', value: conceptoId > 0 ? String(monto) : '' });
            var cuentaDebeId = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
            params.push({ name: 'cuentacontabledebe_ids[]', value: cuentaDebeId > 0 ? String(cuentaDebeId) : '' });
            if (conceptoId <= 0 || Math.abs(monto) < 0.0001) {
                return;
            }
            total += monto;
            var tip = String((conceptosMeta[conceptoId] || {}).tipoconcepto || '');
            if (TIPOS_NETO.indexOf(tip) >= 0) {
                subtotal += monto;
            }
        });

        total = Math.round(total * 100) / 100;
        subtotal = Math.round(subtotal * 100) / 100;
        if (total > 0) {
            params.push({ name: 'total', value: String(total) });
        } else {
            params.push({ name: 'total', value: String(parseMonto($('#total').val() || '0')) });
        }
        if (subtotal > 0) {
            params.push({ name: 'subtotal', value: String(subtotal) });
        } else {
            params.push({ name: 'subtotal', value: String(parseMonto($('#subtotal').val() || '0')) });
        }

        appendDebeGastoParams(params);

        return $.param(params);
    }

    function leerDebeGastoDesdeTabla() {
        var lineas = [];
        $('#tabla-asiento-comprobante-proveedor tr.cp-debe-gasto-row').each(function () {
            var $row = $(this);
            var $campo = $row.find('.cp-asiento-cuenta-editable').first();
            var cuentaId = parseInt($campo.find('.cuentacontable_id').val() || '0', 10) || 0;
            var $imp = $row.find('.cp-debe-gasto-importe');
            var importe = $imp.length
                ? parseMonto($imp.val() || '0')
                : parseMonto($row.find('td').eq(2).text() || '0');
            lineas.push({
                cuenta_id: cuentaId,
                codigo: String($campo.find('.codigocuentacontable').val() || ''),
                nombre: String($campo.find('.nombrecuentacontable').val() || ''),
                importe: Math.round(Math.abs(importe) * 100) / 100,
                centrocosto_id: 0
            });
        });
        return lineas;
    }

    function appendDebeGastoParams(params) {
        if (!debeGastoActivo && !debeGastoPendiente) {
            return;
        }
        var lineas = debeGastoPendiente || leerDebeGastoDesdeTabla();
        if (!lineas.length) {
            // Marca presencia vacía para que el backend limpie el reparto.
            params.push({ name: 'debe_gasto_cuenta_ids[]', value: '' });
            params.push({ name: 'debe_gasto_importes[]', value: '' });
            return;
        }
        lineas.forEach(function (l) {
            params.push({ name: 'debe_gasto_cuenta_ids[]', value: l.cuenta_id > 0 ? String(l.cuenta_id) : '' });
            params.push({ name: 'debe_gasto_importes[]', value: String(l.importe) });
            params.push({ name: 'debe_gasto_centrocosto_ids[]', value: '' });
        });
    }

    function escribirDebeGastoHidden(lineas) {
        var $box = $('#cp-debe-gasto-hidden');
        if (!$box.length) {
            return;
        }
        $box.empty();
        if (!debeGastoActivo) {
            return;
        }
        if (!lineas || !lineas.length) {
            $box.append($('<input type="hidden" name="debe_gasto_cuenta_ids[]">').val(''));
            $box.append($('<input type="hidden" name="debe_gasto_importes[]">').val(''));
            return;
        }
        lineas.forEach(function (l) {
            $box.append($('<input type="hidden" name="debe_gasto_cuenta_ids[]">').val(
                l.cuenta_id > 0 ? String(l.cuenta_id) : ''
            ));
            $box.append($('<input type="hidden" name="debe_gasto_importes[]">').val(String(l.importe || 0)));
            $box.append($('<input type="hidden" name="debe_gasto_centrocosto_ids[]">').val(
                l.centrocosto_id > 0 ? String(l.centrocosto_id) : ''
            ));
        });
    }

    function sincronizarDebeGastoHiddenDesdeTabla() {
        var $wrap = $('#cp-asiento-tabla-wrap');
        if ($wrap.length && String($wrap.data('permite-reparto-gasto')) === '1') {
            debeGastoActivo = true;
            if (!debeGastoPendiente) {
                escribirDebeGastoHidden(leerDebeGastoDesdeTabla());
            }
            actualizarAvisoSumaDebeGasto();
        }
    }

    function actualizarAvisoSumaDebeGasto() {
        var $aviso = $('#cp-debe-gasto-aviso-suma');
        var $wrap = $('#cp-asiento-tabla-wrap');
        if (!$aviso.length || !$wrap.length) {
            return;
        }
        var neto = parseFloat($wrap.attr('data-neto-imputable-gasto') || $wrap.data('neto-imputable-gasto') || '0') || 0;
        var lineas = debeGastoPendiente || leerDebeGastoDesdeTabla();
        if (!lineas.length) {
            $aviso.text('');
            return;
        }
        var suma = 0;
        lineas.forEach(function (l) { suma += l.importe; });
        suma = Math.round(suma * 100) / 100;
        neto = Math.round(neto * 100) / 100;
        var diff = Math.round((suma - neto) * 100) / 100;
        if (Math.abs(diff) <= 0.05) {
            $aviso.removeClass('text-danger').addClass('text-muted').text(
                'Suma gasto: ' + fmtMontoLocal(suma) + ' (ok)'
            );
        } else {
            $aviso.removeClass('text-muted').addClass('text-danger').text(
                'Suma gasto: ' + fmtMontoLocal(suma) + ' ≠ neto ' + fmtMontoLocal(neto)
                + ' (dif. ' + fmtMontoLocal(diff) + ')'
            );
        }
    }

    function fmtMontoLocal(n) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
            return window.AsientoMontosFormato.fmt(n);
        }
        return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function redistribuirImportesDebeGasto(lineas, neto) {
        neto = Math.round(Math.abs(neto) * 100) / 100;
        var n = lineas.length;
        if (n <= 0) {
            return lineas;
        }
        if (n === 1) {
            lineas[0].importe = neto;
            return lineas;
        }
        var base = Math.floor((neto / n) * 100) / 100;
        var asignado = 0;
        for (var i = 0; i < n; i++) {
            if (i === n - 1) {
                lineas[i].importe = Math.round((neto - asignado) * 100) / 100;
            } else {
                lineas[i].importe = base;
                asignado = Math.round((asignado + base) * 100) / 100;
            }
        }
        return lineas;
    }

    function agregarCuentaDebeGasto() {
        var $wrap = $('#cp-asiento-tabla-wrap');
        if (!$wrap.length || String($wrap.data('permite-reparto-gasto')) !== '1') {
            return;
        }
        var neto = parseFloat($wrap.attr('data-neto-imputable-gasto') || $wrap.data('neto-imputable-gasto') || '0') || 0;
        var lineas = leerDebeGastoDesdeTabla();
        if (!lineas.length) {
            alert('No hay líneas de gasto (neto) para repartir. Cargue conceptos IVA primero.');
            return;
        }
        var plantilla = lineas[0];
        lineas.push({
            cuenta_id: plantilla.cuenta_id || 0,
            codigo: plantilla.codigo || '',
            nombre: plantilla.nombre || '',
            importe: 0,
            centrocosto_id: 0
        });
        lineas = redistribuirImportesDebeGasto(lineas, neto);
        debeGastoActivo = true;
        debeGastoPendiente = lineas;
        escribirDebeGastoHidden(lineas);
        recargarPreviewAsiento();
    }

    function quitarCuentaDebeGasto($row) {
        var $wrap = $('#cp-asiento-tabla-wrap');
        var neto = parseFloat($wrap.attr('data-neto-imputable-gasto') || $wrap.data('neto-imputable-gasto') || '0') || 0;
        var lineas = [];
        $('#tabla-asiento-comprobante-proveedor tr.cp-debe-gasto-row').each(function () {
            if ($row.length && this === $row[0]) {
                return;
            }
            var $r = $(this);
            var $campo = $r.find('.cp-asiento-cuenta-editable').first();
            var $imp = $r.find('.cp-debe-gasto-importe');
            lineas.push({
                cuenta_id: parseInt($campo.find('.cuentacontable_id').val() || '0', 10) || 0,
                codigo: String($campo.find('.codigocuentacontable').val() || ''),
                nombre: String($campo.find('.nombrecuentacontable').val() || ''),
                importe: $imp.length ? parseMonto($imp.val() || '0') : 0,
                centrocosto_id: 0
            });
        });
        if (!lineas.length) {
            return;
        }
        lineas = redistribuirImportesDebeGasto(lineas, neto);
        debeGastoActivo = true;
        debeGastoPendiente = lineas;
        escribirDebeGastoHidden(lineas);
        recargarPreviewAsiento();
    }

    function trasRenderPreviewAsiento() {
        debeGastoPendiente = null;
        sincronizarDebeGastoHiddenDesdeTabla();
        formatearInputMontoEn($('#cp-asiento-preview-body'));
        if (typeof window.activa_eventos_consultacuentacontable === 'function') {
            window.activa_eventos_consultacuentacontable();
        }
    }

    function recargarPreviewAsiento() {
        if (contabilizado || !previewUrl) {
            if (!previewUrl && window.console && console.warn) {
                console.warn('CP preview: falta data-preview-url en #form-comprobante-proveedor');
            }
            return;
        }

        // Consulta de proveedor en curso (código escrito, id todavía vacío): no reemplazar el asiento
        // con un error de cuenta que desaparece cuando vuelve el GET.
        var proveedorIdActual = parseInt($('#proveedor_id').val() || '0', 10) || 0;
        var codigoProveedorActual = String($('#codigoproveedor').val() || '').trim();
        if (proveedorIdActual <= 0 && codigoProveedorActual !== '') {
            return;
        }

        var seq = ++previewSeq;
        var $targets = targetsPreviewAsiento();
        if ($targets.length) {
            $targets.data('loading', 1);
            $targets.css('opacity', 0.55);
        }

        if (previewXhr && previewXhr.readyState !== 4) {
            previewXhr.abort();
        }

        previewXhr = $.ajax({
            url: previewUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            data: serializarFormularioPreview()
        })
            .done(function (res) {
                if (seq !== previewSeq) {
                    return;
                }
                if ($targets.length && res && typeof res.html === 'string') {
                    $targets.html(res.html);
                    // El HTML nuevo puede venir sin la cuenta si un request viejo ganó la carrera;
                    // reaplicar la elección manual del operador.
                    aplicarCuentasAsientoManualesEnEditores();
                    trasRenderPreviewAsiento();
                }
                $targets.css('opacity', 1).removeData('loading');
                var ahora = new Date();
                var hh = String(ahora.getHours()).padStart(2, '0');
                var mm = String(ahora.getMinutes()).padStart(2, '0');
                var ss = String(ahora.getSeconds()).padStart(2, '0');
                $('#cp-preview-asiento-status').text('Actualizado ' + hh + ':' + mm + ':' + ss);
                renderBannerAvisos(res.avisos || [], res.error || null);
                aplicarAvisosProveedor(res.avisos || []);
                var coherencia = validarCoherenciaConceptosPantalla();
                actualizarBadgeAsiento(!!(res.error || (res.avisos && res.avisos.length) || !coherencia.valido));
            })
            .fail(function (xhr, status) {
                if (seq !== previewSeq) {
                    return;
                }
                $targets.css('opacity', 1).removeData('loading');
                if (status === 'abort') {
                    return;
                }
                var msg = 'No se pudo actualizar el preview del asiento';
                if (xhr && xhr.status) {
                    msg += ' (HTTP ' + xhr.status + ')';
                }
                $('#cp-preview-asiento-status').text('Error al actualizar');
                renderBannerAvisos([], msg);
            });
    }

    function programarPreviewAsiento() {
        if (contabilizado) {
            return;
        }
        sincronizarTotalesDesdeConceptos();
        marcarAvisosConceptosLocales();
        renderBannerCoherenciaConceptos(validarCoherenciaConceptosPantalla());
        clearTimeout(previewTimer);
        previewTimer = setTimeout(recargarPreviewAsiento, 280);
    }

    window.refrescarPreviewAsiento = function (inmediato) {
        if (inmediato) {
            clearTimeout(previewTimer);
            sincronizarTotalesDesdeConceptos();
            recargarPreviewAsiento();
            return;
        }
        programarPreviewAsiento();
    };

    $('#cp-tabs-comprobante a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var href = $(e.target).attr('href') || '';
        if (href === '#cp-solapa-recepciones-com') {
            actualizarUiRecepcionesCom();
        }
        if (href === '#cp-solapa-asiento-contable' || href === '#cp-solapa-conceptos') {
            window.refrescarPreviewAsiento(true);
        }
    });

    $('#agrega_renglon_concepto').on('click', function (e) {
        e.preventDefault();
        var renglon = $('#template-renglon-concepto').html();
        var $nuevo = $(renglon);
        $('#tbody-concepto-table').append($nuevo);
        if (contratoImputacionManual()) {
            aplicarCuentaContratoEnFila($nuevo, true);
        } else {
            $nuevo.find('.cp-celda-cuenta-debe').addClass('d-none');
            actualizarColumnaCuentaDebe();
        }
        formatearInputMontoEn($nuevo);
        setTimeout(function () {
            $nuevo.find('.codigo_concepto_ivacompra').trigger('focus').select();
        }, 0);
        // Renglón vacío: no refrescar asiento (evita abortar un preview en curso).
    });

    $('#agrega_renglon_cp_articulo').on('click', function (e) {
        e.preventDefault();
        var tpl = document.getElementById('template-renglon-cp-articulo');
        if (!tpl || !tpl.content) {
            return;
        }
        var $nuevo = $(tpl.content.cloneNode(true));
        $('#tbody-cp-articulo-table').append($nuevo);
        formatearInputMontoEn($('#tbody-cp-articulo-table tr.item-cp-articulo').last());
        setTimeout(function () {
            $('#tbody-cp-articulo-table tr.item-cp-articulo').last().find('.codigoarticulo').trigger('focus').select();
        }, 0);
    });

    $(document).on('click', '.eliminar_cp_articulo', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    if (typeof activa_eventos_consultaarticulo === 'function') {
        activa_eventos_consultaarticulo();
    }

    // F1 / Enter en SKU de la solapa Artículos (mismo patrón que conceptos IVA).
    function esTeclaF1ArticuloCp(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112 || e.which === 112);
    }

    function abrirConsultaArticuloDesdeCodigoCp($input) {
        var $btn = $input.closest('tr.item-cp-articulo').find('.consultaarticulo').first();
        if ($btn.length) {
            $btn.trigger('click');
            return;
        }
        $input.closest('td').find('.consultaarticulo').first().trigger('click');
    }

    $(document).on('keydown', '#cp-articulo-table .codigoarticulo', function (e) {
        if (esTeclaF1ArticuloCp(e)) {
            e.preventDefault();
            e.stopPropagation();
            if ($('#consultaarticuloModal').hasClass('show') || $('#consultaarticuloModal').is(':visible')) {
                return;
            }
            abrirConsultaArticuloDesdeCodigoCp($(this));
            return;
        }
        if (e.key !== 'Enter' && e.which !== 13) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var $input = $(this);
        var sku = String($input.val() || '').trim();
        if (sku === '') {
            abrirConsultaArticuloDesdeCodigoCp($input);
            return;
        }
        // Dispara el resolver por SKU de stock/articulo/consulta.js
        $input.trigger('change');
        var $next = $input.closest('tr.item-cp-articulo').find('input[name="articulo_codigos_proveedor[]"]');
        if ($next.length) {
            setTimeout(function () {
                $next.trigger('focus').select();
            }, 50);
        }
    });

    if (!window.__cpArticuloF1CaptureActivo) {
        document.addEventListener('keydown', function (e) {
            if (!esTeclaF1ArticuloCp(e)) {
                return;
            }
            if (!$('#form-comprobante-proveedor').length && !$('#cp-articulo-table').length) {
                return;
            }
            var t = e.target;
            if (!t || !t.classList || !t.classList.contains('codigoarticulo')) {
                return;
            }
            if (!t.closest || !t.closest('#cp-articulo-table')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaArticuloDesdeCodigoCp($(t));
        }, true);
        window.__cpArticuloF1CaptureActivo = true;
    }

    $(document).on('click', '.eliminar_concepto', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        programarPreviewAsiento();
    });

    $(document).on('keydown', '#tbody-concepto-table .monto', function (e) {
        if (e.key !== 'Enter' && e.which !== 13) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var $row = $(this).closest('tr');
        aplicarAlicuotasDesdeGravado($row);
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.formatearInput === 'function') {
            window.AsientoMontosFormato.formatearInput(this);
        }
        var coherencia = validarCoherenciaConceptosPantalla();
        renderBannerCoherenciaConceptos(coherencia);
        marcarAvisosConceptosLocales();
        // No agregar renglón vacío al Enter: abortaba el preview y podía confundir al grabar.
        sincronizarTotalesDesdeConceptos();
        window.refrescarPreviewAsiento(true);
        if (!coherencia.valido) {
            return;
        }
        var $nextCodigo = $row.nextAll('tr.item-concepto').first().find('.codigo_concepto_ivacompra');
        if ($nextCodigo.length) {
            $nextCodigo.trigger('focus').select();
            return;
        }
        $(this).trigger('blur');
    });

    $(document).on('change', '#tbody-concepto-table .monto', function () {
        aplicarAlicuotasDesdeGravado($(this).closest('tr.item-concepto'));
    });

    $(document).on('click', '#cp-refrescar-preview-conceptos', function (e) {
        e.preventDefault();
        window.refrescarPreviewAsiento(true);
    });

    $(document).on('click', '#cp-ir-solapa-asiento', function (e) {
        e.preventDefault();
        mostrarSolapa('#cp-solapa-asiento-contable');
    });

    function formatearMonto(n) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
            return window.AsientoMontosFormato.fmt(n);
        }
        return (Math.round(n * 100) / 100).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function importeComparableComDesdeConceptos() {
        var letra = String($('#letra').val() || '').toUpperCase().trim();
        var total = 0;
        var gravado = 0;
        var exento = 0;
        var impuestoInterno = 0;
        var sumaSinExento = 0;
        var hayLineas = false;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
            var monto = parseMonto($row.find('.monto').val() || '0');
            if (conceptoId <= 0 || Math.abs(monto) < 0.0001) {
                return;
            }
            hayLineas = true;
            total += monto;
            var meta = conceptosMeta[conceptoId] || {};
            var tip = String(meta.tipoconcepto || '').toUpperCase();
            var codigo = String(meta.codigo || '');
            if (esImpuestoInterno(tip, codigo)) {
                impuestoInterno += monto;
                sumaSinExento += monto;
            } else if (esExento(tip, codigo)) {
                exento += monto;
            } else if (TIPOS_NETO.indexOf(tip) >= 0) {
                gravado += monto;
                sumaSinExento += monto;
            } else {
                sumaSinExento += monto;
            }
        });
        if (!hayLineas) {
            return 0;
        }
        if (letra !== '' && letra !== 'A') {
            return Math.round(total * 100) / 100;
        }
        var totalDoc = parseMonto($('#total').val() || '0');
        if (exentoIntegraTotal(totalDoc, sumaSinExento, exento)) {
            gravado += exento;
        }
        if (gravado <= 0) {
            var subtotal = parseMonto($('#subtotal').val() || '0');
            if (subtotal > 0) {
                gravado = subtotal;
            }
        } else {
            var subtotalNeto = parseMonto($('#subtotal').val() || '0');
            if (subtotalNeto > 0 && Math.abs(subtotalNeto - gravado) <= 1) {
                gravado = subtotalNeto;
            }
        }
        if (gravado <= 0) {
            return Math.round(total * 100) / 100;
        }
        if (comSeleccionadasIncluyenIi()) {
            return Math.round((gravado + impuestoInterno) * 100) / 100;
        }
        return Math.round(gravado * 100) / 100;
    }

    function actualizarUiRecepcionesCom() {
        var $bloque = $('#cp-bloque-recepciones-com');
        if (!$bloque.length) {
            return;
        }

        var modo = $('#modo_carga').val();
        if (modo === 'ASIGNA_RECEPCION') {
            $bloque.show();
        } else {
            $bloque.hide();
        }

        var toleranciaPct = parseFloat($bloque.attr('data-tolerancia-pct')) || 0;
        var importeRef = importeComparableComDesdeConceptos();
        if (!(importeRef > 0)) {
            importeRef = parseFloat($bloque.attr('data-importe-ref')) || 0;
        } else {
            $bloque.attr('data-importe-ref', String(importeRef));
        }
        var yaFacturado = parseFloat($bloque.attr('data-ya-facturado')) || 0;
        var cupoNc = parseFloat($bloque.attr('data-cupo-nc')) || 0;
        var ncSinImporte = $bloque.attr('data-nc-sin-importe') === '1';

        var sumaCom = 0;
        var checks = 0;
        $('.cp-com-check:checked').each(function () {
            checks++;
            var $fila = $(this).closest('.cp-com-fila');
            sumaCom += parseFloat($fila.attr('data-importe-com')) || 0;
            var id = String($fila.attr('data-recepcion-id'));
            $('.cp-com-articulos-bloque[data-recepcion-id="' + id + '"]').show();
        });
        $('.cp-com-check:not(:checked)').each(function () {
            var id = String($(this).closest('.cp-com-fila').attr('data-recepcion-id'));
            $('.cp-com-articulos-bloque[data-recepcion-id="' + id + '"]').hide();
        });

        if (checks > 0) {
            $('#cp-com-articulos-vacio').hide();
        } else {
            $('#cp-com-articulos-vacio').show();
        }

        var $resumen = $('#cp-com-resumen-diferencia');
        if (checks === 0) {
            $resumen
                .removeClass('alert-success alert-warning alert-danger')
                .addClass('alert-secondary')
                .html('<i class="fa fa-info-circle"></i> Seleccione al menos una recepción COM.')
                .show();
            return;
        }

        var sumaComDisponible = Math.max(0, sumaCom - yaFacturado);
        var excesoBruto = Math.max(0, Math.round((importeRef - sumaComDisponible) * 100) / 100);
        var cupoEfectivo = cupoNc;
        if (ncSinImporte && excesoBruto > 0.005) {
            cupoEfectivo = Math.max(cupoEfectivo, excesoBruto);
        }
        var cupoAplicado = Math.min(excesoBruto, Math.max(0, cupoEfectivo));
        var importeEfectivo = Math.round((importeRef - cupoAplicado) * 100) / 100;
        var diff = Math.abs(importeEfectivo - sumaComDisponible);
        var pct = sumaComDisponible > 0.00001 ? (diff / Math.abs(sumaComDisponible)) * 100 : (diff > 0.05 ? 100 : 0);
        var okCentavos = diff <= 0.05;
        var okTol = okCentavos || pct <= toleranciaPct + 0.0001;
        var cls = okTol ? 'alert-success' : 'alert-danger';
        var msg = 'Provisión COM: <strong>' + formatearMonto(sumaCom) + '</strong>';
        if (yaFacturado > 0.005) {
            msg += ' − ya facturado legajo <strong>' + formatearMonto(yaFacturado) +
                '</strong> = disponible <strong>' + formatearMonto(sumaComDisponible) + '</strong>';
        }
        msg += ' · Ref. factura: <strong>' + formatearMonto(importeRef) + '</strong>';
        if (cupoAplicado > 0.005) {
            if (ncSinImporte && cupoNc < excesoBruto - 0.005) {
                msg += ' · NC del legajo pendiente de carga (sin importe) cubre el exceso';
            } else {
                msg += ' · Cupo NC legajo: <strong>' + formatearMonto(cupoAplicado) + '</strong>' +
                    ' (ref. efectiva <strong>' + formatearMonto(importeEfectivo) + '</strong>)';
            }
        }
        msg += ' · Diferencia: <strong>' + formatearMonto(diff) +
            '</strong> (' + formatearMonto(pct) + '%) · Tolerancia: ' + formatearMonto(toleranciaPct) + '%';
        if (!okTol) {
            msg += ' — <strong>fuera de tolerancia</strong> (al guardar se devolverá el legajo a Compras).';
        } else if (cupoAplicado > 0.005 && excesoBruto > 0.05) {
            msg += ' — cubierto por NC del legajo; el excedente neto se prorratea en el asiento sobre artículos OC.';
        } else if (!okCentavos && diff > 0) {
            msg += ' — dentro de tolerancia; el excedente neto se prorratea en el asiento sobre artículos COM.';
        } else {
            msg += ' — coincide con la provisión disponible.';
        }
        $resumen.removeClass('alert-secondary alert-success alert-warning alert-danger').addClass(cls).html(msg).show();
    }

    function toggleBloqueRecepcionesCom() {
        actualizarUiRecepcionesCom();
        programarPreviewAsiento();
    }

    $('#modo_carga').on('change', function () {
        toggleBloqueRecepcionesCom();
        actualizarColumnaCuentaDebe();
        marcarAvisosConceptosLocales();
    });
    toggleBloqueRecepcionesCom();

    $form.on('input change', 'input, select, textarea', function () {
        var $el = $(this);
        // La cuenta del asiento tiene handler propio; no disparar otro preview acá
        // (evita carrera que vuelve a pedir el HTML sin la cuenta recién elegida).
        if ($el.closest('.cp-asiento-cuenta-editable').length) {
            return;
        }
        programarPreviewAsiento();
    });

    // Tras formatear es-AR en blur (montos_formato.js dispara asiento:monto-actualizado).
    $(document).on('asiento:monto-actualizado', function () {
        window.refrescarPreviewAsiento(true);
    });

    $(document).on('change', '#tbody-concepto-table .concepto_ivacompra_id, #tbody-concepto-table .monto, #tbody-concepto-table .cp-celda-cuenta-debe .cuentacontable_id', function () {
        marcarAvisosConceptosLocales();
        actualizarUiRecepcionesCom();
        programarPreviewAsiento();
    });

    $(document).on('change', '.cp-asiento-cuenta-editable .cuentacontable_id', function () {
        var $campo = $(this).closest('.cp-asiento-cuenta-editable');
        if ($campo.data('debe-gasto') || $campo.closest('tr.cp-debe-gasto-row').length) {
            sincronizarDebeGastoHiddenDesdeTabla();
            window.refrescarPreviewAsiento(true);
            return;
        }
        var conceptoId = parseInt($campo.attr('data-concepto-ivacompra-id') || '0', 10) || 0;
        recordarCuentaAsientoManual(conceptoId, {
            id: parseInt($(this).val() || '0', 10) || 0,
            codigo: String($campo.find('.codigocuentacontable').val() || ''),
            nombre: String($campo.find('.nombrecuentacontable').val() || '')
        });
        sincronizarCuentasAsientoAConceptos();
        marcarAvisosConceptosLocales();
        window.refrescarPreviewAsiento(true);
    });

    $(document).on('click', '#cp-debe-gasto-agregar', function (e) {
        e.preventDefault();
        agregarCuentaDebeGasto();
    });

    $(document).on('click', '.cp-debe-gasto-quitar', function (e) {
        e.preventDefault();
        quitarCuentaDebeGasto($(this).closest('tr.cp-debe-gasto-row'));
    });

    $(document).on('change blur', '.cp-debe-gasto-importe', function () {
        sincronizarDebeGastoHiddenDesdeTabla();
        window.refrescarPreviewAsiento(true);
    });

    $form.on('submit', function () {
        sincronizarCuentasAsientoAConceptos();
        sincronizarDebeGastoHiddenDesdeTabla();
    });

    trasRenderPreviewAsiento();

    $(document).on('cp:concepto-ivacompra-elegido', function (e, data) {
        var $row = (typeof ptrConceptoIvacompraId !== 'undefined' && ptrConceptoIvacompraId && ptrConceptoIvacompraId.length)
            ? ptrConceptoIvacompraId.closest('tr.item-concepto')
            : $();
        if ($row.length && data && data.id) {
            var id = parseInt(data.id, 10) || 0;
            if (id > 0) {
                conceptosMeta[id] = $.extend({}, conceptosMeta[id] || {}, {
                    tipoconcepto: String(data.tipoconcepto || (conceptosMeta[id] && conceptosMeta[id].tipoconcepto) || ''),
                    nombre: String(data.nombre || (conceptosMeta[id] && conceptosMeta[id].nombre) || ''),
                    codigo: String(data.codigo || (conceptosMeta[id] && conceptosMeta[id].codigo) || ''),
                    impuesto_tasa: parseFloat(data.impuesto_tasa || (conceptosMeta[id] && conceptosMeta[id].impuesto_tasa) || 0) || 0,
                    formula: String(data.formula || (conceptosMeta[id] && conceptosMeta[id].formula) || ''),
                    formula_codigo_base: String(data.formula_codigo_base || (conceptosMeta[id] && conceptosMeta[id].formula_codigo_base) || ''),
                    formula_coeficiente: parseFloat(data.formula_coeficiente || (conceptosMeta[id] && conceptosMeta[id].formula_coeficiente) || 0) || 0,
                    cuenta_debe_id: parseInt(data.cuenta_debe_id || (conceptosMeta[id] && conceptosMeta[id].cuenta_debe_id) || '0', 10) || 0,
                    cuenta_debe_codigo: String(data.cuenta_debe_codigo || (conceptosMeta[id] && conceptosMeta[id].cuenta_debe_codigo) || ''),
                    cuenta_debe_nombre: String(data.cuenta_debe_nombre || (conceptosMeta[id] && conceptosMeta[id].cuenta_debe_nombre) || ''),
                    cuentas_por_empresa: data.cuentas_por_empresa || (conceptosMeta[id] && conceptosMeta[id].cuentas_por_empresa) || {}
                });
            }
            aplicarCuentaConceptoEnFila($row, true);
            marcarAvisosConceptosLocales();
        }
        programarPreviewAsiento();
    });

    $(document).on('click', '.js-cp-ir-conceptos-desde-asiento', function (e) {
        e.preventDefault();
        mostrarSolapa('#cp-solapa-conceptos');
        marcarTabActivo('cp-boton-conceptos');
    });

    function actualizarAbreviaturaTipoComprobante() {
        // Compat: la abreviatura vive en el campo modal (.abreviaturatipotransaccioncompra).
        var abrev = String($('#tipotransaccion_compra_id_abreviatura').val() || '').trim();
        if ($('#cp-tipotransaccion-abreviatura').length) {
            $('#cp-tipotransaccion-abreviatura').val(abrev);
        }
    }

    function formatearMontoConcepto(n) {
        if (window.AsientoMontosFormato && typeof window.AsientoMontosFormato.fmt === 'function') {
            return window.AsientoMontosFormato.fmt(n);
        }
        return (Math.round((n || 0) * 100) / 100).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function esAltaSinPrecarga() {
        return !(parseInt(String($form.attr('data-precarga-id') || '0'), 10) > 0);
    }

    /**
     * Sin precarga: al cargar el gravado (ej. COMPRAS 21% código 2), completa la alícuota
     * cuya fórmula Anita es con(2)*0.21 (IVA 21%).
     */
    function aplicarAlicuotasDesdeGravado($rowOrigen) {
        if (!esAltaSinPrecarga() || !$rowOrigen || !$rowOrigen.length) {
            return;
        }
        var codigoBase = String($rowOrigen.find('.codigo_concepto_ivacompra').val() || '').trim();
        if (codigoBase === '') {
            return;
        }
        var montoGravado = parseMonto($rowOrigen.find('.monto').val() || '0');
        var huboCambio = false;

        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            if ($row.is($rowOrigen)) {
                return;
            }
            var id = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
            if (id <= 0) {
                return;
            }
            var meta = conceptosMeta[id] || {};
            var codigoFormula = String(meta.formula_codigo_base || '').trim();
            var coef = parseFloat(meta.formula_coeficiente || 0) || 0;
            if (codigoFormula === '' || coef <= 0) {
                return;
            }
            if (codigoFormula !== codigoBase) {
                return;
            }
            var $monto = $row.find('.monto');
            if ($monto.prop('readonly') || $monto.prop('disabled')) {
                return;
            }
            var iva = Math.round(montoGravado * coef * 100) / 100;
            var nuevoTxt = iva > 0 || montoGravado === 0 ? formatearMontoConcepto(iva) : '';
            if (String($monto.val() || '') !== nuevoTxt) {
                $monto.val(nuevoTxt);
                huboCambio = true;
            }
        });

        return huboCambio;
    }

    function limpiarFilasConceptos() {
        $('#tbody-concepto-table').empty();
    }

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
            if (tipoI !== 'I' && tipoI !== 'G' && tipoI !== 'E') {
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

    function agregarFilaConcepto(concepto, monto) {
        var tpl = document.getElementById('template-renglon-concepto');
        if (!tpl || !tpl.content) {
            return;
        }
        var $row = $(tpl.content.cloneNode(true));
        var id = parseInt(concepto && concepto.id ? concepto.id : '0', 10) || 0;
        $row.find('.concepto_ivacompra_id').val(id > 0 ? String(id) : '');
        $row.find('.codigo_concepto_ivacompra').val((concepto && concepto.codigo) || '');
        $row.find('.nombre_concepto_ivacompra').val((concepto && concepto.nombre) || '');
        var montoTxt = '';
        if (monto !== undefined && monto !== null && String(monto) !== '') {
            montoTxt = formatearMontoConcepto(parseFloat(monto) || 0);
        }
        $row.find('.monto').val(montoTxt);
                if (id > 0 && concepto) {
            conceptosMeta[id] = $.extend({}, conceptosMeta[id] || {}, {
                tipoconcepto: String(concepto.tipoconcepto || ''),
                nombre: String(concepto.nombre || ''),
                codigo: String(concepto.codigo || ''),
                impuesto_tasa: parseFloat(concepto.impuesto_tasa || 0) || 0,
                formula: String(concepto.formula || ''),
                formula_codigo_base: String(concepto.formula_codigo_base || ''),
                formula_coeficiente: parseFloat(concepto.formula_coeficiente || 0) || 0,
                cuenta_debe_id: parseInt(concepto.cuenta_debe_id || (conceptosMeta[id] && conceptosMeta[id].cuenta_debe_id) || concepto.cuentacontable_id || '0', 10) || 0,
                cuenta_debe_codigo: String(concepto.cuenta_debe_codigo || (conceptosMeta[id] && conceptosMeta[id].cuenta_debe_codigo) || ''),
                cuenta_debe_nombre: String(concepto.cuenta_debe_nombre || (conceptosMeta[id] && conceptosMeta[id].cuenta_debe_nombre) || ''),
                cuentacontable_id: concepto.cuentacontable_id || null,
                cuentas_por_empresa: concepto.cuentas_por_empresa || (conceptosMeta[id] && conceptosMeta[id].cuentas_por_empresa) || {}
            });
        }
        if (contratoImputacionManual()) {
            aplicarCuentaContratoEnFila($row, true);
        } else if (id > 0) {
            aplicarCuentaConceptoEnFila($row, true);
        } else {
            $row.find('.cp-celda-cuenta-debe').addClass('d-none');
        }
        $('#tbody-concepto-table').append($row);
        actualizarColumnaCuentaDebe();
    }

    function numeroOcComprobante() {
        var $f = $('#form-comprobante-proveedor');
        var n = String(($f.length ? $f.attr('data-numero-oc') : '') || '').trim();
        if (n) {
            return n;
        }
        return String($('#numeroordencompra').val() || $('input[name="numeroordencompra"]').val() || '').trim();
    }

    function esTipoProrrateadoComprobante() {
        var abrev = String(
            $('#tipotransaccion_compra_id_abreviatura').val()
            || $('.abreviaturatipotransaccioncompra').first().val()
            || ''
        ).toUpperCase().trim();
        if (abrev.length < 3) {
            return false;
        }
        return abrev.charAt(1) === 'P'
            && ['F', 'C', 'D'].indexOf(abrev.charAt(0)) >= 0
            && ['B', 'S', 'L', 'U'].indexOf(abrev.charAt(2)) >= 0;
    }

    function precargarConceptosPorTipo(tipoId, forzar) {
        var id = parseInt(tipoId || '0', 10) || 0;
        if (id <= 0 || contabilizado) {
            return;
        }
        // FPB/CPB/…: no armar plantilla; los renglones vienen de la precarga (unión).
        if (esTipoProrrateadoComprobante()) {
            return;
        }
        var hayMontos = false;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var monto = parseMonto($(this).find('.monto').val() || '0');
            if (Math.abs(monto) >= 0.0001) {
                hayMontos = true;
            }
        });
        // Nunca pisar conceptos ya valuados (p. ej. precarga prorrateada / API/IA).
        if (hayMontos) {
            return;
        }
        // Tampoco pisar renglones ya elegidos (edición, precarga con IDs, plantilla del server).
        if (hayConceptosCargados()) {
            return;
        }

        var $aviso = $('#cp-conceptos-tipo-aviso');
        var base = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
        var params = {};
        var numeroOc = numeroOcComprobante();
        if (numeroOc) {
            params.numero_oc = numeroOc;
        }
        var totalCabeceraAntes = parseMonto($('#total').val() || '0');
        $.getJSON(base + '/compras/tipotransaccion_compra/' + id + '/conceptos-iva', params)
            .done(function (res) {
                if (res && res.prorrateo_multi_cc) {
                    return;
                }
                var lista = (res && res.conceptos) || [];
                limpiarFilasConceptos();
                if (!lista.length) {
                    agregarFilaConcepto({}, '');
                    if ($aviso.length) {
                        var msgVacio = numeroOc
                            ? 'El tipo no tiene conceptos IVA (ni unión desde la OC). Agréguelos manualmente.'
                            : 'El tipo no tiene conceptos IVA configurados. Si es FPB/prorrateado multi-CC, vinculá la OC.';
                        $aviso.removeClass('d-none').html(
                            '<i class="fa fa-info-circle"></i> ' + msgVacio
                        );
                    }
                } else {
                    var vistos = {};
                    var agregados = 0;
                    lista.forEach(function (c) {
                        var cid = parseInt(String(c && c.id ? c.id : '0'), 10) || 0;
                        var codigo = String((c && c.codigo) || '').trim();
                        var clave = cid > 0 ? ('id:' + cid) : (codigo ? ('cod:' + codigo) : '');
                        if (clave && vistos[clave]) {
                            return;
                        }
                        if (clave) {
                            vistos[clave] = true;
                        }
                        agregarFilaConcepto(c, '');
                        agregados++;
                    });
                    enriquecerMetaGravadosDesdeFormulas();
                    if ($aviso.length) {
                        var msgOk = 'Conceptos del tipo de comprobante. Complete los montos.';
                        $aviso.removeClass('d-none').html(
                            '<i class="fa fa-check-circle"></i> ' + msgOk
                            + (agregados ? ' (' + agregados + ')' : '')
                        );
                    }
                }
                // Plantilla en $0: no llevar a 0 el total/cuotas de una precarga API/IA/portal.
                if (!(Math.abs(totalCabeceraAntes) >= 0.0001)) {
                    sincronizarTotalesDesdeConceptos();
                }
                programarPreviewAsiento();
            })
            .fail(function () {
                if ($aviso.length) {
                    $aviso.removeClass('d-none').html(
                        '<i class="fa fa-exclamation-triangle"></i> No se pudieron precargar los conceptos del tipo.'
                    );
                }
            });
    }

    window.payloadExtraConsultaTipotransaccionCompra = function () {
        var cc = parseInt(String(window.cpCentrocostoOcId || '0'), 10) || 0;
        var fromCampo = parseInt(
            String($('.tm-tipotransaccion-compra-campo').first().attr('data-centrocosto-id') || '0'),
            10
        ) || 0;
        var id = cc || fromCampo;
        return id > 0 ? { centrocosto_id: id } : {};
    };

    function hayConceptosCargados() {
        var hay = false;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            if (parseInt(String($(this).find('.concepto_ivacompra_id').val() || '0'), 10) > 0) {
                hay = true;
                return false;
            }
        });
        return hay;
    }

    function alCambiarTipoComprobante(tipoId) {
        actualizarAbreviaturaTipoComprobante();
        aplicarExcepcionComPorTipo();
        // Si ya hay conceptos (p. ej. al editar FNB→CNB), conservarlos: el asiento
        // se recalcula con el signo del tipo nuevo (NC/ND invierte Debe/Haber).
        if (hayConceptosCargados()) {
            var $aviso = $('#cp-conceptos-tipo-aviso');
            if ($aviso.length) {
                $aviso.removeClass('d-none').html(
                    '<i class="fa fa-info-circle"></i> Se conservaron los conceptos y montos. El asiento se recalcula según el nuevo tipo (NC/ND invierte Debe/Haber).'
                );
            }
            programarPreviewAsiento();
            return;
        }
        precargarConceptosPorTipo(tipoId, true);
    }

    if (typeof activa_eventos_consultatipotransaccioncompra === 'function') {
        activa_eventos_consultatipotransaccioncompra();
    }

    actualizarAbreviaturaTipoComprobante();
    aplicarExcepcionComPorTipo();

    $(document).on('cp:tipotransaccion-compra-elegido', function (e, tipoId) {
        alCambiarTipoComprobante(tipoId);
    });

    // Alta con tipo y grilla vacía (sin IDs): plantilla del tipo. No corre si ya hay renglones.
    (function precargaInicialConceptosTipo() {
        if (contabilizado) {
            return;
        }
        var tipoId = parseInt(String($('#tipotransaccion_compra_id').val() || '0'), 10) || 0;
        if (tipoId <= 0) {
            return;
        }
        var filas = $('#tbody-concepto-table tr.item-concepto').length;
        var conConcepto = 0;
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            if (parseInt(String($(this).find('.concepto_ivacompra_id').val() || '0'), 10) > 0) {
                conConcepto++;
            }
        });
        if (filas === 0 || conConcepto === 0) {
            precargarConceptosPorTipo(tipoId, true);
        }
    })();

    // Compat legado: si queda un select (otras pantallas), mantener el aviso.
    $('#tipotransaccion_compra_id').on('change', function () {
        if ($(this).is('select')) {
            alCambiarTipoComprobante($(this).val());
        }
    });

    $(document).on('change', '#cp-bloque-recepciones-com input[type=checkbox]', function () {
        actualizarUiRecepcionesCom();
        actualizarColumnaCuentaDebe();
        programarPreviewAsiento();
    });

    $form.on('submit', function (e) {
        if (contabilizado) {
            return;
        }
        var modo = $('#modo_carga').val();
        if (!tipoNoExigeCom() && modo === 'ASIGNA_RECEPCION' && $('#cp-bloque-recepciones-com').length) {
            if ($('.cp-com-check:checked').length === 0) {
                e.preventDefault();
                if ($('#cp-solapa-recepciones-com').length) {
                    mostrarSolapa('#cp-solapa-recepciones-com');
                    marcarTabActivo('cp-boton-recepciones-com');
                }
                alert('Debe seleccionar al menos una recepción COM para asociar a la factura del legajo.');
                return;
            }
        }
        var coherencia = validarCoherenciaConceptosPantalla();
        renderBannerCoherenciaConceptos(coherencia);
        if (!coherencia.valido) {
            e.preventDefault();
            mostrarSolapa('#cp-solapa-conceptos');
            marcarTabActivo('cp-boton-conceptos');
            alert(coherencia.errores.join('\n'));
            return;
        }
        if (contratoImputacionManual()) {
            var faltaCuenta = false;
            $('#tbody-concepto-table tr.item-concepto').each(function () {
                var $row = $(this);
                var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10);
                var monto = parseMonto($row.find('.monto').val() || '0');
                if (conceptoId <= 0 || monto <= 0) {
                    return;
                }
                var meta = conceptosMeta[conceptoId] || {};
                if (TIPOS_NETO.indexOf(String(meta.tipoconcepto || '')) < 0) {
                    return;
                }
                var cuentaId = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
                if (cuentaId <= 0) {
                    cuentaId = contratoCuentaManualDatos().id;
                }
                if (cuentaId <= 0) {
                    faltaCuenta = true;
                }
            });
            if (faltaCuenta) {
                e.preventDefault();
                mostrarSolapa('#cp-solapa-conceptos');
                marcarTabActivo('cp-boton-conceptos');
                alert('El contrato exige una cuenta contable del neto. Cárguela en el contrato o en el renglón de neto.');
            }
        }
    });

    // Archivos adjuntos
    $('#cp-agrega-renglon-archivo').on('click', function (e) {
        e.preventDefault();
        var tpl = document.getElementById('cp-template-renglon-archivo');
        if (tpl && tpl.content) {
            $('#cp-tbody-tabla-archivo').append(tpl.content.cloneNode(true));
        }
    });

    $(document).on('click', '.cp-eliminararchivo', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $(document).on('click', '.cp-eliminar-archivo', function (e) {
        e.preventDefault();
        $(this).closest('.cp-archivo-item').remove();
    });

    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }

    (function initProveedorArcaComprobante() {
        var $cfg = $('#cp-proveedor-arca-config');
        if (!$cfg.length || typeof window.ArcaPadronValidacionAsync === 'undefined') {
            return;
        }

        window.cpLimpiarAvisoProveedorArca = function () {
            window.ArcaPadronValidacionAsync.limpiarUltimoModal();
        };

        window.cpValidarProveedorArca = function (proveedorId, condicionivaId) {
            var id = parseInt(proveedorId || '0', 10);
            if (id <= 0) {
                window.cpLimpiarAvisoProveedorArca();
                return;
            }
            window.ArcaPadronValidacionAsync.encolar({
                $config: $cfg,
                proveedorId: id,
                condicionivaId: condicionivaId,
                suspenderUi: false,
            });
        };

        var proveedorInicial = parseInt($('#proveedor_id').val() || '0', 10);
        if (proveedorInicial > 0) {
            window.cpValidarProveedorArca(proveedorInicial);
            if (typeof window.cpValidarProveedorArcaApoc === 'function') {
                window.cpValidarProveedorArcaApoc(proveedorInicial);
            }
        }
    })();

    (function initProveedorArcaApocComprobante() {
        var $cfg = $('#cp-proveedor-arca-apoc-config');
        if (!$cfg.length || typeof window.ArcaApocValidacionAsync === 'undefined') {
            return;
        }

        window.cpLimpiarAvisoProveedorArcaApoc = function () {
            window.ArcaApocValidacionAsync.limpiarUltimoModal();
        };

        window.cpValidarProveedorArcaApoc = function (proveedorId) {
            var id = parseInt(proveedorId || '0', 10);
            if (id <= 0) {
                window.cpLimpiarAvisoProveedorArcaApoc();
                return;
            }
            window.ArcaApocValidacionAsync.encolar({
                $config: $cfg,
                proveedorId: id,
                suspenderUi: false,
            });
        };
    })();

    var paramsUrl = new URLSearchParams(window.location.search);
    if (paramsUrl.get('solapa') === 'asiento' && $('#cp-solapa-asiento-contable').length) {
        mostrarSolapa('#cp-solapa-asiento-contable');
        marcarTabActivo('cp-boton-asiento-contable');
    } else if (paramsUrl.get('solapa') === 'oc' && $('#cp-solapa-ordencompra').length) {
        abrirSolapaOc();
    } else if (paramsUrl.get('solapa') === 'com' && $('#cp-solapa-recepciones-com').length) {
        abrirSolapaCom();
    } else if (paramsUrl.get('solapa') === 'archivos' && $('#cp-solapa-archivos').length) {
        mostrarSolapa('#cp-solapa-archivos');
        marcarTabActivo('cp-boton-archivos');
    } else {
        // Cualquier forma de carga: empezar siempre en datos principales.
        mostrarSolapa('#cp-solapa-principal');
        marcarTabActivo('cp-boton-principal');
    }

    (function initCabeceraFocoEnterYLetra() {
        function focusablesCabeceraCp() {
            return $('#cp-solapa-principal').find('input, select, textarea').filter(function () {
                var $el = $(this);
                if (!$el.is(':visible')) {
                    return false;
                }
                if ($el.is(':disabled') || $el.prop('readonly')) {
                    return false;
                }
                var type = String($el.attr('type') || '').toLowerCase();
                if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset' || type === 'file') {
                    return false;
                }
                if (String($el.attr('tabindex') || '') === '-1') {
                    return false;
                }
                return true;
            });
        }

        function focusSiguienteCampoCp(desde) {
            var $desde = $(desde);
            if (!$desde.length) {
                return;
            }
            var $focusables = focusablesCabeceraCp();
            var idx = $focusables.index($desde);
            if (idx < 0 || idx >= $focusables.length - 1) {
                return;
            }
            setTimeout(function () {
                var $next = $focusables.eq(idx + 1);
                $next.trigger('focus');
                if ($next.is('input:not([type=checkbox]):not([type=radio]), textarea')) {
                    $next.trigger('select');
                }
            }, 0);
        }

        window.focusSiguienteCampoCp = focusSiguienteCampoCp;

        function letraDesdeProveedorJson(data) {
            if (!data) {
                return '';
            }
            var letra = '';
            if (data.condicionivas && data.condicionivas.letra != null) {
                letra = data.condicionivas.letra;
            } else if (data.letra != null) {
                letra = data.letra;
            }
            return String(letra || '').toUpperCase().trim().charAt(0);
        }

        function aplicarLetraDesdeProveedor(data) {
            var $letra = $('#letra');
            if (!$letra.length || $letra.prop('readonly') || $letra.prop('disabled')) {
                return;
            }
            var letra = letraDesdeProveedorJson(data);
            if (letra) {
                $letra.val(letra);
            }
        }

        function forzarLetraMayuscula() {
            var $letra = $('#letra');
            if (!$letra.length) {
                return;
            }
            var v = String($letra.val() || '').toUpperCase().trim().charAt(0);
            if (String($letra.val() || '') !== v) {
                $letra.val(v);
            }
        }

        $('#letra')
            .off('input.cpLetraMayuscula blur.cpLetraMayuscula')
            .on('input.cpLetraMayuscula blur.cpLetraMayuscula', function () {
                var v = String($(this).val() || '').toUpperCase().replace(/[^A-Z]/g, '').charAt(0);
                $(this).val(v);
            });

        window.afterProveedorConsultaOk = function (data, $input) {
            aplicarLetraDesdeProveedor(data);
            var $codigo = $input && $input.length ? $input : $('#codigoproveedor');
            if (!$codigo.data('cp-avanzar-tras-ok')) {
                return;
            }
            $codigo.removeData('cp-avanzar-tras-ok');

            var avanzar = function () {
                focusSiguienteCampoCp($codigo);
            };
            var $modal = $('#consultaproveedorModal');
            // Tras Elegir en el modal, esperar el cierre para no perder el foco en el backdrop.
            if ($modal.length && $modal.hasClass('show')) {
                $modal.one('hidden.bs.modal.cpFocoProveedor', function () {
                    setTimeout(avanzar, 0);
                });
                return;
            }
            setTimeout(avanzar, 0);
        };

        window.afterProveedorConsultaFail = function ($input) {
            var $codigo = $input && $input.length ? $input : $('#codigoproveedor');
            $codigo.removeData('cp-avanzar-tras-ok');
        };

        window.afterTipotransaccionCompraEnterOk = function (data, target) {
            if (!data || !data.id || !target) {
                return;
            }
            focusSiguienteCampoCp(target);
        };

        function esCampoConsultaEspecial(el) {
            if (!el) {
                return false;
            }
            if (el.classList && el.classList.contains('codigoproveedor')) {
                return true;
            }
            if (el.id === 'codigoproveedor') {
                return true;
            }
            if (el.classList && el.classList.contains('abreviaturatipotransaccioncompra')) {
                return true;
            }
            if (el.classList && el.classList.contains('codigoprovincia')) {
                return true;
            }
            if (el.id === 'codigoprovincia' || el.id === 'provincia_destino_codigo') {
                return true;
            }
            return false;
        }

        function validarCampoCabeceraAntesDeAvanzar(target) {
            if (!target) {
                return true;
            }
            var id = target.id || '';
            if (id === 'letra') {
                forzarLetraMayuscula();
                if (!String(target.value || '').trim()) {
                    alert('Indique la letra del comprobante.');
                    target.focus();
                    return false;
                }
            }
            if (id === 'sucursal' && String(target.value || '').trim() === '') {
                alert('Indique el punto de venta / sucursal.');
                target.focus();
                return false;
            }
            if (id === 'numerocomprobante') {
                var nro = parseInt(target.value || '0', 10);
                if (!(nro > 0)) {
                    alert('Indique el número de comprobante.');
                    target.focus();
                    return false;
                }
            }
            if (id === 'empresa_id' && target.tagName === 'SELECT' && !String(target.value || '').trim()) {
                alert('Seleccione la empresa.');
                target.focus();
                return false;
            }
            if (id === 'fechacomprobante' && !String(target.value || '').trim()) {
                alert('Indique la fecha del comprobante.');
                target.focus();
                return false;
            }
            if (target.required && !String(target.value || '').trim() && target.tagName === 'SELECT') {
                alert('Complete el campo antes de continuar.');
                target.focus();
                return false;
            }
            return true;
        }

        var formEl = document.getElementById('form-comprobante-proveedor');
        if (formEl && !formEl.dataset.cpEnterNav) {
            formEl.dataset.cpEnterNav = '1';
            formEl.addEventListener('keydown', function (e) {
                if (!(e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13)) {
                    return;
                }
                var target = e.target;
                if (!target || !$(target).closest('#cp-solapa-principal').length) {
                    return;
                }
                if (target.tagName === 'TEXTAREA') {
                    return;
                }
                if (target.tagName === 'BUTTON' || target.type === 'submit' || target.type === 'button') {
                    return;
                }
                if (esCampoConsultaEspecial(target)) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (!validarCampoCabeceraAntesDeAvanzar(target)) {
                    return;
                }
                // Número: verificar duplicado (ERP/Anita) antes de avanzar, como en Anita.
                if (target.id === 'numerocomprobante'
                    && typeof window.cpVerificarDuplicadoCabecera === 'function') {
                    window.cpOmitirBlurDuplicadoCabecera = true;
                    window.cpVerificarDuplicadoCabecera({ alertar: true }).done(function (res) {
                        if (res && res.duplicado) {
                            try {
                                target.focus();
                            } catch (errFocusDup) {
                                // ignore
                            }
                            return;
                        }
                        focusSiguienteCampoCp(target);
                    });
                    return;
                }
                focusSiguienteCampoCp(target);
            }, true);
        }

        function programarFocoInicialCabeceraCp() {
            setTimeout(function () {
                var $empresa = $('#empresa_id');
                var multiEmpresa = $empresa.is('select') && $empresa.is(':visible') && !$empresa.prop('disabled');
                var $destino = multiEmpresa ? $empresa : $('#codigoproveedor');
                if (!$destino.length || $destino.prop('disabled') || $destino.prop('readonly') || !$destino.is(':visible')) {
                    $destino = focusablesCabeceraCp().first();
                }
                if (!$destino.length) {
                    return;
                }
                try {
                    $destino.trigger('focus');
                    if ($destino.is('input:not([type=checkbox]):not([type=radio])')) {
                        $destino.trigger('select');
                    }
                } catch (errFocus) {
                    // ignore
                }
            }, 150);
        }

        programarFocoInicialCabeceraCp();
        enriquecerMetaGravadosDesdeFormulas();
    })();

    formatearInputMontoEn($form);

    (function initCotizacionDiaComprobante() {
        if (contabilizado) {
            return;
        }
        var carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
        var cotizacionManual = false;
        var refrescoXhr = null;
        var $cot = $('#cotizacion');
        var origenInicial = String($cot.data('cotizacion-origen') || '');
        // Si viene de precarga/factura, no pisar el valor al refrescar del día.
        if (origenInicial === 'precarga' || origenInicial === 'factura') {
            cotizacionManual = true;
        }

        function monedaIdForm() {
            return parseInt($('#moneda_id').val() || '1', 10) || 1;
        }

        function fechaComprobanteForm() {
            return (($('#fechacomprobante').val() || '') + '').substring(0, 10);
        }

        function actualizarHintDia(cotDia, cotFactura) {
            var $hint = $('#cp-cotizacion-dia-hint');
            if (!$hint.length) {
                return;
            }
            var mid = monedaIdForm();
            if (mid <= 1) {
                $hint.html('Moneda local: cotización = 1');
                return;
            }
            var diaTxt = (cotDia > 0) ? cotDia.toFixed(4).replace('.', ',') : '—';
            var html = 'Cotización del día (venta): <strong id="cp-cotizacion-dia-valor">' + diaTxt + '</strong>';
            if (cotFactura && cotFactura > 1 && Math.abs(cotFactura - cotDia) > 0.0000005) {
                html += ' · En factura/precarga: <strong id="cp-cotizacion-factura-valor">'
                    + cotFactura.toFixed(4).replace('.', ',') + '</strong> (campo usa la de factura/precarga)';
            }
            $hint.html(html);
        }

        function refrescarCotizacionDia(opciones) {
            opciones = opciones || {};
            var forzarCampo = !!opciones.forzarCampo;
            var mid = monedaIdForm();
            var fecha = fechaComprobanteForm();
            if (mid <= 1) {
                $cot.val(1);
                actualizarHintDia(1, null);
                return;
            }
            if (!fecha) {
                return;
            }
            if (refrescoXhr && refrescoXhr.readyState !== 4) {
                refrescoXhr.abort();
            }
            refrescoXhr = $.getJSON(carpetaBase + '/compras/comprobante-proveedor/api/cotizacion-moneda-fecha', {
                fecha: fecha,
                moneda_id: mid
            }).done(function (res) {
                var cot = parseFloat(res && res.cotizacion);
                if (!(cot > 0)) {
                    return;
                }
                var cotActual = parseFloat($cot.val() || '0') || 0;
                var cotFacturaAttr = parseFloat($cot.attr('data-cotizacion-factura') || '0') || 0;
                actualizarHintDia(cot, cotFacturaAttr > 1 ? cotFacturaAttr : (cotActual > 1 ? cotActual : null));
                // Completar si no hay cotización; no pisar la de factura/precarga.
                if (forzarCampo || (!cotizacionManual && cotActual <= 1)) {
                    $cot.val(cot);
                    $cot.attr('data-cotizacion-origen', 'dia');
                }
            });
        }

        $cot.on('input change', function () {
            cotizacionManual = true;
            $cot.attr('data-cotizacion-origen', 'manual');
        });

        $('#fechacomprobante, #moneda_id').on('change', function () {
            cotizacionManual = false;
            $cot.attr('data-cotizacion-factura', '');
            refrescarCotizacionDia({ forzarCampo: true });
            if (!contabilizado) {
                programarPreviewAsiento();
            }
        });

        // Al abrir: traer cotización del día (hint + campo si falta).
        refrescarCotizacionDia({ forzarCampo: false });
    })();

    (function initVerificarDuplicadoCabecera() {
        if (contabilizado) {
            return;
        }
        var verificarUrl = String($form.attr('data-verificar-duplicado-url') || '').trim();
        if (!verificarUrl) {
            return;
        }

        var ultimoAvisoKey = '';
        var seqDup = 0;

        function payloadCabecera() {
            var precargaId = parseInt($form.attr('data-precarga-id') || '0', 10) || 0;
            var anitaNro = parseInt($form.attr('data-anita-nro') || '0', 10) || 0;
            return {
                empresa_id: parseInt($('#empresa_id').val() || '0', 10) || 0,
                proveedor_id: parseInt($('#proveedor_id').val() || '0', 10) || 0,
                tipotransaccion_compra_id: parseInt($('#tipotransaccion_compra_id').val() || '0', 10) || 0,
                letra: String($('#letra').val() || '').toUpperCase().trim().charAt(0) || '',
                sucursal: parseInt($('#sucursal').val() || '0', 10) || 0,
                numerocomprobante: parseInt($('#numerocomprobante').val() || '0', 10) || 0,
                excluir_comprobante_id: comprobanteId > 0 ? comprobanteId : undefined,
                excluir_precarga_id: precargaId > 0 ? precargaId : undefined,
                excluir_anita_nro_interno: anitaNro > 0 ? anitaNro : undefined
            };
        }

        function clavePayload(p) {
            return [
                p.empresa_id,
                p.proveedor_id,
                p.tipotransaccion_compra_id,
                p.letra,
                p.sucursal,
                p.numerocomprobante
            ].join('|');
        }

        function limpiarAvisoDup() {
            $('#cp-aviso-duplicado-cabecera-slot').empty();
            if (!$('#cp-aviso-anita-async-slot').children().length) {
                $('.js-cp-contabilizar').prop('disabled', false).removeAttr('title');
            }
            ultimoAvisoKey = '';
            $('#numerocomprobante').removeAttr('data-cp-duplicado-key');
        }

        function aplicarDuplicado(res, opts) {
            opts = opts || {};
            var key = opts.key || '';
            if (!res || !res.duplicado) {
                limpiarAvisoDup();
                return false;
            }
            if (res.html) {
                if (res.fuente === 'anita') {
                    $('#cp-aviso-anita-async-slot').html(res.html);
                    $('#cp-aviso-duplicado-cabecera-slot').empty();
                } else {
                    $('#cp-aviso-duplicado-cabecera-slot').html(res.html);
                }
            }
            if (res.bloquea_contabilizar) {
                $('.js-cp-contabilizar').prop('disabled', true)
                    .attr('title', 'Factura duplicada: no se puede contabilizar');
            }
            if (opts.alertar !== false && key && key !== ultimoAvisoKey) {
                ultimoAvisoKey = key;
                $('#numerocomprobante').attr('data-cp-duplicado-key', key);
                setTimeout(function () {
                    alert(res.mensaje || 'Esta factura ya está cargada.');
                }, 0);
            }
            return true;
        }

        window.cpVerificarDuplicadoCabecera = function (opts) {
            opts = opts || {};
            var p = payloadCabecera();
            var key = clavePayload(p);
            if (!(p.empresa_id > 0
                && p.proveedor_id > 0
                && p.tipotransaccion_compra_id > 0
                && p.letra
                && p.numerocomprobante > 0)) {
                return $.Deferred().resolve({ ok: true, listo: false, duplicado: false }).promise();
            }
            var mySeq = ++seqDup;
            var deferred = $.Deferred();
            $.getJSON(verificarUrl, p)
                .done(function (res) {
                    if (mySeq === seqDup) {
                        aplicarDuplicado(res, { alertar: opts.alertar !== false, key: key });
                    }
                    deferred.resolve(res || { ok: true, duplicado: false });
                })
                .fail(function () {
                    deferred.resolve({ ok: true, listo: false, duplicado: false });
                });
            return deferred.promise();
        };

        $('#numerocomprobante, #letra, #sucursal').on('input', function () {
            var keyActual = clavePayload(payloadCabecera());
            var keyMarcada = String($('#numerocomprobante').attr('data-cp-duplicado-key') || '');
            if (keyMarcada && keyMarcada !== keyActual) {
                limpiarAvisoDup();
            }
        });

        $('#numerocomprobante').on('blur', function () {
            if (window.cpOmitirBlurDuplicadoCabecera) {
                window.cpOmitirBlurDuplicadoCabecera = false;
                return;
            }
            window.cpVerificarDuplicadoCabecera({ alertar: true });
        });

        $('#letra, #sucursal, #empresa_id, #proveedor_id, #tipotransaccion_compra_id').on('change', function () {
            if (parseInt($('#numerocomprobante').val() || '0', 10) > 0) {
                window.cpVerificarDuplicadoCabecera({ alertar: true });
            }
        });
        $(document).on('change.cpProveedorCargado', '#proveedor_id', function () {
            if (parseInt($('#numerocomprobante').val() || '0', 10) > 0) {
                window.cpVerificarDuplicadoCabecera({ alertar: true });
            }
        });
    })();

    (function initAvisoAnitaYSyncOcCom() {
        var avisoUrl = String($form.attr('data-aviso-anita-url') || '').trim();
        var anitaNro = parseInt($form.attr('data-anita-nro') || '0', 10) || 0;

        function aplicarAvisoAnita(res) {
            if (!res || !res.html) {
                return;
            }
            $('#cp-aviso-anita-async-slot').html(res.html);
            $('.js-cp-contabilizar').prop('disabled', true).attr('title', 'Ya existe en Anita: no se puede contabilizar');
        }

        if (comprobanteId > 0) {
            if (avisoUrl && !contabilizado && anitaNro <= 0) {
                $.getJSON(avisoUrl, { comprobante_id: comprobanteId }).done(aplicarAvisoAnita);
            }
            return;
        }
        var precargaId = parseInt($form.attr('data-precarga-id') || '0', 10) || 0;
        var ordencompraId = parseInt($form.attr('data-ordencompra-id') || '0', 10) || 0;
        var numeroOc = String($form.attr('data-numero-oc') || '').replace(/\D/g, '');
        var syncUrl = String($form.attr('data-sync-oc-com-url') || '').trim();
        var precargaCargadaUrl = String($form.attr('data-precarga-cargada-url') || '').trim();
        var formPristine = true;
        $form.on('input change', function () {
            formPristine = false;
        });

        if (precargaId > 0 && avisoUrl) {
            $.getJSON(avisoUrl, { precarga_id: precargaId }).done(function (res) {
                aplicarAvisoAnita(res);
                if (res && res.ya_marcada && precargaCargadaUrl) {
                    window.location.href = precargaCargadaUrl;
                }
            });
        }

        if (!syncUrl || (ordencompraId <= 0 && numeroOc === '')) {
            return;
        }

        var $banner = $('#cp-sync-oc-com-banner');
        var $texto = $('#cp-sync-oc-com-texto');
        $banner.removeClass('d-none');
        $.getJSON(syncUrl, {
            precarga_id: precargaId > 0 ? precargaId : undefined,
            ordencompra_id: ordencompraId > 0 ? ordencompraId : undefined
        }).done(function (res) {
            if (!res || !res.ok) {
                $banner.addClass('d-none');
                return;
            }
            if (res.reload) {
                $texto.text(res.mensaje || 'Se actualizó la OC/COM desde Anita. Recargando…');
                if (formPristine) {
                    window.location.reload();
                    return;
                }
                $banner.removeClass('alert-info').addClass('alert-warning');
                $texto.text((res.mensaje || 'Se importó OC/COM desde Anita.') + ' Recargá la pantalla para verlas.');
                $banner.find('.fa-spinner').removeClass('fa-spinner fa-spin').addClass('fa-exclamation-triangle');
                return;
            }
            $banner.addClass('d-none');
        }).fail(function () {
            $banner.removeClass('alert-info').addClass('alert-warning');
            $texto.text('No se pudo consultar OC/COM en Anita. Podés seguir cargando la factura.');
            $banner.find('.fa-spinner').removeClass('fa-spinner fa-spin').addClass('fa-exclamation-triangle');
        });
    })();

    function cpFmtMonto(n) {
        if (n === null || n === undefined || n === '') {
            return '—';
        }
        var x = Number(n);
        if (isNaN(x)) {
            return '—';
        }
        return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cpFilasOVacias($tbody, rowsHtml) {
        $tbody.html(rowsHtml || '<tr><td colspan="3" class="text-muted text-center">Sin registros</td></tr>');
    }

    $(document).on('click', '.js-cp-ver-legajo', function () {
        var $btn = $(this);
        var url = $btn.data('url-paquete');
        var numero = $btn.data('numero') || '';
        var actual = {
            precargaId: parseInt($btn.data('precarga-id'), 10) || 0,
            comprobanteId: parseInt($btn.data('comprobante-id'), 10) || 0,
            letra: String($('#letra').val() || $btn.data('letra') || '').toUpperCase().trim(),
            sucursal: parseInt($('#sucursal').val() || $btn.data('sucursal'), 10) || 0,
            nro: parseInt($('#numerocomprobante').val() || $btn.data('nro'), 10) || 0
        };
        if (!url) {
            return;
        }
        $('#modalCpVerLegajo .modal-title').text('Legajo OC ' + numero);
        $('#cp-legajo-anticipada-banner').addClass('d-none');
        cpFilasOVacias($('#cp-legajo-facturas-body'), '<tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>');
        cpFilasOVacias($('#cp-legajo-comprobantes-body'), '<tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>');
        cpFilasOVacias($('#cp-legajo-coms-body'), '<tr><td colspan="4" class="text-muted text-center">Cargando…</td></tr>');
        cpFilasOVacias($('#cp-legajo-devoluciones-body'), '<tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>');
        $('#modalCpVerLegajo').modal('show');

        function coincideDoc(item) {
            if (!item) {
                return false;
            }
            if (actual.precargaId > 0 && (parseInt(item.id, 10) === actual.precargaId || parseInt(item.precarga_id, 10) === actual.precargaId)) {
                return true;
            }
            if (actual.comprobanteId > 0 && parseInt(item.id, 10) === actual.comprobanteId) {
                return true;
            }
            if (actual.nro <= 0) {
                return false;
            }
            return String(item.letra || '').toUpperCase().trim() === actual.letra
                && parseInt(item.sucursal, 10) === actual.sucursal
                && parseInt(item.numerocomprobante, 10) === actual.nro;
        }

        function badgeEnCarga() {
            return ' <span class="badge badge-primary ml-1">En carga</span>';
        }

        $.getJSON(url).done(function (p) {
            if (p && p.es_anticipada) {
                $('#cp-legajo-anticipada-banner').removeClass('d-none');
            }
            if (p && p.url_oc) {
                $('#cp-legajo-abrir-oc').attr('href', p.url_oc).removeClass('d-none');
            } else {
                $('#cp-legajo-abrir-oc').addClass('d-none');
            }

            var facHtml = '';
            (p.facturas || []).forEach(function (f) {
                var esActual = coincideDoc(f);
                var doc = String(f.etiqueta || ('#' + f.id));
                var origen = String(f.origen_label || '');
                if (origen) {
                    var suffix = ' (' + origen + ')';
                    if (doc.slice(-suffix.length) === suffix) {
                        doc = doc.slice(0, -suffix.length);
                    }
                }
                if (f.url_pdf) {
                    doc = '<a href="' + f.url_pdf + '" target="_blank" rel="noopener">' + doc + '</a>';
                }
                if (esActual) {
                    doc += badgeEnCarga();
                }
                if (origen) {
                    doc += '<div class="text-muted small text-truncate" title="' + origen.replace(/"/g, '&quot;') + '">' +
                        origen + '</div>';
                }
                facHtml += '<tr class="' + (esActual ? 'table-primary font-weight-bold' : '') + '">' +
                    '<td class="align-middle">' + doc + '</td><td class="align-middle">' + (f.fecha || '—') +
                    '</td><td class="text-right align-middle">' + cpFmtMonto(f.total) + '</td></tr>';
            });
            cpFilasOVacias($('#cp-legajo-facturas-body'), facHtml);

            var cpHtml = '';
            (p.comprobantes || []).forEach(function (c) {
                var esActual = coincideDoc(c);
                var doc = c.etiqueta || ('#' + c.id);
                if (c.url) {
                    doc = '<a href="' + c.url + '" target="_blank" rel="noopener">' + doc + '</a>';
                }
                if (esActual) {
                    doc += badgeEnCarga();
                }
                cpHtml += '<tr class="' + (esActual ? 'table-primary font-weight-bold' : '') + '">' +
                    '<td class="align-middle">' + doc + '</td><td class="align-middle">' + (c.estado || '—') +
                    '</td><td class="text-right align-middle">' + cpFmtMonto(c.total) + '</td></tr>';
            });
            cpFilasOVacias($('#cp-legajo-comprobantes-body'), cpHtml);

            var comAsignadaA = {};
            Object.keys(p.asignadas || {}).forEach(function (preId) {
                var fac = (p.facturas || []).find(function (f) { return String(f.id) === String(preId); });
                var cp = (p.comprobantes || []).find(function (c) { return ('cp-' + c.id) === String(preId); });
                var label = (fac && fac.etiqueta) || (cp && cp.etiqueta) || ('#' + preId);
                (p.asignadas[preId] || []).forEach(function (id) {
                    if (id > 0) {
                        comAsignadaA[String(id)] = label;
                    }
                });
            });
            var comHtml = '';
            (p.coms || []).forEach(function (c) {
                var doc = c.documento || ('#' + c.id);
                if (c.url_pdf) {
                    doc = '<a href="' + c.url_pdf + '" target="_blank" rel="noopener">' + doc + '</a>';
                } else if (c.url_editar) {
                    doc = '<a href="' + c.url_editar + '" target="_blank" rel="noopener">' + doc + '</a>';
                }
                comHtml += '<tr><td class="align-middle">' + doc + '</td><td class="align-middle">' + (c.fecha || '—') +
                    '</td><td class="align-middle">' + (c.estado || '—') +
                    '</td><td class="align-middle">' + (comAsignadaA[String(c.id)] || '—') + '</td></tr>';
            });
            cpFilasOVacias($('#cp-legajo-coms-body'), comHtml);

            var devHtml = '';
            (p.devoluciones || []).forEach(function (d) {
                var doc = d.documento || ('#' + d.id);
                if (d.url_editar) {
                    doc = '<a href="' + d.url_editar + '" target="_blank" rel="noopener">' + doc + '</a>';
                }
                devHtml += '<tr><td class="align-middle">' + doc + '</td><td class="align-middle">' + (d.fecha || '—') +
                    '</td><td class="align-middle">' + (d.estado || '—') + '</td></tr>';
            });
            cpFilasOVacias($('#cp-legajo-devoluciones-body'), devHtml);
        }).fail(function () {
            cpFilasOVacias($('#cp-legajo-facturas-body'), '<tr><td colspan="3" class="text-danger text-center">No se pudo cargar el legajo</td></tr>');
            cpFilasOVacias($('#cp-legajo-comprobantes-body'), '');
            cpFilasOVacias($('#cp-legajo-coms-body'), '');
            cpFilasOVacias($('#cp-legajo-devoluciones-body'), '');
        });
    });

    if (!contabilizado) {
        // Sembrar mapa con cuentas ya cargadas (edición / old) para que el preview no las limpie.
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var conceptoId = parseInt($row.find('.concepto_ivacompra_id').val() || '0', 10) || 0;
            var cuentaId = parseInt($row.find('.cp-celda-cuenta-debe .cuentacontable_id').val() || '0', 10) || 0;
            if (conceptoId <= 0 || cuentaId <= 0) {
                return;
            }
            recordarCuentaAsientoManual(conceptoId, {
                id: cuentaId,
                codigo: String($row.find('.cp-celda-cuenta-debe .codigocuentacontable').val() || ''),
                nombre: String($row.find('.cp-celda-cuenta-debe .nombrecuentacontable').val() || '')
            });
        });
        marcarAvisosConceptosLocales();
        programarPreviewAsiento();
    }
});
