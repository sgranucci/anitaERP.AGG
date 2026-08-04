(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionSalaConfig || {};
    var filaModalParcial = null;
    var filaModalAuth = null;
    var authModalAceptado = false;
    var enviandoFormulario = false;
    var grabarPendiente = false;
    var grabarIndiceLinea = 0;
    var onAceptarAuthCallback = null;
    var onAceptarParcialCallback = null;
    var colaValidacionFila = [];
    var validacionFilaActiva = false;
    var tecnicosCache = {};
    var indiceLinea = $('#tbody-lineas-cumple tr.fila-cumple-linea').length;

    function crsModoIntercompanyActivo() {
        return $('#crs_modo_intercompany').val() === '1';
    }

    function actualizarUiModoIntercompanyCrs() {
        var activo = crsModoIntercompanyActivo();
        var $btn = $('#crs_btn_intercompany');
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
     * Con modo intercompany: todos los depósitos de todas las empresas
     * (mismo payload que movimientos de stock / transferencia mercadería).
     */
    window.payloadExtraConsultaDeposito = function () {
        if (crsModoIntercompanyActivo()) {
            return { intercompany: 1, omitir_filtro_usuario: 1 };
        }

        return {};
    };

    function escapeHtml(text) {
        return $('<div/>').text(text || '').html();
    }

    function nombreMotivoParcial(valor) {
        var lista = cfg.estadoParcialEnum || [];
        for (var i = 0; i < lista.length; i++) {
            if (String(lista[i].valor) === String(valor)) {
                return lista[i].nombre;
            }
        }
        return valor;
    }

    function reindexLineas() {
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function (idx) {
            var $fila = $(this);
            $fila.find('[name^="lineas["]').each(function () {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/lineas\[\d+\]/, 'lineas[' + idx + ']'));
                }
            });
        });
        indiceLinea = $('#tbody-lineas-cumple tr.fila-cumple-linea').length;
        actualizarResumenNpu();
    }

    function actualizarResumenNpu() {
        var n = $('#tbody-lineas-cumple tr.fila-cumple-linea').length;
        var reqs = {};
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
            reqs[$(this).data('requisicion-id')] = true;
        });
        var numReqs = Object.keys(reqs).length;
        var texto = n + ' l\u00ednea' + (n === 1 ? '' : 's');
        if (numReqs > 1) {
            texto += ' (' + numReqs + ' requisiciones)';
        }
        $('#badge-requisiciones-npu').text(texto);
        if (n > 0) {
            $('#btn-grabar-cumple').prop('disabled', false);
            $('#bloque-cabecera-requisicion').removeClass('d-none');
        }
    }

    function opcionesTecnicos(tecnicos) {
        var html = '<option value="">Seleccione\u2026</option>';
        (tecnicos || []).forEach(function (t) {
            html += '<option value="' + t.id + '">' + escapeHtml(t.nombre) + '</option>';
        });
        return html;
    }

    function cacheTecnicos(empresaId, tecnicos) {
        if (empresaId) {
            tecnicosCache[empresaId] = tecnicos;
        }
    }

    function tecnicosParaEmpresa(empresaId, tecnicos) {
        if (tecnicos && tecnicos.length) {
            cacheTecnicos(empresaId, tecnicos);
            return tecnicos;
        }
        return tecnicosCache[empresaId] || [];
    }

    function htmlDepositoInline(idx, depositoId, codigo, nombre) {
        var html = '<td class="tm-deposito-campo">';
        html += '<input type="hidden" class="deposito_id" name="lineas[' + idx + '][deposito_origen_id]" value="' + (depositoId || cfg.depositoLabId) + '">';
        html += '<div class="input-group input-group-sm">';
        html += '<input type="text" class="form-control form-control-sm codigodeposito" placeholder="C\u00f3d." maxlength="20" value="' + escapeHtml(codigo || cfg.depositoLabCodigo || '') + '">';
        html += '<input type="text" class="form-control form-control-sm descripciondeposito" readonly placeholder="Dep\u00f3sito" value="' + escapeHtml(nombre || cfg.depositoLabNombre || '') + '">';
        html += '<div class="input-group-append">';
        html += '<button type="button" class="btn btn-outline-secondary btn-sm consultadeposito" title="Consultar dep\u00f3sito"><i class="fa fa-search"></i></button>';
        html += '</div></div></td>';
        return html;
    }

    function esLineaReparacion(destino) {
        return String(destino || '') === 'R';
    }

    function htmlTecnicoCell(idx, tecnicos, destino) {
        if (!esLineaReparacion(destino)) {
            return '<td class="text-muted small">No aplica<input type="hidden" name="lineas[' + idx + '][tecnico_laboratorio_id]" value=""></td>';
        }
        return '<td><select name="lineas[' + idx + '][tecnico_laboratorio_id]" class="form-control form-control-sm select-tecnico select-tecnico-reparacion">' + opcionesTecnicos(tecnicos) + '</select></td>';
    }

    function construirFilaLinea(linea, req, tecnicos, idx) {
        var reqNro = req ? req.numerorequisicion : '';
        var reqId = req ? req.id : '';
        var destino = linea.destino || '';
        var html = '<tr class="fila-cumple-linea" data-linea-id="' + linea.id + '" data-articulo-id="' + (linea.articulo_id || '') + '" data-requisicion-id="' + reqId + '" data-destino="' + escapeHtml(destino) + '">';
        html += '<td>#' + escapeHtml(String(reqNro)) + '</td>';
        html += '<td>' + escapeHtml(linea.sku) + '</td>';
        html += '<td>' + escapeHtml(linea.descripcion) + '</td>';
        html += '<td class="text-right pendiente-cell">' + Number(linea.pendiente).toFixed(2) + '</td>';
        html += '<td class="align-middle text-right col-saldo-orig"><span class="ms-saldo-origen text-monospace small" title="Saldo en dep\u00f3sito origen">\u2014</span></td>';
        html += htmlDepositoInline(idx, linea.deposito_origen_id, linea.deposito_origen_codigo, linea.deposito_origen_nombre);
        html += htmlTecnicoCell(idx, tecnicos, destino);
        html += '<td>' + escapeHtml(linea.uid) + '</td>';
        html += '<td><input type="text" name="lineas[' + idx + '][numeroparte]" class="form-control form-control-sm input-npu-linea" maxlength="50" value="' + escapeHtml(linea.numeroparte || '') + '" placeholder="NPU"></td>';
        html += '<td>';
        html += '<input type="hidden" name="lineas[' + idx + '][requisicion_sala_articulo_id]" value="' + linea.id + '">';
        html += '<input type="hidden" name="lineas[' + idx + '][estadoparcial]" class="input-estadoparcial" value="' + escapeHtml(linea.estadoparcial || '') + '">';
        html += '<input type="hidden" name="lineas[' + idx + '][estado_linea]" class="input-estado-linea" value="' + escapeHtml(linea.estado_linea || '') + '">';
        html += '<input type="hidden" name="lineas[' + idx + '][fecha_entrega]" class="input-fecha-entrega" value="' + escapeHtml(linea.fecha_entrega || '') + '">';
        html += '<input type="hidden" name="lineas[' + idx + '][numeroremito]" class="input-numeroremito" value="' + escapeHtml(linea.numeroremito || '') + '">';
        html += '<input type="hidden" name="lineas[' + idx + '][nombreresponsable]" class="input-nombreresponsable" value="' + escapeHtml(linea.nombreresponsable || '') + '">';
        html += '<input type="number" step="0.01" min="0" name="lineas[' + idx + '][cantidad_entrega]" class="form-control form-control-sm input-cantidad-entrega text-right" data-pendiente="' + linea.pendiente + '" value="' + escapeHtml(linea.cantidad_entrega != null && linea.cantidad_entrega !== '' ? String(linea.cantidad_entrega) : '') + '">';
        html += '</td>';
        html += '<td class="motivo-parcial-label small text-muted">' + escapeHtml(linea.estadoparcial ? nombreMotivoParcial(linea.estadoparcial) : '') + '</td>';
        html += '</tr>';
        return html;
    }

    function refrescarSaldosGrilla(avisar) {
        if (typeof window.crsRefrescarSaldosOrigen === 'function') {
            window.crsRefrescarSaldosOrigen({ avisar: !!avisar });
        }
    }

    function cargarSaldoFilaNueva($fila, avisar) {
        if (typeof window.crsCargarSaldoFila === 'function') {
            window.crsCargarSaldoFila($fila, !!avisar);
        }
    }

    function renderLineas(lineas, tecnicos, req) {
        var html = '';
        (lineas || []).forEach(function (linea, idx) {
            html += construirFilaLinea(linea, req, tecnicos, idx);
        });
        $('#tbody-lineas-cumple').html(html);
        indiceLinea = (lineas || []).length;
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
        actualizarResumenNpu();
        refrescarSaldosGrilla(true);
    }

    function agregarLinea(linea, req, tecnicos) {
        if ($('#tbody-lineas-cumple tr[data-linea-id="' + linea.id + '"]').length) {
            alert('El NPU ya est\u00e1 cargado en la grilla.');
            return false;
        }
        var lista = tecnicosParaEmpresa(req.empresa_id, tecnicos);
        $('#tbody-lineas-cumple').append(construirFilaLinea(linea, req, lista, indiceLinea));
        indiceLinea++;
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }
        actualizarResumenNpu();
        var $fila = $('#tbody-lineas-cumple tr.fila-cumple-linea').last();
        cargarSaldoFilaNueva($fila, true);
        return true;
    }

    function renderCabecera(req, multi) {
        if (multi) {
            $('#cabecera-estado').text('Varias');
            $('#cabecera-fecha').text('\u2014');
            $('#cabecera-fecha-entrega').text('\u2014');
            $('#cabecera-empresa').text('\u2014');
            $('#cabecera-deposito').text('\u2014');
            $('#cabecera-centrocosto').text('\u2014');
            $('#requisicion_display').val('Carga por NPU');
            $('#requisicion_sala_id').val('');
        } else if (req) {
            $('#cabecera-estado').text(req.estado || '');
            $('#cabecera-fecha').text(req.fecha || '');
            $('#cabecera-fecha-entrega').text(req.fecha_entrega || '');
            $('#cabecera-empresa').text(req.empresa || '');
            $('#cabecera-deposito').text(req.deposito || '');
            $('#cabecera-centrocosto').text(req.centrocosto || '');
            $('#requisicion_display').val('#' + req.numerorequisicion + ' \u2014 id ' + req.id);
            $('#requisicion_sala_id').val(req.id);
        }
        $('#bloque-cabecera-requisicion').removeClass('d-none');
        $('#btn-grabar-cumple').prop('disabled', false);
    }

    function buscarRequisiciones() {
        var q = $('#consultarequisicionsalaCumple').val();
        $.get(cfg.urlConsulta, { q: q }, function (resp) {
            var html = '';
            (resp.data || []).forEach(function (row) {
                html += '<tr>';
                html += '<td>' + row.id + '</td>';
                html += '<td>' + escapeHtml(row.numerorequisicion) + '</td>';
                html += '<td>' + escapeHtml(row.fecha) + '</td>';
                html += '<td>' + escapeHtml(row.estado) + '</td>';
                html += '<td>' + escapeHtml(row.empresa) + '</td>';
                html += '<td>' + escapeHtml(row.deposito) + '</td>';
                html += '<td>' + escapeHtml(row.centrocosto) + '</td>';
                html += '<td><button type="button" class="btn btn-warning btn-sm elige-requisicion-cumple" data-id="' + row.id + '">Elegir</button></td>';
                html += '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
            }
            $('#datosrequisicionsalaCumple').html(html);
        });
    }

    function cargarRequisicion(id) {
        $.get(cfg.urlDatos + '/' + id, function (resp) {
            if (!resp.ok) {
                alert(resp.mensaje || 'No se pudo cargar la requisici\u00f3n');
                return;
            }
            renderCabecera(resp.requisicion, false);
            $('#empresa_id').val(resp.requisicion.empresa_id || '');
            cacheTecnicos(resp.requisicion.empresa_id, resp.tecnicos);
            renderLineas(resp.lineas, resp.tecnicos, resp.requisicion);
            $('#consultarequisicionsalaCumpleModal').modal('hide');
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al cargar requisici\u00f3n');
        });
    }

    function cargarPorNpu(npu) {
        npu = $.trim(npu);
        if (!npu) {
            return;
        }
        $.get(cfg.urlConsultaNpu, { npu: npu }, function (resp) {
            if (!resp.ok) {
                alert(resp.mensaje || 'NPU no encontrado');
                return;
            }
            cacheTecnicos(resp.requisicion.empresa_id, resp.tecnicos);
            var agregada = agregarLinea(resp.linea, resp.requisicion, resp.tecnicos);
            if (!agregada) {
                return;
            }
            var numReqs = {};
            $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
                numReqs[$(this).data('requisicion-id')] = true;
            });
            renderCabecera(resp.requisicion, Object.keys(numReqs).length > 1);
            if (Object.keys(numReqs).length === 1) {
                $('#empresa_id').val(resp.requisicion.empresa_id || '');
            } else {
                $('#empresa_id').val('');
            }
            $('#input-npu-cumple').val('').focus();
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al consultar NPU');
            $('#input-npu-cumple').select();
        });
    }

    function datosLinea($fila) {
        var entrega = parseFloat($fila.find('.input-cantidad-entrega').val()) || 0;
        var pendiente = parseFloat($fila.find('.input-cantidad-entrega').data('pendiente')) || 0;
        return {
            entrega: entrega,
            pendiente: pendiente,
            motivo: String($fila.find('.input-estadoparcial').val() || ''),
            estadoLinea: String($fila.find('.input-estado-linea').val() || ''),
        };
    }

    function limpiarEstadoLinea($fila) {
        $fila.find('.input-estadoparcial').val('');
        $fila.find('.motivo-parcial-label').text('');
        $fila.find('.input-estado-linea').val('');
        $fila.find('.input-fecha-entrega').val('');
        $fila.find('.input-numeroremito').val('');
        $fila.find('.input-nombreresponsable').val('');
    }

    function hayModalCumpleAbierto() {
        return $('#modalMotivoParcialCumple').hasClass('show') ||
            $('#modalAutorizacionLineaCumple').hasClass('show');
    }

    function estadoDefaultPorDestino($fila, entrega, pendiente) {
        var destino = String($fila.data('destino') || '');
        var parcial = entrega < pendiente;
        if (destino === 'R') {
            return parcial ? 'A' : 'P';
        }

        return parcial ? 'A' : 'E';
    }

    function finalizarValidacionFila(ok, alListo) {
        if (typeof alListo === 'function') {
            alListo(ok);
        }
        validacionFilaActiva = false;
        procesarColaValidacionFila();
    }

    function procesarColaValidacionFila() {
        if (validacionFilaActiva || hayModalCumpleAbierto() || colaValidacionFila.length === 0) {
            return;
        }

        var item = colaValidacionFila.shift();
        validacionFilaActiva = true;
        ejecutarValidacionCantidadEnFila(item.$fila, function (ok) {
            finalizarValidacionFila(ok, item.alListo);
        });
    }

    function validarCantidadEnFila($fila, alListo) {
        colaValidacionFila.push({
            $fila: $fila,
            alListo: typeof alListo === 'function' ? alListo : null,
        });
        procesarColaValidacionFila();
    }

    function mostrarModalAutorizacion($fila, entrega, pendiente, alAceptar) {
        if (entrega <= 0) {
            if (typeof alAceptar === 'function') {
                alAceptar(true);
            }
            return;
        }
        filaModalAuth = $fila;
        authModalAceptado = false;
        onAceptarAuthCallback = alAceptar || null;
        $('#modal-auth-estado').val(estadoDefaultPorDestino($fila, entrega, pendiente));
        $('#modal-auth-fecha').val(new Date().toISOString().slice(0, 10));
        $('#modal-auth-remito').val('');
        $('#modal-auth-responsable').val('');
        $('#modalAutorizacionLineaCumple').modal('show');
    }

    function mostrarModalParcial($fila, entrega, pendiente, alAceptar) {
        filaModalParcial = $fila;
        onAceptarParcialCallback = alAceptar || null;
        $('#modal-parcial-articulo').text($fila.find('td').eq(1).text() + ' \u2014 pendiente ' + pendiente + ', entrega ' + entrega);
        $('#modal-parcial-motivo').val('');
        $('#modalMotivoParcialCumple').modal('show');
    }

    function ejecutarValidacionCantidadEnFila($fila, alListo) {
        var datos = datosLinea($fila);
        var entrega = datos.entrega;
        var pendiente = datos.pendiente;

        if (entrega <= 0) {
            if (datos.motivo === '6') {
                if (!datos.estadoLinea) {
                    $fila.find('.input-estado-linea').val('C');
                }
                if (typeof alListo === 'function') {
                    alListo(true);
                }
                return;
            }
            limpiarEstadoLinea($fila);
            if (typeof alListo === 'function') {
                alListo(true);
            }
            return;
        }

        if (entrega > pendiente) {
            alert('La cantidad no puede superar el pendiente (' + pendiente + ')');
            $fila.find('.input-cantidad-entrega').val('');
            if (typeof alListo === 'function') {
                alListo(false);
            }
            return;
        }

        var saldoAttr = $fila.attr('data-saldo-origen');
        var controlaStock = $fila.attr('data-controla-stock') !== '0';
        if (controlaStock && saldoAttr !== '' && saldoAttr !== undefined) {
            var saldo = Number(saldoAttr);
            if (!Number.isNaN(saldo) && saldo + 1e-9 < entrega) {
                alert(
                    'La cantidad supera el saldo disponible en el dep\u00f3sito origen. Saldo: '
                    + saldo
                    + ', solicitado: '
                    + entrega
                    + '.'
                );
                $fila.addClass('fila-saldo-insuficiente');
                if (typeof alListo === 'function') {
                    alListo(false);
                }
                return;
            }
        }

        if (entrega < pendiente) {
            if (datos.motivo === '6') {
                $fila.find('.input-estado-linea').val('C');
                if (typeof alListo === 'function') {
                    alListo(true);
                }
                return;
            }
            if (datos.motivo && datos.estadoLinea) {
                if (typeof alListo === 'function') {
                    alListo(true);
                }
                return;
            }
            if (datos.motivo) {
                mostrarModalAutorizacion($fila, entrega, pendiente, function (ok) {
                    if (typeof alListo === 'function') {
                        alListo(ok !== false);
                    }
                });
                return;
            }
            mostrarModalParcial($fila, entrega, pendiente, function (ok) {
                if (typeof alListo === 'function') {
                    alListo(ok !== false);
                }
            });
            return;
        }

        $fila.find('.input-estadoparcial').val('');
        $fila.find('.motivo-parcial-label').text('');

        if (datos.estadoLinea) {
            if (typeof alListo === 'function') {
                alListo(true);
            }
            return;
        }

        mostrarModalAutorizacion($fila, entrega, pendiente, function (ok) {
            if (typeof alListo === 'function') {
                alListo(ok !== false);
            }
        });
    }

    function lineaParticipaEnGrabado($fila) {
        var datos = datosLinea($fila);
        return datos.entrega > 0 || datos.motivo === '6';
    }

    function lineaListaParaGrabar($fila) {
        var datos = datosLinea($fila);
        if (!lineaParticipaEnGrabado($fila)) {
            return true;
        }
        if (datos.entrega > datos.pendiente) {
            return false;
        }
        if (datos.entrega < datos.pendiente && !datos.motivo) {
            return false;
        }
        if (datos.motivo === '6') {
            return datos.estadoLinea !== '';
        }
        if (datos.entrega > 0 && !datos.estadoLinea) {
            return false;
        }
        return true;
    }

    function aplicarValoresPostEnFila($fila, linea) {
        if (!$fila || !$fila.length || !linea) {
            return;
        }
        if (linea.tecnico_laboratorio_id) {
            $fila.find('.select-tecnico').val(String(linea.tecnico_laboratorio_id));
        }
        if (linea.deposito_origen_id) {
            $fila.find('.deposito_id').val(linea.deposito_origen_id);
            $fila.find('.codigodeposito').val(linea.deposito_origen_codigo || '');
            $fila.find('.descripciondeposito').val(linea.deposito_origen_nombre || '');
        }
        if (linea.cantidad_entrega !== undefined && linea.cantidad_entrega !== null && linea.cantidad_entrega !== '') {
            $fila.find('.input-cantidad-entrega').val(linea.cantidad_entrega);
        }
        if (linea.numeroparte !== undefined) {
            $fila.find('.input-npu-linea').val(linea.numeroparte || '');
        }
        $fila.find('.input-estadoparcial').val(linea.estadoparcial || '');
        $fila.find('.input-estado-linea').val(linea.estado_linea || '');
        $fila.find('.input-fecha-entrega').val(linea.fecha_entrega || '');
        $fila.find('.input-numeroremito').val(linea.numeroremito || '');
        $fila.find('.input-nombreresponsable').val(linea.nombreresponsable || '');
        $fila.find('.motivo-parcial-label').text(linea.estadoparcial ? nombreMotivoParcial(linea.estadoparcial) : '');
    }

    function restaurarLineasDesdeOld() {
        var olds = cfg.oldLineas || [];
        if (!olds.length) {
            return;
        }

        var porEmpresa = cfg.tecnicosPorEmpresa || {};
        Object.keys(porEmpresa).forEach(function (empresaId) {
            cacheTecnicos(empresaId, porEmpresa[empresaId]);
        });

        if (cfg.modoNpu && $('#tbody-lineas-cumple tr.fila-cumple-linea').length === 0) {
            olds.forEach(function (oldLinea) {
                var req = oldLinea.requisicion || null;
                var empresaId = req ? req.empresa_id : null;
                var tecnicos = tecnicosParaEmpresa(empresaId, porEmpresa[empresaId] || []);
                $('#tbody-lineas-cumple').append(construirFilaLinea(oldLinea, req, tecnicos, indiceLinea));
                var $fila = $('#tbody-lineas-cumple tr.fila-cumple-linea').last();
                aplicarValoresPostEnFila($fila, oldLinea);
                indiceLinea++;
            });
            if (typeof activa_eventos_consultadeposito === 'function') {
                activa_eventos_consultadeposito();
            }
            var numReqs = {};
            $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
                numReqs[$(this).data('requisicion-id')] = true;
            });
            var multi = Object.keys(numReqs).length > 1;
            var primera = olds[0] && olds[0].requisicion ? olds[0].requisicion : null;
            renderCabecera(primera, multi);
            if (!multi && primera) {
                $('#empresa_id').val(primera.empresa_id || '');
            } else {
                $('#empresa_id').val('');
            }
            actualizarResumenNpu();
            refrescarSaldosGrilla(true);
            return;
        }

        var byId = {};
        olds.forEach(function (oldLinea) {
            byId[String(oldLinea.id)] = oldLinea;
        });
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
            var $fila = $(this);
            aplicarValoresPostEnFila($fila, byId[String($fila.data('linea-id'))]);
        });
        refrescarSaldosGrilla(true);
    }

    function mostrarErrorCumple(mensaje) {
        var $box = $('#cumple-alerta-error');
        if (!$box.length) {
            alert(mensaje || 'Error al grabar cumplimiento.');
            return;
        }
        $box.find('.cumple-alerta-error-texto').text(mensaje || 'Error al grabar cumplimiento.');
        $box.removeClass('d-none');
        var top = $box.offset() ? $box.offset().top - 80 : 0;
        $('html, body').animate({ scrollTop: Math.max(top, 0) }, 200);
    }

    function ocultarErrorCumple() {
        $('#cumple-alerta-error').addClass('d-none');
    }

    function restaurarBotonGrabar() {
        enviandoFormulario = false;
        grabarPendiente = false;
        $('#btn-grabar-cumple').prop('disabled', false).html('<i class="fa fa-save"></i> Grabar cumplimiento');
    }

    function enviarFormularioCumple() {
        enviandoFormulario = true;
        grabarPendiente = false;
        grabarIndiceLinea = 0;
        ocultarErrorCumple();
        $('#btn-grabar-cumple').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Grabando&hellip;');

        var $form = $('#form-cumple-requisicion-sala');
        $.ajax({
            url: cfg.urlGrabar || $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        }).done(function (resp) {
            if (resp && resp.ok && resp.redirect) {
                window.location.href = resp.redirect;
                return;
            }
            restaurarBotonGrabar();
            mostrarErrorCumple((resp && resp.mensaje) || 'Error al grabar cumplimiento.');
        }).fail(function (xhr) {
            restaurarBotonGrabar();
            var mensaje = 'Error al grabar cumplimiento.';
            if (xhr.responseJSON && xhr.responseJSON.mensaje) {
                mensaje = xhr.responseJSON.mensaje;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                mensaje = xhr.responseJSON.message;
            }
            mostrarErrorCumple(mensaje);
        });
    }

    function continuarGrabarDesdeIndice(indice) {
        var $filas = $('#tbody-lineas-cumple tr.fila-cumple-linea');
        if (indice >= $filas.length) {
            enviarFormularioCumple();
            return;
        }

        var $fila = $filas.eq(indice);
        if (!lineaParticipaEnGrabado($fila)) {
            continuarGrabarDesdeIndice(indice + 1);
            return;
        }

        grabarIndiceLinea = indice;
        validarCantidadEnFila($fila, function (ok) {
            if (!ok) {
                grabarPendiente = false;
                $('#btn-grabar-cumple').prop('disabled', false);
                return;
            }
            continuarGrabarDesdeIndice(indice + 1);
        });
    }

    function validarFilasIncompletasAntesDeGrabar(alContinuar) {
        var $incompleta = null;
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
            var $fila = $(this);
            if (lineaParticipaEnGrabado($fila) && !lineaListaParaGrabar($fila)) {
                $incompleta = $fila;
                return false;
            }
        });
        if (!$incompleta || !$incompleta.length) {
            alContinuar();
            return;
        }
        validarCantidadEnFila($incompleta, function (ok) {
            if (!ok) {
                return;
            }
            validarFilasIncompletasAntesDeGrabar(alContinuar);
        });
    }

    function ejecutarSecuenciaGrabar() {
        grabarPendiente = true;
        grabarIndiceLinea = 0;
        continuarGrabarDesdeIndice(0);
    }

    function esperarValidacionesYGrabar() {
        if (validacionFilaActiva || hayModalCumpleAbierto() || colaValidacionFila.length > 0) {
            window.setTimeout(esperarValidacionesYGrabar, 80);
            return;
        }
        validarFilasIncompletasAntesDeGrabar(function () {
            ejecutarSecuenciaGrabar();
        });
    }

    function iniciarGrabar() {
        if (enviandoFormulario) {
            return;
        }

        var tieneLinea = false;
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
            if (lineaParticipaEnGrabado($(this))) {
                tieneLinea = true;
            }
        });
        if (!tieneLinea) {
            alert('Indique cantidades a cumplir o cierre de \u00edtem en al menos una l\u00ednea.');
            return;
        }

        if (typeof window.crsValidarSaldosAntesDeGrabar === 'function'
            && !window.crsValidarSaldosAntesDeGrabar()) {
            return;
        }

        esperarValidacionesYGrabar();
    }

    $(function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        actualizarUiModoIntercompanyCrs();

        $(document).on('click', '#crs_btn_intercompany', function () {
            var activo = crsModoIntercompanyActivo();
            $('#crs_modo_intercompany').val(activo ? '0' : '1');
            actualizarUiModoIntercompanyCrs();
            if ($('#consultadepositoModal').hasClass('show') && typeof buscar_datos_deposito === 'function') {
                buscar_datos_deposito($('#consultadeposito').val());
            }
        });

        if (cfg.modoNpu) {
            if (!(cfg.oldLineas || []).length) {
                renderCabecera(null, $('#tbody-lineas-cumple tr').length === 0);
                setTimeout(function () {
                    $('#input-npu-cumple').focus();
                }, 300);
            }
        }

        restaurarLineasDesdeOld();

        if (cfg.modoNpu && (cfg.oldLineas || []).length) {
            setTimeout(function () {
                $('#input-npu-cumple').focus();
            }, 300);
        }

        $('#btn-consulta-requisicion-cumple').on('click', function () {
            $('#consultarequisicionsalaCumple').val('');
            buscarRequisiciones();
            $('#consultarequisicionsalaCumpleModal').modal('show');
        });

        $('#consultarequisicionsalaCumple').on('keyup', function (e) {
            if (e.key === 'Enter') {
                buscarRequisiciones();
            }
        });

        $(document).on('click', '.elige-requisicion-cumple', function () {
            cargarRequisicion($(this).data('id'));
        });

        $('#input-npu-cumple').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                cargarPorNpu($(this).val());
            }
        });

        $(document).on('click', '.btn-quitar-linea-cumple', function () {
            $(this).closest('tr').remove();
            reindexLineas();
            var numReqs = {};
            $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
                numReqs[$(this).data('requisicion-id')] = true;
            });
            if ($('#tbody-lineas-cumple tr').length === 0) {
                $('#btn-grabar-cumple').prop('disabled', true);
            }
            renderCabecera(null, Object.keys(numReqs).length > 1);
        });

        $(document).on('blur focusout', '.input-cantidad-entrega', function (e) {
            if (grabarPendiente) {
                return;
            }
            if (e.type === 'focusout' && e.relatedTarget && $(e.relatedTarget).closest('#btn-grabar-cumple').length) {
                return;
            }
            validarCantidadEnFila($(this).closest('tr'));
        });

        $(document).on('keydown', '.input-cantidad-entrega', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                if (grabarPendiente) {
                    return;
                }
                validarCantidadEnFila($(this).closest('tr'));
            }
        });

        $('#btn-aceptar-motivo-parcial').on('click', function () {
            var motivo = $('#modal-parcial-motivo').val();
            if (!motivo) {
                alert('Seleccione un motivo');
                return;
            }
            if (!filaModalParcial) {
                return;
            }

            var $fila = filaModalParcial;
            var entrega = parseFloat($fila.find('.input-cantidad-entrega').val()) || 0;
            var pendiente = parseFloat($fila.find('.input-cantidad-entrega').data('pendiente')) || 0;
            var callback = onAceptarParcialCallback;
            onAceptarParcialCallback = null;
            filaModalParcial = null;

            $fila.find('.input-estadoparcial').val(motivo);
            $fila.find('.motivo-parcial-label').text(nombreMotivoParcial(motivo));

            $('#modalMotivoParcialCumple').one('hidden.bs.modal', function () {
                if (motivo === '6') {
                    $fila.find('.input-estado-linea').val('C');
                    if (typeof callback === 'function') {
                        callback(true);
                    }
                    return;
                }
                if (entrega > 0) {
                    mostrarModalAutorizacion($fila, entrega, pendiente, function (ok) {
                        if (typeof callback === 'function') {
                            callback(ok !== false);
                        }
                    });
                    return;
                }
                if (typeof callback === 'function') {
                    callback(true);
                }
            });
            $('#modalMotivoParcialCumple').modal('hide');
        });

        $('#modalMotivoParcialCumple').on('hidden.bs.modal', function () {
            if (filaModalParcial) {
                filaModalParcial = null;
                if (typeof onAceptarParcialCallback === 'function') {
                    var cb = onAceptarParcialCallback;
                    onAceptarParcialCallback = null;
                    cb(false);
                }
            }
        });

        $('#btn-aceptar-autorizacion-linea').on('click', function () {
            if (!filaModalAuth) {
                return;
            }
            var estado = $('#modal-auth-estado').val();
            if (estado === 'E' && (parseFloat(filaModalAuth.find('.input-cantidad-entrega').val()) || 0) <= 0) {
                alert('No puede marcar como entregado con cantidad 0');
                return;
            }

            filaModalAuth.find('.input-estado-linea').val(estado);
            filaModalAuth.find('.input-fecha-entrega').val($('#modal-auth-fecha').val());
            filaModalAuth.find('.input-numeroremito').val($('#modal-auth-remito').val());
            filaModalAuth.find('.input-nombreresponsable').val($('#modal-auth-responsable').val());

            authModalAceptado = true;
            var callback = onAceptarAuthCallback;
            onAceptarAuthCallback = null;
            filaModalAuth = null;

            $('#modalAutorizacionLineaCumple').one('hidden.bs.modal', function () {
                if (typeof callback === 'function') {
                    callback(true);
                }
            });
            $('#modalAutorizacionLineaCumple').modal('hide');
        });

        $('#modalAutorizacionLineaCumple').on('hidden.bs.modal', function () {
            if (!authModalAceptado) {
                if (typeof onAceptarAuthCallback === 'function') {
                    var cb = onAceptarAuthCallback;
                    onAceptarAuthCallback = null;
                    cb(false);
                }
            }
            authModalAceptado = false;
            filaModalAuth = null;
        });

        $('#form-cumple-requisicion-sala').on('submit', function (e) {
            if (enviandoFormulario) {
                return;
            }
            e.preventDefault();
            iniciarGrabar();
        });
    });
}(jQuery));

window.cumpleRequisicionSalaFormV = 2;
