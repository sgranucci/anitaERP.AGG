(function ($) {
    'use strict';

    var pedido_combinacion_ids = [];
    var ordentrabajo_ids = [];
    var filasFacturaPicking = [];
    var nombrecliente = '';
    var descuentoCliente = 0;
    var offFactura = 0;

    function idsSeleccionados() {
        var ids = [];
        $('#tabla-picking-pedido .check-picking-linea:checked').each(function () {
            ids.push(parseInt($(this).val(), 10) || 0);
        });
        return ids.filter(function (id) { return id > 0; });
    }

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('picking-factura-overlay');
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('picking-factura-titulo');
            if (t) t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('picking-factura-overlay');
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function extraerUrlImpresionSesion(data) {
        var items = Array.isArray(data) ? data : [data];
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (item && typeof item === 'object' && item.impresion_url) {
                var url = String(item.impresion_url).trim();
                if (url !== '') {
                    return url;
                }
            }
        }
        return null;
    }

    function pathRetornoPicking() {
        try {
            return window.location.pathname + window.location.search;
        } catch (e) {
            return '';
        }
    }

    function leePuntoVenta(puntoventa_id) {
        if (!puntoventa_id) return;
        $.get(carpetaBase + '/ventas/chequeapuntoventa/' + puntoventa_id, function (data) {
            if (data.modofacturacion == 'E') {
                $('#div_formapago, #div_mercaderia, #div_incoterm, #div_leyendaexportacion').show();
            } else {
                $('#div_formapago, #div_mercaderia, #div_incoterm, #div_leyendaexportacion').hide();
            }
        });
    }

    function cargarSelectsModal(modal) {
        var datos = document.querySelector('#datosfactura');
        if (!datos) return;

        var sel_puntoventa = JSON.parse(datos.dataset.puntoventa || '[]');
        var sel_tipotransaccion = JSON.parse(datos.dataset.tipotransaccion || '[]');
        var puntoVentaDefault = $('#puntoventadefault_id').val();
        var puntoVentaRemitoDefault = $('#puntoventaremitodefault_id').val();
        var tipoTransaccionDefault = $('#tipotransacciondefault_id').val();

        var selectTipoTransaccion = modal.find('#tipotransaccion_id');
        selectTipoTransaccion.empty();
        selectTipoTransaccion.append('<option value="">-- Seleccionar tipo de transacción --</option>');
        $.each(sel_tipotransaccion, function (obj, item) {
            var op = (tipoTransaccionDefault == item.id) ? ' selected="selected"' : '';
            selectTipoTransaccion.append('<option value="' + item.id + '"' + op + '>' + item.abreviatura + '-' + item.nombre + '</option>');
        });

        var selectPuntoVenta = modal.find('#puntoventa_id');
        selectPuntoVenta.empty();
        selectPuntoVenta.append('<option value="">-- Seleccionar punto de venta --</option>');
        $.each(sel_puntoventa, function (obj, item) {
            var op = (puntoVentaDefault == item.id) ? ' selected="selected"' : '';
            selectPuntoVenta.append('<option value="' + item.id + '"' + op + '>' + item.codigo + '-' + item.nombre + '</option>');
        });

        var selectPuntoVentaRemito = modal.find('#puntoventaremito_id');
        selectPuntoVentaRemito.empty();
        selectPuntoVentaRemito.append('<option value="">-- Seleccionar punto de venta --</option>');
        $.each(sel_puntoventa, function (obj, item) {
            var op = (puntoVentaRemitoDefault == item.id) ? ' selected="selected"' : '';
            selectPuntoVentaRemito.append('<option value="' + item.id + '"' + op + '>' + item.codigo + '-' + item.nombre + '</option>');
        });

        if (datos.dataset.incoterm) {
            var sel_incoterm = JSON.parse(datos.dataset.incoterm || '[]');
            var selectIncoterm = modal.find('#incoterm_id');
            selectIncoterm.empty();
            selectIncoterm.append('<option value="">-- Seleccionar incoterm --</option>');
            $.each(sel_incoterm, function (obj, item) {
                selectIncoterm.append('<option value="' + item.id + '">' + (item.abreviatura ? (item.abreviatura + ' — ') : '') + item.nombre + '</option>');
            });
        }

        if (datos.dataset.formapago) {
            var sel_formapago = JSON.parse(datos.dataset.formapago || '[]');
            var selectFormapago = modal.find('#formapago_id');
            selectFormapago.empty();
            selectFormapago.append('<option value="">-- Seleccionar forma de pago --</option>');
            $.each(sel_formapago, function (obj, item) {
                selectFormapago.append('<option value="' + item.id + '">' + item.nombre + '</option>');
            });
        }

        if (datos.dataset.transporte) {
            var sel_transporte = JSON.parse(datos.dataset.transporte || '[]');
            var selectTransporte = modal.find('#transporte_id');
            if (selectTransporte.length) {
                selectTransporte.empty();
                selectTransporte.append('<option value="">-- Seleccionar transporte --</option>');
                $.each(sel_transporte, function (obj, item) {
                    selectTransporte.append('<option value="' + item.id + '">' + item.nombre + '</option>');
                });
            }
        }

        leePuntoVenta(puntoVentaDefault);
    }

    function escHtml(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderMedidasFacturaPicking(filas) {
        var $dest = $('#facturarMedidasModal');
        $dest.empty();
        var tot = 0;
        (filas || []).forEach(function (fila) {
            var cant = parseFloat(fila.cantidad) || 0;
            tot += cant;
            var titulo = escHtml((fila.sku || '') + ' ' + (fila.combinacion || ''));
            $dest.append('<div class="mb-1"><strong>' + titulo + '</strong> — ' + cant.toFixed(0) + ' pares</div>');
            var talles = fila.talles || [];
            if (!talles.length) {
                return;
            }
            var html = "<table class='table table-bordered table-sm table-striped mb-3'><thead><tr>";
            talles.forEach(function (t) {
                html += "<th class='text-center' style='min-width:32px;background:#D2D8DC;'>" + escHtml(t.nombre) + "</th>";
            });
            html += "</tr></thead><tbody><tr>";
            talles.forEach(function (t) {
                var q = parseFloat(t.cantidad);
                html += "<td class='text-center'><input type='text' class='cantidadesportalles form-control form-control-sm text-center' readonly value='" + (q ? q : '') + "' style='width:42px;display:inline-block;'></td>";
            });
            html += "</tr></tbody></table>";
            $dest.append(html);
        });
        $('#facturartotpares').val(tot ? String(Math.round(tot)) : '');
    }

    $(function () {
        $('#check-all-picking').on('change', function () {
            $('.check-picking-linea:not(:disabled)').prop('checked', $(this).is(':checked'));
        });

        $('#btn-excel-picking').on('click', function (e) {
            e.preventDefault();
            var ids = idsSeleccionados();
            if (!ids.length) {
                alert('Seleccione al menos una línea para el Excel');
                return;
            }
            var base = $(this).attr('href') || '';
            var sep = base.indexOf('?') >= 0 ? '&' : '?';
            var qs = ids.map(function (id) {
                return 'pedido_combinacion_id[]=' + encodeURIComponent(id);
            }).join('&');
            window.location = base + sep + qs;
        });

        $('#btn-facturar-picking').on('click', function () {
            var ids = idsSeleccionados();
            if (!ids.length) {
                alert('Seleccione al menos una línea');
                return;
            }

            var token = $('#csrf_token').val();
            $.post(carpetaBase + '/stock/picking-pedido/payload-factura', {
                pedido_combinacion_id: ids,
                _token: token
            })
                .done(function (data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    pedido_combinacion_ids = data.pedido_combinacion_ids || [];
                    ordentrabajo_ids = data.ordentrabajo_ids || [];
                    filasFacturaPicking = data.filas || [];
                    nombrecliente = data.nombrecliente || '';
                    descuentoCliente = 0;
                    offFactura = pedido_combinacion_ids.length;
                    $('#facturarOrdenTrabajoModal').modal('show');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo armar la factura';
                    alert(msg);
                });
        });

        $(document).on('shown.bs.modal', '#facturarOrdenTrabajoModal', function () {
            var modal = $(this);
            var hoy = new Date();
            modal.find('#fechafactura').val(hoy.toISOString().substring(0, 10));
            modal.find('#nombrecliente').val(nombrecliente);
            modal.find('.modal-title').text('Factura PICKING — ' + nombrecliente);
            modal.find('#descuentopie').val(descuentoCliente);
            cargarSelectsModal(modal);
            renderMedidasFacturaPicking(filasFacturaPicking);
            alert('Va a facturar ' + offFactura + ' ítems de picking');
        });

        $('#cierraFacturarOrdenTrabajoModal').on('click', function () {
            $('#facturarOrdenTrabajoModal').modal('hide');
        });

        $('#aceptaFacturarOrdenTrabajoModal').on('click', function () {
            var token = $('#csrf_token').val();
            var puntoventa_id = $('#puntoventa_id').val();
            var tipotransaccion_id = $('#tipotransaccion_id').val();
            var descuentopie = $('#descuentopie').val();
            var descuentoimportepie = $('#descuentoimportepie').val();
            var descuentolinea = $('#descuentolinea').val();
            var fechafactura = $('#fechafactura').val();
            var leyendafactura = $('#leyendafactura').val();
            var cantidadbulto = $('#cantidadbulto').val();
            var puntoventaremito_id = $('#puntoventaremito_id').val();
            var formapago_id = $('#formapago_id').val();
            var incoterm_id = $('#incoterm_id').val();
            var mercaderia = $('#mercaderia').val();
            var leyendaexportacion = $('#leyendaexportacion').val();
            var transporte_id = $('#transporte_id').val();

            if (cantidadbulto < 1 || cantidadbulto > 999999) {
                alert('No permite facturar sin cargar bultos');
                return false;
            }

            $('#facturarOrdenTrabajoModal').modal('hide');
            mostrarOverlay('Emitiendo factura…');

            $.post(carpetaBase + '/ventas/facturarItemOt', {
                origen: 'picking',
                pedido_combinacion_id: pedido_combinacion_ids,
                ordentrabajo_id: ordentrabajo_ids,
                tipotransaccion_id: tipotransaccion_id,
                puntoventa_id: puntoventa_id,
                fechafactura: fechafactura,
                descuentopie: descuentopie,
                descuentoimportepie: descuentoimportepie,
                descuentolinea: descuentolinea,
                leyendafactura: leyendafactura,
                cantidadbulto: cantidadbulto,
                puntoventaremito_id: puntoventaremito_id,
                formapago_id: formapago_id,
                incoterm_id: incoterm_id,
                mercaderia: mercaderia,
                leyendaexportacion: leyendaexportacion,
                transporte_id: transporte_id,
                retorno_index: pathRetornoPicking(),
                con_envios: $('#con_envios').is(':checked') ? 1 : 0,
                _token: token
            })
                .done(function (data, status) {
                    var errMsg = (typeof data === 'string')
                        ? data
                        : (data && data.error != null ? String(data.error) : '');
                    if (errMsg !== '') {
                        ocultarOverlay();
                        alert(errMsg);
                        return;
                    }
                    var urlImpresion = extraerUrlImpresionSesion(data);
                    var avisoImpresion = (data && data.aviso_impresion) ? String(data.aviso_impresion) : '';
                    if (urlImpresion) {
                        if (avisoImpresion) {
                            alert(avisoImpresion);
                        }
                        mostrarOverlay('Abriendo programa de impresión…');
                        window.location = urlImpresion;
                        return;
                    }
                    ocultarOverlay();
                    alert((avisoImpresion ? avisoImpresion + '\n' : '') + 'Factura Número: ' + data.factura + '\nEstado: ' + status);
                    window.location.reload();
                })
                .fail(function (xhr) {
                    ocultarOverlay();
                    alert((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al facturar');
                });
        });

        $('#puntoventa_id').on('change', function () {
            leePuntoVenta($(this).val());
        });

        function tokenCsrf() {
            return $('#csrf_token').val() || $('input[name="_token"]').val() || '';
        }

        function mensajeVacioPickingsDia() {
            var texto = ($('#consultapickingsdia').val() || '').trim();
            if (texto !== '' && /^\d+$/.test(texto)) {
                return 'No se encontró el picking Nº ' + texto;
            }
            var estado = ($('#consultapickingsdia_estado').val() || 'pendientes');
            if (estado === 'facturados') {
                return 'Sin pickings facturados en la fecha';
            }
            if (estado === 'todos') {
                return 'Sin pickings en la fecha';
            }
            return 'Sin pickings pendientes';
        }

        function renderPickingsDia(filas) {
            var $tbody = $('#datospickingsdia');
            $tbody.empty();
            if (!filas || !filas.length) {
                $tbody.append('<tr><td colspan="8" class="text-center text-muted">' + mensajeVacioPickingsDia() + '</td></tr>');
                return;
            }
            $.each(filas, function (_i, fila) {
                var $tr = $('<tr/>');
                $tr.append($('<td/>').text(fila.codigo));
                $tr.append($('<td/>').text(fila.fecha || ''));
                $tr.append($('<td/>').text(fila.estado || ''));
                $tr.append($('<td/>').text(fila.usuario || ''));
                var pend = parseInt(fila.lineas_pendientes, 10) || 0;
                var fact = parseInt(fila.lineas_facturadas, 10) || 0;
                var celdaPend = String(pend);
                if (fact > 0 && pend === 0) {
                    celdaPend = '0 (facturado)';
                } else if (fact > 0) {
                    celdaPend = pend + ' (+' + fact + ' fact.)';
                }
                $tr.append($('<td class="text-right"/>').text(celdaPend));
                $tr.append($('<td class="text-right"/>').text(fila.clientes || 0));
                $tr.append($('<td/>').text(fila.clientes_nombres || ''));
                var $acciones = $('<td class="text-nowrap"/>');
                var $btn = $('<button type="button" class="btn btn-warning btn-sm eligeconsultapickingdia mr-1">Elegir</button>');
                $btn.attr('data-id', fila.id || 0);
                $btn.attr('data-codigo', fila.codigo || 0);
                $acciones.append($btn);
                if (fila.puede_borrar) {
                    var $btnBorrar = $('<button type="button" class="btn btn-outline-danger btn-sm btn-borrar-picking-dia" title="Quitar líneas, devolver stock y eliminar picking"><i class="fa fa-trash"></i></button>');
                    $btnBorrar.attr('data-id', fila.id || 0);
                    $btnBorrar.attr('data-codigo', fila.codigo || 0);
                    $acciones.append($btnBorrar);
                }
                $tr.append($acciones);
                $tbody.append($tr);
            });
        }

        function borrarPicking(pickingId, pickingCodigo, $btn) {
            var codigo = pickingCodigo || '';
            var msg = '¿Borrar el picking' + (codigo ? (' #' + codigo) : '') + '?\n\n'
                + 'Se quitan todas las líneas preparadas, se devuelve el stock al lote/OT y se elimina el picking.\n'
                + 'Solo si aún no facturaron ninguna línea.';
            if (!window.confirm(msg)) {
                return;
            }
            if ($btn) {
                $btn.prop('disabled', true);
            }
            $.post(carpetaBase + '/stock/picking-pedido/borrar', {
                picking_id: pickingId || 0,
                picking_codigo: pickingCodigo || 0,
                _token: tokenCsrf()
            })
                .done(function (data) {
                    if (data.error) {
                        alert(data.error);
                        if ($btn) {
                            $btn.prop('disabled', false);
                        }
                        return;
                    }
                    alert(data.aviso || ('Picking' + (codigo ? (' #' + codigo) : '') + ' borrado'));
                    $('#consultapickingsdiaModal').modal('hide');
                    window.location = carpetaBase + '/stock/picking-pedido';
                })
                .fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo borrar el picking');
                    if ($btn) {
                        $btn.prop('disabled', false);
                    }
                });
        }

        function quitarLineaPicking(pedidoCombinacionId, $btn) {
            if (!window.confirm('¿Quitar la preparación de esta línea y devolver el stock al lote/OT?')) {
                return;
            }
            if ($btn) {
                $btn.prop('disabled', true);
            }
            $.post(carpetaBase + '/stock/picking-pedido/desmarcar', {
                pedido_combinacion_id: pedidoCombinacionId,
                _token: tokenCsrf()
            })
                .done(function (data) {
                    if (data.error) {
                        alert(data.error);
                        if ($btn) {
                            $btn.prop('disabled', false);
                        }
                        return;
                    }
                    var $tr = $btn ? $btn.closest('tr') : null;
                    if ($tr && $tr.length) {
                        $tr.fadeOut(200, function () {
                            $(this).remove();
                            var restantes = $('#tabla-picking-pedido tbody tr[data-pedido-combinacion-id]').length;
                            if (restantes === 0) {
                                window.location.reload();
                            }
                        });
                    } else {
                        window.location.reload();
                    }
                })
                .fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo quitar la línea');
                    if ($btn) {
                        $btn.prop('disabled', false);
                    }
                });
        }

        function estadoFiltroFormulario() {
            return ($('#estado').val() || 'pendientes');
        }

        function pintarEtiquetasEstadoForm(estado) {
            estado = estado || 'pendientes';
            $('#estado').val(estado);
            $('.picking-estado-etiq').each(function () {
                var val = $(this).attr('data-estado');
                var activo = val === estado;
                $(this).removeClass(
                    'btn-warning btn-outline-warning btn-success btn-outline-success btn-primary btn-outline-primary'
                );
                if (val === 'pendientes') {
                    $(this).addClass(activo ? 'btn-warning' : 'btn-outline-warning');
                } else if (val === 'facturados') {
                    $(this).addClass(activo ? 'btn-success' : 'btn-outline-success');
                } else {
                    $(this).addClass(activo ? 'btn-primary' : 'btn-outline-primary');
                }
            });
        }

        function consultarPickingConEstado(estado) {
            pintarEtiquetasEstadoForm(estado);
            // Al cambiar estado, listar todos los de ese estado (no un picking puntual).
            $('#picking_id').val(0);
            $('#picking_codigo').val('');
            $('#form-picking-pedido').trigger('submit');
        }

        function pintarEtiquetasEstadoModal(estado) {
            estado = estado || 'pendientes';
            $('#consultapickingsdia_estado').val(estado);
            $('.consultapickingsdia-estado-etiq').each(function () {
                var val = $(this).attr('data-estado');
                var activo = val === estado;
                $(this).removeClass(
                    'btn-warning btn-outline-warning btn-success btn-outline-success btn-primary btn-outline-primary'
                );
                if (val === 'pendientes') {
                    $(this).addClass(activo ? 'btn-warning' : 'btn-outline-warning');
                } else if (val === 'facturados') {
                    $(this).addClass(activo ? 'btn-success' : 'btn-outline-success');
                } else {
                    $(this).addClass(activo ? 'btn-primary' : 'btn-outline-primary');
                }
            });
        }

        function actualizarUiEstadoPickings() {
            var estado = ($('#consultapickingsdia_estado').val() || 'pendientes');
            var usaFecha = estado !== 'pendientes';
            $('#consultapickingsdia_fecha').prop('disabled', !usaFecha);
            pintarEtiquetasEstadoModal(estado);
        }

        function buscarPickingsDia() {
            var fecha = $('#consultapickingsdia_fecha').val() || '';
            var texto = $('#consultapickingsdia').val() || '';
            var estado = $('#consultapickingsdia_estado').val() || 'pendientes';
            $('#datospickingsdia').html('<tr><td colspan="8" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Buscando…</td></tr>');
            $.post(carpetaBase + '/stock/picking-pedido/consulta-pickings-dia', {
                fecha: fecha,
                texto: texto,
                estado: estado,
                _token: tokenCsrf()
            })
                .done(function (data) {
                    renderPickingsDia(data.filas || []);
                })
                .fail(function () {
                    renderPickingsDia([]);
                });
        }

        function abrirModalPickingsDia() {
            if (!$('#consultapickingsdia_fecha').val()) {
                $('#consultapickingsdia_fecha').val(new Date().toISOString().substring(0, 10));
            }
            pintarEtiquetasEstadoModal(estadoFiltroFormulario());
            $('#consultapickingsdia').val(($('#picking_codigo').val() || '').trim());
            actualizarUiEstadoPickings();
            $('#consultapickingsdiaModal').modal('show');
            buscarPickingsDia();
        }

        function aplicarPickingElegido(id, codigo) {
            $('#picking_id').val(id || 0);
            $('#picking_codigo').val(codigo || '');
            $('#consultapickingsdiaModal').modal('hide');
            $('#form-picking-pedido').trigger('submit');
        }

        function crearNuevoPicking() {
            $.post(carpetaBase + '/stock/picking-pedido/crear', {
                fecha: $('#consultapickingsdia_fecha').val() || '',
                _token: tokenCsrf()
            })
                .done(function (data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    aplicarPickingElegido(data.id, data.codigo);
                })
                .fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No se pudo crear el picking');
                });
        }

        $('#btn-consulta-pickings-dia').on('click', function (e) {
            e.preventDefault();
            abrirModalPickingsDia();
        });

        $('#btn-buscar-pickings-dia').on('click', function () {
            buscarPickingsDia();
        });

        $('#consultapickingsdia').on('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                var $btn = $('#datospickingsdia .eligeconsultapickingdia').first();
                if ($btn.length) {
                    $btn.trigger('click');
                } else {
                    buscarPickingsDia();
                }
            }
        }).on('keyup', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                return;
            }
            clearTimeout(window.__debouncePickingsDia);
            window.__debouncePickingsDia = setTimeout(buscarPickingsDia, 300);
        });

        $('#consultapickingsdia_fecha').on('change', buscarPickingsDia);
        $(document).on('click', '.consultapickingsdia-estado-etiq', function (e) {
            e.preventDefault();
            pintarEtiquetasEstadoModal($(this).attr('data-estado') || 'pendientes');
            actualizarUiEstadoPickings();
            buscarPickingsDia();
        });

        $(document).on('click', '.picking-estado-etiq', function (e) {
            e.preventDefault();
            consultarPickingConEstado($(this).attr('data-estado') || 'pendientes');
        });

        $(document).on('click', '.eligeconsultapickingdia', function () {
            aplicarPickingElegido(
                parseInt($(this).attr('data-id'), 10) || 0,
                parseInt($(this).attr('data-codigo'), 10) || 0
            );
        });

        $(document).on('click', '.btn-borrar-picking-dia', function (e) {
            e.preventDefault();
            e.stopPropagation();
            borrarPicking(
                parseInt($(this).attr('data-id'), 10) || 0,
                parseInt($(this).attr('data-codigo'), 10) || 0,
                $(this)
            );
        });

        $(document).on('click', '.btn-quitar-picking-linea', function (e) {
            e.preventDefault();
            quitarLineaPicking(parseInt($(this).attr('data-id'), 10) || 0, $(this));
        });

        $('#btn-borrar-picking').on('click', function (e) {
            e.preventDefault();
            borrarPicking(
                parseInt($(this).attr('data-picking-id'), 10) || 0,
                parseInt($(this).attr('data-picking-codigo'), 10) || 0,
                $(this)
            );
        });

        $('#btn-nuevo-picking, #btn-nuevo-picking-modal').on('click', function (e) {
            e.preventDefault();
            crearNuevoPicking();
        });

        $('#picking_codigo').on('keydown', function (e) {
            if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrirModalPickingsDia();
            }
        });

        if (!window.__pickingDiaF1Capture) {
            document.addEventListener('keydown', function (e) {
                if (!(e.key === 'F1' || e.code === 'F1' || e.keyCode === 112)) {
                    return;
                }
                if (!e.target || e.target.id !== 'picking_codigo') {
                    return;
                }
                e.preventDefault();
                abrirModalPickingsDia();
            }, true);
            window.__pickingDiaF1Capture = true;
        }

        window.addEventListener('pageshow', ocultarOverlay);
    });
})(jQuery);
