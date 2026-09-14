(function ($) {
    'use strict';

    var cfg = window.CC_CLIENTES_REPORTE || {};
    var destinoModal = 'seleccion';
    var clientePendiente = null;

    function baseUrl() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
    }

    function leerClienteUrl(codigo) {
        var base = cfg.leerClienteUrlBase || (baseUrl() + '/ventas/leerunclienteporcodigo');
        return String(base).replace(/\/$/, '') + '/' + encodeURIComponent(codigo);
    }

    function syncAlcancePanels() {
        var alcance = $('input[name="alcance_clientes"]:checked').val() || 'todos';
        $('#panel-alcance-todos').toggle(alcance === 'todos');
        $('#panel-alcance-puntuales').toggle(alcance === 'puntuales');
        $('#panel-alcance-rango').toggle(alcance === 'rango');
        $('.cc-reporte-alcance-card').removeClass('is-active');
        $('.cc-reporte-alcance-card').has('input[value="' + alcance + '"]').addClass('is-active');
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

    function idsDesdeTabla() {
        var ids = [];
        $('#tbody-clientes-cc-reporte tr').each(function () {
            var id = parseInt($(this).attr('data-id'), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function actualizarHiddenIds() {
        $('#cliente_ids').val(idsDesdeTabla().join(','));
        var n = idsDesdeTabla().length;
        $('#aviso-clientes-cc-reporte').text(
            n === 0
                ? 'Todavía no agregó clientes. Elija al menos uno para consultar.'
                : (n + ' cliente(s) en la lista.')
        );
    }

    function yaEstaEnLista(id) {
        return $('#tbody-clientes-cc-reporte tr[data-id="' + id + '"]').length > 0;
    }

    function agregarCliente(cli) {
        if (!cli || !cli.id) {
            return;
        }
        var id = parseInt(cli.id, 10);
        if (!id || yaEstaEnLista(id)) {
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
        actualizarHiddenIds();
    }

    function limpiarCampoSeleccion() {
        $('#codigocliente_cc_reporte').val('');
        $('#nombrecliente_cc_reporte').val('');
        clientePendiente = null;
    }

    function resolverCodigo(codigo, done) {
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

    function aplicarClienteDesdeModal(cli) {
        if (!cli || !cli.id) {
            return;
        }
        var normalizado = {
            id: cli.id,
            codigo: cli.codigo || '',
            nombre: cli.nombre || '',
        };
        if (destinoModal === 'rango_desde') {
            $('#cliente_codigo_desde').val(normalizado.codigo);
            $('#nombrecliente_rango_desde').val(normalizado.nombre);
            return;
        }
        if (destinoModal === 'rango_hasta') {
            $('#cliente_codigo_hasta').val(normalizado.codigo);
            $('#nombrecliente_rango_hasta').val(normalizado.nombre);
            return;
        }
        agregarCliente(normalizado);
        limpiarCampoSeleccion();
        $('#codigocliente_cc_reporte').focus();
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
        var alcance = $('input[name="alcance_clientes"]:checked').val() || 'todos';
        if (alcance === 'puntuales' && idsDesdeTabla().length === 0) {
            alert('Elija al menos un cliente o cambie a «Todos» / «Rango de códigos».');
            $('#codigocliente_cc_reporte').focus();
            return false;
        }
        if (alcance === 'rango') {
            var desde = String($('#cliente_codigo_desde').val() || '').trim();
            var hasta = String($('#cliente_codigo_hasta').val() || '').trim();
            if (!desde && !hasta) {
                alert('Indique al menos un código en el rango (desde y/o hasta).');
                $('#cliente_codigo_desde').focus();
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
        syncAlcancePanels();
        syncOpcionesModo();
        actualizarHiddenIds();

        if (typeof window.activa_eventos_consultacliente === 'function') {
            window.activa_eventos_consultacliente();
        }

        $('input[name="alcance_clientes"]').on('change', syncAlcancePanels);
        $('input[name="modo"], #solo_totales, input[name="expresion"]').on('change', syncOpcionesModo);

        $('.consultacliente-cc-reporte').on('click', function () {
            destinoModal = $(this).data('destino') || 'seleccion';
            abrirModalCliente();
        });

        $('#btn-agregar-cliente-cc-reporte').on('click', function () {
            var codigo = $('#codigocliente_cc_reporte').val();
            if (clientePendiente && String(clientePendiente.codigo) === String(codigo).trim()) {
                agregarCliente(clientePendiente);
                limpiarCampoSeleccion();
                return;
            }
            resolverCodigo(codigo, function (cli) {
                if (!cli) {
                    alert('Cliente no encontrado.');
                    $('#codigocliente_cc_reporte').focus();
                    return;
                }
                agregarCliente(cli);
                limpiarCampoSeleccion();
                $('#codigocliente_cc_reporte').focus();
            });
        });

        $('#codigocliente_cc_reporte').on('keydown', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModal = 'seleccion';
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
            resolverCodigo(codigo, function (cli) {
                if (!cli) {
                    $('#nombrecliente_cc_reporte').val('');
                    clientePendiente = null;
                    return;
                }
                clientePendiente = cli;
                $('#nombrecliente_cc_reporte').val(cli.nombre || '');
            });
        });

        $('#cliente_codigo_desde').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#cliente_codigo_hasta').focus();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModal = 'rango_desde';
                abrirModalCliente();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrecliente_rango_desde').val('');
                return;
            }
            resolverCodigo(codigo, function (cli) {
                $('#nombrecliente_rango_desde').val(cli ? (cli.nombre || '') : '');
            });
        });

        $('#cliente_codigo_hasta').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModal = 'rango_hasta';
                abrirModalCliente();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombrecliente_rango_hasta').val('');
                return;
            }
            resolverCodigo(codigo, function (cli) {
                $('#nombrecliente_rango_hasta').val(cli ? (cli.nombre || '') : '');
            });
        });

        $(document).on('click', '.btn-quitar-cliente-cc-reporte', function () {
            $(this).closest('tr').remove();
            actualizarHiddenIds();
        });

        $('#form-cc-clientes-reporte').on('submit', function (e) {
            if (this.checkValidity && !this.checkValidity()) {
                return;
            }
            if (!validarAntesDeConsultar()) {
                e.preventDefault();
                return;
            }
            actualizarHiddenIds();
            var alcance = $('input[name="alcance_clientes"]:checked').val() || 'todos';
            if (alcance !== 'puntuales') {
                $('#cliente_ids').val('');
            }
            if (alcance !== 'rango') {
                $('#cliente_codigo_desde, #cliente_codigo_hasta').prop('disabled', true);
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
