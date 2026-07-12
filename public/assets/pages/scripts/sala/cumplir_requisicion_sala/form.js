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
    var tecnicosCache = {};
    var indiceLinea = $('#tbody-lineas-cumple tr.fila-cumple-linea').length;

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
        var html = '<tr class="fila-cumple-linea" data-linea-id="' + linea.id + '" data-requisicion-id="' + reqId + '" data-destino="' + escapeHtml(destino) + '">';
        html += '<td>#' + escapeHtml(String(reqNro)) + '</td>';
        html += '<td>' + escapeHtml(linea.sku) + '</td>';
        html += '<td>' + escapeHtml(linea.descripcion) + '</td>';
        html += '<td class="text-right pendiente-cell">' + Number(linea.pendiente).toFixed(2) + '</td>';
        html += htmlDepositoInline(idx, linea.deposito_origen_id, linea.deposito_origen_codigo, linea.deposito_origen_nombre);
        html += htmlTecnicoCell(idx, tecnicos, destino);
        html += '<td>' + escapeHtml(linea.uid) + '</td>';
        html += '<td><input type="text" name="lineas[' + idx + '][numeroparte]" class="form-control form-control-sm input-npu-linea" maxlength="50" value="' + escapeHtml(linea.numeroparte || '') + '" placeholder="NPU"></td>';
        html += '<td>';
        html += '<input type="hidden" name="lineas[' + idx + '][requisicion_sala_articulo_id]" value="' + linea.id + '">';
        html += '<input type="hidden" name="lineas[' + idx + '][estadoparcial]" class="input-estadoparcial" value="">';
        html += '<input type="hidden" name="lineas[' + idx + '][estado_linea]" class="input-estado-linea" value="">';
        html += '<input type="hidden" name="lineas[' + idx + '][fecha_entrega]" class="input-fecha-entrega" value="">';
        html += '<input type="hidden" name="lineas[' + idx + '][numeroremito]" class="input-numeroremito" value="">';
        html += '<input type="hidden" name="lineas[' + idx + '][nombreresponsable]" class="input-nombreresponsable" value="">';
        html += '<input type="number" step="0.01" min="0" name="lineas[' + idx + '][cantidad_entrega]" class="form-control form-control-sm input-cantidad-entrega text-right" data-pendiente="' + linea.pendiente + '">';
        html += '</td>';
        html += '<td class="motivo-parcial-label small text-muted"></td>';
        html += '</tr>';
        return html;
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

    function mostrarModalAutorizacion($fila, entrega, pendiente, alAceptar) {
        if (entrega <= 0) {
            if (typeof alAceptar === 'function') {
                alAceptar();
            }
            return;
        }
        filaModalAuth = $fila;
        authModalAceptado = false;
        onAceptarAuthCallback = alAceptar || null;
        var estadoDefault = entrega >= pendiente ? 'E' : 'A';
        $('#modal-auth-estado').val(estadoDefault);
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

    function validarCantidadEnFila($fila, alListo) {
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
                if (!ok) {
                    if (typeof alListo === 'function') {
                        alListo(false);
                    }
                    return;
                }
                if (typeof alListo === 'function') {
                    alListo(true);
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

    function enviarFormularioCumple() {
        enviandoFormulario = true;
        grabarPendiente = false;
        grabarIndiceLinea = 0;
        $('#btn-grabar-cumple').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Grabando&hellip;');
        window.setTimeout(function () {
            document.getElementById('form-cumple-requisicion-sala').submit();
        }, 50);
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

        grabarPendiente = true;
        grabarIndiceLinea = 0;
        continuarGrabarDesdeIndice(0);
    }

    $(function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        if (cfg.modoNpu) {
            renderCabecera(null, $('#tbody-lineas-cumple tr').length === 0);
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

        $(document).on('blur', '.input-cantidad-entrega', function () {
            if (grabarPendiente) {
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
