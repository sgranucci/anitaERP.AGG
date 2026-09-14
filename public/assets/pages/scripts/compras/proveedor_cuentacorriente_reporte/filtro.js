(function ($) {
    'use strict';

    var cfg = window.CC_PROVEEDORES_REPORTE || {};
    var destinoModal = 'seleccion';
    var proveedorPendiente = null;

    function baseUrl() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
    }

    function leerProveedorUrl(codigo) {
        var base = cfg.leerProveedorUrlBase || (baseUrl() + '/compras/leerproveedorporcodigo');
        return String(base).replace(/\/$/, '') + '/' + encodeURIComponent(codigo);
    }

    function syncAlcancePanels() {
        var alcance = $('input[name="alcance_proveedores"]:checked').val() || 'todos';
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
        $('#tbody-proveedores-cc-reporte tr').each(function () {
            var id = parseInt($(this).attr('data-id'), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function actualizarHiddenIds() {
        $('#proveedor_ids').val(idsDesdeTabla().join(','));
        var n = idsDesdeTabla().length;
        $('#aviso-proveedores-cc-reporte').text(
            n === 0
                ? 'Todavía no agregó proveedores. Elija al menos uno para consultar.'
                : (n + ' proveedor(es) en la lista.')
        );
    }

    function yaEstaEnLista(id) {
        return $('#tbody-proveedores-cc-reporte tr[data-id="' + id + '"]').length > 0;
    }

    function agregarProveedor(prov) {
        if (!prov || !prov.id) {
            return;
        }
        var id = parseInt(prov.id, 10);
        if (!id || yaEstaEnLista(id)) {
            return;
        }
        var $tr = $('<tr></tr>').attr('data-id', id);
        $tr.append($('<td></td>').text(prov.codigo || ''));
        $tr.append($('<td></td>').text(prov.nombre || ''));
        $tr.append(
            $('<td class="text-center"></td>').append(
                $('<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-proveedor-cc-reporte" title="Quitar"></button>')
                    .append('<i class="fa fa-times"></i>')
            )
        );
        $('#tbody-proveedores-cc-reporte').append($tr);
        actualizarHiddenIds();
    }

    function limpiarCampoSeleccion() {
        $('#codigoproveedor_cc_reporte').val('');
        $('#nombreproveedor_cc_reporte').val('');
        proveedorPendiente = null;
    }

    function resolverCodigo(codigo, done) {
        codigo = String(codigo || '').trim();
        if (!codigo) {
            done(null);
            return;
        }
        $.get(leerProveedorUrl(codigo))
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

    function aplicarProveedorDesdeModal(prov) {
        if (!prov || !prov.id) {
            return;
        }
        var normalizado = {
            id: prov.id,
            codigo: prov.codigo || '',
            nombre: prov.nombre || '',
        };
        if (destinoModal === 'rango_desde') {
            $('#proveedor_codigo_desde').val(normalizado.codigo);
            $('#nombreproveedor_rango_desde').val(normalizado.nombre);
            return;
        }
        if (destinoModal === 'rango_hasta') {
            $('#proveedor_codigo_hasta').val(normalizado.codigo);
            $('#nombreproveedor_rango_hasta').val(normalizado.nombre);
            return;
        }
        agregarProveedor(normalizado);
        limpiarCampoSeleccion();
        $('#codigoproveedor_cc_reporte').focus();
    }

    function abrirModalProveedor() {
        window.onProveedorElegidoEnConsulta = function (fila) {
            aplicarProveedorDesdeModal(fila);
            $('#consultaproveedorModal').modal('hide');
            return true;
        };
        if (typeof window.activa_eventos_consultaproveedor === 'function') {
            window.activa_eventos_consultaproveedor();
        }
        if (typeof buscar_datos_proveedor === 'function') {
            buscar_datos_proveedor('');
        }
        $('#consultaproveedorModal').modal('show');
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('cc-proveedores-reporte-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('cc-proveedores-reporte-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('cc-proveedores-reporte-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('cc-proveedores-reporte-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function validarAntesDeConsultar() {
        var alcance = $('input[name="alcance_proveedores"]:checked').val() || 'todos';
        if (alcance === 'puntuales' && idsDesdeTabla().length === 0) {
            alert('Elija al menos un proveedor o cambie a «Todos» / «Rango de códigos».');
            $('#codigoproveedor_cc_reporte').focus();
            return false;
        }
        if (alcance === 'rango') {
            var desde = String($('#proveedor_codigo_desde').val() || '').trim();
            var hasta = String($('#proveedor_codigo_hasta').val() || '').trim();
            if (!desde && !hasta) {
                alert('Indique al menos un código en el rango (desde y/o hasta).');
                $('#proveedor_codigo_desde').focus();
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

        if (typeof window.activa_eventos_consultaproveedor === 'function') {
            window.activa_eventos_consultaproveedor();
        }

        $('input[name="alcance_proveedores"]').on('change', syncAlcancePanels);
        $('input[name="modo"], #solo_totales, input[name="expresion"]').on('change', syncOpcionesModo);

        $('.consultaproveedor-cc-reporte').on('click', function () {
            destinoModal = $(this).data('destino') || 'seleccion';
            abrirModalProveedor();
        });

        $('#btn-agregar-proveedor-cc-reporte').on('click', function () {
            var codigo = $('#codigoproveedor_cc_reporte').val();
            if (proveedorPendiente && String(proveedorPendiente.codigo) === String(codigo).trim()) {
                agregarProveedor(proveedorPendiente);
                limpiarCampoSeleccion();
                return;
            }
            resolverCodigo(codigo, function (prov) {
                if (!prov) {
                    alert('Proveedor no encontrado.');
                    $('#codigoproveedor_cc_reporte').focus();
                    return;
                }
                agregarProveedor(prov);
                limpiarCampoSeleccion();
                $('#codigoproveedor_cc_reporte').focus();
            });
        });

        $('#codigoproveedor_cc_reporte').on('keydown', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModal = 'seleccion';
                abrirModalProveedor();
                return;
            }
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            $('#btn-agregar-proveedor-cc-reporte').trigger('click');
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombreproveedor_cc_reporte').val('');
                proveedorPendiente = null;
                return;
            }
            resolverCodigo(codigo, function (prov) {
                if (!prov) {
                    $('#nombreproveedor_cc_reporte').val('');
                    proveedorPendiente = null;
                    return;
                }
                proveedorPendiente = prov;
                $('#nombreproveedor_cc_reporte').val(prov.nombre || '');
            });
        });

        $('#proveedor_codigo_desde').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#proveedor_codigo_hasta').focus();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModal = 'rango_desde';
                abrirModalProveedor();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombreproveedor_rango_desde').val('');
                return;
            }
            resolverCodigo(codigo, function (prov) {
                $('#nombreproveedor_rango_desde').val(prov ? (prov.nombre || '') : '');
            });
        });

        $('#proveedor_codigo_hasta').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                destinoModal = 'rango_hasta';
                abrirModalProveedor();
            }
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombreproveedor_rango_hasta').val('');
                return;
            }
            resolverCodigo(codigo, function (prov) {
                $('#nombreproveedor_rango_hasta').val(prov ? (prov.nombre || '') : '');
            });
        });

        $(document).on('click', '.btn-quitar-proveedor-cc-reporte', function () {
            $(this).closest('tr').remove();
            actualizarHiddenIds();
        });

        $('#form-cc-proveedores-reporte').on('submit', function (e) {
            if (this.checkValidity && !this.checkValidity()) {
                return;
            }
            if (!validarAntesDeConsultar()) {
                e.preventDefault();
                return;
            }
            actualizarHiddenIds();
            var alcance = $('input[name="alcance_proveedores"]:checked').val() || 'todos';
            if (alcance !== 'puntuales') {
                $('#proveedor_ids').val('');
            }
            if (alcance !== 'rango') {
                $('#proveedor_codigo_desde, #proveedor_codigo_hasta').prop('disabled', true);
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
