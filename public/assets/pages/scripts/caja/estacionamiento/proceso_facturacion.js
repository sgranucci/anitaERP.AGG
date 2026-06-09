(function ($) {
    'use strict';

    const G = window.ESTACIONAMIENTO || {};
    const apiBase = (G.rutas && G.rutas.apiBase) ? G.rutas.apiBase.replace(/\/$/, '') : '';

    let cuenta = null;
    let categorias = [];
    let itemsCatalogo = [];
    let cuentasCaja = [];
    let cuentasCajaPorId = {};
    let cuentacajaxcodigo = null;
    let monedaFacturaId = G.monedaFacturaId || 1;
    let emitiendo = false;

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function toast(msg, tipo) {
        if (typeof toastr !== 'undefined') {
            toastr[tipo || 'warning'](msg);
        } else {
            alert(msg);
        }
    }

    function avisoModal(titulo, cuerpo) {
        $('#modal-est-aviso-titulo').text(titulo || 'Aviso');
        $('#modal-est-aviso-body').text(cuerpo || '');
        $('#modal-est-aviso').modal('show');
    }

    function csrfHeaders() {
        return {
            'X-CSRF-TOKEN': G.csrf || $('meta[name="csrf-token"]').attr('content'),
            Accept: 'application/json',
        };
    }

    async function api(method, path, data) {
        const opts = {
            method: method,
            headers: csrfHeaders(),
        };
        if (data !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        const res = await fetch(apiBase + path, opts);
        const json = await res.json().catch(function () {
            return { error: 'Respuesta inválida del servidor' };
        });
        if (!res.ok) {
            throw new Error(json.error || json.mensaje || ('Error HTTP ' + res.status));
        }
        return json;
    }

    function exigirOperacion() {
        if (!G.tieneCfgPv) {
            toast('Configure el punto de venta estacionamiento para esta terminal.', 'error');
            return false;
        }
        if (G.jornadaObligatoria && G.jornada && !G.jornada.jornada_abierta) {
            toast('Debe abrir la jornada antes de operar.', 'error');
            return false;
        }
        if (G.requiereHabilitacionTurno && G.turnoOperativo && !G.turnoOperativo.turno_habilitado) {
            toast('Debe habilitar el turno en esta terminal.', 'error');
            return false;
        }
        return true;
    }

    function categoriaIdActual() {
        const v = $('#est-categoria-select').val();
        return v ? parseInt(v, 10) : 0;
    }

    function actualizarBarraCategoria() {
        const id = categoriaIdActual();
        const cat = categorias.find(function (c) { return parseInt(c.id, 10) === id; });
        const bar = $('#est-bar-categoria');
        if (id > 0 && cat) {
            bar.removeClass('sin-categoria');
            $('#est-categoria-nombre-visible').removeClass('d-none').text(cat.nombre);
        } else {
            bar.addClass('sin-categoria');
            $('#est-categoria-nombre-visible').addClass('d-none').text('');
        }
    }

    function pintarCategoriasSelect() {
        const sel = $('#est-categoria-select');
        const prev = sel.val();
        sel.find('option:not(:first)').remove();
        categorias.forEach(function (c) {
            sel.append($('<option></option>').val(c.id).text(c.nombre));
        });
        if (prev) {
            sel.val(prev);
        }
        actualizarBarraCategoria();
    }

    function renderIconosItems() {
        const cont = $('#est-items-iconos');
        cont.empty();
        if (!itemsCatalogo.length) {
            $('#est-items-vacio').removeClass('d-none');
            return;
        }
        $('#est-items-vacio').addClass('d-none');
        itemsCatalogo.forEach(function (it) {
            const btn = $('<button type="button" class="est-item-icono"></button>');
            btn.attr('title', it.nombre);
            btn.append($('<span class="est-item-id"></span>').text('#' + it.id));
            btn.append($('<span></span>').text(it.nombre));
            btn.append($('<span class="est-item-precio"></span>').text('$ ' + fmt(it.precio)));
            btn.on('click', function () {
                seleccionarItemPreview(it);
                agregarItemActual();
            });
            cont.append(btn);
        });
    }

    function seleccionarItemPreview(it) {
        $('#est-item-id-input').val(it.id);
        $('#est-item-nombre-preview').val(it.nombre + ' — $ ' + fmt(it.precio));
    }

    async function cargarItemsCatalogo() {
        const catId = categoriaIdActual();
        if (catId <= 0) {
            itemsCatalogo = [];
            renderIconosItems();
            return;
        }
        try {
            const data = await api('GET', '/items-catalogo?categoria_id=' + catId);
            itemsCatalogo = data.items || [];
            renderIconosItems();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cargarCategorias() {
        try {
            const data = await api('GET', '/categorias');
            categorias = data.categorias || [];
            pintarCategoriasSelect();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function aplicarClienteDescuentoFijo() {
        const cli = G.clienteDescuento;
        if (cli && cli.id) {
            $('#cliente_descuento_id').val(cli.id);
            $('#codigocliente_descuento').val(cli.codigo != null ? cli.codigo : G.clienteDescuentoCodigo);
            $('#nombrecliente_descuento').val(cli.nombre || '');
            const txt = (cli.codigo != null ? cli.codigo : G.clienteDescuentoCodigo) + ' — ' + (cli.nombre || '');
            $('#modal-f8-cliente-descuento-texto').text(txt);
        } else {
            $('#modal-f8-cliente-descuento-texto').text('Código ' + (G.clienteDescuentoCodigo || '501') + ' (no encontrado en maestro)');
        }
    }

    function pintarDescuento(data) {
        if (!data || !data.id) {
            $('#descuento_estacionamiento_id').val('');
            $('#codigodescuento').val('');
            $('#nombredescuento').val('');
            $('#panel-cliente-descuento').addClass('d-none');
            return;
        }
        $('#descuento_estacionamiento_id').val(data.id);
        $('#codigodescuento').val(data.codigo != null ? String(data.codigo) : '');
        $('#nombredescuento').val(data.nombre || '');
        $('#panel-cliente-descuento').removeClass('d-none');
        aplicarClienteDescuentoFijo();
    }

    async function cargarDescuentoPorCodigo(codigo) {
        codigo = String(codigo || '').trim();
        if (!codigo) {
            pintarDescuento(null);
            return null;
        }
        try {
            const res = await fetch(G.rutas.descuentoLeer + '/' + encodeURIComponent(codigo), {
                headers: csrfHeaders(),
            });
            const data = await res.json();
            if (!res.ok || !data.id) {
                throw new Error(data.error || data.mensaje || 'Descuento no encontrado');
            }
            pintarDescuento(data);
            return data;
        } catch (e) {
            toast(e.message, 'error');
            return null;
        }
    }

    function datosCabeceraDesdeFormulario() {
        return {
            categoria_automovil_estacionamiento_id: categoriaIdActual() || null,
            patente: ($('#est-patente').val() || '').trim().toUpperCase() || null,
            cliente_id: ($('#cliente_id').val() || '').trim() || null,
            descuento_estacionamiento_id: ($('#descuento_estacionamiento_id').val() || '').trim() || null,
            cliente_interno_descuento_id: ($('#cliente_descuento_id').val() || '').trim() || null,
            factura_receptor_nombre: ($('#fld-factura-receptor-nombre').val() || '').trim() || null,
            factura_receptor_documento: ($('#fld-factura-receptor-documento').val() || '').trim() || null,
            factura_receptor_domicilio: ($('#fld-factura-receptor-domicilio').val() || '').trim() || null,
        };
    }

    function aplicarCuentaEnFormulario(c) {
        if (!c) {
            return;
        }
        if (c.categoria_automovil_estacionamiento_id) {
            $('#est-categoria-select').val(c.categoria_automovil_estacionamiento_id);
            actualizarBarraCategoria();
        }
        $('#est-patente').val(c.patente || '');
        $('#cliente_id').val(c.cliente_id || '');
        $('#codigocliente').val(c.cliente && c.cliente.codigo != null ? c.cliente.codigo : '');
        $('#nombrecliente').val(c.cliente ? (c.cliente.nombre || '') : '');
        if (c.descuento_estacionamiento) {
            pintarDescuento(c.descuento_estacionamiento);
        } else {
            pintarDescuento(null);
        }
        $('#fld-factura-receptor-nombre').val(c.factura_receptor_nombre || '');
        $('#fld-factura-receptor-documento').val(c.factura_receptor_documento || '');
        $('#fld-factura-receptor-domicilio').val(c.factura_receptor_domicilio || '');
    }

    function renderLineas(c) {
        const panel = $('#panel-detalle-lineas');
        panel.empty();
        const lineas = (c && c.lineas) ? c.lineas : [];
        if (!lineas.length) {
            panel.html('<p class="text-muted small mb-0">Sin ítems cargados.</p>');
            return;
        }
        let html = '<table class="table table-sm table-striped mb-0"><thead><tr><th>Ítem</th><th class="text-right">Precio</th><th></th></tr></thead><tbody>';
        lineas.forEach(function (ln) {
            const nom = (ln.item_estacionamiento && ln.item_estacionamiento.nombre) || ln.descripcion || ('#' + ln.item_estacionamiento_id);
            html += '<tr><td>' + nom + '</td><td class="text-right">$ ' + fmt(ln.precio_unitario) + '</td>';
            html += '<td class="text-right"><button type="button" class="btn btn-xs btn-outline-danger est-quitar-linea" data-linea-id="' + ln.id + '"><i class="fa fa-trash"></i></button></td></tr>';
        });
        html += '</tbody></table>';
        panel.html(html);
    }

    function actualizarBarraCuenta(c) {
        if (!c || !c.id) {
            $('#est-bar-cuenta-activa').addClass('d-none');
            return;
        }
        $('#est-bar-cuenta-activa').removeClass('d-none');
        const cat = c.categoria_automovil ? c.categoria_automovil.nombre : 'Sin categoría';
        const pat = c.patente ? ' · ' + c.patente : '';
        const tot = c.total_facturar_ars != null ? ' · Total $ ' + fmt(c.total_facturar_ars) : '';
        $('#est-cuenta-activa-linea').text('Cuenta #' + c.id + ' · ' + cat + pat + tot);
    }

    function renderTotales(c) {
        const el = $('#est-totales-cobranza');
        if (!c) {
            el.empty();
            return;
        }
        const total = parseFloat(c.total_facturar_ars || 0);
        const cobrado = sumaCobranzaGrilla();
        let html = '<strong>Total factura: $ ' + fmt(total) + '</strong>';
        if (Math.abs(cobrado - total) > 0.02 && total > 0) {
            html += ' <span class="est-total-diff">(cobranza: $ ' + fmt(cobrado) + ')</span>';
        }
        if (c.sin_cobranza) {
            html += ' <span class="badge badge-warning">Sin cobranza</span>';
        }
        el.html(html);
        $('#factura-moneda-id').val(monedaFacturaId);
    }

    function refrescarUiCuenta(c) {
        cuenta = c;
        aplicarCuentaEnFormulario(c);
        renderLineas(c);
        actualizarBarraCuenta(c);
        renderTotales(c);
    }

    async function guardarCabecera(silencioso) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        try {
            const data = await api('PATCH', '/cuenta/' + cuenta.id, datosCabeceraDesdeFormulario());
            refrescarUiCuenta(data.cuenta);
            if (!silencioso) {
                toast('Datos guardados.', 'success');
            }
        } catch (e) {
            toast(e.message, 'error');
            throw e;
        }
    }

    async function initCuentaActiva() {
        try {
            const data = await api('GET', '/cuenta-activa');
            refrescarUiCuenta(data.cuenta);
            if (categoriaIdActual() > 0) {
                await cargarItemsCatalogo();
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function buscarItemPorId() {
        const id = parseInt($('#est-item-id-input').val(), 10);
        const catId = categoriaIdActual();
        if (catId <= 0) {
            toast('Seleccione la categoría del vehículo primero.', 'warning');
            return;
        }
        if (!id) {
            toast('Indique el ID del ítem.', 'warning');
            return;
        }
        try {
            const data = await api('GET', '/item/' + id + '?categoria_id=' + catId);
            seleccionarItemPreview(data.item);
        } catch (e) {
            $('#est-item-nombre-preview').val('');
            toast(e.message, 'error');
        }
    }

    async function agregarItemActual() {
        if (!exigirOperacion()) {
            return;
        }
        const id = parseInt($('#est-item-id-input').val(), 10);
        if (!id || !cuenta || !cuenta.id) {
            toast('Indique un ítem válido.', 'warning');
            return;
        }
        if (categoriaIdActual() <= 0) {
            toast('Seleccione la categoría antes de cargar ítems.', 'warning');
            return;
        }
        try {
            await guardarCabecera(true);
            const data = await api('POST', '/cuenta/' + cuenta.id + '/linea', {
                item_estacionamiento_id: id,
            });
            refrescarUiCuenta(data.cuenta);
            $('#est-item-id-input').val('').focus();
            $('#est-item-nombre-preview').val('');
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function quitarLinea(lineaId) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        try {
            const data = await api('DELETE', '/cuenta/' + cuenta.id + '/linea/' + lineaId);
            refrescarUiCuenta(data.cuenta);
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function sumaCobranzaGrilla() {
        let s = 0;
        $('#tbody-est-cuenta-table tr.item-cuenta-est').each(function () {
            const m = parseFloat(String($(this).find('.monto').val()).replace(',', '.')) || 0;
            s += m;
        });
        return s;
    }

    function mediosPagoDesdeGrilla() {
        const medios = [];
        $('#tbody-est-cuenta-table tr.item-cuenta-est').each(function () {
            const ccId = parseInt($(this).find('.cuentacaja_id').val(), 10);
            const monedaId = parseInt($(this).data('moneda-id'), 10) || monedaFacturaId;
            const monto = parseFloat(String($(this).find('.monto').val()).replace(',', '.')) || 0;
            if (ccId > 0 && monto > 0) {
                medios.push({ cuentacaja_id: ccId, moneda_id: monedaId, monto: monto });
            }
        });
        return medios;
    }

    function resolverIconoCuentacaja(cuenta) {
        if (!cuenta) {
            return { icono: 'fa fa-search', color: 'text-primary' };
        }
        if (cuenta.icono) {
            return {
                icono: cuenta.icono,
                color: cuenta.icono_color || cuenta.color || 'text-primary',
            };
        }
        const cfg = cuentasCajaPorId[String(cuenta.id)] || cuentasCajaPorId[parseInt(cuenta.id, 10)];
        if (cfg && cfg.icono) {
            return {
                icono: cfg.icono,
                color: cfg.icono_color || 'text-primary',
            };
        }
        return { icono: 'fa fa-search', color: 'text-primary' };
    }

    function htmlIconoMedio(info) {
        const icono = info && info.icono ? info.icono : 'fa fa-search';
        const color = info && info.color ? info.color : 'text-primary';
        if (icono.indexOf('gastro-icon-') === 0) {
            return '<span class="' + icono + '" aria-hidden="true"></span>';
        }
        return '<i class="' + icono + ' ' + color + '" aria-hidden="true"></i>';
    }

    function actualizarIconoConsultaFila(tr, cuenta) {
        if (!tr) {
            return;
        }
        const btnConsulta = tr.querySelector('.consultacuentacaja');
        if (!btnConsulta) {
            return;
        }
        btnConsulta.querySelectorAll('i, .gastro-icon-mercadopago').forEach(function (el) {
            el.remove();
        });
        btnConsulta.insertAdjacentHTML('afterbegin', htmlIconoMedio(resolverIconoCuentacaja(cuenta)));
    }

    function etiquetaCortaMedioPago(cuenta) {
        if (!cuenta) {
            return '';
        }
        if (cuenta.etiqueta_boton) {
            return String(cuenta.etiqueta_boton);
        }
        const codigo = String(cuenta.codigo || '').trim();
        if (codigo) {
            return codigo;
        }
        const nombre = String(cuenta.nombre || '').trim();
        if (!nombre) {
            return 'Medio';
        }
        const palabras = nombre.split(/\s+/).filter(Boolean);
        if (palabras.length <= 2) {
            return nombre;
        }
        return palabras.slice(0, 2).join(' ');
    }

    function asignarCuentaCajaEnFila(tr, cuenta) {
        if (!tr || !cuenta || !cuenta.id) {
            return;
        }
        const $tr = $(tr);
        $tr.find('.cuentacaja_id').val(cuenta.id);
        $tr.find('.codigo').val(cuenta.codigo || '');
        $tr.find('.nombre').val(cuenta.nombre || '');
        $tr.find('.moneda-abrev').val(cuenta.moneda_abreviatura || 'ARS');
        $tr.data('moneda-id', cuenta.moneda_id || monedaFacturaId);
        actualizarIconoConsultaFila(tr, cuenta);
        if (cuenta.monto) {
            $tr.find('.monto').val(cuenta.monto);
        }
        $tr.find('.monto').focus();
        renderTotales(cuenta);
    }

    function agregarRenglonCobranza(prefill) {
        const tpl = document.getElementById('est-template-renglon-cuenta');
        if (!tpl) {
            return;
        }
        const row = $(tpl.content.cloneNode(true)).find('tr');
        $('#tbody-est-cuenta-table').append(row);
        activarEventosCobranzaFila(row);
        if (prefill && prefill.id) {
            asignarCuentaCajaEnFila(row[0], prefill);
        } else {
            renderTotales(cuenta);
        }
    }

    function activarEventosCobranzaFila($row) {
        const tr = $row[0];
        $row.find('.est-quitar-renglon-cobranza').on('click', function () {
            $(this).closest('tr').remove();
            renderTotales(cuenta);
        });
        $row.find('.monto').on('input change', function () {
            renderTotales(cuenta);
        });
        $row.find('.codigo').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverCuentacajaPorCodigo($(this).closest('tr'));
            }
        });
        $row.find('.consultacuentacaja').on('click', function () {
            abrirConsultaCuentacajaEst(tr);
        });
    }

    function abrirConsultaCuentacajaEst(tr) {
        const emp = document.getElementById('empresa_id') || document.getElementById('est-empresa-id');
        if (emp && G.empresaId) {
            emp.value = G.empresaId;
        }
        cuentacajaxcodigo = tr.querySelector('.cuentacaja_id');
        $('#consultacuentacajaModal').one('shown.bs.modal.estCuenta', function () {
            if (typeof buscar_datos_cuentacaja === 'function') {
                buscar_datos_cuentacaja('');
            }
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    async function resolverCuentacajaPorCodigo($row) {
        const cod = ($row.find('.codigo').val() || '').trim();
        if (!cod) {
            return;
        }
        try {
            const data = await api('GET', '/cuentacaja-por-codigo/' + encodeURIComponent(cod));
            if (data.id) {
                asignarCuentaCajaEnFila($row[0], data);
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function seleccionarMedioPagoRapido(cuenta) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        const tbody = document.getElementById('tbody-est-cuenta-table');
        if (!tbody) {
            return;
        }
        let tr = Array.from(tbody.querySelectorAll('tr')).find(function (row) {
            const ccId = (row.querySelector('.cuentacaja_id')?.value || '').trim();
            return !ccId;
        });
        if (!tr) {
            agregarRenglonCobranza(null);
            tr = tbody.querySelector('tr:last-child');
        }
        if (!tr) {
            return;
        }
        asignarCuentaCajaEnFila(tr, cuenta);
    }

    function renderMediosRapidos() {
        const wrap = document.getElementById('est-medios-rapidos');
        if (!wrap) {
            return;
        }
        cuentasCajaPorId = {};
        cuentasCaja.forEach(function (c) {
            if (c && c.id) {
                cuentasCajaPorId[String(c.id)] = c;
            }
        });
        wrap.innerHTML = '';
        if (!cuentasCaja.length) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        cuentasCaja.forEach(function (cuenta) {
            const info = resolverIconoCuentacaja(cuenta);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary est-medio-rapido';
            btn.title = (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || '');
            btn.dataset.cuentacajaId = String(cuenta.id);
            btn.innerHTML = htmlIconoMedio(info) + '<span>' + etiquetaCortaMedioPago(cuenta) + '</span>';
            btn.addEventListener('click', function () {
                seleccionarMedioPagoRapido(cuenta);
            });
            wrap.appendChild(btn);
        });
    }

    async function cargarCuentasCaja() {
        try {
            const data = await api('GET', '/cuentas-caja');
            cuentasCaja = data.cuentas_caja || [];
            renderMediosRapidos();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function prepararCobranzaEfectivo() {
        const total = parseFloat(cuenta && cuenta.total_facturar_ars ? cuenta.total_facturar_ars : 0);
        if (total <= 0 || cuenta.sin_cobranza) {
            return;
        }
        if (mediosPagoDesdeGrilla().length > 0) {
            return;
        }
        const eff = G.cuentacajaEfectivo || null;
        if (!eff || !eff.id) {
            toast(G.cuentacajaEfectivoError || 'Configure cuenta de caja efectivo para F5.', 'warning');
            return;
        }
        $('#tbody-est-cuenta-table').empty();
        agregarRenglonCobranza({
            id: eff.id,
            codigo: eff.codigo,
            nombre: eff.nombre,
            moneda_id: eff.moneda_id || monedaFacturaId,
            moneda_abreviatura: eff.moneda_abreviatura || 'ARS',
            monto: total.toFixed(2),
        });
    }

    async function emitirFactura(opciones) {
        opciones = opciones || {};
        if (emitiendo || !exigirOperacion() || !cuenta || !cuenta.id) {
            return;
        }
        emitiendo = true;
        $('#est-facturacion-loading').removeClass('d-none');

        try {
            await guardarCabecera(true);
            if (!opciones.exigirDescuento) {
                prepararCobranzaEfectivo();
            }
            const medios = mediosPagoDesdeGrilla();
            const body = {
                cuenta_id: cuenta.id,
                moneda_id: monedaFacturaId,
                medios_pago: medios,
                facturacion_con_descuento: !!opciones.exigirDescuento,
            };
            const val = await api('POST', '/validar-emision', body);
            if (!val.ok) {
                throw new Error((val.errores || [val.error]).join(' '));
            }
            const res = await api('POST', '/emitir-factura', body);
            avisoModal('Factura emitida', res.factura || ('Venta #' + (res.venta_id || '')));
            await initCuentaActiva();
            $('#tbody-est-cuenta-table').empty();
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            emitiendo = false;
            $('#est-facturacion-loading').addClass('d-none');
        }
    }

    function efectivizar() {
        emitirFactura({ exigirDescuento: false });
    }

    function facturarConDescuento() {
        aplicarClienteDescuentoFijo();
        $('#modal-f8-codigo-descuento').val($('#codigodescuento').val() || '');
        $('#modal-f8-nombre-descuento').val($('#nombredescuento').val() || '');
        $('#modal-f8-descuento-id').val($('#descuento_estacionamiento_id').val() || '');
        $('#modal-f8-descuento').modal('show');
    }

    async function confirmarModalF8() {
        const cod = ($('#modal-f8-codigo-descuento').val() || '').trim();
        const desc = await cargarDescuentoPorCodigo(cod);
        if (!desc) {
            return;
        }
        const cliId = ($('#cliente_descuento_id').val() || '').trim();
        if (!cliId && G.clienteDescuento && G.clienteDescuento.id) {
            $('#cliente_descuento_id').val(G.clienteDescuento.id);
        }
        try {
            await guardarCabecera(true);
            $('#modal-f8-descuento').modal('hide');
            await emitirFactura({ exigirDescuento: true });
        } catch (e) {
            /* guardarCabecera ya mostró error */
        }
    }

    async function cerrarCuentaSinFacturar() {
        if (!cuenta || !cuenta.id) {
            return;
        }
        if (!confirm('¿Cerrar la cuenta sin facturar? Se perderán los ítems cargados.')) {
            return;
        }
        try {
            await api('POST', '/cerrar-cuenta/' + cuenta.id);
            await initCuentaActiva();
            toast('Cuenta cerrada.', 'info');
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cargarConfig() {
        try {
            const cfg = await api('GET', '/config');
            G.cuentacajaEfectivo = cfg.cuentacaja_efectivo || null;
            G.cuentacajaEfectivoError = cfg.cuentacaja_efectivo_error || null;
            monedaFacturaId = cfg.moneda_factura_id || monedaFacturaId;
            if (cfg.cliente_descuento) {
                G.clienteDescuento = cfg.cliente_descuento;
            }
            aplicarClienteDescuentoFijo();
        } catch (e) {
            /* cfg opcional al inicio */
        }
    }

    function registrarEventos() {
        $('#est-categoria-select').on('change', async function () {
            actualizarBarraCategoria();
            await guardarCabecera(true).catch(function () {});
            await cargarItemsCatalogo();
        });

        $('#btn-est-buscar-item, #est-item-id-input').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarItemPorId();
            }
        });
        $('#btn-est-buscar-item').on('click', buscarItemPorId);
        $('#btn-est-agregar-item').on('click', agregarItemActual);

        $('#btn-est-guardar-cabecera').on('click', function () { guardarCabecera(false); });
        $('#btn-est-cerrar-cuenta').on('click', cerrarCuentaSinFacturar);
        $('#tool-facturar').on('click', efectivizar);
        $('#tool-descuento').on('click', facturarConDescuento);

        $('#codigodescuento').on('change blur', function () {
            cargarDescuentoPorCodigo($(this).val());
        });

        $(document).on('click', '.est-quitar-linea', function () {
            quitarLinea($(this).data('linea-id'));
        });

        $('#est-agrega-renglon-cuenta').on('click', function () {
            agregarRenglonCobranza(null);
        });

        $('#modal-f8-descuento-confirmar').on('click', confirmarModalF8);
        $('#modal-f8-codigo-descuento').on('keydown', async function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const desc = await cargarDescuentoPorCodigo($(this).val());
                if (desc) {
                    $('#modal-f8-nombre-descuento').val(desc.nombre || '');
                    $('#modal-f8-descuento-id').val(desc.id);
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'F5') {
                e.preventDefault();
                efectivizar();
            } else if (e.key === 'F8') {
                e.preventDefault();
                facturarConDescuento();
            }
        }, true);

        if (typeof activa_eventos_consultacliente === 'function') {
            activa_eventos_consultacliente();
        }

        $(document).off('click.estCuentaElige', '.eligeconsultacuentacaja');
        $(document).on('click.estCuentaElige', '.eligeconsultacuentacaja', function () {
            if (!cuentacajaxcodigo) {
                return;
            }
            const trModal = $(this).parents('tr');
            const id = trModal.find('.cuentacaja_id').html();
            const tr = cuentacajaxcodigo.closest('tr');
            asignarCuentaCajaEnFila(tr, {
                id: parseInt(id, 10),
                nombre: trModal.find('.nombre').html(),
                codigo: trModal.find('.codigo').html(),
                moneda_id: parseInt(trModal.find('.moneda_id').html(), 10),
                moneda_abreviatura: trModal.find('.nombremoneda').html() || 'ARS',
            });
            $('#consultacuentacajaModal').modal('hide');
            cuentacajaxcodigo = null;
        });
    }

    $(async function () {
        if (!G.tieneCfgPv) {
            return;
        }
        aplicarClienteDescuentoFijo();
        registrarEventos();
        await cargarConfig();
        await cargarCategorias();
        await cargarCuentasCaja();
        await initCuentaActiva();
    });
}(jQuery));
