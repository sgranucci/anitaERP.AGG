(function () {
    'use strict';

    var $card = $('#card-cot-guia');
    if (!$card.length) {
        return;
    }

    var urls = $card.data('urls') || {};
    var csrf = $card.data('csrf') || $('input[name="_token"]').first().val();
    var arcaConstanciaUrl = $card.data('arca-constancia-url') || '';
    var suburbanoHabilitado = String($card.data('suburbano-habilitado') || '0') === '1';
    var suburbanoExcelBase = String($card.data('suburbano-excel-base') || '').replace(/\/$/, '');
    var consultaCuitXhr = null;

    function token() {
        return csrf || $('input[name="_token"]').first().val() || '';
    }

    function soloDigitosCuit(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function formatearInputCuit(input) {
        if (input && typeof window.formatarCUIT === 'function') {
            window.formatarCUIT(input);
        }
    }

    function esCuitValido(valor) {
        var cuit = soloDigitosCuit(valor);
        if (cuit.length !== 11) {
            return false;
        }

        var codes = '6789456789';
        var resultado = 0;
        var x;

        for (x = 0; x < 10; x++) {
            resultado += parseInt(codes.charAt(x), 10) * parseInt(cuit.charAt(x), 10);
        }

        resultado = resultado % 11;

        return resultado === parseInt(cuit.charAt(10), 10);
    }

    function marcarEstadoCuit(estado) {
        var $input = $('#cuit_chofer');
        var $error = $('#fila-cuit-chofer-cot .cot-cuit-error');
        var $titular = $('#titular_cuit_chofer');

        $input.removeClass('is-valid is-invalid');

        if (estado === 'valido') {
            $input.addClass('is-valid');
            $error.addClass('d-none');
            return;
        }

        if (estado === 'invalido') {
            $input.addClass('is-invalid');
            $error.removeClass('d-none');
            $titular.val('').attr('title', 'CUIT inválida');
            return;
        }

        $error.addClass('d-none');
        $titular.val('').attr('title', 'Se completa al validar la CUIT en padrón ARCA');
    }

    function nombreDesdePadronArca(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }
        if (data.nombre) {
            return String(data.nombre).trim();
        }
        if (data.razonSocial) {
            return String(data.razonSocial).trim();
        }
        return String((data.apellido || '') + ' ' + (data.nombre || '')).trim();
    }

    function consultarTitularCuit() {
        var $input = $('#cuit_chofer');
        if (!$input.length) {
            return $.Deferred().resolve().promise();
        }

        var cuit = soloDigitosCuit($input.val());
        var $titular = $('#titular_cuit_chofer');
        var $loading = $('#fila-cuit-chofer-cot .cot-cuit-loading');

        if (consultaCuitXhr && consultaCuitXhr.readyState !== 4) {
            consultaCuitXhr.abort();
        }

        if (cuit === '') {
            marcarEstadoCuit('vacio');
            return $.Deferred().resolve().promise();
        }

        formatearInputCuit($input[0]);
        cuit = soloDigitosCuit($input.val());

        if (!esCuitValido(cuit)) {
            marcarEstadoCuit('invalido');
            return $.Deferred().resolve().promise();
        }

        marcarEstadoCuit('valido');

        if (!arcaConstanciaUrl) {
            $titular.val('').attr('title', 'Consulta padrón no configurada');
            return $.Deferred().resolve().promise();
        }

        $loading.removeClass('d-none');

        consultaCuitXhr = $.ajax({
            url: arcaConstanciaUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || token(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            contentType: 'application/json; charset=UTF-8',
            data: JSON.stringify({ cuit: cuit }),
        });

        return consultaCuitXhr
            .done(function (resp) {
                var nombre = '';
                if (resp && resp.ok && resp.data) {
                    nombre = nombreDesdePadronArca(resp.data);
                }
                if (nombre !== '') {
                    $titular.val(nombre).attr('title', nombre);
                    return;
                }
                var mensaje = (resp && resp.message) ? resp.message : 'Sin datos en padrón ARCA';
                $titular.val(mensaje).attr('title', mensaje);
            })
            .fail(function (jqXHR, status) {
                if (status === 'abort') {
                    return;
                }
                var mensaje = 'No se pudo consultar padrón ARCA';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    mensaje = jqXHR.responseJSON.message;
                }
                $titular.val(mensaje).attr('title', mensaje);
            })
            .always(function () {
                $loading.addClass('d-none');
            });
    }

    function esTeclaEnter(e) {
        return e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13);
    }

    function enfocar($el) {
        var $input = $($el);
        if (!$input.length) {
            return;
        }
        setTimeout(function () {
            $input.trigger('focus');
            if ($input[0] && typeof $input[0].select === 'function') {
                $input[0].select();
            }
        }, 0);
    }

    function primeraFacturaEditable() {
        var $primera = $('#tabla-cot-guia-lineas tbody tr.fila-cot-guia-linea')
            .first()
            .find('.linea-factura-codigo');
        if (!$primera.length) {
            agregarLinea();
            $primera = $('#tabla-cot-guia-lineas tbody tr.fila-cot-guia-linea')
                .first()
                .find('.linea-factura-codigo');
        }
        return $primera;
    }

    function inicializarCuitChofer() {
        var $input = $('#cuit_chofer');
        if (!$input.length) {
            return;
        }
        formatearInputCuit($input[0]);
        if (soloDigitosCuit($input.val()).length === 11) {
            consultarTitularCuit();
        }
    }

    function esTransporteSuburbano() {
        var nombre = String($('#nombretransporte').val() || '').toUpperCase();
        var codigo = String($('#transporte_codigo').val() || '').trim();
        if (nombre.indexOf('SUBURBANO') !== -1) {
            return true;
        }
        return codigo === '88';
    }

    function actualizarBotonGuiaSuburbano() {
        var $btn = $('#btn-guia-suburbano-excel');
        if (!$btn.length || !suburbanoHabilitado) {
            return;
        }
        var guiaId = String($('#guia_id').val() || '').trim();
        var mostrar = guiaId !== '' && esTransporteSuburbano();
        if (!mostrar) {
            $btn.addClass('d-none').attr('href', '#');
            return;
        }
        var href = urls.suburbanoExcel || '';
        if (!href && suburbanoExcelBase) {
            href = suburbanoExcelBase + '/' + encodeURIComponent(guiaId) + '/suburbano-excel';
        }
        $btn.removeClass('d-none').attr('href', href || '#');
    }

    function actualizarTotales() {
        var bultos = 0;
        var pares = 0;
        var valor = 0;
        var cant = 0;
        $('#tabla-cot-guia-lineas tbody tr.fila-cot-guia-linea').each(function () {
            var $tr = $(this);
            if (!$tr.find('.linea-numero').val()) {
                return;
            }
            cant++;
            bultos += parseFloat(String($tr.find('.linea-bultos').val() || '0').replace(',', '.')) || 0;
            pares += parseFloat(String($tr.find('.linea-cantidad').val() || '0').replace(',', '.')) || 0;
            valor += parseFloat(String($tr.find('.linea-valor').val() || '0').replace(',', '.')) || 0;
        });
        $('#tot-bultos').text(bultos.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }));
        $('#tot-pares').text(pares.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }));
        $('#tot-valor').text(valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#tot-cant').text(cant + ' factura(s)');
    }

    function aplicarLinea($tr, linea) {
        $tr.find('.linea-tipo').val(linea.tipo || '');
        $tr.find('.linea-letra').val(linea.letra || '');
        $tr.find('.linea-sucursal').val(linea.sucursal || '');
        $tr.find('.linea-numero').val(linea.numero || '');
        $tr.find('.linea-venta-id').val(linea.venta_id || '');
        $tr.find('.linea-transporte-id').val(linea.transporte_id || '');
        $tr.find('.linea-transporte-codigo').val(linea.transporte_codigo || '');
        $tr.find('.linea-entrega').val(linea.entrega || '');
        $tr.find('.linea-cliente-codigo').val(linea.cliente_codigo || '');
        $tr.find('.linea-cliente-nombre').val(linea.cliente_nombre || '');
        $tr.find('.linea-bultos').val(linea.bultos != null ? Number(linea.bultos).toFixed(2) : '');
        $tr.find('.linea-cantidad').val(linea.cantidad != null ? Number(linea.cantidad).toFixed(2) : '');
        $tr.find('.linea-valor').val(linea.valor_declarado != null ? Number(linea.valor_declarado).toFixed(2) : '');
        $tr.find('.linea-factura-codigo').val(linea.etiqueta || [
            linea.tipo, linea.letra + '-' + String(linea.sucursal || 0).padStart(4, '0') + '-' + String(linea.numero || 0).padStart(8, '0')
        ].join(' '));
        $tr.find('.linea-error').addClass('d-none').text('');
        actualizarTotales();
    }

    function resolverFactura($tr, avanzar) {
        var codigo = String($tr.find('.linea-factura-codigo').val() || '').trim();
        if (!codigo) {
            if (avanzar) {
                enfocar($tr.find('.linea-bultos'));
            }
            return;
        }
        var $err = $tr.find('.linea-error');
        $err.addClass('d-none').text('');

        $.ajax({
            url: urls.resolver,
            method: 'POST',
            data: { _token: token(), codigo: codigo },
            success: function (resp) {
                if (!resp || !resp.ok) {
                    $err.removeClass('d-none').text((resp && resp.mensaje) || 'No encontrada');
                    enfocar($tr.find('.linea-factura-codigo'));
                    return;
                }
                aplicarLinea($tr, resp.linea || {});
                if (avanzar) {
                    enfocar($tr.find('.linea-bultos'));
                }
            },
            error: function () {
                $err.removeClass('d-none').text('Error al resolver factura');
                enfocar($tr.find('.linea-factura-codigo'));
            }
        });
    }

    function agregarLinea(linea) {
        var html = $('#tpl-cot-guia-linea').html();
        var $tr = $(html);
        $('#tabla-cot-guia-lineas tbody').append($tr);
        if (linea) {
            aplicarLinea($tr, linea);
        }
        return $tr;
    }

    function claveFactura($tr) {
        return [
            String($tr.find('.linea-tipo').val() || ''),
            String($tr.find('.linea-letra').val() || ''),
            String($tr.find('.linea-sucursal').val() || ''),
            String($tr.find('.linea-numero').val() || '')
        ].join('|');
    }

    function clavesExistentes() {
        var map = {};
        $('#tabla-cot-guia-lineas tbody tr.fila-cot-guia-linea').each(function () {
            var k = claveFactura($(this));
            if (k !== '|||' && k.indexOf('||') !== 0) {
                map[k] = true;
            }
        });
        return map;
    }

    function mirrorCamposParaEnviar() {
        var $mirror = $('#enviar-campos-mirror').empty();
        var $form = $('#form-cot-guia-guardar');
        $form.find('input[name], select[name], textarea[name]').each(function () {
            var $el = $(this);
            var name = $el.attr('name');
            if (!name || name === '_token') {
                return;
            }
            $('<input type="hidden">').attr('name', name).val($el.val()).appendTo($mirror);
        });
        var imprimir = $('#imprimir_al_procesar').is(':checked') ? '1' : '0';
        $mirror.append($('<input type="hidden" name="imprimir_al_procesar">').val(imprimir));
    }

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('cot-guia-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('cot-guia-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('cot-guia-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    $(document).on('click', '#btn-agregar-linea', function () {
        agregarLinea().find('.linea-factura-codigo').focus();
    });

    $(document).on('click', '.btn-quitar-linea', function () {
        var $tbody = $('#tabla-cot-guia-lineas tbody');
        var $tr = $(this).closest('tr');
        if ($tbody.find('tr.fila-cot-guia-linea').length <= 1) {
            $tr.find('input').val('');
            actualizarTotales();
            return;
        }
        $tr.remove();
        actualizarTotales();
    });

    $(document).on('keydown', '.linea-factura-codigo', function (e) {
        if (e.key === 'F1') {
            e.preventDefault();
            resolverFactura($(this).closest('tr'), false);
            return;
        }
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        resolverFactura($(this).closest('tr'), true);
    });

    $(document).on('keydown', '.linea-bultos', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        enfocar($(this).closest('tr').find('.linea-cantidad'));
    });

    $(document).on('keydown', '.linea-cantidad', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        enfocar($(this).closest('tr').find('.linea-valor'));
    });

    $(document).on('keydown', '.linea-valor', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var $next = $tr.next('tr.fila-cot-guia-linea');
        if (!$next.length) {
            agregarLinea();
            $next = $tr.next('tr.fila-cot-guia-linea');
        }
        enfocar($next.find('.linea-factura-codigo'));
    });

    $(document).on('click', '.btn-resolver-factura', function () {
        resolverFactura($(this).closest('tr'), true);
    });

    $(document).on('input', '.linea-bultos, .linea-cantidad, .linea-valor', actualizarTotales);

    $('#btn-consulta-guia').on('click', function () {
        $('#consultaGuiaCotModal').modal('show');
        buscarGuias();
    });

    $('#numero_guia').on('keydown', function (e) {
        if (e.key === 'F1') {
            e.preventDefault();
            $('#btn-consulta-guia').click();
            return;
        }
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        var n = parseInt($(this).val(), 10);
        if (n > 0) {
            window.location = (urls.leer || '') + '?numero_guia=' + n;
            return;
        }
        enfocar('#fecha');
    });

    $('#fecha').on('keydown', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        if (!$(this).val()) {
            enfocar('#fecha');
            return;
        }
        enfocar('#transporte_codigo');
    });

    /**
     * Enter en expreso: captura nativa antes que transporte/consulta.js
     * (ese handler no tiene focusSiguiente en esta pantalla).
     */
    function onEnterTransporteGuia(e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }

        var codigo = $.trim($('#transporte_codigo').val() || '');
        var $ctx = $('#transporte_codigo').closest('.tm-transporte-campo');

        if (codigo === '') {
            enfocar('#cuit_chofer');
            return false;
        }

        if (typeof window.resolverPorCodigoTransporte === 'function') {
            window.resolverPorCodigoTransporte(codigo, $ctx.length ? $ctx : null, {
                focusSiguiente: '#cuit_chofer',
            });
            // Rellena CUIT/dominio del maestro (misma lectura) y refuerza el foco.
            setTimeout(function () {
                rellenarChoferDesdeTransporte(function () {
                    enfocar('#cuit_chofer');
                });
            }, 200);
        } else {
            rellenarChoferDesdeTransporte(function () {
                enfocar('#cuit_chofer');
            });
        }
        return false;
    }

    var elTransporte = document.getElementById('transporte_codigo');
    if (elTransporte) {
        elTransporte.addEventListener('keydown', onEnterTransporteGuia, true);
        elTransporte.addEventListener('keypress', onEnterTransporteGuia, true);
    }
    $(document).on('keydown.cotGuiaTransporte keypress.cotGuiaTransporte', '#transporte_codigo', onEnterTransporteGuia);

    function buscarGuias() {
        $.ajax({
            url: urls.consultar,
            method: 'POST',
            data: { _token: token(), texto: $('#consulta-guia-texto').val() },
            success: function (resp) {
                $('#consulta-guia-body').html(resp.data || '');
            }
        });
    }

    $('#consulta-guia-texto').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarGuias();
        }
    });

    $(document).on('click', '.elige-cot-guia', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        if (id) {
            window.location = (urls.leer || '') + '?guia_id=' + id;
        }
    });

    $('#btn-pendientes-dia').on('click', function () {
        var fecha = $('#fecha').val();
        if (!fecha) {
            alert('Indique la fecha de la guía');
            return;
        }
        mostrarOverlay('Buscando facturas pendientes…');
        $.ajax({
            url: urls.pendientes,
            method: 'POST',
            data: {
                _token: token(),
                fecha: fecha,
                transporte_id: $('#transporte_id').val() || '',
                guia_id: $('#guia_id').val() || ''
            },
            success: function (resp) {
                ocultarOverlay();
                var filas = (resp && resp.filas) || [];
                var html = '';
                if (!filas.length) {
                    var emitidas = Number((resp && resp.cantidad_emitidas) || 0);
                    var enGuia = Number((resp && resp.cantidad_en_guia) || 0);
                    var sinImporte = Number((resp && resp.cantidad_sin_importe) || 0);
                    var totalDia = Number((resp && resp.cantidad_total_dia) || 0);
                    var msg = 'No hay pendientes para la fecha';
                    if (emitidas > 0) {
                        msg = 'Las ' + emitidas + ' factura(s) del día ya tienen COT emitido';
                        if (enGuia > 0) {
                            msg += ' (o ya están en esta guía)';
                        }
                    } else if (enGuia > 0) {
                        msg = 'Las facturas del día ya están en esta guía';
                    } else if (sinImporte > 0 && totalDia > 0) {
                        msg = 'Hay remitos del día sin importe de factura utilizable para COT';
                    } else if (totalDia === 0) {
                        msg = 'No hay remitos/facturas Anita ni ERP para la fecha';
                    }
                    html = '<tr><td colspan="5" class="text-center text-muted">' + msg + '</td></tr>';
                } else {
                    filas.forEach(function (f, idx) {
                        html += '<tr class="fila-pendiente" data-idx="' + idx + '">'
                            + '<td class="text-center"><input type="checkbox" class="check-pendiente" checked></td>'
                            + '<td>' + (f.etiqueta || f.factura_codigo || '') + '</td>'
                            + '<td>' + (f.cliente_nombre || '') + '</td>'
                            + '<td>' + (f.transporte_codigo || '') + '</td>'
                            + '<td class="text-right">' + Number(f.importe || f.valor_declarado || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) + '</td>'
                            + '</tr>';
                    });
                    window.__cotPendientesFilas = filas;
                }
                $('#pendientes-cot-body').html(html);
                $('#pendientesCotModal').modal('show');
            },
            error: function () {
                ocultarOverlay();
                alert('No se pudieron cargar las facturas pendientes');
            }
        });
    });

    $('#check-todos-pendientes').on('change', function () {
        $('.check-pendiente').prop('checked', $(this).is(':checked'));
    });

    $('#btn-agregar-pendientes').on('click', function () {
        var filas = window.__cotPendientesFilas || [];
        var existentes = clavesExistentes();
        var vacia = $('#tabla-cot-guia-lineas tbody tr.fila-cot-guia-linea').filter(function () {
            return !$(this).find('.linea-numero').val();
        }).first();

        $('#pendientes-cot-body tr.fila-pendiente').each(function () {
            if (!$(this).find('.check-pendiente').is(':checked')) {
                return;
            }
            var idx = parseInt($(this).data('idx'), 10);
            var f = filas[idx];
            if (!f) {
                return;
            }
            var k = [f.tipo, f.letra, f.sucursal, f.numero].join('|');
            if (existentes[k]) {
                return;
            }
            existentes[k] = true;
            if (vacia && vacia.length) {
                aplicarLinea(vacia, f);
                vacia = null;
            } else {
                agregarLinea(f);
            }
        });
        $('#pendientesCotModal').modal('hide');
        actualizarTotales();
    });

    // Prefill CUIT/dominio al elegir transporte y pasar foco al CUIT
    $(document).on('click', '#aceptaconsultatransporteModal, .eligeconsultatransporte', function () {
        setTimeout(function () {
            rellenarChoferDesdeTransporte(function () {
                if ($('#card-cot-guia').length) {
                    enfocar('#cuit_chofer');
                }
            });
            actualizarBotonGuiaSuburbano();
        }, 200);
    });
    $(document).on('blur change', '#transporte_codigo', function () {
        setTimeout(function () {
            rellenarChoferDesdeTransporte();
            actualizarBotonGuiaSuburbano();
        }, 200);
    });
    $(document).on('input change', '#nombretransporte', function () {
        actualizarBotonGuiaSuburbano();
    });

    $('#btn-guia-suburbano-excel').on('click', function (e) {
        if ($(this).hasClass('d-none')) {
            e.preventDefault();
            return;
        }
        if (!esTransporteSuburbano()) {
            e.preventDefault();
            alert('La guía Excel suburbano solo aplica cuando el expreso es suburbano.');
            return;
        }
        if (!$('#guia_id').val()) {
            e.preventDefault();
            alert('Guarde la guía antes de descargar el Excel suburbano.');
        }
    });

    function rellenarChoferDesdeTransporte(done) {
        var codigo = $.trim($('#transporte_codigo').val() || '');
        var finish = typeof done === 'function' ? done : function () {};
        if (!codigo) {
            finish();
            return;
        }
        $.ajax({
            url: (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/ventas/leertransporte/' + encodeURIComponent(codigo),
            method: 'GET',
            success: function (data) {
                if (!data) {
                    finish();
                    return;
                }
                if (data.cuit_chofer) {
                    $('#cuit_chofer').val(data.cuit_chofer);
                    formatearInputCuit(document.getElementById('cuit_chofer'));
                    consultarTitularCuit().always(function () {
                        if (!$('#dominio').val() && (data.patentevehiculo || data.patente)) {
                            $('#dominio').val(data.patentevehiculo || data.patente);
                        }
                        finish();
                    });
                    return;
                }
                if (!$('#dominio').val() && (data.patentevehiculo || data.patente)) {
                    $('#dominio').val(data.patentevehiculo || data.patente);
                }
                finish();
            },
            error: function () {
                finish();
            }
        });
    }

    $(document).on('input', '#cuit_chofer, .input-cuit-chofer-guia', function () {
        formatearInputCuit(this);
        $('#titular_cuit_chofer').val('');
        marcarEstadoCuit('vacio');
    });

    $(document).on('blur', '#cuit_chofer, .input-cuit-chofer-guia', function () {
        consultarTitularCuit();
    });

    /**
     * Enter en CUIT: captura nativa antes que el bloqueo global
     * $('input').keydown de transporte/consulta.js (return false corta bubbling).
     */
    function onEnterCuitChofer(e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }

        var $input = $('#cuit_chofer');
        if ($input.prop('readonly') || $input.prop('disabled')) {
            return false;
        }

        formatearInputCuit($input[0]);
        var cuit = soloDigitosCuit($input.val());

        if (cuit !== '' && !esCuitValido(cuit)) {
            marcarEstadoCuit('invalido');
            enfocar('#cuit_chofer');
            return false;
        }

        consultarTitularCuit().always(function () {
            enfocar('#dominio');
        });
        return false;
    }

    var elCuit = document.getElementById('cuit_chofer');
    if (elCuit) {
        elCuit.addEventListener('keydown', onEnterCuitChofer, true);
        elCuit.addEventListener('keypress', onEnterCuitChofer, true);
    }
    $('#cuit_chofer').on('keydown.cotGuiaCuit keypress.cotGuiaCuit', onEnterCuitChofer);

    function onEnterDominio(e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        var $dom = $('#dominio');
        if ($dom.prop('readonly') || $dom.prop('disabled')) {
            return false;
        }
        var dominio = String($dom.val() || '').trim().toUpperCase();
        $dom.val(dominio);
        enfocar(primeraFacturaEditable());
        return false;
    }

    var elDominio = document.getElementById('dominio');
    if (elDominio) {
        elDominio.addEventListener('keydown', onEnterDominio, true);
        elDominio.addEventListener('keypress', onEnterDominio, true);
    }
    $('#dominio').on('keydown.cotGuiaDominio keypress.cotGuiaDominio', onEnterDominio);

    // Evitar que Enter en cabecera/grilla envíe el form de guardar.
    $('#form-cot-guia-guardar').on('keydown', 'input:not([type=hidden]):not([type=submit]):not([type=button])', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }
        e.preventDefault();
    });

    $('#form-cot-guia-enviar').on('submit', function (e) {
        var tiene = false;
        $('#tabla-cot-guia-lineas tbody tr.fila-cot-guia-linea').each(function () {
            if ($(this).find('.linea-numero').val()) {
                tiene = true;
            }
        });
        if (!tiene) {
            e.preventDefault();
            alert('Agregue al menos una factura a la guía');
            return;
        }
        if (!$('#transporte_id').val() && !$('#transporte_codigo').val()) {
            e.preventDefault();
            alert('Indique el expreso de cabecera');
            return;
        }
        if (!confirm('¿Guardar la guía y enviar a ARBA?')) {
            e.preventDefault();
            return;
        }
        mirrorCamposParaEnviar();
        mostrarOverlay('Enviando COT a ARBA…');
    });

    window.addEventListener('pageshow', ocultarOverlay);
    actualizarTotales();
    actualizarBotonGuiaSuburbano();
    inicializarCuitChofer();

    if (typeof window.activa_eventos_consultatransporte === 'function') {
        window.activa_eventos_consultatransporte();
    }

    // Si vinieron a ver una sesión, el detalle queda arriba: scrollear ahí (AdminLTE no honra #hash).
    var $detalle = $('#sesion-detalle');
    if ($detalle.length) {
        setTimeout(function () {
            var el = $detalle[0];
            if (el && typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 80);
    } else if (!$('#transporte_codigo').prop('readonly')) {
        // Arranque: foco en expreso/transporte para cargar ágil.
        enfocar('#transporte_codigo');
    } else if (!$('#cuit_chofer').prop('readonly')) {
        enfocar('#cuit_chofer');
    }
})();
