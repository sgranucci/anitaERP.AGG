(function ($) {
    'use strict';

    var cfg = window.CC_CLIENTES_REPORTE || {};
    var destinoModalCliente = 'seleccion';
    var destinoModalVendedor = 'seleccion';
    var clientePendiente = null;
    var vendedorPendiente = null;

    function baseUrl() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
    }

    function leerClienteUrl(codigo) {
        var base = cfg.leerClienteUrlBase || (baseUrl() + '/ventas/leerunclienteporcodigo');
        return String(base).replace(/\/$/, '') + '/' + encodeURIComponent(codigo);
    }

    function leerVendedorUrl(codigo) {
        var base = cfg.leerVendedorUrlBase || (baseUrl() + '/ventas/leervendedor');
        return String(base).replace(/\/$/, '') + '/' + encodeURIComponent(codigo);
    }

    function syncAlcanceClientes() {
        var $bloque = $('#bloque-alcance-clientes-cc');
        var alcance = $('input[name="alcance_clientes"]:checked').val() || 'todos';
        $('#panel-alcance-todos').toggle(alcance === 'todos');
        $('#panel-alcance-puntuales').toggle(alcance === 'puntuales');
        $('#panel-alcance-rango').toggle(alcance === 'rango');
        $bloque.find('.cc-reporte-alcance-card').removeClass('is-active');
        if (alcance === 'todos' || ($('input[name="alcance_vendedores"]:checked').val() || 'todos') === 'todos') {
            $bloque.find('.cc-reporte-alcance-card').has('input[value="' + alcance + '"]').addClass('is-active');
        }
        aplicarEstadoVisualCortes();
    }

    function syncAlcanceVendedores() {
        var $bloque = $('#bloque-alcance-vendedores-cc');
        var alcance = $('input[name="alcance_vendedores"]:checked').val() || 'todos';
        $('#panel-alcance-vendedores-todos').toggle(alcance === 'todos');
        $('#panel-alcance-vendedores-puntuales').toggle(alcance === 'puntuales');
        $('#panel-alcance-vendedores-rango').toggle(alcance === 'rango');
        $bloque.find('.cc-reporte-alcance-card').removeClass('is-active');
        if (alcance === 'todos' || ($('input[name="alcance_clientes"]:checked').val() || 'todos') === 'todos') {
            $bloque.find('.cc-reporte-alcance-card').has('input[value="' + alcance + '"]').addClass('is-active');
        }
        aplicarEstadoVisualCortes();
    }

    function forzarClientesTodos() {
        $('input[name="alcance_clientes"][value="todos"]').prop('checked', true);
        $('#cliente_ids').val('');
        $('#tbody-clientes-cc-reporte').empty();
        $('#cliente_codigo_desde, #cliente_codigo_hasta').val('');
        $('#nombrecliente_rango_desde, #nombrecliente_rango_hasta').val('');
        actualizarHiddenClientes();
        $('#panel-alcance-todos').show();
        $('#panel-alcance-puntuales, #panel-alcance-rango').hide();
        $('#bloque-alcance-clientes-cc .cc-reporte-alcance-card').removeClass('is-active');
    }

    function forzarVendedoresTodos() {
        $('input[name="alcance_vendedores"][value="todos"]').prop('checked', true);
        $('#vendedor_ids').val('');
        $('#tbody-vendedores-cc-reporte').empty();
        $('#vendedor_codigo_desde, #vendedor_codigo_hasta').val('');
        $('#nombrevendedor_rango_desde, #nombrevendedor_rango_hasta').val('');
        actualizarHiddenVendedores();
        $('#panel-alcance-vendedores-todos').show();
        $('#panel-alcance-vendedores-puntuales, #panel-alcance-vendedores-rango').hide();
        $('#bloque-alcance-vendedores-cc .cc-reporte-alcance-card').removeClass('is-active');
    }

    function aplicarEstadoVisualCortes() {
        var alcanceClientes = $('input[name="alcance_clientes"]:checked').val() || 'todos';
        var alcanceVendedores = $('input[name="alcance_vendedores"]:checked').val() || 'todos';
        var cortePorVendedor = alcanceVendedores !== 'todos';
        var cortePorCliente = alcanceClientes !== 'todos';

        $('#bloque-alcance-clientes-cc .cc-reporte-alcance')
            .toggleClass('is-dimmed', cortePorVendedor);
        $('#bloque-alcance-vendedores-cc .cc-reporte-alcance')
            .toggleClass('is-dimmed', cortePorCliente);

        if (cortePorVendedor) {
            $('#bloque-alcance-clientes-cc .cc-reporte-alcance-card').removeClass('is-active');
        }
        if (cortePorCliente) {
            $('#bloque-alcance-vendedores-cc .cc-reporte-alcance-card').removeClass('is-active');
        }
    }

    function syncOpcionesModo() {
        var modo = $('input[name="modo"]:checked').val() || 'deuda';
        var soloTotales = $('#solo_totales').is(':checked');
        $('#wrap-incluir-aplicaciones').toggle(modo === 'deuda' && !soloTotales);
        if (modo !== 'deuda' || soloTotales) {
            $('#incluir_aplicaciones').prop('checked', false);
        }
        var enPesos = $('input[name="expresion"]:checked').val() === 'pesos';
        $('#wrap-cotizacion-modo').toggle(enPesos);
    }

    function idsClientesDesdeTabla() {
        var ids = [];
        $('#tbody-clientes-cc-reporte tr').each(function () {
            var id = parseInt($(this).attr('data-id'), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function idsVendedoresDesdeTabla() {
        var ids = [];
        $('#tbody-vendedores-cc-reporte tr').each(function () {
            var id = parseInt($(this).attr('data-id'), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function actualizarHiddenClientes() {
        $('#cliente_ids').val(idsClientesDesdeTabla().join(','));
        var n = idsClientesDesdeTabla().length;
        $('#aviso-clientes-cc-reporte').text(
            n === 0
                ? 'Todavía no agregó clientes. Elija al menos uno para consultar.'
                : (n + ' cliente(s) en la lista.')
        );
    }

    function actualizarHiddenVendedores() {
        $('#vendedor_ids').val(idsVendedoresDesdeTabla().join(','));
        var n = idsVendedoresDesdeTabla().length;
        $('#aviso-vendedores-cc-reporte').text(
            n === 0
                ? 'Todavía no agregó vendedores. Elija al menos uno para consultar.'
                : (n + ' vendedor(es) en la lista.')
        );
    }

    function yaEstaClienteEnLista(id) {
        return $('#tbody-clientes-cc-reporte tr[data-id="' + id + '"]').length > 0;
    }

    function yaEstaVendedorEnLista(id) {
        return $('#tbody-vendedores-cc-reporte tr[data-id="' + id + '"]').length > 0;
    }

    function agregarCliente(cli) {
        if (!cli || !cli.id) {
            return;
        }
        var id = parseInt(cli.id, 10);
        if (!id || yaEstaClienteEnLista(id)) {
            return;
        }
        var $tr = $('<tr></tr>').attr('data-id', id);
        $tr.append($('<td></td>').text(cli.codigo || ''));
        $tr.append($('<td></td>').text(cli.nombre || ''));
        $tr.append(
            $('<td class="text-center"></td>').append(
                $('<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-cliente-cc-reporte" title="Quitar"></button>')
                    .append('<i class="fa fa-times"></i>')
            )
        );
        $('#tbody-clientes-cc-reporte').append($tr);
        actualizarHiddenClientes();
    }

    function agregarVendedor(vend) {
        if (!vend || !vend.id) {
            return;
        }
        var id = parseInt(vend.id, 10);
        if (!id || yaEstaVendedorEnLista(id)) {
            return;
        }
        var $tr = $('<tr></tr>').attr('data-id', id);
        $tr.append($('<td></td>').text(vend.codigo || ''));
        $tr.append($('<td></td>').text(vend.nombre || ''));
        $tr.append(
            $('<td class="text-center"></td>').append(
                $('<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-vendedor-cc-reporte" title="Quitar"></button>')
                    .append('<i class="fa fa-times"></i>')
            )
        );
        $('#tbody-vendedores-cc-reporte').append($tr);
        actualizarHiddenVendedores();
    }

    function limpiarCampoSeleccionCliente() {
        $('#codigocliente_cc_reporte').val('');
        $('#nombrecliente_cc_reporte').val('');
        clientePendiente = null;
    }

    function limpiarCampoSeleccionVendedor() {
        $('#codigovendedor_cc_reporte').val('');
        $('#nombrevendedor_cc_reporte').val('');
        vendedorPendiente = null;
    }

    function resolverCodigoCliente(codigo, done) {
        codigo = String(codigo || '').trim();
        if (!codigo) {
            done(null);
            return;
        }
        $.get(leerClienteUrl(codigo))
            .done(function (data) {
                if (!data || !data.id) {
                    done(null);
                    return;
                }
                done({
                    id: data.id,
                    codigo: data.codigo || codigo,
                    nombre: data.nombre || '',
                });
            })
            .fail(function () {
                done(null);
            });
    }

    function resolverCodigoVendedor(codigo, done) {
        codigo = String(codigo || '').trim();
        if (!codigo) {
            done(null);
            return;
        }
        $.get(leerVendedorUrl(codigo))
            .done(function (data) {
                if (!data || !data.id) {
                    done(null);
                    return;
                }
                done({
                    id: data.id,
                    codigo: data.codigo || codigo,
                    nombre: data.nombre || '',
                });
            })
            .fail(function () {
                done(null);
            });
    }

    function aplicarClienteDesdeModal(cli) {
        if (!cli || !cli.id) {
            return;
        }
        var normalizado = {
            id: cli.id,
            codigo: cli.codigo || '',
            nombre: cli.nombre || '',
        };
        if (destinoModalCliente === 'rango_desde') {
            $('#cliente_codigo_desde').val(normalizado.codigo);
            $('#nombrecliente_rango_desde').val(normalizado.nombre);
            return;
        }
        if (destinoModalCliente === 'rango_hasta') {
            $('#cliente_codigo_hasta').val(normalizado.codigo);
            $('#nombrecliente_rango_hasta').val(normalizado.nombre);
            return;
        }
        agregarCliente(normalizado);
        limpiarCampoSeleccionCliente();
        $('#codigocliente_cc_reporte').focus();
    }

    function aplicarVendedorDesdeModal(vend) {
        if (!vend || !vend.id) {
            return;
        }
        var normalizado = {
            id: vend.id,
            codigo: vend.codigo || '',
            nombre: vend.nombre || '',
        };
        if (destinoModalVendedor === 'rango_desde') {
            $('#vendedor_codigo_desde').val(normalizado.codigo);
            $('#nombrevendedor_rango_desde').val(normalizado.nombre);
            return;
        }
        if (destinoModalVendedor === 'rango_hasta') {
            $('#vendedor_codigo_hasta').val(normalizado.codigo);
            $('#nombrevendedor_rango_hasta').val(normalizado.nombre);
            return;
        }
        agregarVendedor(normalizado);
        limpiarCampoSeleccionVendedor();
        $('#codigovendedor_cc_reporte').focus();
    }

    function abrirModalCliente() {
        window.onClienteElegidoEnConsulta = function (fila) {
            aplicarClienteDesdeModal(fila);
            $('#consultaclienteModal').modal('hide');
            return true;
        };
        if (typeof window.activa_eventos_consultacliente === 'function') {
            window.activa_eventos_consultacliente();
        }
        if (typeof window.abrirModalConsultaCliente === 'function') {
            window.abrirModalConsultaCliente();
            return;
        }
        $('#consultaclienteModal').modal('show');
    }

    function abrirModalVendedor() {
        window.onVendedorElegidoEnConsulta = function (fila) {
            aplicarVendedorDesdeModal(fila);
            return true;
        };
        if (typeof window.activa_eventos_consultavendedor === 'function') {
            window.activa_eventos_consultavendedor();
        }
        $('#consultavendedorModal').modal('show');
        if (typeof window.buscar_datos_vendedor === 'function') {
            window.buscar_datos_vendedor('');
        }
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('cc-clientes-reporte-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('cc-clientes-reporte-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('cc-clientes-reporte-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('cc-clientes-reporte-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function validarAntesDeConsultar() {
        var alcanceClientes = $('input[name="alcance_clientes"]:checked').val() || 'todos';
        if (alcanceClientes === 'puntuales' && idsClientesDesdeTabla().length === 0) {
            alert('Elija al menos un cliente o cambie a «Todos» / «Rango de códigos».');
            $('#codigocliente_cc_reporte').focus();
            return false;
        }
        if (alcanceClientes === 'rango') {
            var desdeCli = String($('#cliente_codigo_desde').val() || '').trim();
            var hastaCli = String($('#cliente_codigo_hasta').val() || '').trim();
            if (!desdeCli && !hastaCli) {
                alert('Indique al menos un código de cliente en el rango (desde y/o hasta).');
                $('#cliente_codigo_desde').focus();
                return false;
            }
        }

        var alcanceVendedores = $('input[name="alcance_vendedores"]:checked').val() || 'todos';
        if (alcanceVendedores === 'puntuales' && idsVendedoresDesdeTabla().length === 0) {
            alert('Elija al menos un vendedor o cambie a «Todos» / «Rango de códigos».');
            $('#codigovendedor_cc_reporte').focus();
            return false;
        }
        if (alcanceVendedores === 'rango') {
            var desdeVend = String($('#vendedor_codigo_desde').val() || '').trim();
            var hastaVend = String($('#vendedor_codigo_hasta').val() || '').trim();
            if (!desdeVend && !hastaVend) {
                alert('Indique al menos un código de vendedor en el rango (desde y/o hasta).');
                $('#vendedor_codigo_desde').focus();
                return false;
            }
        }

        var empresa = $('#empresa_id').val();
        if (!empresa) {
            alert('Seleccione la empresa.');
            $('#empresa_id').focus();
            return false;
        }
        return true;
    }

    $(function () {
        syncAlcanceClientes();
        syncAlcanceVendedores();
        syncOpcionesModo();
        actualizarHiddenClientes();
        actualizarHiddenVendedores();

        if (typeof window.activa_eventos_consultacliente === 'function') {
            window.activa_eventos_consultacliente();
        }
        if (typeof window.activa_eventos_consultavendedor === 'function') {
            window.activa_eventos_consultavendedor();
        }

        $('input[name="alcance_clientes"]').on('change', function () {
            if ($(this).val() !== 'todos') {
                forzarVendedoresTodos();
            }
            syncAlcanceClientes();
        });
        $('input[name="alcance_vendedores"]').on('change', function () {
            if ($(this).val() !== 'todos') {
                forzarClientesTodos();
            }
            syncAlcanceVendedores();
        });
        $('input[name="modo"], #solo_totales, input[name="expresion"]').on('change', syncOpcionesModo);

        $('.consultacliente-cc-reporte').on('click', function () {
            destinoModalCliente = $(this).data('destino') || 'seleccion';
            abrirModalCliente();
        });

        $('.consultavendedor-cc-reporte').on('click', function () {
            destinoModalVendedor = $(this).data('destino') || 'seleccion';
            abrirModalVendedor();
        });

        $('#btn-agregar-cliente-cc-reporte').on('click', function () {
            var codigo = $('#codigocliente_cc_reporte').val();
            if (clientePendiente && String(clientePendiente.codigo) === String(codigo).trim()) {
                agregarCliente(clientePendiente);
                limpiarCampoSeleccionCliente();
                return;
            }
            resolverCodigoCliente(codigo, function (cli) {
                if (!cli) {
                    alert('Cliente no encontrado.');
                    $('#codigocliente_cc_reporte').focus();
                    return;
                }
                agregarCliente(cli);
                limpiarCampoSeleccionCliente();
                $('#codigocliente_cc_reporte').focus();
            });
        });

        $('#btn-agregar-vendedor-cc-reporte').on('click', function () {
            var codigo = $('#codigovendedor_cc_reporte').val();
            if (vendedorPendiente && String(vendedorPendiente.codigo) === String(codigo).trim()) {
                agregarVendedor(vendedorPendiente);
                limpiarCampoSeleccionVendedor();
                return;
            }
            resolverCodigoVendedor(codigo, function (vend) {
                if (!vend) {
                    alert('Vendedor no encontrado.');
                    $('#codigovendedor_cc_reporte').focus();
                    return;
                }
                agregarVendedor(vend);
                limpiarCampoSeleccionVendedor();
                $('#codigovendedor_cc_reporte').focus();
            });
        });

        $('#codigocliente_cc_reporte').on('keydown', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModalCliente = 'seleccion';
                abrirModalCliente();
                return;
            }
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            $('#btn-agregar-cliente-cc-reporte').trigger('click');
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrecliente_cc_reporte').val('');
                clientePendiente = null;
                return;
            }
            resolverCodigoCliente(codigo, function (cli) {
                if (!cli) {
                    $('#nombrecliente_cc_reporte').val('');
                    clientePendiente = null;
                    return;
                }
                clientePendiente = cli;
                $('#nombrecliente_cc_reporte').val(cli.nombre || '');
            });
        });

        $('#codigovendedor_cc_reporte').on('keydown', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModalVendedor = 'seleccion';
                abrirModalVendedor();
                return;
            }
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            $('#btn-agregar-vendedor-cc-reporte').trigger('click');
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrevendedor_cc_reporte').val('');
                vendedorPendiente = null;
                return;
            }
            resolverCodigoVendedor(codigo, function (vend) {
                if (!vend) {
                    $('#nombrevendedor_cc_reporte').val('');
                    vendedorPendiente = null;
                    return;
                }
                vendedorPendiente = vend;
                $('#nombrevendedor_cc_reporte').val(vend.nombre || '');
            });
        });

        $('#cliente_codigo_desde').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#cliente_codigo_hasta').focus();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModalCliente = 'rango_desde';
                abrirModalCliente();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrecliente_rango_desde').val('');
                return;
            }
            resolverCodigoCliente(codigo, function (cli) {
                $('#nombrecliente_rango_desde').val(cli ? (cli.nombre || '') : '');
            });
        });

        $('#cliente_codigo_hasta').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModalCliente = 'rango_hasta';
                abrirModalCliente();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrecliente_rango_hasta').val('');
                return;
            }
            resolverCodigoCliente(codigo, function (cli) {
                $('#nombrecliente_rango_hasta').val(cli ? (cli.nombre || '') : '');
            });
        });

        $('#vendedor_codigo_desde').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#vendedor_codigo_hasta').focus();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModalVendedor = 'rango_desde';
                abrirModalVendedor();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrevendedor_rango_desde').val('');
                return;
            }
            resolverCodigoVendedor(codigo, function (vend) {
                $('#nombrevendedor_rango_desde').val(vend ? (vend.nombre || '') : '');
            });
        });

        $('#vendedor_codigo_hasta').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModalVendedor = 'rango_hasta';
                abrirModalVendedor();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrevendedor_rango_hasta').val('');
                return;
            }
            resolverCodigoVendedor(codigo, function (vend) {
                $('#nombrevendedor_rango_hasta').val(vend ? (vend.nombre || '') : '');
            });
        });

        $(document).on('click', '.btn-quitar-cliente-cc-reporte', function () {
            $(this).closest('tr').remove();
            actualizarHiddenClientes();
        });

        $(document).on('click', '.btn-quitar-vendedor-cc-reporte', function () {
            $(this).closest('tr').remove();
            actualizarHiddenVendedores();
        });

        $('#form-cc-clientes-reporte').on('submit', function (e) {
            if (this.checkValidity && !this.checkValidity()) {
                return;
            }
            if (!validarAntesDeConsultar()) {
                e.preventDefault();
                return;
            }
            actualizarHiddenClientes();
            actualizarHiddenVendedores();

            var alcanceClientes = $('input[name="alcance_clientes"]:checked').val() || 'todos';
            if (alcanceClientes !== 'puntuales') {
                $('#cliente_ids').val('');
            }
            if (alcanceClientes !== 'rango') {
                $('#cliente_codigo_desde, #cliente_codigo_hasta').prop('disabled', true);
            }

            var alcanceVendedores = $('input[name="alcance_vendedores"]:checked').val() || 'todos';
            if (alcanceVendedores !== 'puntuales') {
                $('#vendedor_ids').val('');
            }
            if (alcanceVendedores !== 'rango') {
                $('#vendedor_codigo_desde, #vendedor_codigo_hasta').prop('disabled', true);
            }

            mostrarOverlay('Consultando cuenta corriente…');
        });

        $('.js-cc-reporte-export').on('click', function () {
            mostrarOverlay(
                'Generando exportación…',
                'Pulse Esc si la descarga ya terminó y el aviso sigue visible.'
            );
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                ocultarOverlay();
            }
        });
        $(window).on('pageshow pagehide focus', ocultarOverlay);
    });
})(jQuery);
