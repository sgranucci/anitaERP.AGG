(function ($) {
    'use strict';

    var cfg = window.DESCUENTO_REPORTE || {};
    var descuentosSel = {};
    var clientesSel = {};
    var modalDestinoDescuento = 'seleccion';

    function normalizarCodigo(c) {
        return String(c || '').trim();
    }

    function normalizarId(id) {
        var n = parseInt(id, 10);
        return isNaN(n) || n <= 0 ? 0 : n;
    }

    function listarTodosActivo() {
        return $('#listar_todos').is(':checked');
    }

    function modoSeleccionActual() {
        return $('input[name="agrupar_por"]:checked').val() === 'cliente_descuento'
            ? 'cliente_descuento'
            : 'codigo_descuento';
    }

    function esModoCliente() {
        return modoSeleccionActual() === 'cliente_descuento';
    }

    function sincronizarHiddenDescuentos() {
        var codigos = Object.keys(descuentosSel).sort(function (a, b) {
            var na = parseInt(a, 10);
            var nb = parseInt(b, 10);
            if (!isNaN(na) && !isNaN(nb)) {
                return na - nb;
            }
            return a.localeCompare(b);
        });
        $('#codigos_descuento').val(codigos.join(','));
    }

    function sincronizarHiddenClientes() {
        var ids = Object.keys(clientesSel).map(function (k) {
            return parseInt(k, 10);
        }).filter(function (n) {
            return n > 0;
        }).sort(function (a, b) {
            return a - b;
        });
        $('#clientes_descuento_ids').val(ids.join(','));
    }

    function pintarTablaDescuentos() {
        var $tbody = $('#tbody-descuentos-seleccionados-reporte');
        $tbody.empty();
        var codigos = Object.keys(descuentosSel).sort();
        codigos.forEach(function (codigo) {
            var item = descuentosSel[codigo];
            $tbody.append(
                '<tr data-codigo="' + $('<div>').text(codigo).html() + '">' +
                '<td>' + $('<div>').text(codigo).html() + '</td>' +
                '<td>' + $('<div>').text(item.nombre || '').html() + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-descuento-reporte" title="Quitar">' +
                '<i class="fa fa-times"></i></button></td></tr>'
            );
        });
        $('#aviso-sin-descuentos-reporte').toggle(codigos.length === 0);
    }

    function pintarTablaClientes() {
        var $tbody = $('#tbody-clientes-seleccionados-reporte');
        $tbody.empty();
        var ids = Object.keys(clientesSel).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        });
        ids.forEach(function (id) {
            var item = clientesSel[id];
            $tbody.append(
                '<tr data-id="' + $('<div>').text(id).html() + '">' +
                '<td>' + $('<div>').text(item.codigo || '').html() + '</td>' +
                '<td>' + $('<div>').text(item.nombre || '').html() + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-cliente-reporte" title="Quitar">' +
                '<i class="fa fa-times"></i></button></td></tr>'
            );
        });
        $('#aviso-sin-clientes-reporte').toggle(ids.length === 0);
    }

    function agregarDescuento(data) {
        var codigo = normalizarCodigo(data && data.codigo);
        if (!codigo || descuentosSel[codigo]) {
            return false;
        }
        descuentosSel[codigo] = {
            id: data.id || null,
            codigo: codigo,
            nombre: (data.nombre || '').trim(),
        };
        sincronizarHiddenDescuentos();
        pintarTablaDescuentos();
        return true;
    }

    function quitarDescuento(codigo) {
        codigo = normalizarCodigo(codigo);
        if (!codigo || !descuentosSel[codigo]) {
            return;
        }
        delete descuentosSel[codigo];
        sincronizarHiddenDescuentos();
        pintarTablaDescuentos();
    }

    function agregarCliente(data) {
        var id = normalizarId(data && data.id);
        if (!id || clientesSel[id]) {
            return false;
        }
        clientesSel[id] = {
            id: id,
            codigo: (data.codigo || '').trim(),
            nombre: (data.nombre || '').trim(),
        };
        sincronizarHiddenClientes();
        pintarTablaClientes();
        return true;
    }

    function quitarCliente(id) {
        id = normalizarId(id);
        if (!id || !clientesSel[id]) {
            return;
        }
        delete clientesSel[id];
        sincronizarHiddenClientes();
        pintarTablaClientes();
    }

    function limpiarCampoDescuento() {
        $('#codigodescuento_reporte').val('');
        $('#nombredescuento_reporte').val('');
    }

    function limpiarCampoCliente() {
        $('#codigocliente_reporte').val('');
        $('#nombrecliente_reporte').val('');
    }

    function baseUrl() {
        return typeof carpetaBase !== 'undefined' ? carpetaBase : '';
    }

    function buscarDatosDescuento(consulta) {
        $.ajax({
            url: baseUrl() + '/ventas/descuento-gastronomia/consultadescuento',
            type: 'POST',
            dataType: 'HTML',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' },
        }).done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            }
            $('#datosdescuento').html(html);
        }).fail(function () {
            $('#datosdescuento').html('<tr><td colspan="7">Error al consultar descuentos.</td></tr>');
        });
    }

    function buscarDatosCliente(consulta) {
        $.ajax({
            url: baseUrl() + '/ventas/consultacliente',
            type: 'POST',
            dataType: 'HTML',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { consulta: consulta || '' },
        }).done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            }
            $('#datoscliente').html(html);
        }).fail(function () {
            $('#datoscliente').html('<tr><td colspan="7">Error al consultar clientes.</td></tr>');
        });
    }

    function leerDescuentoPorCodigo(codigo, callback) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            if (callback) { callback(null); }
            return;
        }
        $.get(baseUrl() + '/ventas/descuento-gastronomia/leer/' + encodeURIComponent(cod))
            .done(function (data) { if (callback) { callback(data && data.id ? data : null); } })
            .fail(function () { if (callback) { callback(null); } });
    }

    function leerClientePorCodigo(codigo, callback) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            if (callback) { callback(null); }
            return;
        }
        $.get(baseUrl() + '/ventas/leerunclienteporcodigo/' + encodeURIComponent(cod))
            .done(function (data) { if (callback) { callback(data && data.id ? data : null); } })
            .fail(function () { if (callback) { callback(null); } });
    }

    function agregarDescuentoDesdeCampo() {
        var codigo = normalizarCodigo($('#codigodescuento_reporte').val());
        if (!codigo) { return; }
        leerDescuentoPorCodigo(codigo, function (data) {
            if (!data) {
                alert('Descuento no encontrado: ' + codigo);
                return;
            }
            agregarDescuento(data);
            limpiarCampoDescuento();
        });
    }

    function agregarClienteDesdeCampo() {
        var codigo = normalizarCodigo($('#codigocliente_reporte').val());
        if (!codigo) { return; }
        leerClientePorCodigo(codigo, function (data) {
            if (!data) {
                alert('Cliente no encontrado: ' + codigo);
                return;
            }
            agregarCliente(data);
            limpiarCampoCliente();
        });
    }

    function initSeleccionados() {
        descuentosSel = {};
        (cfg.descuentosIniciales || []).forEach(function (item) {
            var codigo = normalizarCodigo(item.codigo);
            if (codigo) {
                descuentosSel[codigo] = {
                    id: item.id || null,
                    codigo: codigo,
                    nombre: (item.nombre || '').trim(),
                };
            }
        });

        clientesSel = {};
        (cfg.clientesIniciales || []).forEach(function (item) {
            var id = normalizarId(item.id);
            if (id) {
                clientesSel[id] = {
                    id: id,
                    codigo: (item.codigo || '').trim(),
                    nombre: (item.nombre || '').trim(),
                };
            }
        });

        sincronizarHiddenDescuentos();
        sincronizarHiddenClientes();
        pintarTablaDescuentos();
        pintarTablaClientes();
    }

    function agregarCodigoAlFiltroCliente(codigo) {
        codigo = normalizarCodigo(codigo);
        if (!codigo) {
            return;
        }
        var $input = $('#codigos_descuento_cliente');
        var actual = String($input.val() || '').trim();
        var partes = actual ? actual.split(/[,;]+/) : [];
        var existe = false;
        partes.forEach(function (parte) {
            if (normalizarCodigo(parte) === codigo) {
                existe = true;
            }
        });
        if (!existe) {
            partes.push(codigo);
        }
        $input.val(partes.join(','));
    }

    function enfocarCodigoSeleccionActiva() {
        if (listarTodosActivo()) {
            return;
        }

        var $campo = esModoCliente() ? $('#codigocliente_reporte') : $('#codigodescuento_reporte');
        if (!$campo.length || !$campo.is(':visible')) {
            return;
        }

        window.setTimeout(function () {
            $campo.trigger('focus');
        }, 0);
    }

    function actualizarAyudaTipoSeleccion() {
        var listarTodos = listarTodosActivo();
        var modoCliente = esModoCliente();
        var $ayuda = $('#ayuda-tipo-seleccion');
        if (!$ayuda.length) {
            return;
        }
        if (listarTodos) {
            $ayuda.text(
                'Define cómo se arman las secciones del reporte para todos los códigos/clientes con ventas en el período.',
            );
        } else if (modoCliente) {
            $ayuda.text(
                'Elija clientes internos y el reporte mostrará un bloque por cada uno '
                + '(ventas con descuento asignadas a ese cliente en la cuenta).',
            );
        } else {
            $ayuda.text(
                'Elija códigos de descuento de cabecera y el reporte mostrará un bloque por cada código '
                + '(artículos vendidos con ese descuento).',
            );
        }
    }

    function actualizarVisibilidadSeleccion(aplicarFoco) {
        var listarTodos = listarTodosActivo();
        var modoCliente = esModoCliente();

        $('#label-tipo-seleccion').text(listarTodos ? 'Agrupar por' : 'Filtrar por');
        actualizarAyudaTipoSeleccion();

        if (modoCliente) {
            $('#wrap-filtro-descuento-cliente').show();
        } else {
            $('#wrap-filtro-descuento-cliente').hide();
        }

        if (listarTodos) {
            $('#wrap-seleccion-descuento, #wrap-seleccion-cliente').hide();
            return;
        }

        if (modoCliente) {
            $('#wrap-seleccion-descuento').hide();
            $('#wrap-seleccion-cliente').show();
        } else {
            $('#wrap-seleccion-descuento').show();
            $('#wrap-seleccion-cliente').hide();
        }

        if (aplicarFoco) {
            enfocarCodigoSeleccionActiva();
        }
    }

    function sincronizarPresentacionColumnasHidden() {
        $('#presentacion_columnas_hidden').val($('#presentacion_columnas').is(':checked') ? '1' : '0');
    }

    function marcarReconsultaPendiente() {
        if ($('#form-descuento-reporte').data('consultado') === 1) {
            $('#aviso-reconsultar-descuento-reporte').show();
            $('#btn-consultar-descuento-reporte').removeClass('btn-primary').addClass('btn-warning');
        }
    }

    function actualizarOpcionesPresentacion() {
        sincronizarPresentacionColumnasHidden();
        var columnas = $('#presentacion_columnas').is(':checked');
        var listarTodos = listarTodosActivo();
        if (columnas || listarTodos) {
            $('#excel_solapas').prop('checked', false).prop('disabled', true);
        } else {
            $('#excel_solapas').prop('disabled', false);
        }
    }

    function activarEventosConsultaDescuento() {
        $('.consultadescuento-reporte').off('click.dr').on('click.dr', function () {
            modalDestinoDescuento = 'seleccion';
            $('#consultadescuentoModal').modal('show');
        });

        $(document).off('click.drFiltroCliente', '.consultadescuento-filtro-cliente').on('click.drFiltroCliente', '.consultadescuento-filtro-cliente', function () {
            modalDestinoDescuento = 'filtro_cliente';
            $('#consultadescuentoModal').modal('show');
        });

        $('#consultadescuentoModal').off('shown.bs.modal.dr').on('shown.bs.modal.dr', function () {
            $(this).find('#consultadescuento').val('').focus();
            buscarDatosDescuento('');
        });

        $(document).off('keyup.drConsulta', '#consultadescuento').on('keyup.drConsulta', '#consultadescuento', function () {
            buscarDatosDescuento($(this).val());
        });

        $(document).off('click.drElige', '#consultadescuentoModal .eligeconsultadescuento').on('click.drElige', '#consultadescuentoModal .eligeconsultadescuento', function () {
            var $tr = $(this).closest('tr');
            var codigo = ($tr.find('.codigo').text() || '').trim();
            if (modalDestinoDescuento === 'filtro_cliente') {
                agregarCodigoAlFiltroCliente(codigo);
                return;
            }
            agregarDescuento({
                id: ($tr.find('.id').text() || '').trim(),
                codigo: codigo,
                nombre: ($tr.find('.nombre').text() || '').trim(),
            });
            limpiarCampoDescuento();
            $('#consultadescuentoModal').modal('hide');
        });

        $('#aceptaconsultadescuentoModal').off('click.dr').on('click.dr', function () {
            $('#consultadescuentoModal').modal('hide');
        });
    }

    function activarEventosConsultaCliente() {
        $('.consultacliente-reporte').off('click.cr').on('click.cr', function () {
            $('#consultaclienteModal').modal('show');
        });

        $('#consultaclienteModal').off('shown.bs.modal.cr').on('shown.bs.modal.cr', function () {
            $(this).find('#consultacliente').val('').focus();
            buscarDatosCliente('');
        });

        $(document).off('keyup.crConsulta', '#consultacliente').on('keyup.crConsulta', '#consultacliente', function () {
            buscarDatosCliente($(this).val());
        });

        $(document).off('click.crElige', '#consultaclienteModal .eligeconsultacliente').on('click.crElige', '#consultaclienteModal .eligeconsultacliente', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            agregarCliente({
                id: ($tr.find('td.id').first().text() || '').trim(),
                codigo: ($tr.find('td.codigo').first().text() || '').trim(),
                nombre: ($tr.find('td.nombre').first().text() || '').trim(),
            });
            limpiarCampoCliente();
            $('#consultaclienteModal').modal('hide');
        });

        $('#aceptaconsultaclienteModal').off('click.cr').on('click.cr', function () {
            $('#consultaclienteModal').modal('hide');
        });
    }

    $(function () {
        initSeleccionados();
        activarEventosConsultaDescuento();
        activarEventosConsultaCliente();
        actualizarVisibilidadSeleccion();
        actualizarOpcionesPresentacion();
        sincronizarPresentacionColumnasHidden();
        $('#form-descuento-reporte').data('consultado', cfg.consultado ? 1 : 0);

        $('#btn-agregar-descuento-reporte').on('click', agregarDescuentoDesdeCampo);
        $('#btn-agregar-cliente-reporte').on('click', agregarClienteDesdeCampo);

        $('#codigodescuento_reporte').on('keydown', function (e) {
            if (e.which === 13) { e.preventDefault(); agregarDescuentoDesdeCampo(); }
        }).on('change', function () {
            var codigo = normalizarCodigo($(this).val());
            if (!codigo) { $('#nombredescuento_reporte').val(''); return; }
            leerDescuentoPorCodigo(codigo, function (data) {
                $('#nombredescuento_reporte').val(data ? (data.nombre || '') : '');
            });
        });

        $('#codigocliente_reporte').on('keydown', function (e) {
            if (e.which === 13) { e.preventDefault(); agregarClienteDesdeCampo(); }
        }).on('change', function () {
            var codigo = normalizarCodigo($(this).val());
            if (!codigo) { $('#nombrecliente_reporte').val(''); return; }
            leerClientePorCodigo(codigo, function (data) {
                $('#nombrecliente_reporte').val(data ? (data.nombre || '') : '');
            });
        });

        $(document).on('click', '.btn-quitar-descuento-reporte', function () {
            quitarDescuento($(this).closest('tr').data('codigo'));
        });

        $(document).on('click', '.btn-quitar-cliente-reporte', function () {
            quitarCliente($(this).closest('tr').data('id'));
        });

        $('#listar_todos').on('change', function () {
            actualizarVisibilidadSeleccion(!listarTodosActivo());
            actualizarOpcionesPresentacion();
            marcarReconsultaPendiente();
        });

        $('input[name="agrupar_por"]').on('change', function () {
            actualizarVisibilidadSeleccion(true);
            marcarReconsultaPendiente();
        });

        $('#presentacion_columnas').on('change', function () {
            actualizarOpcionesPresentacion();
            marcarReconsultaPendiente();
        });

        $('#form-descuento-reporte').on('submit', function (e) {
            sincronizarPresentacionColumnasHidden();
            sincronizarHiddenDescuentos();
            sincronizarHiddenClientes();
            $('#refrescar_cache_descuento_reporte').val('1');
            $('#aviso-reconsultar-descuento-reporte').hide();
            $('#btn-consultar-descuento-reporte').removeClass('btn-warning').addClass('btn-primary');
            mostrarOverlayExportacion('Consultando reporte…', 'Procesando ventas y costos. Por favor espere.');

            if (listarTodosActivo()) {
                $('#codigos_descuento').val('');
                $('#clientes_descuento_ids').val('');
                return true;
            }

            if (esModoCliente()) {
                $('#codigos_descuento').val('');
                if (normalizarCodigo($('#clientes_descuento_ids').val()) === '') {
                    e.preventDefault();
                    ocultarOverlayExportacion();
                    alert('Seleccione al menos un cliente interno de descuento, o marque Listar todos.');
                    return false;
                }
            } else {
                $('#clientes_descuento_ids').val('');
                $('#codigos_descuento_cliente').val('');
                if (normalizarCodigo($('#codigos_descuento').val()) === '') {
                    e.preventDefault();
                    ocultarOverlayExportacion();
                    alert('Seleccione al menos un c\u00f3digo de descuento, o marque Listar todos.');
                    return false;
                }
            }
        });

        activarOverlayExportacion();
        activarModalFacturasTotales();
    });

    var overlayExportId = 'descuento-reporte-exportando-overlay';
    var overlayExportTimer = null;

    function mostrarOverlayExportacion(titulo, subtitulo) {
        var $ov = $('#' + overlayExportId);
        if (!$ov.length) {
            return;
        }
        $('#descuento-reporte-exportando-titulo').text(titulo || 'Generando exportación…');
        $('#descuento-reporte-exportando-subtitulo').text(
            subtitulo || 'Por favor espere. El Excel puede tardar varios minutos según el volumen.',
        );
        $ov.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
        if (overlayExportTimer) {
            clearTimeout(overlayExportTimer);
        }
        overlayExportTimer = setTimeout(function () {
            ocultarOverlayExportacion();
        }, 600000);
    }

    function ocultarOverlayExportacion() {
        if (overlayExportTimer) {
            clearTimeout(overlayExportTimer);
            overlayExportTimer = null;
        }
        var $ov = $('#' + overlayExportId);
        if ($ov.length) {
            $ov.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
        }
        $('body').css('overflow', '');
    }

    function nombreArchivoDesdeContentDisposition(disposition) {
        if (!disposition) {
            return 'descuento_reporte_gastronomia';
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return 'descuento_reporte_gastronomia';
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || 'descuento_reporte_gastronomia';
        }
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'descuento_reporte_gastronomia';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1000);
    }

    function activarOverlayExportacion() {
        $(document).on('click', 'a.btn-app[href*="listar-gastronomia-descuento-reporte"]', function (e) {
            e.preventDefault();

            var href = $(this).attr('href') || '';
            if (!href) {
                return;
            }

            var formato = 'archivo';
            if (href.indexOf('/EXCEL') !== -1) {
                formato = 'Excel';
            } else if (href.indexOf('/PDF') !== -1) {
                formato = 'PDF';
            } else if (href.indexOf('/CSV') !== -1) {
                formato = 'CSV';
            }

            mostrarOverlayExportacion(
                'Generando ' + formato + '…',
                'Por favor espere. El archivo se descargará automáticamente al finalizar.',
            );

            fetch(href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/octet-stream, application/pdf, application/vnd.ms-excel, text/csv, */*',
                },
            })
                .then(function (resp) {
                    var contentType = (resp.headers.get('Content-Type') || '').toLowerCase();
                    if (contentType.indexOf('text/html') !== -1) {
                        ocultarOverlayExportacion();
                        window.location.href = href;
                        return null;
                    }
                    if (!resp.ok) {
                        throw new Error('export_failed');
                    }
                    var filename = nombreArchivoDesdeContentDisposition(
                        resp.headers.get('Content-Disposition'),
                    );
                    return resp.blob().then(function (blob) {
                        return { blob: blob, filename: filename };
                    });
                })
                .then(function (resultado) {
                    if (!resultado) {
                        return;
                    }
                    dispararDescargaBlob(resultado.blob, resultado.filename);
                })
                .catch(function () {
                    ocultarOverlayExportacion();
                    alert('No se pudo generar la exportación. Verifique los filtros e intente de nuevo.');
                })
                .finally(function () {
                    ocultarOverlayExportacion();
                });
        });
    }

    function formatearMoneda(n) {
        var num = parseFloat(n);
        if (isNaN(num)) {
            return '0,00';
        }
        return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatearFechaJornada(fecha) {
        if (!fecha) {
            return '—';
        }
        var partes = String(fecha).substring(0, 10).split('-');
        if (partes.length !== 3) {
            return fecha;
        }
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function activarModalFacturasTotales() {
        $(document).on('click', '.btn-ver-facturas-total-descuento', function () {
            var clave = $(this).data('clave');
            var titulo = $(this).data('titulo') || 'Facturas del total';
            if (!clave) {
                return;
            }
            abrirModalFacturasBloque(clave, titulo);
        });
    }

    function abrirModalFacturasBloque(clave, titulo) {
        var cfgLocal = window.DESCUENTO_REPORTE || {};
        $('#modalFacturasDescuentoReporteTitulo').text(titulo);
        $('#modal-facturas-descuento-cargando').show();
        $('#modal-facturas-descuento-error').addClass('d-none').text('');
        $('#modal-facturas-descuento-wrap').addClass('d-none');
        $('#tbody-facturas-descuento-bloque').empty();
        $('#modal-facturas-descuento-contador').text('');
        $('#modalFacturasDescuentoReporte').modal('show');

        var payload = $.extend({}, cfgLocal.filtrosConsulta || {}, {
            clave_bloque: clave,
            titulo_bloque: titulo,
            consultar: 1,
        });

        $.ajax({
            url: cfgLocal.consultaFacturasUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: payload,
        }).done(function (resp) {
            $('#modal-facturas-descuento-cargando').hide();
            var ventas = (resp && resp.ventas) ? resp.ventas : [];
            if (!ventas.length) {
                $('#modal-facturas-descuento-error')
                    .removeClass('d-none')
                    .text('No se encontraron facturas para este total en el período.');
                return;
            }

            var $tbody = $('#tbody-facturas-descuento-bloque');
            ventas.forEach(function (v) {
                var verHtml = '—';
                if (cfgLocal.puedeVerFactura && v.venta_id) {
                    var urlVer = cfgLocal.urlVerFacturaBase + '/' + v.venta_id + '/ver'
                        + '?origen=modal_consulta&vista=consulta';
                    verHtml = '<a href="' + urlVer + '" class="btn btn-outline-primary btn-xs" '
                        + 'target="_blank" rel="noopener">Ver</a>';
                }
                var comprobante = (v.tipo_comprobante ? v.tipo_comprobante + ' ' : '')
                    + (v.numerocomprobante || '');
                $tbody.append(
                    '<tr>' +
                    '<td>' + formatearFechaJornada(v.fechajornada) + '</td>' +
                    '<td>' + $('<div>').text(comprobante.trim() || '—').html() + '</td>' +
                    '<td>' + $('<div>').text(v.codigo || '—').html() + '</td>' +
                    '<td class="text-right">' + formatearMoneda(v.total_venta) + '</td>' +
                    '<td class="text-center">' + verHtml + '</td>' +
                    '</tr>',
                );
            });

            $('#modal-facturas-descuento-wrap').removeClass('d-none');
            $('#modal-facturas-descuento-contador').text(ventas.length + ' factura(s)');
        }).fail(function (xhr) {
            $('#modal-facturas-descuento-cargando').hide();
            var msg = 'Error al consultar facturas.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                msg = xhr.responseJSON.error;
            }
            $('#modal-facturas-descuento-error').removeClass('d-none').text(msg);
        });
    }
}(jQuery));
