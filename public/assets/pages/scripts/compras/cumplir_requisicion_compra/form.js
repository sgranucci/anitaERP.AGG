(function ($) {
    'use strict';

    var cfg = window.cumpleRequisicionCompraConfig || {};
    var enviando = false;

    function escapeHtml(text) {
        return $('<div/>').text(text === null || text === undefined ? '' : text).html();
    }

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function formatCantidadPendiente(n) {
        if (Math.abs(n - Math.trunc(n)) < 1e-9) {
            return String(Math.trunc(n));
        }
        return n.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    }

    function actualizarToolbarLineas() {
        var tieneLineas = $('#tbody-lineas-cumple tr.fila-cumple-linea').length > 0;
        $('#btn-precargar-pendientes-cumple').prop('disabled', !tieneLineas);
    }

    function precargarCantidadesPendientes() {
        var cargadas = 0;
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
            var $input = $(this).find('.input-cantidad-entrega');
            var pendiente = num($input.data('pendiente'));
            if (pendiente > 0) {
                $input.val(formatCantidadPendiente(pendiente));
                cargadas++;
            } else {
                $input.val('');
            }
        });
        if (cargadas === 0) {
            alert('No hay cantidades pendientes para precargar.');
            return;
        }
        primerCantidadEntrega().trigger('focus').select();
    }

    function construirFila(linea, req, idx) {
        var pendiente = num(linea.pendiente);
        var articuloId = parseInt(linea.articulo_id, 10) || 0;
        var html = '<tr class="fila-cumple-linea" data-linea-id="' + linea.id + '" data-requisicion-id="' + (req ? req.id : '') + '" data-articulo-id="' + articuloId + '">';
        html += '<td>#' + escapeHtml(req ? req.numerorequisicion : '') + '</td>';
        if (cfg.puedeCambiarArticulo && typeof window.crcHtmlCeldaArticuloCumple === 'function') {
            html += window.crcHtmlCeldaArticuloCumple(linea, idx);
        } else {
            html += '<td>' + escapeHtml(linea.sku) + '</td>';
        }
        html += '<td class="descripcion-articulo-celda">' + escapeHtml(linea.descripcion) + '</td>';
        html += '<td class="align-middle text-right col-saldo-orig"><span class="ms-saldo-origen text-monospace small text-muted" title="Saldo en dep\u00f3sito origen">\u2014</span></td>';
        html += '<td class="text-right">' + num(linea.cantidad).toFixed(2) + '</td>';
        html += '<td class="text-right">' + num(linea.cantidadentregada).toFixed(2) + '</td>';
        html += '<td class="text-right pendiente-cell">' + pendiente.toFixed(2) + '</td>';
        html += '<td>';
        html += '<input type="hidden" name="lineas[' + idx + '][requisicion_articulo_id]" value="' + linea.id + '">';
        html += '<input type="number" step="0.01" min="0" name="lineas[' + idx + '][cantidad_entrega]" class="form-control form-control-sm input-cantidad-entrega text-right" data-pendiente="' + pendiente + '">';
        html += '</td>';
        html += '</tr>';
        return html;
    }

    function renderCabecera(req) {
        if (!req) {
            return;
        }
        $('#cabecera-estado').text(req.estado || '');
        $('#cabecera-fecha').text(req.fecha || '');
        $('#cabecera-empresa').text(req.empresa || '');
        $('#cabecera-centrocosto').text(req.centrocosto || '');
        $('#requisicion_display').val('#' + req.numerorequisicion + ' \u2014 id ' + req.id);
        $('#requisicion_id').val(req.id);
        $('#empresa_id').val(req.empresa_id || '');
        $('#bloque-cabecera-requisicion').removeClass('d-none');
        $('#btn-grabar-cumple').prop('disabled', false);
    }

    function renderLineas(lineas, req) {
        var html = '';
        (lineas || []).forEach(function (linea, idx) {
            html += construirFila(linea, req, idx);
        });
        $('#tbody-lineas-cumple').html(html);
        actualizarToolbarLineas();
        if (typeof window.crcRefrescarSaldosOrigen === 'function') {
            window.crcRefrescarSaldosOrigen();
        }
    }

    function buscarRequisiciones() {
        var q = $('#consultarequisicioncompraCumple').val();
        $.get(cfg.urlConsulta, { q: q }, function (resp) {
            var html = '';
            (resp.data || []).forEach(function (row) {
                html += '<tr>';
                html += '<td>' + row.id + '</td>';
                html += '<td>' + escapeHtml(row.numerorequisicion) + '</td>';
                html += '<td>' + escapeHtml(row.fecha) + '</td>';
                html += '<td>' + escapeHtml(row.estado) + '</td>';
                html += '<td>' + escapeHtml(row.empresa) + '</td>';
                html += '<td>' + escapeHtml(row.centrocosto) + '</td>';
                html += '<td><button type="button" class="btn btn-warning btn-sm elige-requisicion-cumple" data-id="' + row.id + '">Elegir</button></td>';
                html += '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="7" class="text-center text-muted">Sin resultados</td></tr>';
            }
            $('#datosrequisicioncompraCumple').html(html);
        });
    }

    function cargarRequisicion(id) {
        $.get(cfg.urlDatos + '/' + id, function (resp) {
            if (!resp.ok) {
                alert(resp.mensaje || 'No se pudo cargar la requisici\u00f3n');
                return;
            }
            renderCabecera(resp.requisicion);
            renderLineas(resp.lineas, resp.requisicion);
            $('#consultarequisicioncompraCumpleModal').modal('hide');
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.mensaje) || 'Error al cargar requisici\u00f3n');
        });
    }

    function validarAntesDeGrabar() {
        var origen = parseInt($('#deposito_origen_id').val(), 10) || 0;
        var destino = parseInt($('#deposito_destino_id').val(), 10) || 0;
        if (origen <= 0 || destino <= 0) {
            alert('Indique dep\u00f3sito de origen y de destino.');
            return false;
        }
        if (origen === destino) {
            alert('El dep\u00f3sito de origen y destino no pueden ser el mismo.');
            return false;
        }

        var tieneCantidad = false;
        var error = null;
        $('#tbody-lineas-cumple tr.fila-cumple-linea').each(function () {
            var $fila = $(this);
            var entrega = num($fila.find('.input-cantidad-entrega').val());
            var pendiente = num($fila.find('.input-cantidad-entrega').data('pendiente'));
            if (entrega > 0) {
                tieneCantidad = true;
            }
            if (entrega > pendiente + 0.0001) {
                error = 'La cantidad a cumplir supera el pendiente en alguna l\u00ednea.';
                return false;
            }
        });

        if (error) {
            alert(error);
            return false;
        }
        if (!tieneCantidad) {
            alert('Indique cantidad a cumplir mayor a cero en al menos una l\u00ednea.');
            return false;
        }
        return true;
    }

    function primerCantidadEntrega() {
        return $('#tbody-lineas-cumple tr.fila-cumple-linea .input-cantidad-entrega').filter(':visible').first();
    }

    $(function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        var $form = $('#form-cumple-requisicion-compra');

        // F1 sobre el campo código de depósito abre el modal de consulta de ese campo.
        $form.on('keydown', '.codigodeposito', function (e) {
            if (e.key === 'F1' || e.which === 112) {
                e.preventDefault();
                $(this).closest('.tm-deposito-campo').find('.consultadeposito').first().trigger('click');
            }
        });

        // Enter en depósito origen: valida (lo resuelve consulta.js) y salta al depósito destino.
        $form.on('keydown', '#deposito_origen_id_codigo', function (e) {
            if (e.key === 'Enter' || e.which === 13) {
                setTimeout(function () {
                    $('#deposito_destino_id_codigo').trigger('focus').select();
                }, 0);
            }
        });

        // Enter en depósito destino: salta a la primera cantidad a cumplir de la grilla.
        $form.on('keydown', '#deposito_destino_id_codigo', function (e) {
            if (e.key === 'Enter' || e.which === 13) {
                setTimeout(function () {
                    var $cant = primerCantidadEntrega();
                    if ($cant.length) {
                        $cant.trigger('focus').select();
                    }
                }, 0);
            }
        });

        $('#btn-consulta-requisicion-cumple').on('click', function () {
            $('#consultarequisicioncompraCumple').val('');
            buscarRequisiciones();
            $('#consultarequisicioncompraCumpleModal').modal('show');
        });

        $('#consultarequisicioncompraCumple').on('keyup', function (e) {
            if (e.key === 'Enter') {
                buscarRequisiciones();
            }
        });

        $(document).on('click', '.elige-requisicion-cumple', function () {
            cargarRequisicion($(this).data('id'));
        });

        $('#btn-precargar-pendientes-cumple').on('click', function () {
            precargarCantidadesPendientes();
        });

        $('#form-cumple-requisicion-compra').on('submit', function (e) {
            if (enviando) {
                return;
            }
            if (!validarAntesDeGrabar()) {
                e.preventDefault();
                return;
            }
            enviando = true;
            $('#btn-grabar-cumple').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Grabando\u2026');
        });

        actualizarToolbarLineas();
    });
}(jQuery));
