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
        resolverNroClienteSilencioso();
    }

    // --- Nº cliente / medidor: código editable + detalle + modal de consulta (F1 / lupa) ---
    var modalServicioAbierto = false;
    var modalServicioAbriendo = false;
    var resolverNroClienteSeq = 0;
    var buscarServicioSeq = 0;
    var buscarServicioTimer = null;
    var avisoServicioDefault = '';

    function empresaIdFiltro() {
        return parseInt(String($('#empresa_id').val() || '0'), 10) || 0;
    }

    function serviciosUrl() {
        return cfg.serviciosUrl || (baseUrl() + '/compras/comprobantes-pdf-lote/servicios');
    }

    function avisoServicio(texto) {
        var $aviso = $('#aviso-nro-cliente-pdf-lote');
        if (!$aviso.length) {
            return;
        }
        $aviso.text(texto ? texto : avisoServicioDefault);
    }

    function focoSiguienteDespuesDeServicio() {
        var $sig = $('#form-comprobantes-pdf-lote input[name="tipos[]"]').first();
        if ($sig.length) {
            $sig.trigger('focus');
        }
    }

    function aplicarServicioElegido(cliente, detalle) {
        $('#nro_cliente').val(cliente).removeAttr('data-fuera-ficha');
        $('#descripcion_nro_cliente').val(detalle || '');
        avisoServicio(null);
    }

    /** Resuelve el código tipeado contra las fichas de los proveedores: sin alert, solo marca y detalle. */
    function resolverNroClienteSilencioso() {
        var codigo = $.trim($('#nro_cliente').val());
        var $desc = $('#descripcion_nro_cliente');
        if (!codigo) {
            $desc.val('');
            $('#nro_cliente').removeAttr('data-fuera-ficha');
            avisoServicio(null);
            return;
        }
        var ids = idsDesdeTabla();
        if (ids.length === 0) {
            $desc.val('');
            return;
        }
        var seq = ++resolverNroClienteSeq;
        $.getJSON(serviciosUrl(), {
            proveedor_ids: ids.join(','),
            empresa_id: empresaIdFiltro(),
            codigo: codigo
        })
            .done(function (res) {
                if (seq !== resolverNroClienteSeq || $.trim($('#nro_cliente').val()) !== codigo) {
                    return;
                }
                var lista = (res && res.servicios) ? res.servicios : [];
                if (lista.length > 0) {
                    $desc.val(lista[0].detalle || lista[0].proveedor_nombre || '');
                    $('#nro_cliente').removeAttr('data-fuera-ficha');
                    avisoServicio(null);
                } else {
                    $desc.val('');
                    $('#nro_cliente').attr('data-fuera-ficha', '1');
                    avisoServicio('«' + codigo + '» no está en la ficha de los proveedores de la lista para esta empresa: se filtra igual por ese texto.');
                }
            })
            .fail(function () {
                if (seq === resolverNroClienteSeq) {
                    $desc.val('');
                }
            });
    }

    function renderServicios(lista) {
        var $tb = $('#datosservicio-pdf-lote').empty();
        if (!lista.length) {
            $tb.append('<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>');
            return;
        }
        lista.forEach(function (op) {
            var $tr = $('<tr></tr>')
                .attr('data-cliente', op.cliente || '')
                .attr('data-detalle', op.detalle || op.proveedor_nombre || '');
            $tr.append($('<td></td>').text(((op.proveedor_codigo || '') + ' ' + (op.proveedor_nombre || '')).trim()));
            $tr.append($('<td></td>').text(op.cliente || ''));
            $tr.append($('<td></td>').text(op.detalle || ''));
            var $acc = $('<td class="text-nowrap"></td>');
            $acc.append($('<a href="#" class="btn btn-warning btn-sm eligeconsultaservicio-pdf-lote">Elegir</a>'));
            if (op.url_consultar) {
                $acc.append(' ').append(
                    $('<a class="btn btn-info btn-sm" target="_blank" rel="noopener">Consultar</a>').attr('href', op.url_consultar)
                );
            }
            $tr.append($acc);
            $tb.append($tr);
        });
    }

    function cargarServicios(texto) {
        var ids = idsDesdeTabla();
        var $aviso = $('#consultaservicio-pdf-lote-aviso');
        var seq = ++buscarServicioSeq;
        $('#datosservicio-pdf-lote').html('<tr><td colspan="4" class="text-muted">Buscando…</td></tr>');
        $.getJSON(serviciosUrl(), {
            proveedor_ids: ids.join(','),
            empresa_id: empresaIdFiltro(),
            q: texto || ''
        })
            .done(function (res) {
                if (seq !== buscarServicioSeq) {
                    return;
                }
                var lista = (res && res.servicios) ? res.servicios : [];
                renderServicios(lista);
                $aviso.text(lista.length + ' cliente(s)/medidor(es) de los proveedores de la lista'
                    + (empresaIdFiltro() > 0 ? ', de la empresa elegida o sin empresa en la ficha.' : '.'));
            })
            .fail(function () {
                if (seq !== buscarServicioSeq) {
                    return;
                }
                $('#datosservicio-pdf-lote').html('<tr><td colspan="4" class="text-danger">No se pudieron cargar los clientes/medidores.</td></tr>');
            });
    }

    function abrirModalServicio() {
        if (modalServicioAbierto || modalServicioAbriendo) {
            return;
        }
        if (idsDesdeTabla().length === 0) {
            avisoServicio('Primero agregue al menos un proveedor a la lista para ver sus clientes/medidores.');
            $('#codigoproveedor_pdf_lote').trigger('focus');
            return;
        }
        modalServicioAbriendo = true;
        $('#consultaservicio-pdf-lote').val('');
        cargarServicios('');
        $('#consultaservicioPdfLoteModal').modal('show');
    }

    function elegirPrimerServicioDelModal() {
        var $btn = $('#datosservicio-pdf-lote .eligeconsultaservicio-pdf-lote').first();
        if (!$btn.length) {
            return false;
        }
        $btn.get(0).click();
        return true;
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
        avisoServicioDefault = $.trim($('#aviso-nro-cliente-pdf-lote').text()).replace(/\s+/g, ' ');

        $('#empresa_id').on('change', resolverNroClienteSilencioso);

        $('#btn-consulta-nro-cliente').on('click', abrirModalServicio);

        $('#btn-limpiar-nro-cliente').on('click', function () {
            $('#nro_cliente').val('').removeAttr('data-fuera-ficha');
            $('#descripcion_nro_cliente').val('');
            avisoServicio(null);
            $('#nro_cliente').trigger('focus');
        });

        $('#nro_cliente')
            .on('keydown', function (e) {
                if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
                    e.preventDefault();
                    abrirModalServicio();
                    return;
                }
                if (e.key !== 'Enter') {
                    return;
                }
                // Enter avanza al campo siguiente (no envía el formulario); el blur resuelve en silencio.
                e.preventDefault();
                focoSiguienteDespuesDeServicio();
            })
            .on('blur', function () {
                if (modalServicioAbierto || modalServicioAbriendo) {
                    return;
                }
                resolverNroClienteSilencioso();
            })
            .on('input', function () {
                $(this).removeAttr('data-fuera-ficha');
            });

        $('#consultaservicioPdfLoteModal')
            .on('shown.bs.modal', function () {
                modalServicioAbierto = true;
                modalServicioAbriendo = false;
                $('#consultaservicio-pdf-lote').trigger('focus');
            })
            .on('hidden.bs.modal', function () {
                modalServicioAbierto = false;
                modalServicioAbriendo = false;
                if (typeof window.liberarPantallaModalesBloqueados === 'function' && $('.modal.show').length === 0) {
                    window.liberarPantallaModalesBloqueados();
                }
            });

        $('#consultaservicio-pdf-lote')
            .on('keydown', function (e) {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                clearTimeout(buscarServicioTimer);
                if (!elegirPrimerServicioDelModal()) {
                    cargarServicios($.trim($(this).val()));
                }
            })
            .on('input', function () {
                var texto = $.trim($(this).val());
                clearTimeout(buscarServicioTimer);
                buscarServicioTimer = setTimeout(function () {
                    cargarServicios(texto);
                }, 250);
            });

        $(document).on('click', '.eligeconsultaservicio-pdf-lote', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            aplicarServicioElegido(String($tr.attr('data-cliente') || ''), String($tr.attr('data-detalle') || ''));
            $('#consultaservicioPdfLoteModal').one('hidden.bs.modal', focoSiguienteDespuesDeServicio);
            $('#consultaservicioPdfLoteModal').modal('hide');
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
