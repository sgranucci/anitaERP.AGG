(function ($) {
    'use strict';

    function meta() {
        return typeof window.msTipoTransaccionMeta === 'function'
            ? window.msTipoTransaccionMeta()
            : {
                operacion: '',
                manejaContabilidad: false,
                origenBienUso: false,
                destinoBienUso: false,
                requiereAprobacion: false,
                nombre: '',
            };
    }

    function esTransferencia() {
        return meta().operacion === 'T';
    }

    function tipoDestinoBienUso() {
        return meta().destinoBienUso;
    }

    function tipoOrigenBienUso() {
        return meta().origenBienUso;
    }

    function tipoRequiereAprobacion() {
        return meta().requiereAprobacion;
    }

    function depositoEntradaId() {
        return parseInt($('#deposito_entrada_id').val(), 10) || 0;
    }

    function mostrarPanel($panel, visible) {
        if (!$panel || !$panel.length) {
            return;
        }
        if (visible) {
            // Restaurar display por defecto de .row (flex); display:block apila label e inputs.
            $panel.css('display', '');
        } else {
            $panel.css('display', 'none');
        }
    }

    function limpiarDestinatarioMs() {
        $('#usuario_destino_id').val('');
        $('#ms_usuario_destino_nombre').val('');
        $('#usuario_destino_sugeridos').val('');
    }

    function enfocarPrimerArticuloMovStock() {
        if (typeof window.enfocarPrimerArticuloMovimientoStock === 'function') {
            window.enfocarPrimerArticuloMovimientoStock();
            return;
        }

        var el = document.querySelector('#tabla-items-movimientostock .codigoarticulo');
        if (!el || el.readOnly || el.disabled) {
            return;
        }

        window.setTimeout(function () {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }, 0);
    }

    function validarUsuarioDestinoMs(opciones) {
        opciones = opciones || {};
        var uid = parseInt($('#usuario_destino_id').val(), 10) || 0;
        if (!uid) {
            return;
        }

        var dep = depositoEntradaId();
        var destinoBien = tipoDestinoBienUso();
        if (!dep && !destinoBien) {
            return;
        }

        if (!window.MS_TRANSFERENCIA_URLS || !window.MS_TRANSFERENCIA_URLS.validarDestinatario) {
            return;
        }

        $.get(window.MS_TRANSFERENCIA_URLS.validarDestinatario, {
            usuario_id: uid,
            deposito_entrada_id: dep,
            destino_bien_uso: destinoBien ? 1 : 0,
        }).done(function (resp) {
            if (!resp || !resp.ok) {
                alert((resp && resp.mensaje) ? resp.mensaje : 'Usuario no valido como destinatario.');
                limpiarDestinatarioMs();
                return;
            }
            if (resp.nombre) {
                $('#ms_usuario_destino_nombre').val(resp.nombre);
            }
            if (opciones.enfocarArticulo) {
                enfocarPrimerArticuloMovStock();
            }
        });
    }

    function cargarDestinatariosMs() {
        var dep = depositoEntradaId();
        var destinoBien = tipoDestinoBienUso();
        var $sel = $('#usuario_destino_sugeridos');
        if ((!dep && !destinoBien) || !window.MS_TRANSFERENCIA_URLS || !window.MS_TRANSFERENCIA_URLS.destinatarios) {
            $sel.hide().find('option:not(:first)').remove();
            return;
        }

        $.get(window.MS_TRANSFERENCIA_URLS.destinatarios, {
            deposito_entrada_id: dep,
            destino_bien_uso: destinoBien ? 1 : 0,
        })
            .done(function (resp) {
                $sel.find('option:not(:first)').remove();
                var opciones = resp.opciones || [];
                opciones.forEach(function (o) {
                    var label = o.nombre + (o.principal ? ' (principal)' : '');
                    if (o.email) {
                        label += ' — ' + o.email;
                    }
                    $sel.append(
                        $('<option/>')
                            .val(o.id)
                            .text(label)
                            .attr('data-nombre', o.nombre)
                    );
                });
                $sel.toggle(opciones.length > 0);
            });
    }

    function actualizarPanelDestinatarioMs() {
        if ($('#ms_transferencia_vinculada').length) {
            mostrarPanel($('#ms_panel_destinatario'), false);
            return;
        }

        var show = esTransferencia() && tipoRequiereAprobacion();
        var puedeCargarOpciones = tipoDestinoBienUso() || depositoEntradaId() > 0;

        mostrarPanel($('#ms_panel_destinatario'), show);

        if (tipoDestinoBienUso()) {
            $('#ms_destinatario_ayuda').text(
                'Indique el usuario del ERP que confirmará la recepción. Vacío = automático. Use la lupa para buscar otro usuario.'
            );
        } else if (!puedeCargarOpciones) {
            $('#ms_destinatario_ayuda').text(
                'Seleccione depósito destino. Vacío = administrador principal. Use la lupa para buscar otro usuario del ERP.'
            );
        } else {
            $('#ms_destinatario_ayuda').text(
                'Usuario del ERP que recibirá el aviso de aprobación (activo y con email). Vacío = administrador principal del depósito. Use la lupa para buscar otro usuario.'
            );
        }

        if (show && puedeCargarOpciones) {
            cargarDestinatariosMs();
        } else if (show) {
            $('#usuario_destino_sugeridos').hide().find('option:not(:first)').remove();
        }
    }

    function actualizarRequiredDepositoSimple(activo) {
        $('#deposito_id').prop('required', activo);
    }

    function actualizarCamposTransferenciaEnSubmit(esT) {
        var $salida = $('#deposito_salida_id');
        var $entrada = $('#deposito_entrada_id');
        var $bienOrigen = $('#bien_uso_origen_id');
        var $bienDestino = $('#bien_uso_destino_id');

        if (esT) {
            $salida.attr('name', 'deposito_salida_id');
            $entrada.attr('name', 'deposito_entrada_id');
            $bienOrigen.attr('name', 'bien_uso_origen_id');
            $bienDestino.attr('name', 'bien_uso_destino_id');
        } else {
            $salida.prop('required', false).removeAttr('name');
            $entrada.prop('required', false).removeAttr('name');
            $bienOrigen.prop('required', false).removeAttr('name');
            $bienDestino.prop('required', false).removeAttr('name');
        }
    }

    function actualizarRequiredTransferencia() {
        var esT = esTransferencia();
        var origenBien = tipoOrigenBienUso();
        var destinoBien = tipoDestinoBienUso();

        $('#deposito_salida_id').prop('required', esT && !origenBien);
        $('#deposito_entrada_id').prop('required', esT && !destinoBien);
        $('#bien_uso_origen_id').prop('required', esT && origenBien);
        $('#bien_uso_destino_id').prop('required', esT && destinoBien);
        actualizarCamposTransferenciaEnSubmit(esT);
    }

    function actualizarPanelesTransferencia() {
        if ($('#ms_transferencia_vinculada').length) {
            return;
        }

        var esT = esTransferencia();
        var origenBien = tipoOrigenBienUso();
        var destinoBien = tipoDestinoBienUso();

        $('#ms_deposito_simple').toggle(!esT);
        $('#ms_panel_transferencia').toggle(esT);
        $('#ms_panel_intercompany').toggle(esT && $('#ms_btn_intercompany').length > 0);

        if (esT) {
            $('#deposito_id').prop('required', false).removeAttr('name');
            var depSimple = String($('#deposito_id').val() || '').trim();
            if (depSimple && !String($('#deposito_salida_id').val() || '').trim() && !origenBien) {
                window.copiarDepositoCampo('deposito_id', 'deposito_salida_id');
                var tipoSimple = $('#tm_deposito_movimientostock').attr('data-tipodeposito') || '';
                if (tipoSimple) {
                    $('#tm_deposito_salida').attr('data-tipodeposito', tipoSimple);
                    $('#deposito_salida_id').attr('data-tipodeposito', tipoSimple);
                }
            }
        } else {
            $('#deposito_id').attr('name', 'deposito_id');
        }

        $('#tm_deposito_salida').toggle(esT && !origenBien);
        $('#ms_panel_bien_origen').toggle(esT && origenBien);
        $('#tm_deposito_entrada').toggle(esT && !destinoBien);
        $('#ms_panel_bien_destino').toggle(esT && destinoBien);

        actualizarRequiredDepositoSimple(!esT);
        actualizarRequiredTransferencia();
        actualizarPanelDestinatarioMs();

        if (typeof window.msRefrescarConversionFormulaFilas === 'function') {
            window.msRefrescarConversionFormulaFilas();
        }
    }

    $(document).on('change input', '#tipotransaccion_stock_id', actualizarPanelesTransferencia);

    $(document).on('change', '#tm_deposito_entrada .deposito_id, #deposito_entrada_id', function () {
        actualizarPanelDestinatarioMs();
    });

    $(document).on('change', '#bien_uso_destino_id', actualizarPanelDestinatarioMs);

    $(document).on('change', '#usuario_destino_sugeridos', function () {
        var $opt = $(this).find('option:selected');
        var uid = parseInt($opt.val(), 10) || 0;
        if (!uid) {
            limpiarDestinatarioMs();
            return;
        }
        $('#usuario_destino_id').val(String(uid));
        $('#ms_usuario_destino_nombre').val($opt.attr('data-nombre') || $opt.text());
        validarUsuarioDestinoMs({ enfocarArticulo: true });
    });

    $(document).on('click', '#ms_btn_limpiar_destinatario', function () {
        limpiarDestinatarioMs();
    });

    $(document).on('hidden.bs.modal', '#consultausuarioModal', function () {
        if (!$('#ms_panel_destinatario').is(':visible')) {
            return;
        }
        var ptrId = typeof ptrusuario_id !== 'undefined' ? ptrusuario_id : null;
        if (ptrId === '#usuario_destino_id' || ptrId === '#ms_usuario_destino_id') {
            validarUsuarioDestinoMs({ enfocarArticulo: true });
        }
    });

    $(document).on('usuario-operativo:resuelto', '#usuario_destino_id', function () {
        if (!$('#ms_panel_destinatario').is(':visible')) {
            return;
        }
        validarUsuarioDestinoMs({ enfocarArticulo: true });
    });

    $(document).on('change', '#ms_panel_destinatario .usuario_id', function () {
        var valor = $.trim($(this).val());
        if (!valor) {
            $('#ms_usuario_destino_nombre').val('');
        }
    });

    function msModoIntercompanyActivo() {
        return $('#ms_modo_intercompany').val() === '1';
    }

    function actualizarUiModoIntercompanyMs() {
        var activo = msModoIntercompanyActivo();
        var $btn = $('#ms_btn_intercompany');
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

    function enfocarCodigoDepositoDestinoTransferencia() {
        if (!esTransferencia() || tipoDestinoBienUso()) {
            return;
        }

        var el = document.getElementById('deposito_entrada_id_codigo');
        if (!el || el.readOnly || el.disabled || !$('#tm_deposito_entrada').is(':visible')) {
            return;
        }

        window.setTimeout(function () {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }, 0);
    }

    function debeEnfocarUsuarioDestinoTrasDepositoEntrada() {
        return esTransferencia()
            && tipoRequiereAprobacion()
            && $('#ms_panel_destinatario').is(':visible');
    }

    function enfocarUsuarioDestinoTransferencia() {
        if (!debeEnfocarUsuarioDestinoTrasDepositoEntrada()) {
            return;
        }

        var el = document.getElementById('usuario_destino_id');
        if (!el || el.readOnly || el.disabled) {
            return;
        }

        window.setTimeout(function () {
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }, 0);
    }

    function activarEnterUsuarioDestinoValidaEnfocaArticulo() {
        if (!document.getElementById('usuario_destino_id')) {
            return;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.which !== 13) {
                return;
            }

            var target = e.target;
            if (!target || target.id !== 'usuario_destino_id') {
                return;
            }

            if (!$('#ms_panel_destinatario').is(':visible')) {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            var valor = $.trim(String(target.value || ''));
            if (!valor) {
                limpiarDestinatarioMs();
                return;
            }

            $(target).trigger('change');
        }, true);
    }

    function activarEnterDepositoSalidaEnfocaDestino() {
        if (!document.getElementById('deposito_salida_id_codigo')) {
            return;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.which !== 13) {
                return;
            }

            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigodeposito')) {
                return;
            }

            if (!target.closest('#tm_deposito_salida') || !esTransferencia()) {
                return;
            }

            if (typeof esFormularioDepmaeAbm === 'function' && esFormularioDepmaeAbm()) {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            if (typeof empresaRequeridaPendienteEnFormulario === 'function' && empresaRequeridaPendienteEnFormulario()) {
                alert('Seleccione la empresa del formulario antes de consultar dep\u00f3sitos.');
                if (typeof enfocarEmpresaFormularioDeposito === 'function') {
                    enfocarEmpresaFormularioDeposito();
                }
                return;
            }

            if (typeof leerDepositoPorCodigo !== 'function') {
                return;
            }

            leerDepositoPorCodigo($(target).val(), target, function (data) {
                if (data && data.id) {
                    enfocarCodigoDepositoDestinoTransferencia();
                }
            });
        }, true);
    }

    function activarEnterDepositoDestinoEnfocaArticulo() {
        if (!document.getElementById('deposito_entrada_id_codigo')) {
            return;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.which !== 13) {
                return;
            }

            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('codigodeposito')) {
                return;
            }

            if (!target.closest('#tm_deposito_entrada') || !esTransferencia()) {
                return;
            }

            if (typeof esFormularioDepmaeAbm === 'function' && esFormularioDepmaeAbm()) {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            if (typeof empresaRequeridaPendienteEnFormulario === 'function' && empresaRequeridaPendienteEnFormulario()) {
                alert('Seleccione la empresa del formulario antes de consultar dep\u00f3sitos.');
                if (typeof enfocarEmpresaFormularioDeposito === 'function') {
                    enfocarEmpresaFormularioDeposito();
                }
                return;
            }

            var codigo = $.trim(String(target.value || ''));
            if (!codigo) {
                return;
            }

            // Ya resuelto: solo pasar al SKU
            var depId = parseInt($('#deposito_entrada_id').val(), 10) || 0;
            if (depId > 0 && $.trim(String($('#deposito_entrada_id_descripcion').val() || ''))) {
                enfocarPrimerArticuloMovStock();
                return;
            }

            if (typeof leerDepositoPorCodigo !== 'function') {
                return;
            }

            leerDepositoPorCodigo(codigo, target, function (data) {
                if (data && data.id) {
                    enfocarPrimerArticuloMovStock();
                }
            });
        }, true);
    }

    /**
     * Destino de TRA: cualquier depósito de la empresa (o intercompany).
     * Origen / ingreso / egreso: sigue filtrando por usuario_deposito.
     */
    window.payloadExtraConsultaDeposito = function ($ctx) {
        if (msModoIntercompanyActivo() && esTransferencia()) {
            return { intercompany: 1, omitir_filtro_usuario: 1 };
        }

        if ($ctx && $ctx.length && $ctx.is('#tm_deposito_entrada') && esTransferencia()) {
            return { omitir_filtro_usuario: 1 };
        }

        return {};
    };

    window.payloadExtraConsultaUsuario = function () {
        if ($('#ms_panel_destinatario').is(':visible') && window._consultaUsuarioOmitirFiltroEmpresa) {
            return { omitir_filtro_empresa: 1 };
        }

        return {};
    };

    window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
        if (!$ctx || !$ctx.length) {
            return;
        }

        if ($ctx.is('#tm_deposito_entrada')) {
            actualizarPanelDestinatarioMs();
            // Enter / elección de depósito destino: foco al SKU (usuario destino es opcional)
            enfocarPrimerArticuloMovStock();
            return;
        }

        if (!$ctx.is('#tm_deposito_salida')) {
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

    $(document).on('click', '#ms_btn_intercompany', function () {
        var activo = msModoIntercompanyActivo();
        $('#ms_modo_intercompany').val(activo ? '0' : '1');
        actualizarUiModoIntercompanyMs();
        if ($('#consultadepositoModal').hasClass('show') && typeof buscar_datos_deposito === 'function') {
            buscar_datos_deposito($('#consultadeposito').val());
        }
    });

    $(function () {
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
        activarEnterDepositoSalidaEnfocaDestino();
        activarEnterDepositoDestinoEnfocaArticulo();
        activarEnterUsuarioDestinoValidaEnfocaArticulo();
        actualizarPanelesTransferencia();
        actualizarUiModoIntercompanyMs();
    });
})(jQuery);
