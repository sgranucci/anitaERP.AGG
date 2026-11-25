(function ($) {
    'use strict';

    var cfg = window.COMPROBANTES_PDF_LOTE || {};
    var proveedorPendiente = null;

    function baseUrl() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
    }

    function leerProveedorUrl(codigo) {
        var base = cfg.leerProveedorUrlBase || (baseUrl() + '/compras/leerproveedorporcodigo');
        var url = String(base).replace(/\/$/, '') + '/' + encodeURIComponent(codigo);
        var empresaId = parseInt(String($('#empresa_id').val() || '0'), 10) || 0;
        if (empresaId > 0) {
            url += '?empresa_id=' + encodeURIComponent(String(empresaId));
        }
        return url;
    }

    function idsDesdeTabla() {
        var ids = [];
        $('#tbody-proveedores-pdf-lote tr').each(function () {
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
        $('#aviso-proveedores-pdf-lote').text(
            n === 0 ? 'Todavía no agregó proveedores.' : (n + ' proveedor(es) en la lista.')
        );
        refrescarSelectClientes();
    }

    var serviciosRequestSeq = 0;

    function refrescarSelectClientes() {
        var $sel = $('#nro_cliente');
        if (!$sel.length) {
            return;
        }
        var ids = idsDesdeTabla();
        var seleccionado = String($sel.val() || cfg.nroClienteSeleccionado || '').trim();
        var $aviso = $('#aviso-nro-cliente-pdf-lote');

        if (ids.length === 0) {
            $sel.empty().append($('<option></option>').val('').text('— Sin filtrar (todos) —'));
            if ($aviso.length) {
                $aviso.text('Opcional. Agregá proveedores arriba para ver sus clientes/medidores. Solo filtra facturas.');
            }
            cfg.nroClienteSeleccionado = '';
            return;
        }

        var url = cfg.serviciosUrl || (baseUrl() + '/compras/comprobantes-pdf-lote/servicios');
        var seq = ++serviciosRequestSeq;
        $sel.prop('disabled', true);
        $.getJSON(url, { proveedor_ids: ids.join(',') })
            .done(function (res) {
                if (seq !== serviciosRequestSeq) {
                    return;
                }
                var lista = (res && res.servicios) ? res.servicios : [];
                $sel.empty().append($('<option></option>').val('').text('— Sin filtrar (todos) —'));
                var sigue = false;
                lista.forEach(function (op) {
                    var cliente = String(op.cliente || '').trim();
                    if (!cliente) {
                        return;
                    }
                    var $opt = $('<option></option>')
                        .val(cliente)
                        .attr('data-servicio-id', op.id || '')
                        .attr('data-proveedor-id', op.proveedor_id || '')
                        .text(op.etiqueta || cliente);
                    if (seleccionado !== '' && seleccionado === cliente) {
                        $opt.prop('selected', true);
                        sigue = true;
                    }
                    $sel.append($opt);
                });
                if (!sigue) {
                    cfg.nroClienteSeleccionado = '';
                } else {
                    cfg.nroClienteSeleccionado = seleccionado;
                }
                if ($aviso.length) {
                    $aviso.text(
                        lista.length === 0
                            ? 'Esos proveedores no tienen clientes/medidores cargados en la ficha (solapa Servicios).'
                            : (lista.length + ' cliente(s)/medidor(es) disponibles. Opcional; solo filtra facturas.')
                    );
                }
            })
            .fail(function () {
                if (seq !== serviciosRequestSeq) {
                    return;
                }
                if ($aviso.length) {
                    $aviso.text('No se pudieron cargar los clientes/medidores de los proveedores.');
                }
            })
            .always(function () {
                if (seq === serviciosRequestSeq) {
                    $sel.prop('disabled', false);
                }
            });
    }

    function yaEstaEnLista(id) {
        return $('#tbody-proveedores-pdf-lote tr[data-id="' + id + '"]').length > 0;
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
                $('<button type="button" class="btn btn-outline-danger btn-xs btn-quitar-proveedor-pdf-lote" title="Quitar"></button>')
                    .append('<i class="fa fa-times"></i>')
            )
        );
        $('#tbody-proveedores-pdf-lote').append($tr);
        actualizarHiddenIds();
    }

    function limpiarCampo() {
        $('#codigoproveedor_pdf_lote').val('');
        $('#nombreproveedor_pdf_lote').val('');
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
                    nombre: data.nombre || ''
                });
            })
            .fail(function () {
                done(null);
            });
    }

    function aplicarDesdeModal(prov) {
        if (!prov || !prov.id) {
            return;
        }
        agregarProveedor({
            id: prov.id,
            codigo: prov.codigo || '',
            nombre: prov.nombre || ''
        });
        limpiarCampo();
        $('#codigoproveedor_pdf_lote').focus();
    }

    function abrirModalProveedor() {
        window.onProveedorElegidoEnConsulta = function (fila) {
            aplicarDesdeModal(fila);
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

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('comprobantes-pdf-lote-overlay');
        if (!overlay) {
            return;
        }
        var t = document.getElementById('comprobantes-pdf-lote-titulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    $(function () {
        // No llamar refrescar al inicio si ya hay opciones server-side: evita flicker.
        $('#proveedor_ids').val(idsDesdeTabla().join(','));
        var nInit = idsDesdeTabla().length;
        $('#aviso-proveedores-pdf-lote').text(
            nInit === 0 ? 'Todavía no agregó proveedores.' : (nInit + ' proveedor(es) en la lista.')
        );
        if (nInit > 0 && $('#nro_cliente option').length <= 1) {
            refrescarSelectClientes();
        }

        $('#nro_cliente').on('change', function () {
            cfg.nroClienteSeleccionado = String($(this).val() || '').trim();
        });

        if (typeof window.activa_eventos_consultaproveedor === 'function') {
            window.activa_eventos_consultaproveedor();
        }

        $('.consultaproveedor-pdf-lote').on('click', function () {
            abrirModalProveedor();
        });

        $('#btn-agregar-proveedor-pdf-lote').on('click', function () {
            var codigo = $('#codigoproveedor_pdf_lote').val();
            if (proveedorPendiente && String(proveedorPendiente.codigo) === String(codigo).trim()) {
                agregarProveedor(proveedorPendiente);
                limpiarCampo();
                $('#codigoproveedor_pdf_lote').focus();
                return;
            }
            resolverCodigo(codigo, function (prov) {
                if (!prov) {
                    alert('Proveedor no encontrado.');
                    $('#codigoproveedor_pdf_lote').focus();
                    return;
                }
                agregarProveedor(prov);
                limpiarCampo();
                $('#codigoproveedor_pdf_lote').focus();
            });
        });

        $('#codigoproveedor_pdf_lote').on('keydown', function (e) {
            if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrirModalProveedor();
                return;
            }
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            $('#btn-agregar-proveedor-pdf-lote').trigger('click');
        }).on('blur', function () {
            var codigo = String($(this).val() || '').trim();
            if (!codigo) {
                $('#nombreproveedor_pdf_lote').val('');
                proveedorPendiente = null;
                return;
            }
            resolverCodigo(codigo, function (prov) {
                if (!prov) {
                    $('#nombreproveedor_pdf_lote').val('');
                    proveedorPendiente = null;
                    return;
                }
                proveedorPendiente = prov;
                $('#nombreproveedor_pdf_lote').val(prov.nombre || '');
            });
        });

        $(document).on('click', '.btn-quitar-proveedor-pdf-lote', function () {
            $(this).closest('tr').remove();
            actualizarHiddenIds();
        });

        $('#form-comprobantes-pdf-lote').on('submit', function (e) {
            actualizarHiddenIds();
            if (idsDesdeTabla().length === 0) {
                e.preventDefault();
                alert('Agregue al menos un proveedor.');
                $('#codigoproveedor_pdf_lote').focus();
                return;
            }
            if ($('#form-comprobantes-pdf-lote input[name="tipos[]"]:checked').length === 0) {
                e.preventDefault();
                alert('Marque al menos un tipo: facturas, OC, COM, OPP u OPA.');
                return;
            }
            mostrarOverlay('Consultando documentos…');
        });

        $('#marcar-todos-pdf-lote').on('change', function () {
            $('.js-clave-pdf-lote').prop('checked', $(this).is(':checked'));
        });

        $(document).on('change', '.js-clave-pdf-lote', function () {
            var todos = $('.js-clave-pdf-lote').length;
            var marcados = $('.js-clave-pdf-lote:checked').length;
            $('#marcar-todos-pdf-lote').prop('checked', todos > 0 && marcados === todos);
        });

        $('#form-zip-marcados').on('submit', function (e) {
            var claves = $('.js-clave-pdf-lote:checked');
            if (claves.length === 0) {
                e.preventDefault();
                alert('Marque al menos un documento de la lista, o use Descargar todo el lote.');
                return;
            }
            var $form = $(this);
            $form.find('input[name="claves[]"]').remove();
            claves.each(function () {
                $form.append($('<input type="hidden" name="claves[]">').val($(this).val()));
            });
            mostrarOverlay('Armando el ZIP de los marcados…');
        });

        $('.js-descargar-zip').on('click', function () {
            mostrarOverlay('Armando el ZIP del lote…');
        });
    });
})(jQuery);
