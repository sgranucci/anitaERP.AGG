(function ($) {
    'use strict';

    var filas = [];
    var cargando = false;

    function depositoSalidaId() {
        return parseInt($('#deposito_salida_id').val(), 10) || 0;
    }

    function depositoEntradaId() {
        return parseInt($('#deposito_entrada_id').val(), 10) || 0;
    }

    function bienUsoDestinoId() {
        return parseInt($('#bien_uso_destino_id').val(), 10) || 0;
    }

    function bienUsoOrigenId() {
        return parseInt($('#bien_uso_origen_id').val(), 10) || 0;
    }

    function selectedTipoAttr(attr) {
        var sel = document.getElementById('tipotransaccion_stock_id');
        if (!sel || sel.selectedIndex < 0) {
            return '0';
        }
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            return '0';
        }

        return opt.getAttribute('data-' + attr) || '0';
    }

    function selectedTipoFlag(attr) {
        return parseInt(selectedTipoAttr(attr), 10) === 1;
    }

    function tipoDestinoBienUso() {
        return selectedTipoFlag('destino-bien-uso');
    }

    function tipoOrigenBienUso() {
        return selectedTipoFlag('origen-bien-uso');
    }

    function tipoManejaContabilidad() {
        return selectedTipoFlag('maneja-contabilidad');
    }

    function actualizarPanelCentrocosto() {
        var visible = tipoManejaContabilidad();
        $('#tm_panel_centrocosto').toggle(visible);
        if (!visible && typeof window.limpiarCentrocostoCampo === 'function') {
            window.limpiarCentrocostoCampo('centrocosto_destino_id');
        }
    }

    function actualizarPanelesDestino() {
        var destinoBien = tipoDestinoBienUso();
        var origenBien = tipoOrigenBienUso();
        $('#tm_deposito_salida').toggle(!origenBien);
        $('#tm_panel_bien_origen').toggle(origenBien);
        $('#tm_deposito_entrada').toggle(!destinoBien);
        $('#tm_panel_bien_destino').toggle(destinoBien);
        if (destinoBien) {
            window.limpiarDepositoCampo('deposito_entrada_id');
        } else {
            $('#bien_uso_destino_id').val('');
        }
        if (origenBien) {
            window.limpiarDepositoCampo('deposito_salida_id');
        } else {
            $('#bien_uso_origen_id').val('');
        }
        $('#tm_btn_cargar').html(
            origenBien
                ? '<i class="fa fa-refresh"></i> Cargar stock asignado al bien'
                : '<i class="fa fa-refresh"></i> Cargar stock del depósito de salida'
        );
        actualizarPanelDestinatario();
        actualizarPanelCentrocosto();
    }

    function vaciarListaPickeo() {
        renderLista([]);
    }

    function notificarCambioOrigen() {
        guardarPreferencias();
        cargarDestinatarios();
        vaciarListaPickeo();
        setEstado('');
        focarPickeo();
    }

    function notificarCambioDeposito() {
        guardarPreferencias();
        cargarDestinatarios();
        vaciarListaPickeo();
        setEstado('');
        focarPickeo();
    }

    function tipotransaccionStockId() {
        return parseInt($('#tipotransaccion_stock_id').val(), 10) || 0;
    }

    function empresaId() {
        return parseInt($('#empresa_id').val(), 10) || 0;
    }

    function modoIntercompanyActivo() {
        return $('#tm_modo_intercompany').val() === '1';
    }

    function actualizarUiModoIntercompany() {
        var activo = modoIntercompanyActivo();
        var $btn = $('#tm_btn_intercompany');
        if (!$btn.length) {
            return;
        }
        $btn.toggleClass('btn-outline-secondary', !activo);
        $btn.toggleClass('btn-warning', activo);
        $btn.html(
            activo
                ? '<i class="fa fa-building"></i> Mostrando dep&oacute;sitos de todas las empresas'
                : '<i class="fa fa-building"></i> Ver dep&oacute;sitos de otras empresas'
        );
    }

    /**
     * Destino de TRA: cualquier depósito de la empresa (o intercompany).
     * Origen: sigue filtrando por usuario_deposito.
     */
    window.payloadExtraConsultaDeposito = function ($ctx) {
        if (modoIntercompanyActivo()) {
            return { intercompany: 1, omitir_filtro_usuario: 1 };
        }

        if ($ctx && $ctx.length && $ctx.is('#tm_deposito_entrada')) {
            return { omitir_filtro_usuario: 1 };
        }

        return {};
    };

    window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
        if (!$ctx || !$ctx.length || !$ctx.is('#tm_deposito_salida')) {
            return;
        }
        var empId = parseInt(data.empresa_id, 10) || 0;
        if (empId <= 0) {
            return;
        }
        var $emp = $('#empresa_id');
        if (!$emp.length) {
            return;
        }
        window._omitirLimpiarDepositoAlCambiarEmpresa = true;
        $emp.val(String(empId)).trigger('change');
        window._omitirLimpiarDepositoAlCambiarEmpresa = false;
    };

    function validarLineaContableAsync(articuloId) {
        if (!window.TM_URLS.validarLineaContable) {
            return $.Deferred()
                .resolve({ ok: true, permitido: true, contabilidad_activa: false })
                .promise();
        }
        if (tipoManejaContabilidad() && tipoOrigenBienUso()) {
            return $.Deferred()
                .resolve({
                    ok: true,
                    permitido: false,
                    contabilidad_activa: true,
                    motivo: 'TRCONT requiere depósito de salida (no bien de uso como origen).',
                })
                .promise();
        }

        return $.get(window.TM_URLS.validarLineaContable, {
            articulo_id: articuloId,
            deposito_salida_id: depositoSalidaId(),
            empresa_id: empresaId(),
            tipotransaccion_stock_id: tipotransaccionStockId(),
        });
    }

    var tipoIdAntesTrcontAuto = 0;
    var trcontAutoAplicado = false;

    function tipoTraId() {
        var encontrado = 0;
        $('#tipotransaccion_stock_id option').each(function () {
            if (String($(this).attr('data-abreviatura') || '').toUpperCase() === 'TRA' && this.value) {
                encontrado = parseInt(this.value, 10) || 0;
                return false;
            }
        });
        return encontrado;
    }

    function esFalloSinComDeposito(resp) {
        return !!(resp && resp.sin_recepcion_deposito);
    }

    function revertirATraPorSinCom(resp) {
        var traId = tipoIdAntesTrcontAuto > 0 ? tipoIdAntesTrcontAuto : tipoTraId();
        trcontAutoAplicado = false;
        tipoIdAntesTrcontAuto = 0;
        if (traId > 0 && tipotransaccionStockId() !== traId) {
            $('#tipotransaccion_stock_id').val(String(traId)).trigger('change');
        }
        var detalle = (resp && resp.motivo) ? resp.motivo : 'El depósito de salida no tiene COM de este artículo.';
        setEstado(detalle + ' Se registra como TRA.');
    }

    function aplicarTipoTrcontSiCorresponde(resp) {
        var tipoTrcontId = parseInt(resp && resp.tipo_trcont_id, 10) || 0;
        var familia = String((resp && resp.familia) || '');
        var esOtrosActivos = familia === 'otros_activos';
        if (!resp || !esOtrosActivos || !resp.es_contabilizable || tipoManejaContabilidad() || tipoTrcontId <= 0) {
            return false;
        }
        if (esFalloSinComDeposito(resp)) {
            return false;
        }
        var $tipo = $('#tipotransaccion_stock_id');
        if (!$tipo.find('option[value="' + tipoTrcontId + '"]').length) {
            return false;
        }
        tipoIdAntesTrcontAuto = tipotransaccionStockId();
        $tipo.val(String(tipoTrcontId)).trigger('change');
        trcontAutoAplicado = true;
        setEstado('Artículo de otros activos: se seleccionó TRCONT para no omitir la contabilidad.');
        return true;
    }

    function todasLineasContablesValidas() {
        if (!tipoManejaContabilidad() || tipoOrigenBienUso()) {
            return true;
        }
        var ok = true;
        filas.forEach(function (f) {
            if (f.contable_valido === false) {
                ok = false;
            }
        });
        return ok;
    }

    function filtrarFilasContables(filasEntrada, done) {
        if (!tipoManejaContabilidad() || tipoOrigenBienUso()) {
            filasEntrada.forEach(function (f) {
                f.contable_valido = true;
            });
            done(filasEntrada, 0);
            return;
        }

        var validas = [];
        var omitidas = 0;
        var idx = 0;

        function siguiente() {
            if (idx >= filasEntrada.length) {
                done(validas, omitidas);
                return;
            }
            var f = filasEntrada[idx];
            idx += 1;
            validarLineaContableAsync(f.articulo_id)
                .done(function (resp) {
                    if (resp.ok && resp.permitido) {
                        f.contable_valido = true;
                        f.familia_contable = resp.familia || '';
                        validas.push(f);
                    } else if (resp.ok && !resp.contabilidad_activa) {
                        f.contable_valido = true;
                        validas.push(f);
                    } else {
                        omitidas += 1;
                    }
                    siguiente();
                })
                .fail(function () {
                    omitidas += 1;
                    siguiente();
                });
        }

        siguiente();
    }

    function tipoRequiereAprobacion() {
        return selectedTipoFlag('requiere-aprobacion');
    }

    function tipoAvisoOpcional() {
        return selectedTipoFlag('aviso-opcional');
    }

    function mostrarPanel($panel, visible) {
        if (!$panel || !$panel.length) {
            return;
        }
        if (visible) {
            $panel.css('display', 'block');
        } else {
            $panel.css('display', 'none');
        }
    }

    function actualizarPanelDestinatario() {
        var show = tipoRequiereAprobacion() || tipoAvisoOpcional();
        var puedeCargarOpciones = tipoDestinoBienUso() || depositoEntradaId() > 0;

        mostrarPanel($('#tm_panel_destinatario'), show);
        if (tipoDestinoBienUso()) {
            $('#tm_destinatario_ayuda').text('Indique el usuario del ERP que confirmará la recepción en el bien de uso.');
        } else if (tipoOrigenBienUso()) {
            $('#tm_destinatario_ayuda').text('Indique el usuario del ERP que aprobará el ingreso en el depósito destino.');
        } else if (!puedeCargarOpciones) {
            $('#tm_destinatario_ayuda').text(
                'Seleccione depósito de entrada para listar usuarios. Vacío = administrador principal del depósito.'
            );
        } else {
            $('#tm_destinatario_ayuda').text('Usuario del ERP que recibirá el aviso (activo y con email). Vacío = administrador principal del depósito.');
        }
        if (show && puedeCargarOpciones) {
            cargarDestinatarios();
        } else if (show) {
            $('#usuario_destino_id').find('option:not(:first)').remove();
        }
    }

    function cargarDestinatarios() {
        var dep = depositoEntradaId();
        var destinoBien = tipoDestinoBienUso();
        var $sel = $('#usuario_destino_id');
        if ((!dep && !destinoBien) || !window.TM_URLS.destinatarios) {
            $sel.find('option:not(:first)').remove();
            return;
        }
        $.get(window.TM_URLS.destinatarios, {
            deposito_entrada_id: dep,
            destino_bien_uso: destinoBien ? 1 : 0,
        })
            .done(function (resp) {
                $sel.find('option:not(:first)').remove();
                (resp.opciones || []).forEach(function (o) {
                    var label = o.nombre + (o.principal ? ' (principal)' : '');
                    if (o.email) {
                        label += ' — ' + o.email;
                    }
                    $sel.append($('<option/>').val(o.id).text(label));
                });
            });
    }

    function guardarPreferencias() {
        if (!window.TM_URLS.preferencias) {
            return;
        }
        $.post(window.TM_URLS.preferencias, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            deposito_salida_id: tipoOrigenBienUso() ? '' : (depositoSalidaId() || ''),
            deposito_entrada_id: tipoDestinoBienUso() ? '' : (depositoEntradaId() || ''),
            bien_uso_destino_id: tipoDestinoBienUso() ? (bienUsoDestinoId() || '') : '',
            bien_uso_origen_id: tipoOrigenBienUso() ? (bienUsoOrigenId() || '') : '',
            tipotransaccion_stock_id: tipotransaccionStockId() || '',
        });
    }

    function setEstado(msg, esError) {
        var $e = $('#tm_estado');
        $e.text(msg || '');
        $e.toggleClass('text-danger', !!esError);
    }

    function mostrarAlertaBanner(mensaje, titulo) {
        $('#tm_alerta_titulo').text(titulo || 'No se pudo completar la operación');
        $('#tm_alerta_texto').text(mensaje || '');
        $('#tm_alerta_overlay').addClass('tm-alerta-visible');
    }

    function ocultarAlertaBanner() {
        $('#tm_alerta_overlay').removeClass('tm-alerta-visible');
    }

    function consultarSaldoErp(articuloId, done) {
        if (tipoOrigenBienUso() || !window.TM_URLS.saldoArticulo) {
            done(0);
            return;
        }
        var dep = depositoSalidaId();
        if (!dep) {
            done(0);
            return;
        }
        $.get(window.TM_URLS.saldoArticulo, {
            articulo_id: articuloId,
            deposito_id: dep,
        })
            .done(function (resp) {
                done(resp.ok ? parseFloat(resp.saldo) || 0 : 0);
            })
            .fail(function () {
                done(0);
            });
    }

    function lineasConCantidad() {
        var out = [];
        $('#tm_lista .tm-item').each(function () {
            var $row = $(this);
            if ($row.is(':hidden')) {
                return;
            }
            var cant = parseFloat($row.find('.tm-cant').val());
            if (!cant || cant <= 0) {
                return;
            }
            var articuloId = parseInt($row.data('articulo-id'), 10);
            if (!articuloId) {
                return;
            }
            out.push({
                articulo_id: articuloId,
                cantidad: cant,
            });
        });
        return out;
    }

    function actualizarBotonTransferir() {
        var n = lineasConCantidad().length;
        var $btn = $('#tm_btn_transferir');
        var bloqueadoContable = tipoManejaContabilidad() && !tipoOrigenBienUso() && !todasLineasContablesValidas();
        $btn.prop('disabled', n === 0 || cargando || bloqueadoContable);
        var label = tipoRequiereAprobacion() ? 'Enviar (' + n + ')' : 'Transferir (' + n + ')';
        $btn.text(label);
    }

    function urlConsultaArticulo(id) {
        if (!window.TM_URLS.articuloConsultaUrl) {
            return '#';
        }
        return String(window.TM_URLS.articuloConsultaUrl).replace('__ID__', String(id));
    }

    function focarFiltroSku() {
        focarPickeo();
    }

    function focarPickeo() {
        var $pickeo = $('#tm_pickeo_codigo');
        if (!$pickeo.length) {
            return;
        }
        setTimeout(function () {
            $pickeo.trigger('focus').trigger('select');
        }, 0);
    }

    function vibrarPickeo(ok) {
        if (!navigator.vibrate) {
            return;
        }
        navigator.vibrate(ok ? 40 : [80, 40, 80]);
    }

    function sincronizarCantidadesDesdeDom() {
        $('#tm_lista .tm-item').each(function () {
            var id = parseInt($(this).data('articulo-id'), 10);
            if (!id) {
                return;
            }
            var cant = parseFloat($(this).find('.tm-cant').val());
            if (isNaN(cant) || cant < 0) {
                cant = 0;
            }
            filas.forEach(function (f) {
                if (parseInt(f.articulo_id, 10) === id) {
                    f.cantidad = cant;
                }
            });
        });
    }

    function resaltarCard(articuloId) {
        var $card = $('#tm_lista .tm-item[data-articulo-id="' + articuloId + '"]');
        if (!$card.length) {
            return;
        }
        $('#tm_lista .tm-item').removeClass('tm-item-recien');
        $card.addClass('tm-item-recien');
        if ($card[0] && $card[0].scrollIntoView) {
            $card[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function abrirModalArticulo() {
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        $('#consultaarticuloModal').modal('show');
    }

    function modalArticuloAbierto() {
        return $('#consultaarticuloModal').hasClass('show')
            || $('#consultaarticuloModal').hasClass('showing');
    }

    function camaraPickeoActiva() {
        return !!window.tmCamaraPickeoActiva;
    }

    function resolverPickeo(codigoForzado) {
        if (modalArticuloAbierto()) {
            return;
        }
        var codigo = codigoForzado != null && String(codigoForzado).trim() !== ''
            ? String(codigoForzado).trim()
            : ($('#tm_pickeo_codigo').val() || '').trim();
        if (!codigo) {
            return;
        }
        if (tipoOrigenBienUso()) {
            if (!bienUsoOrigenId()) {
                setEstado('Seleccione bien de uso de origen.', true);
                return;
            }
        } else if (!depositoSalidaId()) {
            setEstado('Seleccione depósito de salida.', true);
            return;
        }
        if (cargando) {
            return;
        }

        cargando = true;
        $('#tm_btn_pickeo').prop('disabled', true);
        $('#tm_pickeo_codigo').val(codigo);
        setEstado('Leído: ' + codigo + '. Buscando en el ERP…');

        $.ajax({
            url: window.TM_URLS.resolverArticulo,
            method: 'GET',
            data: {
                codigo: codigo,
                deposito_id: depositoSalidaId(),
                empresa_id: empresaId(),
            },
            dataType: 'json',
        })
            .done(function (resp) {
                if (!resp || !resp.ok) {
                    var msgNo = 'Se leyó ' + codigo + '. No hay un artículo activo con ese SKU o código de barras.';
                    if (resp && resp.mensaje) {
                        msgNo = 'Se leyó ' + codigo + '. ' + resp.mensaje;
                    }
                    setEstado(msgNo, true);
                    mostrarAlertaBanner(msgNo, 'Código leído — no está en el ERP');
                    vibrarPickeo(false);
                    return;
                }
                agregarFilaManual({
                    articulo_id: resp.articulo_id,
                    sku: resp.sku || '',
                    descripcion: resp.descripcion || '',
                    saldo: parseFloat(resp.saldo) || 0,
                });
            })
            .fail(function (xhr) {
                var msg = 'Se leyó ' + codigo + '. No hay un artículo activo con ese SKU o código de barras.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = 'Se leyó ' + codigo + '. ' + xhr.responseJSON.mensaje;
                }
                setEstado(msg, true);
                mostrarAlertaBanner(msg, 'Código leído — no está en el ERP');
                vibrarPickeo(false);
            })
            .always(function () {
                cargando = false;
                $('#tm_btn_pickeo').prop('disabled', false);
                actualizarBotonTransferir();
                if (!camaraPickeoActiva()) {
                    focarPickeo();
                }
            });
    }

    window.tmAplicarCodigoPickeo = function (codigo) {
        resolverPickeo(codigo);
    };

    function cantidadMaximaFila(f) {
        return parseFloat(f && f.saldo) || 0;
    }

    function incrementarFilaExistente(f, incremento) {
        incremento = incremento == null ? 1 : incremento;
        var max = cantidadMaximaFila(f);
        var actual = parseFloat(f.cantidad) || 0;
        var siguiente = actual + incremento;
        if (siguiente > max + 0.000001) {
            var msg = 'No hay más stock. Saldo ' + max + ', ya cargados ' + actual + '.';
            setEstado(msg, true);
            mostrarAlertaBanner(msg, 'Stock insuficiente');
            vibrarPickeo(false);
            resaltarCard(f.articulo_id);
            return false;
        }
        f.cantidad = siguiente;
        var $card = $('#tm_lista .tm-item[data-articulo-id="' + f.articulo_id + '"]');
        if ($card.length) {
            $card.find('.tm-cant').val(f.cantidad);
            resaltarCard(f.articulo_id);
        }
        actualizarBotonTransferir();
        setEstado((f.sku || '') + ' — cantidad ' + f.cantidad + ' (saldo ' + max + ')');
        vibrarPickeo(true);
        return true;
    }

    function aplicarFiltro() {
        var q = ($('#tm_filtro_desc').val() || '').toLowerCase().trim();
        $('#tm_lista .tm-item').each(function () {
            var $row = $(this);
            if (!q) {
                $row.show();
                return;
            }
            var texto = ($row.data('busqueda') || '').toLowerCase();
            $row.toggle(texto.indexOf(q) !== -1);
        });
        actualizarBotonTransferir();
    }

    function agregarFilaManual(f) {
        if (!f || !f.articulo_id) {
            return;
        }

        if (!tipoOrigenBienUso()) {
            validarLineaContableAsync(f.articulo_id)
                .done(function (resp) {
                    if (aplicarTipoTrcontSiCorresponde(resp)) {
                        validarLineaContableAsync(f.articulo_id)
                            .done(function (respTrcont) {
                                procesarValidacionFilaManual(f, respTrcont);
                            })
                            .fail(function () {
                                setEstado('Error al validar línea contable.', true);
                            });
                    } else {
                        procesarValidacionFilaManual(f, resp);
                    }
                })
                .fail(function () {
                    setEstado('Error al validar línea contable.', true);
                });
            return;
        }

        f.contable_valido = true;
        agregarFilaManualConfirmado(f);
    }

    function procesarValidacionFilaManual(f, resp) {
        if (resp.contabilidad_activa && !resp.permitido && esFalloSinComDeposito(resp)) {
            revertirATraPorSinCom(resp);
            f.contable_valido = true;
            f.familia_contable = resp.familia || '';
            agregarFilaManualConfirmado(f);
            setEstado((resp.motivo || 'Sin COM en el depósito de salida.') + ' Se registra como TRA.');
            return;
        }
        if (resp.contabilidad_activa && !resp.permitido) {
            var msgContable = resp.motivo || 'Línea no válida para TRCONT.';
            setEstado(msgContable, true);
            mostrarAlertaBanner(msgContable, 'Artículo no válido para TRCONT');
            return;
        }
        f.contable_valido = !resp.contabilidad_activa || !!resp.permitido;
        f.familia_contable = resp.familia || '';
        agregarFilaManualConfirmado(f);
        if (!resp.contabilidad_activa && esFalloSinComDeposito(resp) && String(resp.familia || '') === 'otros_activos') {
            setEstado('Artículo de otros activos sin COM en este depósito: se registra como TRA.');
        }
    }

    function evaluarTipoAutomaticoFila($card) {
        if (tipoManejaContabilidad() || tipoOrigenBienUso()) {
            return;
        }
        var cantidad = parseFloat($card.find('.tm-cant').val()) || 0;
        var articuloId = parseInt($card.attr('data-articulo-id'), 10) || 0;
        if (cantidad <= 0 || articuloId <= 0) {
            return;
        }
        validarLineaContableAsync(articuloId).done(function (resp) {
            if (aplicarTipoTrcontSiCorresponde(resp)) {
                validarLineaContableAsync(articuloId).done(function (respTrcont) {
                    if (respTrcont.contabilidad_activa && !respTrcont.permitido && esFalloSinComDeposito(respTrcont)) {
                        revertirATraPorSinCom(respTrcont);
                    }
                });
            }
        });
    }

    function agregarFilaManualConfirmado(f) {
        sincronizarCantidadesDesdeDom();
        var articuloId = parseInt(f.articulo_id, 10);
        var existente = filas.find(function (x) {
            return parseInt(x.articulo_id, 10) === articuloId;
        });
        if (existente) {
            existente.saldo = f.saldo != null ? f.saldo : existente.saldo;
            incrementarFilaExistente(existente, 1);
            return;
        }

        var saldo = parseFloat(f.saldo) || 0;
        if (saldo <= 0) {
            var msgSin = 'Sin stock en el depósito de salida.';
            setEstado(msgSin, true);
            mostrarAlertaBanner(msgSin, 'Sin stock');
            vibrarPickeo(false);
            return;
        }

        f.cantidad = 1;
        f.saldo = saldo;
        filas.unshift(f);
        renderLista(filas);
        resaltarCard(articuloId);
        setEstado((f.sku || '') + ' — cantidad 1 (saldo ' + saldo + ')');
        vibrarPickeo(true);
        if (!camaraPickeoActiva()) {
            focarPickeo();
        }
    }

    function renderLista(data) {
        filas = data || [];
        var $lista = $('#tm_lista').empty();

        if (!filas.length) {
            $lista.html(
                '<div class="tm-vacio">Pickeá o ingresá el SKU para ir cargando. Al actualizar se genera la TRA.</div>'
            );
            $('#tm_panel_filtro').hide();
            actualizarBotonTransferir();
            return;
        }

        $('#tm_panel_filtro').show();

        filas.forEach(function (f) {
            var articuloId = f.articulo_id;
            var sinErp = !articuloId;
            var sku = f.sku || f.sku_anita || '';
            var desc = f.descripcion || '(Sin descripción en ERP)';
            var saldo = parseFloat(f.saldo) || 0;
            var busqueda = (sku + ' ' + desc).toLowerCase();

            var $card = $('<div class="tm-item"/>')
                .toggleClass('tm-sin-erp', sinErp)
                .attr('data-busqueda', busqueda)
                .attr('data-articulo-id', articuloId || '')
                .attr('data-saldo', saldo);

            var $top = $('<div class="d-flex justify-content-between align-items-start"/>');
            var $descBlock = $('<div class="flex-grow-1 pr-2"/>').append(
                $('<div class="tm-desc"/>').text(desc),
                $('<div class="tm-meta"/>').text('SKU: ' + sku)
            );
            if (f.familia_contable === 'tito') {
                $descBlock.append(
                    $('<span class="badge badge-info ml-1"/>').text('TITO')
                );
            } else if (f.familia_contable === 'otros_activos') {
                $descBlock.append(
                    $('<span class="badge badge-secondary ml-1"/>').text('Otros activos')
                );
            }
            $top.append($descBlock);

            var $acciones = $('<div class="d-flex align-items-center" style="gap:4px;"/>');
            if (articuloId && window.TM_URLS.articuloConsultaUrl) {
                $acciones.append(
                    $('<a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"/>')
                        .attr('href', urlConsultaArticulo(articuloId))
                        .attr('title', 'Consultar artículo')
                        .html('<i class="fa fa-edit"></i>')
                );
            }
            $acciones.append(
                $('<button type="button" class="btn btn-outline-danger btn-sm tm-quitar"/>')
                    .attr('title', 'Quitar de la lista')
                    .html('<i class="fa fa-times"></i>')
                    .on('click', function () {
                        filas = filas.filter(function (x) {
                            return parseInt(x.articulo_id, 10) !== parseInt(articuloId, 10);
                        });
                        renderLista(filas);
                        focarPickeo();
                    })
            );
            $top.append($acciones);

            $card.append($top);

            var $fila = $('<div class="d-flex justify-content-between align-items-center mt-2"/>');
            $fila.append($('<span class="tm-meta"/>').text('Saldo'));
            $fila.append($('<span class="tm-saldo"/>').text(saldo));
            $card.append($fila);

            var $cantRow = $('<div class="d-flex justify-content-between align-items-center mt-2"/>');
            $cantRow.append($('<span class="tm-meta"/>').text('A transferir'));
            var cantidad = parseFloat(f.cantidad);
            if (isNaN(cantidad) || cantidad < 0) {
                cantidad = 0;
            }
            var $input = $('<input type="number" class="form-control tm-cant" min="0" step="any" inputmode="decimal"/>')
                .attr('max', saldo > 0 ? saldo : null)
                .prop('disabled', sinErp)
                .val(cantidad > 0 ? cantidad : '')
                .attr('placeholder', '0');
            var $btnTodo = $('<button type="button" class="btn btn-outline-primary btn-sm ml-2"/>')
                .text('Todo')
                .prop('disabled', sinErp || saldo <= 0)
                .on('click', function () {
                    $input.val(saldo).trigger('change');
                });
            $cantRow.append($('<div class="d-flex align-items-center"/>').append($input, $btnTodo));
            $card.append($cantRow);

            if (sinErp) {
                $card.append(
                    $('<div class="text-danger small mt-1"/>').text('Sin artículo en ERP: no se puede transferir.')
                );
            }

            $lista.append($card);
        });

        aplicarFiltro();
        focarFiltroSku();
    }

    function cargarInventario() {
        var origenBien = tipoOrigenBienUso();
        var dep = depositoSalidaId();
        var bien = bienUsoOrigenId();

        if (origenBien) {
            if (!bien) {
                setEstado('Seleccione bien de uso de origen.', true);
                return;
            }
        } else if (!dep) {
            setEstado('Seleccione depósito de salida.', true);
            return;
        }

        cargando = true;
        $('#tm_btn_cargar').prop('disabled', true);
        setEstado(origenBien ? 'Consultando stock asignado al bien…' : 'Consultando saldos en el depósito de salida…');

        $.ajax({
            url: window.TM_URLS.inventario,
            method: 'GET',
            data: origenBien
                ? { origen_bien_uso: 1, bien_uso_origen_id: bien }
                : { deposito_salida_id: dep },
            dataType: 'json',
        })
            .done(function (resp) {
                if (!resp.ok) {
                    setEstado(resp.mensaje || 'Error al cargar.', true);
                    renderLista([]);
                    return;
                }
                var msgBase =
                    resp.filas.length +
                    (origenBien
                        ? ' artículo(s) asignados al bien de uso.'
                        : ' artículo(s) con saldo en el depósito de salida.');

                filtrarFilasContables(resp.filas || [], function (validas, omitidas) {
                    if (tipoManejaContabilidad() && !tipoOrigenBienUso() && omitidas > 0) {
                        setEstado(
                            validas.length +
                                ' artículo(s) válidos para TRCONT. Omitidos ' +
                                omitidas +
                                ' (no contabilizables, TITO fuera de última recepción u otros activos sin COM en el depósito).'
                        );
                    } else {
                        setEstado(msgBase);
                    }
                    renderLista(validas);
                });
            })
            .fail(function (xhr) {
                var msg = 'Error de comunicación.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                setEstado(msg, true);
                renderLista([]);
            })
            .always(function () {
                cargando = false;
                $('#tm_btn_cargar').prop('disabled', false);
                actualizarBotonTransferir();
            });
    }

    function grabarTransferencia() {
        var depSal = depositoSalidaId();
        var depEnt = depositoEntradaId();
        var bienDest = bienUsoDestinoId();
        var bienOrig = bienUsoOrigenId();
        var destinoBien = tipoDestinoBienUso();
        var origenBien = tipoOrigenBienUso();
        var tipo = tipotransaccionStockId();
        var lineas = lineasConCantidad();

        if (origenBien) {
            if (!bienOrig) {
                alert('Seleccione el bien de uso de origen.');
                return;
            }
            if (!depEnt) {
                alert('Seleccione depósito de entrada.');
                return;
            }
        } else {
            if (!depSal) {
                alert('Seleccione depósito de salida.');
                return;
            }
            if (destinoBien) {
                if (!bienDest) {
                    alert('Seleccione el bien de uso destino.');
                    return;
                }
            } else if (!depEnt) {
                alert('Seleccione depósito de entrada.');
                return;
            }
            if (!destinoBien && depSal === depEnt) {
                alert('Los depósitos deben ser distintos.');
                return;
            }
        }
        if (!tipo) {
            alert('Seleccione tipo de transacción.');
            return;
        }
        if (!lineas.length) {
            alert('Indique cantidad en al menos un artículo.');
            return;
        }

        var tipoDepSalida = String($('#tm_deposito_salida').attr('data-tipodeposito') || '').trim();
        var omitirControlSaldo = tipoDepSalida.toLowerCase() === 'centro de consumo'
            || tipoDepSalida.toUpperCase() === 'M';

        if (!omitirControlSaldo) {
            var invalido = false;
            $('#tm_lista .tm-item:visible').each(function () {
                var $row = $(this);
                var cant = parseFloat($row.find('.tm-cant').val());
                if (!cant || cant <= 0) {
                    return;
                }
                var saldo = parseFloat($row.data('saldo')) || 0;
                if (cant > saldo + 0.000001) {
                    invalido = true;
                }
            });
            if (invalido) {
                mostrarAlertaBanner(
                    'Alguna cantidad supera el saldo disponible en el depósito de salida. Revise las cantidades indicadas.',
                    'Saldo insuficiente'
                );
                return;
            }
        }

        if (tipoManejaContabilidad() && !(parseInt($('#centrocosto_destino_id').val(), 10) > 0)) {
            alert('Debe indicar centro de costo destino (transferencia con contabilidad).');
            $('#centrocosto_destino_id_codigo').trigger('focus');
            return;
        }

        var contexto = {
            depSal: depSal,
            depEnt: depEnt,
            bienDest: bienDest,
            bienOrig: bienOrig,
            destinoBien: destinoBien,
            origenBien: origenBien,
            tipo: tipo,
            lineas: lineas,
        };

        if (tipoAvisoOpcional() && typeof window.msPreguntarEnvioAviso === 'function') {
            window.msPreguntarEnvioAviso(function (decision) {
                if (decision === null) {
                    return; // cancelado
                }
                ejecutarGrabado(contexto, decision);
            });
            return;
        }

        ejecutarGrabado(contexto, null);
    }

    function ejecutarGrabado(ctx, enviarAviso) {
        var depSal = ctx.depSal;
        var depEnt = ctx.depEnt;
        var bienDest = ctx.bienDest;
        var bienOrig = ctx.bienOrig;
        var destinoBien = ctx.destinoBien;
        var origenBien = ctx.origenBien;
        var tipo = ctx.tipo;
        var lineas = ctx.lineas;

        var conAviso = tipoRequiereAprobacion() || enviarAviso === true;
        var msgConfirm = conAviso
            ? '¿Confirma el envío de ' + lineas.length + ' artículo(s)? Quedará pendiente de aprobación.'
            : '¿Confirma la transferencia de ' + lineas.length + ' artículo(s)?';
        if (!confirm(msgConfirm)) {
            return;
        }

        var enviarAvisoPayload = '';
        if (enviarAviso === true) {
            enviarAvisoPayload = 1;
        } else if (enviarAviso === false) {
            enviarAvisoPayload = 0;
        }

        cargando = true;
        actualizarBotonTransferir();
        setEstado('Grabando movimiento de stock…');

        $.ajax({
            url: window.TM_URLS.guardar,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                empresa_id: parseInt($('#empresa_id').val(), 10) || '',
                deposito_salida_id: origenBien ? '' : depSal,
                deposito_entrada_id: destinoBien ? '' : depEnt,
                bien_uso_destino_id: destinoBien ? bienDest : '',
                bien_uso_origen_id: origenBien ? bienOrig : '',
                tipotransaccion_stock_id: tipo,
                centrocosto_destino_id: parseInt($('#centrocosto_destino_id').val(), 10) || '',
                usuario_destino_id: parseInt($('#usuario_destino_id').val(), 10) || '',
                enviar_aviso: enviarAvisoPayload,
                lineas: lineas,
            },
            dataType: 'json',
        })
            .done(function (resp) {
                if (resp.ok) {
                    ocultarAlertaBanner();
                    setEstado(resp.mensaje || 'Transferencia registrada.');
                    if (resp.requiere_aprobacion) {
                        window.location.href = $('a[href*="pendientes"]').attr('href') || window.location.href;
                        return;
                    }
                    vaciarListaPickeo();
                    focarPickeo();
                } else {
                    var msgError = resp.mensaje || 'No se pudo grabar.';
                    setEstado(msgError, true);
                    mostrarAlertaBanner(msgError, 'Error al transferir');
                }
            })
            .fail(function (xhr) {
                var msg = 'Error al grabar.';
                if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                    msg = xhr.responseJSON.mensaje;
                }
                setEstado(msg, true);
                mostrarAlertaBanner(msg, 'Error al transferir');
            })
            .always(function () {
                cargando = false;
                actualizarBotonTransferir();
            });
    }

    $(function () {
        $('#tm_btn_cargar').on('click', cargarInventario);
        $('#tm_btn_transferir').on('click', grabarTransferencia);
        $('#tm_filtro_desc').on('input', aplicarFiltro);
        $(document).on('input change', '.tm-cant', function () {
            var $input = $(this);
            var $card = $input.closest('.tm-item');
            var max = parseFloat($card.data('saldo')) || 0;
            var cant = parseFloat($input.val());
            if (isNaN(cant) || cant < 0) {
                cant = 0;
            }
            if (max > 0 && cant > max + 0.000001) {
                cant = max;
                $input.val(cant);
            }
            var articuloId = parseInt($card.data('articulo-id'), 10);
            filas.forEach(function (f) {
                if (parseInt(f.articulo_id, 10) === articuloId) {
                    f.cantidad = cant;
                }
            });
            actualizarBotonTransferir();
        });
        $(document).on('change', '.tm-cant', function () {
            evaluarTipoAutomaticoFila($(this).closest('.tm-item'));
        });
        $('#tipotransaccion_stock_id').on('change input', function () {
            guardarPreferencias();
            actualizarPanelesDestino();
            actualizarPanelDestinatario();
            if (filas.length) {
                sincronizarCantidadesDesdeDom();
                filtrarFilasContables(filas.slice(), function (validas, omitidas) {
                    if (tipoManejaContabilidad() && omitidas > 0) {
                        setEstado(
                            validas.length +
                                ' artículo(s) válidos para TRCONT. Omitidos ' +
                                omitidas +
                                ' al cambiar tipo.',
                            omitidas > 0 && validas.length === 0
                        );
                    }
                    renderLista(validas);
                });
            } else {
                actualizarBotonTransferir();
            }
        });

        actualizarPanelCentrocosto();

        $('#bien_uso_destino_id').on('change', function () {
            guardarPreferencias();
            actualizarPanelDestinatario();
        });

        $('#bien_uso_origen_id').on('change', function () {
            notificarCambioOrigen();
        });

        $(document).on('change', '#deposito_salida_id', function () {
            notificarCambioDeposito();
        });

        $(document).on('change', '#tm_deposito_entrada .deposito_id, #deposito_entrada_id', function () {
            guardarPreferencias();
            actualizarPanelDestinatario();
        });

        $('#empresa_id').on('change', function () {
            if (window._omitirLimpiarDepositoAlCambiarEmpresa) {
                return;
            }
            $('.tm-deposito-campo').each(function () {
                $(this).find('.deposito_id').val('').trigger('change');
                $(this).find('.codigodeposito').val('');
                $(this).find('.descripciondeposito').val('');
            });
            vaciarListaPickeo();
            setEstado('');
        });

        $('#tm_btn_agregar_articulo, #tm_btn_pickeo_lupa').on('click', function () {
            abrirModalArticulo();
        });

        $('#tm_btn_pickeo').on('click', function () {
            resolverPickeo();
        });
        $('#tm_pickeo_codigo').on('keydown', function (e) {
            if (e.key === 'Enter' || e.which === 13) {
                e.preventDefault();
                resolverPickeo();
                return;
            }
            if (e.key === 'F1' || e.which === 112) {
                e.preventDefault();
                abrirModalArticulo();
            }
        });

        window.onArticuloSeleccionado = function (dataArticulo) {
            if (!dataArticulo || !dataArticulo.id) {
                return;
            }
            var articuloId = parseInt(dataArticulo.id, 10);
            consultarSaldoErp(articuloId, function (saldo) {
                agregarFilaManual({
                    articulo_id: articuloId,
                    sku: dataArticulo.sku || '',
                    descripcion: dataArticulo.descripcion || '',
                    saldo: saldo,
                });
            });
        };

        $('#tm_alerta_cerrar, #tm_alerta_overlay').on('click', function (e) {
            if (e.target === this) {
                ocultarAlertaBanner();
            }
        });

        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }

        $('#tm_btn_intercompany').on('click', function () {
            var activo = modoIntercompanyActivo();
            $('#tm_modo_intercompany').val(activo ? '0' : '1');
            actualizarUiModoIntercompany();
            if ($('#consultadepositoModal').hasClass('show') && typeof buscar_datos_deposito === 'function') {
                buscar_datos_deposito($('#consultadeposito').val());
            }
        });
        actualizarUiModoIntercompany();

        actualizarPanelesDestino();
        vaciarListaPickeo();
        focarPickeo();
    });
})(jQuery);
