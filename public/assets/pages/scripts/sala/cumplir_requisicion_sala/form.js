(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionSalaConfig || {};
    var filaModalParcial = null;
    var filaModalAuth = null;
    var pendienteAuthResolver = null;
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

    function construirFilaLinea(linea, req, tecnicos, idx) {
        var reqNro = req ? req.numerorequisicion : '';
        var reqId = req ? req.id : '';
        var html = '<tr class="fila-cumple-linea" data-linea-id="' + linea.id + '" data-requisicion-id="' + reqId + '">';
        html += '<td>#' + escapeHtml(String(reqNro)) + '</td>';
        html += '<td>' + escapeHtml(linea.sku) + '</td>';
        html += '<td>' + escapeHtml(linea.descripcion) + '</td>';
        html += '<td class="text-right pendiente-cell">' + Number(linea.pendiente).toFixed(2) + '</td>';
        html += htmlDepositoInline(idx, linea.deposito_origen_id, linea.deposito_origen_codigo, linea.deposito_origen_nombre);
        html += '<td><select name="lineas[' + idx + '][tecnico_laboratorio_id]" class="form-control form-control-sm select-tecnico">' + opcionesTecnicos(tecnicos) + '</select></td>';
        html += '<td>' + escapeHtml(linea.uid) + '</td>';
        html += '<td>' + escapeHtml(linea.numeroparte) + '</td>';
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

    function abrirModalAutorizacion($fila, entrega, pendiente) {
        filaModalAuth = $fila;
        var estadoDefault = entrega >= pendiente ? 'E' : 'A';
        if (entrega <= 0) {
            return $.Deferred().resolve().promise();
        }
        $('#modal-auth-estado').val(estadoDefault);
        $('#modal-auth-fecha').val(new Date().toISOString().slice(0, 10));
        $('#modal-auth-remito').val('');
        $('#modal-auth-responsable').val('');
        $('#modalAutorizacionLineaCumple').modal('show');
        return new $.Deferred(function (def) {
            pendienteAuthResolver = def;
        }).promise();
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

        $(document).on('change blur', '.input-cantidad-entrega', function () {
            var $input = $(this);
            var $fila = $input.closest('tr');
            var entrega = parseFloat($input.val()) || 0;
            var pendiente = parseFloat($input.data('pendiente')) || 0;

            if (entrega <= 0) {
                $fila.find('.input-estadoparcial').val('');
                $fila.find('.motivo-parcial-label').text('');
                return;
            }

            if (entrega > pendiente) {
                alert('La cantidad no puede superar el pendiente (' + pendiente + ')');
                $input.val('');
                return;
            }

            if (entrega < pendiente) {
                filaModalParcial = $fila;
                $('#modal-parcial-articulo').text($fila.find('td').eq(1).text() + ' \u2014 pendiente ' + pendiente + ', entrega ' + entrega);
                $('#modal-parcial-motivo').val('');
                $('#modalMotivoParcialCumple').modal('show');
            } else {
                $fila.find('.input-estadoparcial').val('');
                $fila.find('.motivo-parcial-label').text('');
                abrirModalAutorizacion($fila, entrega, pendiente);
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
            filaModalParcial.find('.input-estadoparcial').val(motivo);
            filaModalParcial.find('.motivo-parcial-label').text(nombreMotivoParcial(motivo));
            $('#modalMotivoParcialCumple').modal('hide');

            var entrega = parseFloat(filaModalParcial.find('.input-cantidad-entrega').val()) || 0;
            var pendiente = parseFloat(filaModalParcial.find('.input-cantidad-entrega').data('pendiente')) || 0;
            if (motivo !== '6' && entrega > 0) {
                abrirModalAutorizacion(filaModalParcial, entrega, pendiente);
            } else if (motivo === '6') {
                filaModalParcial.find('.input-estado-linea').val('C');
            }
            filaModalParcial = null;
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
            $('#modalAutorizacionLineaCumple').modal('hide');
            if (pendienteAuthResolver) {
                pendienteAuthResolver.resolve();
                pendienteAuthResolver = null;
            }
            filaModalAuth = null;
        });

        $('#modalAutorizacionLineaCumple').on('hidden.bs.modal', function () {
            if (pendienteAuthResolver) {
                pendienteAuthResolver.reject();
                pendienteAuthResolver = null;
            }
        });

        $('#form-cumple-requisicion-sala').on('submit', function (e) {
            var tieneLinea = false;
            $('.fila-cumple-linea').each(function () {
                var entrega = parseFloat($(this).find('.input-cantidad-entrega').val()) || 0;
                var motivo = $(this).find('.input-estadoparcial').val();
                if (entrega > 0 || motivo === '6') {
                    tieneLinea = true;
                }
            });
            if (!tieneLinea) {
                e.preventDefault();
                alert('Indique cantidades a cumplir o cierre de \u00edtem en al menos una l\u00ednea.');
            }
        });
    });
}(jQuery));
