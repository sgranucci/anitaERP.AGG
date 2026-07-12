(function ($) {
    'use strict';

    var cfg = window.DESCUENTO_REPORTE || {};
    var descuentosSel = {};
    var clientesSel = {};
    var mozosSel = {};
    var vipsSel = {};
    var modalDestinoDescuento = 'seleccion';
    var modalDestinoCliente = 'seleccion';
    var modalDestinoMozo = 'seleccion';
    var modalDestinoVip = 'seleccion';

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
        var val = $('input[name="agrupar_por"]:checked').val();
        if (val === 'cliente_descuento') {
            return 'cliente_descuento';
        }
        if (val === 'mozo_descuento') {
            return 'mozo_descuento';
        }
        if (val === 'cliente_vip') {
            return 'cliente_vip';
        }
        return 'codigo_descuento';
    }

    function esModoCliente() {
        return modoSeleccionActual() === 'cliente_descuento';
    }

    function esModoMozo() {
        return modoSeleccionActual() === 'mozo_descuento';
    }

    function esModoVip() {
        return modoSeleccionActual() === 'cliente_vip';
    }

    function empresaIdFormulario() {
        var val = parseInt($('#empresa_id').val(), 10);
        return isNaN(val) || val <= 0 ? 0 : val;
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

    function sincronizarHiddenMozos() {
        var ids = Object.keys(mozosSel).map(function (k) {
            return parseInt(k, 10);
        }).filter(function (n) {
            return n > 0;
        }).sort(function (a, b) {
            return a - b;
        });
        $('#mozos_descuento_ids').val(ids.join(','));
    }

    function sincronizarHiddenVips() {
        var ids = Object.keys(vipsSel).map(function (k) {
            return parseInt(k, 10);
        }).filter(function (n) {
            return n > 0;
        }).sort(function (a, b) {
            return a - b;
        });
        $('#vips_descuento_ids').val(ids.join(','));
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

    function tieneRangoClienteDefinido() {
        return normalizarCodigo($('#cliente_codigo_desde').val()) !== ''
            || normalizarCodigo($('#cliente_codigo_hasta').val()) !== '';
    }

    function expandirRangoCodigoCliente(desde, hasta) {
        desde = normalizarCodigo(desde);
        hasta = normalizarCodigo(hasta);
        if (!desde && !hasta) {
            return [];
        }
        if (desde && !hasta) {
            hasta = desde;
        } else if (hasta && !desde) {
            desde = hasta;
        }

        var numDesde = parseInt(desde, 10);
        var numHasta = parseInt(hasta, 10);
        if (!isNaN(numDesde) && !isNaN(numHasta) && String(numDesde) === desde && String(numHasta) === hasta) {
            if (numDesde > numHasta) {
                var tmp = numDesde;
                numDesde = numHasta;
                numHasta = tmp;
            }
            var codigos = [];
            for (var n = numDesde; n <= numHasta; n++) {
                codigos.push(String(n));
            }
            return codigos;
        }

        if (desde === hasta) {
            return [desde];
        }

        return [desde, hasta];
    }

    function actualizarAvisoClientesVacios() {
        var sinTabla = Object.keys(clientesSel).length === 0;
        var sinRango = !tieneRangoClienteDefinido();
        $('#aviso-sin-clientes-reporte').toggle(sinTabla && sinRango);
    }

    function resolverNombreClienteEnCampo(codigo, $nombre) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            $nombre.val('');
            return;
        }
        leerClientePorCodigo(cod, function (data) {
            $nombre.val(data ? (data.nombre || '') : '');
        });
    }

    function aplicarClienteEnDestinoModal(data) {
        if (!data || !data.id) {
            return;
        }

        if (modalDestinoCliente === 'rango_desde') {
            $('#cliente_codigo_desde').val((data.codigo || '').trim());
            $('#nombrecliente_rango_desde').val((data.nombre || '').trim());
            $('#consultaclienteModal').modal('hide');
            return;
        }

        if (modalDestinoCliente === 'rango_hasta') {
            $('#cliente_codigo_hasta').val((data.codigo || '').trim());
            $('#nombrecliente_rango_hasta').val((data.nombre || '').trim());
            $('#consultaclienteModal').modal('hide');
            return;
        }

        agregarCliente(data);
        limpiarCampoCliente();
        $('#consultaclienteModal').modal('hide');
    }

    function agregarRangoClientesDesdeCampos() {
        var codigos = expandirRangoCodigoCliente(
            $('#cliente_codigo_desde').val(),
            $('#cliente_codigo_hasta').val(),
        );
        if (!codigos.length) {
            alert('Indique al menos un c\u00f3digo de cliente en el rango.');
            return;
        }

        var pendientes = codigos.length;
        var agregados = 0;
        var noEncontrados = [];

        codigos.forEach(function (codigo) {
            leerClientePorCodigo(codigo, function (data) {
                pendientes -= 1;
                if (data && data.id) {
                    if (agregarCliente(data)) {
                        agregados += 1;
                    }
                } else {
                    noEncontrados.push(codigo);
                }

                if (pendientes === 0) {
                    actualizarAvisoClientesVacios();
                    if (noEncontrados.length) {
                        alert('C\u00f3digos de cliente no registrados en el rango: ' + noEncontrados.join(', '));
                    } else if (agregados === 0) {
                        alert('Los clientes del rango ya estaban en la lista.');
                    }
                }
            });
        });
    }

    function initNombresRangoCliente() {
        resolverNombreClienteEnCampo($('#cliente_codigo_desde').val(), $('#nombrecliente_rango_desde'));
        resolverNombreClienteEnCampo($('#cliente_codigo_hasta').val(), $('#nombrecliente_rango_hasta'));
    }

    function resolverNombreMozoEnCampo(codigo, $nombre) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            $nombre.val('');
            return;
        }
        leerMozoPorCodigo(cod, function (data) {
            $nombre.val(data ? (data.nombre || '') : '');
        });
    }

    function initNombresRangoMozo() {
        resolverNombreMozoEnCampo($('#mozo_codigo_desde').val(), $('#nombremozo_rango_desde'));
        resolverNombreMozoEnCampo($('#mozo_codigo_hasta').val(), $('#nombremozo_rango_hasta'));
    }

    function tieneRangoMozoDefinido() {
        return normalizarCodigo($('#mozo_codigo_desde').val()) !== ''
            || normalizarCodigo($('#mozo_codigo_hasta').val()) !== '';
    }

    function actualizarAvisoMozosVacios() {
        var sinTabla = Object.keys(mozosSel).length === 0;
        var sinRango = !tieneRangoMozoDefinido();
        $('#aviso-sin-mozos-reporte').toggle(sinTabla && sinRango);
    }

    function pintarTablaMozos() {
        var $tbody = $('#tbody-mozos-seleccionados-reporte');
        $tbody.empty();
        var ids = Object.keys(mozosSel).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        });
        ids.forEach(function (id) {
            var item = mozosSel[id];
            $tbody.append(
                '<tr data-id="' + $('<div>').text(id).html() + '">' +
                '<td>' + $('<div>').text(item.codigo || '').html() + '</td>' +
                '<td>' + $('<div>').text(item.nombre || '').html() + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-mozo-reporte" title="Quitar">' +
                '<i class="fa fa-times"></i></button></td></tr>'
            );
        });
        actualizarAvisoMozosVacios();
    }

    function agregarMozo(data) {
        var id = normalizarId(data && data.id);
        if (!id || mozosSel[id]) {
            return false;
        }
        mozosSel[id] = {
            id: id,
            codigo: (data.codigo || '').trim(),
            nombre: (data.nombre || '').trim(),
        };
        sincronizarHiddenMozos();
        pintarTablaMozos();
        return true;
    }

    function quitarMozo(id) {
        id = normalizarId(id);
        if (!id || !mozosSel[id]) {
            return;
        }
        delete mozosSel[id];
        sincronizarHiddenMozos();
        pintarTablaMozos();
    }

    function limpiarCampoMozo() {
        $('#codigomozo_reporte').val('');
        $('#nombremozo_reporte').val('');
    }

    function aplicarMozoEnDestinoModal(data) {
        if (!data || !data.id) {
            return;
        }

        if (modalDestinoMozo === 'rango_desde') {
            $('#mozo_codigo_desde').val((data.codigo || '').trim());
            $('#nombremozo_rango_desde').val((data.nombre || '').trim());
            $('#consultamozoModal').modal('hide');
            return;
        }

        if (modalDestinoMozo === 'rango_hasta') {
            $('#mozo_codigo_hasta').val((data.codigo || '').trim());
            $('#nombremozo_rango_hasta').val((data.nombre || '').trim());
            $('#consultamozoModal').modal('hide');
            return;
        }

        agregarMozo(data);
        limpiarCampoMozo();
        $('#consultamozoModal').modal('hide');
    }

    function agregarRangoMozosDesdeCampos() {
        var codigos = expandirRangoCodigoCliente(
            $('#mozo_codigo_desde').val(),
            $('#mozo_codigo_hasta').val(),
        );
        if (!codigos.length) {
            alert('Indique al menos un c\u00f3digo de mozo en el rango.');
            return;
        }

        var pendientes = codigos.length;
        var agregados = 0;
        var noEncontrados = [];

        codigos.forEach(function (codigo) {
            leerMozoPorCodigo(codigo, function (data) {
                pendientes -= 1;
                if (data && data.id) {
                    if (agregarMozo(data)) {
                        agregados += 1;
                    }
                } else {
                    noEncontrados.push(codigo);
                }

                if (pendientes === 0) {
                    actualizarAvisoMozosVacios();
                    if (noEncontrados.length) {
                        alert('C\u00f3digos de mozo no registrados en el rango: ' + noEncontrados.join(', '));
                    } else if (agregados === 0) {
                        alert('Los mozos del rango ya estaban en la lista.');
                    }
                }
            });
        });
    }

    function resolverNombreVipEnCampo(codigo, $nombre) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            $nombre.val('');
            return;
        }
        leerVipPorCodigo(cod, function (data) {
            $nombre.val(data ? (data.nombre || '') : '');
        });
    }

    function initNombresRangoVip() {
        resolverNombreVipEnCampo($('#vip_codigo_desde').val(), $('#nombrevip_rango_desde'));
        resolverNombreVipEnCampo($('#vip_codigo_hasta').val(), $('#nombrevip_rango_hasta'));
    }

    function tieneRangoVipDefinido() {
        return normalizarCodigo($('#vip_codigo_desde').val()) !== ''
            || normalizarCodigo($('#vip_codigo_hasta').val()) !== '';
    }

    function actualizarAvisoVipsVacios() {
        var sinTabla = Object.keys(vipsSel).length === 0;
        var sinRango = !tieneRangoVipDefinido();
        $('#aviso-sin-vips-reporte').toggle(sinTabla && sinRango);
    }

    function pintarTablaVips() {
        var $tbody = $('#tbody-vips-seleccionados-reporte');
        $tbody.empty();
        var ids = Object.keys(vipsSel).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        });
        ids.forEach(function (id) {
            var item = vipsSel[id];
            $tbody.append(
                '<tr data-id="' + $('<div>').text(id).html() + '">' +
                '<td>' + $('<div>').text(item.codigo || '').html() + '</td>' +
                '<td>' + $('<div>').text(item.nombre || '').html() + '</td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-vip-reporte" title="Quitar">' +
                '<i class="fa fa-times"></i></button></td></tr>'
            );
        });
        actualizarAvisoVipsVacios();
    }

    function agregarVip(data) {
        var id = normalizarId(data && data.id);
        if (!id || vipsSel[id]) {
            return false;
        }
        vipsSel[id] = {
            id: id,
            codigo: (data.codigo || '').trim(),
            nombre: (data.nombre || '').trim(),
        };
        sincronizarHiddenVips();
        pintarTablaVips();
        return true;
    }

    function quitarVip(id) {
        id = normalizarId(id);
        if (!id || !vipsSel[id]) {
            return;
        }
        delete vipsSel[id];
        sincronizarHiddenVips();
        pintarTablaVips();
    }

    function limpiarCampoVip() {
        $('#codigovip_reporte').val('');
        $('#nombrevip_reporte').val('');
    }

    function aplicarVipEnDestinoModal(data) {
        if (!data || !data.id) {
            return;
        }

        if (modalDestinoVip === 'rango_desde') {
            $('#vip_codigo_desde').val((data.codigo || '').trim());
            $('#nombrevip_rango_desde').val((data.nombre || '').trim());
            $('#consultaclientevipModal').modal('hide');
            return;
        }

        if (modalDestinoVip === 'rango_hasta') {
            $('#vip_codigo_hasta').val((data.codigo || '').trim());
            $('#nombrevip_rango_hasta').val((data.nombre || '').trim());
            $('#consultaclientevipModal').modal('hide');
            return;
        }

        agregarVip(data);
        limpiarCampoVip();
        $('#consultaclientevipModal').modal('hide');
    }

    function agregarRangoVipsDesdeCampos() {
        var codigos = expandirRangoCodigoCliente(
            $('#vip_codigo_desde').val(),
            $('#vip_codigo_hasta').val(),
        );
        if (!codigos.length) {
            alert('Indique al menos un c\u00f3digo de cliente VIP en el rango.');
            return;
        }

        var pendientes = codigos.length;
        var agregados = 0;
        var noEncontrados = [];

        codigos.forEach(function (codigo) {
            leerVipPorCodigo(codigo, function (data) {
                pendientes -= 1;
                if (data && data.id) {
                    if (agregarVip(data)) {
                        agregados += 1;
                    }
                } else {
                    noEncontrados.push(codigo);
                }

                if (pendientes === 0) {
                    actualizarAvisoVipsVacios();
                    if (noEncontrados.length) {
                        alert('C\u00f3digos de cliente VIP no registrados en el rango: ' + noEncontrados.join(', '));
                    } else if (agregados === 0) {
                        alert('Los clientes VIP del rango ya estaban en la lista.');
                    }
                }
            });
        });
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
        actualizarAvisoClientesVacios();
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

    function leerMozoPorCodigo(codigo, callback) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            if (callback) { callback(null); }
            return;
        }
        var empresaId = empresaIdFormulario();
        if (empresaId <= 0) {
            if (callback) { callback(null); }
            return;
        }
        var url = (cfg.leerMozoUrlBase || (baseUrl() + '/ventas/gastronomia/descuento-reporte/leer-mozo'))
            + '/' + encodeURIComponent(cod)
            + '?empresa_id=' + encodeURIComponent(String(empresaId));
        $.get(url)
            .done(function (data) { if (callback) { callback(data && data.id ? data : null); } })
            .fail(function () { if (callback) { callback(null); } });
    }

    function buscarDatosMozo(consulta) {
        var empresaId = empresaIdFormulario();
        $.ajax({
            url: cfg.consultaMozoUrl || (baseUrl() + '/ventas/gastronomia/descuento-reporte/consulta-mozo'),
            type: 'POST',
            dataType: 'HTML',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                consulta: consulta || '',
                empresa_id: empresaId,
            },
        }).done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            }
            $('#datosmozo').html(html);
        }).fail(function () {
            $('#datosmozo').html('<tr><td colspan="4">Error al consultar mozos.</td></tr>');
        });
    }

    function leerVipPorCodigo(codigo, callback) {
        var cod = normalizarCodigo(codigo);
        if (!cod) {
            if (callback) { callback(null); }
            return;
        }
        var empresaId = empresaIdFormulario();
        if (empresaId <= 0) {
            if (callback) { callback(null); }
            return;
        }
        var url = (cfg.leerVipUrlBase || (baseUrl() + '/ventas/gastronomia/descuento-reporte/leer-clientevip'))
            + '/' + encodeURIComponent(cod)
            + '?empresa_id=' + encodeURIComponent(String(empresaId));
        $.get(url)
            .done(function (data) { if (callback) { callback(data && data.id ? data : null); } })
            .fail(function () { if (callback) { callback(null); } });
    }

    function buscarDatosVip(consulta) {
        var empresaId = empresaIdFormulario();
        $.ajax({
            url: cfg.consultaVipUrl || (baseUrl() + '/ventas/gastronomia/descuento-reporte/consulta-clientevip'),
            type: 'POST',
            dataType: 'HTML',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                consulta: consulta || '',
                empresa_id: empresaId,
            },
        }).done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            }
            $('#datosclientevip').html(html);
        }).fail(function () {
            $('#datosclientevip').html('<tr><td colspan="8">Error al consultar clientes VIP.</td></tr>');
        });
    }

    function agregarVipDesdeCampo() {
        var codigo = normalizarCodigo($('#codigovip_reporte').val());
        if (!codigo) { return; }
        if (empresaIdFormulario() <= 0) {
            alert('Seleccione la empresa antes de consultar clientes VIP.');
            return;
        }
        leerVipPorCodigo(codigo, function (data) {
            if (!data) {
                alert('Cliente VIP no encontrado: ' + codigo);
                return;
            }
            agregarVip(data);
            limpiarCampoVip();
        });
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

    function agregarMozoDesdeCampo() {
        var codigo = normalizarCodigo($('#codigomozo_reporte').val());
        if (!codigo) { return; }
        if (empresaIdFormulario() <= 0) {
            alert('Seleccione la empresa antes de consultar mozos.');
            return;
        }
        leerMozoPorCodigo(codigo, function (data) {
            if (!data) {
                alert('Mozo no encontrado: ' + codigo);
                return;
            }
            agregarMozo(data);
            limpiarCampoMozo();
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

        mozosSel = {};
        (cfg.mozosIniciales || []).forEach(function (item) {
            var id = normalizarId(item.id);
            if (id) {
                mozosSel[id] = {
                    id: id,
                    codigo: (item.codigo || '').trim(),
                    nombre: (item.nombre || '').trim(),
                };
            }
        });

        vipsSel = {};
        (cfg.vipsIniciales || []).forEach(function (item) {
            var id = normalizarId(item.id);
            if (id) {
                vipsSel[id] = {
                    id: id,
                    codigo: (item.codigo || '').trim(),
                    nombre: (item.nombre || '').trim(),
                };
            }
        });

        sincronizarHiddenDescuentos();
        sincronizarHiddenClientes();
        sincronizarHiddenMozos();
        sincronizarHiddenVips();
        pintarTablaDescuentos();
        pintarTablaClientes();
        pintarTablaMozos();
        pintarTablaVips();
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

        var $campo = esModoCliente()
            ? $('#codigocliente_reporte')
            : (esModoMozo()
                ? $('#codigomozo_reporte')
                : (esModoVip() ? $('#codigovip_reporte') : $('#codigodescuento_reporte')));
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
        var modoMozo = esModoMozo();
        var modoVip = esModoVip();
        var $ayuda = $('#ayuda-tipo-seleccion');
        if (!$ayuda.length) {
            return;
        }
        if (listarTodos) {
            $ayuda.text(
                'Define cómo se arman las secciones del reporte para todos los códigos/clientes/mozos/VIP con ventas en el período.',
            );
        } else if (modoVip) {
            $ayuda.text(
                'Elija clientes VIP puntuales y/o un rango de c\u00f3digos; si no ingresa nada se listan todos los clientes VIP con ventas en el per\u00edodo (canjes de marketing).',
            );
        } else if (modoMozo) {
            $ayuda.text(
                'Elija mozos puntuales y/o un rango de c\u00f3digos; si no ingresa nada se listan todos los mozos con ventas en el per\u00edodo.',
            );
        } else if (modoCliente) {
            $ayuda.text(
                'Elija clientes internos puntuales y/o un rango de c\u00f3digos; el reporte mostrar\u00e1 un bloque por cada uno '
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
        var modoMozo = esModoMozo();
        var modoVip = esModoVip();

        $('#label-tipo-seleccion').text(listarTodos ? 'Agrupar por' : 'Filtrar por');
        actualizarAyudaTipoSeleccion();

        if (modoCliente || modoMozo || modoVip) {
            $('#wrap-filtro-descuento-cliente').show();
        } else {
            $('#wrap-filtro-descuento-cliente').hide();
        }

        if (listarTodos) {
            $('#wrap-seleccion-descuento, #wrap-seleccion-cliente, #wrap-seleccion-mozo, #wrap-seleccion-vip').hide();
            return;
        }

        if (modoCliente) {
            $('#wrap-seleccion-descuento, #wrap-seleccion-mozo, #wrap-seleccion-vip').hide();
            $('#wrap-seleccion-cliente').show();
        } else if (modoMozo) {
            $('#wrap-seleccion-descuento, #wrap-seleccion-cliente, #wrap-seleccion-vip').hide();
            $('#wrap-seleccion-mozo').show();
        } else if (modoVip) {
            $('#wrap-seleccion-descuento, #wrap-seleccion-cliente, #wrap-seleccion-mozo').hide();
            $('#wrap-seleccion-vip').show();
        } else {
            $('#wrap-seleccion-descuento').show();
            $('#wrap-seleccion-cliente, #wrap-seleccion-mozo, #wrap-seleccion-vip').hide();
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
            modalDestinoCliente = $(this).data('destino') || 'seleccion';
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
            aplicarClienteEnDestinoModal({
                id: ($tr.find('td.id').first().text() || '').trim(),
                codigo: ($tr.find('td.codigo').first().text() || '').trim(),
                nombre: ($tr.find('td.nombre').first().text() || '').trim(),
            });
        });

        $('#aceptaconsultaclienteModal').off('click.cr').on('click.cr', function () {
            $('#consultaclienteModal').modal('hide');
        });
    }

    function activarEventosConsultaMozo() {
        $('.consultamozo-reporte').off('click.mr').on('click.mr', function () {
            if (empresaIdFormulario() <= 0) {
                alert('Seleccione la empresa antes de consultar mozos.');
                return;
            }
            modalDestinoMozo = $(this).data('destino') || 'seleccion';
            $('#consultamozoModal').modal('show');
        });

        $('#consultamozoModal').off('shown.bs.modal.mr').on('shown.bs.modal.mr', function () {
            $(this).find('#consultamozo').val('').focus();
            buscarDatosMozo('');
        });

        $(document).off('keyup.mrConsulta', '#consultamozo').on('keyup.mrConsulta', '#consultamozo', function () {
            buscarDatosMozo($(this).val());
        });

        $(document).off('click.mrElige', '#consultamozoModal .eligeconsultamozo').on('click.mrElige', '#consultamozoModal .eligeconsultamozo', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            aplicarMozoEnDestinoModal({
                id: ($tr.find('td.id, .id').first().text() || '').trim(),
                codigo: ($tr.find('td.codigo, .codigo').first().text() || '').trim(),
                nombre: ($tr.find('td.nombre, .nombre').first().text() || '').trim(),
            });
        });

        $('#aceptaconsultamozoModal').off('click.mr').on('click.mr', function () {
            $('#consultamozoModal').modal('hide');
        });
    }

    function activarEventosConsultaVip() {
        $('.consultavip-reporte').off('click.vr').on('click.vr', function () {
            if (empresaIdFormulario() <= 0) {
                alert('Seleccione la empresa antes de consultar clientes VIP.');
                return;
            }
            modalDestinoVip = $(this).data('destino') || 'seleccion';
            $('#consultaclientevipModal').modal('show');
        });

        $('#consultaclientevipModal').off('shown.bs.modal.vr').on('shown.bs.modal.vr', function () {
            $(this).find('#consultaclientevip').val('').focus();
            buscarDatosVip('');
        });

        $(document).off('keyup.vrConsulta', '#consultaclientevip').on('keyup.vrConsulta', '#consultaclientevip', function () {
            buscarDatosVip($(this).val());
        });

        $(document).off('click.vrElige', '#consultaclientevipModal .eligeconsultaclientevip').on('click.vrElige', '#consultaclientevipModal .eligeconsultaclientevip', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            aplicarVipEnDestinoModal({
                id: ($tr.find('td.id, .id').first().text() || '').trim(),
                codigo: ($tr.find('td.codigo, .codigo').first().text() || '').trim(),
                nombre: ($tr.find('td.nombre, .nombre').first().text() || '').trim(),
            });
        });

        $('#aceptaconsultaclientevipModal').off('click.vr').on('click.vr', function () {
            $('#consultaclientevipModal').modal('hide');
        });
    }

    $(function () {
        initSeleccionados();
        initNombresRangoCliente();
        initNombresRangoMozo();
        initNombresRangoVip();
        activarEventosConsultaDescuento();
        activarEventosConsultaCliente();
        activarEventosConsultaMozo();
        activarEventosConsultaVip();
        actualizarVisibilidadSeleccion();
        actualizarOpcionesPresentacion();
        sincronizarPresentacionColumnasHidden();
        $('#form-descuento-reporte').data('consultado', cfg.consultado ? 1 : 0);

        $('#btn-agregar-descuento-reporte').on('click', agregarDescuentoDesdeCampo);
        $('#btn-agregar-cliente-reporte').on('click', agregarClienteDesdeCampo);
        $('#btn-agregar-mozo-reporte').on('click', agregarMozoDesdeCampo);
        $('#btn-agregar-vip-reporte').on('click', agregarVipDesdeCampo);
        $('#btn-agregar-rango-cliente-reporte').on('click', agregarRangoClientesDesdeCampos);
        $('#btn-agregar-rango-mozo-reporte').on('click', agregarRangoMozosDesdeCampos);
        $('#btn-agregar-rango-vip-reporte').on('click', agregarRangoVipsDesdeCampos);

        $('#cliente_codigo_desde').on('change blur', function () {
            resolverNombreClienteEnCampo($(this).val(), $('#nombrecliente_rango_desde'));
            actualizarAvisoClientesVacios();
        });
        $('#cliente_codigo_hasta').on('change blur', function () {
            resolverNombreClienteEnCampo($(this).val(), $('#nombrecliente_rango_hasta'));
            actualizarAvisoClientesVacios();
        });

        $('#mozo_codigo_desde').on('change blur', function () {
            resolverNombreMozoEnCampo($(this).val(), $('#nombremozo_rango_desde'));
            actualizarAvisoMozosVacios();
        });
        $('#mozo_codigo_hasta').on('change blur', function () {
            resolverNombreMozoEnCampo($(this).val(), $('#nombremozo_rango_hasta'));
            actualizarAvisoMozosVacios();
        });

        $('#vip_codigo_desde').on('change blur', function () {
            resolverNombreVipEnCampo($(this).val(), $('#nombrevip_rango_desde'));
            actualizarAvisoVipsVacios();
        });
        $('#vip_codigo_hasta').on('change blur', function () {
            resolverNombreVipEnCampo($(this).val(), $('#nombrevip_rango_hasta'));
            actualizarAvisoVipsVacios();
        });

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

        $('#codigomozo_reporte').on('keydown', function (e) {
            if (e.which === 13) { e.preventDefault(); agregarMozoDesdeCampo(); }
        }).on('change', function () {
            var codigo = normalizarCodigo($(this).val());
            if (!codigo) { $('#nombremozo_reporte').val(''); return; }
            if (empresaIdFormulario() <= 0) { return; }
            leerMozoPorCodigo(codigo, function (data) {
                $('#nombremozo_reporte').val(data ? (data.nombre || '') : '');
            });
        });

        $('#codigovip_reporte').on('keydown', function (e) {
            if (e.which === 13) { e.preventDefault(); agregarVipDesdeCampo(); }
        }).on('change', function () {
            var codigo = normalizarCodigo($(this).val());
            if (!codigo) { $('#nombrevip_reporte').val(''); return; }
            if (empresaIdFormulario() <= 0) { return; }
            leerVipPorCodigo(codigo, function (data) {
                $('#nombrevip_reporte').val(data ? (data.nombre || '') : '');
            });
        });

        $(document).on('click', '.btn-quitar-descuento-reporte', function () {
            quitarDescuento($(this).closest('tr').data('codigo'));
        });

        $(document).on('click', '.btn-quitar-cliente-reporte', function () {
            quitarCliente($(this).closest('tr').data('id'));
            actualizarAvisoClientesVacios();
        });

        $(document).on('click', '.btn-quitar-mozo-reporte', function () {
            quitarMozo($(this).closest('tr').data('id'));
            actualizarAvisoMozosVacios();
        });

        $(document).on('click', '.btn-quitar-vip-reporte', function () {
            quitarVip($(this).closest('tr').data('id'));
            actualizarAvisoVipsVacios();
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
            sincronizarHiddenMozos();
            sincronizarHiddenVips();
            $('#refrescar_cache_descuento_reporte').val('1');
            $('#aviso-reconsultar-descuento-reporte').hide();
            $('#btn-consultar-descuento-reporte').removeClass('btn-warning').addClass('btn-primary');
            mostrarOverlayExportacion('Consultando reporte…', 'Procesando ventas y costos. Por favor espere.');

            if (listarTodosActivo()) {
                $('#codigos_descuento').val('');
                $('#clientes_descuento_ids').val('');
                $('#mozos_descuento_ids').val('');
                $('#vips_descuento_ids').val('');
                $('#cliente_codigo_desde, #cliente_codigo_hasta, #mozo_codigo_desde, #mozo_codigo_hasta, #vip_codigo_desde, #vip_codigo_hasta').prop('disabled', false);
                return true;
            }

            if (esModoCliente()) {
                $('#codigos_descuento').val('');
                $('#mozos_descuento_ids').val('');
                $('#vips_descuento_ids').val('');
                $('#mozo_codigo_desde, #mozo_codigo_hasta, #vip_codigo_desde, #vip_codigo_hasta').val('');
                var sinClientesPuntuales = normalizarCodigo($('#clientes_descuento_ids').val()) === '';
                var sinRango = !tieneRangoClienteDefinido();
                if (sinClientesPuntuales && sinRango) {
                    e.preventDefault();
                    ocultarOverlayExportacion();
                    alert('Seleccione al menos un cliente interno, defina un rango de c\u00f3digos, o marque Listar todos.');
                    return false;
                }
            } else if (esModoMozo()) {
                $('#codigos_descuento').val('');
                $('#clientes_descuento_ids').val('');
                $('#vips_descuento_ids').val('');
                $('#cliente_codigo_desde, #cliente_codigo_hasta, #vip_codigo_desde, #vip_codigo_hasta').val('');
            } else if (esModoVip()) {
                $('#codigos_descuento').val('');
                $('#clientes_descuento_ids').val('');
                $('#mozos_descuento_ids').val('');
                $('#cliente_codigo_desde, #cliente_codigo_hasta, #mozo_codigo_desde, #mozo_codigo_hasta').val('');
            } else {
                $('#clientes_descuento_ids').val('');
                $('#mozos_descuento_ids').val('');
                $('#vips_descuento_ids').val('');
                $('#cliente_codigo_desde, #cliente_codigo_hasta, #mozo_codigo_desde, #mozo_codigo_hasta, #vip_codigo_desde, #vip_codigo_hasta').val('');
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
