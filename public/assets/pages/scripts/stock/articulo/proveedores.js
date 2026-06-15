$(function () {
    if (!$('#tabla-articulo-proveedor').length) {
        return;
    }

    var articuloId = parseInt($('#articulo_id').val() || '0', 10);
    var puedeConsultarLista = $('#tabla-articulo-proveedor').data('puede-consultar-lista') === 1
        || $('#tabla-articulo-proveedor').data('puede-consultar-lista') === '1';
    var $filaProveedorModal = null;

    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }

    function formatearPrecio(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '—';
        }
        var n = parseFloat(valor);
        if (isNaN(n)) {
            return '—';
        }
        var s = n.toFixed(6).replace(/\.?0+$/, '');
        return s === '' ? '0' : s;
    }

    function urlListaConsulta(listaId) {
        return carpetaBase + '/compras/listaprecio_proveedor/' + encodeURIComponent(listaId)
            + '/editar?origen=modal_consulta&vista=consulta';
    }

    function claveCatalogoFila($row) {
        var provId = parseInt($row.find('.proveedor_id').val() || '0', 10);
        var codigo = ($row.find('input[name="ap_codigos_articulo_proveedor[]"]').val() || '').trim();
        if (!provId || codigo === '') {
            return null;
        }

        return provId + '|' + codigo;
    }

    function marcarDuplicadosEnGrilla() {
        var conteo = {};
        $('#tbody-articulo-proveedor tr.item-articulo-proveedor').each(function () {
            var clave = claveCatalogoFila($(this));
            if (clave) {
                conteo[clave] = (conteo[clave] || 0) + 1;
            }
        });

        $('#tbody-articulo-proveedor tr.item-articulo-proveedor').each(function () {
            var $row = $(this);
            var clave = claveCatalogoFila($row);
            var duplicado = clave && conteo[clave] > 1;
            $row.toggleClass('table-danger', duplicado);
            $row.find('input[name="ap_codigos_articulo_proveedor[]"]').toggleClass('is-invalid', duplicado);
            $row.find('.codigoproveedor').toggleClass('is-invalid', duplicado && !$row.find('input[name="ap_codigos_articulo_proveedor[]"]').hasClass('is-invalid'));
        });
    }

    function filaConClaveCatalogo(clave, $exceptRow) {
        if (!clave) {
            return null;
        }

        var encontrada = null;
        $('#tbody-articulo-proveedor tr.item-articulo-proveedor').each(function () {
            var $row = $(this);
            if ($exceptRow && $row.is($exceptRow)) {
                return;
            }
            if (claveCatalogoFila($row) === clave) {
                encontrada = $row;
                return false;
            }
        });

        return encontrada;
    }

    function limpiarProveedorFila($row) {
        $row.find('.proveedor_id, .ap_linea_id').val('');
        $row.find('.codigoproveedor').val('');
        $row.find('.nombreproveedor').val('');
        $row.find('.ap-preferido').val('').prop('checked', false);
        $row.find('.ap-moneda-vigente, .ap-precio-vigente, .ap-vigencia-lista').val('—');
        actualizarCeldaLista($row, 0, '');
        marcarDuplicadosEnGrilla();
    }

    function avisarCodigoDuplicado(codigo, nombreProveedor) {
        alert('El código de artículo proveedor "' + (codigo || '') + '" ya está cargado para el proveedor "' + (nombreProveedor || '') + '".');
    }

    function asignarProveedorFila($row, proveedor) {
        if (!proveedor || !proveedor.id) {
            limpiarProveedorFila($row);
            return false;
        }

        $row.find('.proveedor_id').val(proveedor.id);
        $row.find('.nombreproveedor').val(proveedor.nombre || '');
        if (proveedor.codigo) {
            $row.find('.codigoproveedor').val(proveedor.codigo);
        }
        $row.find('.ap-preferido').val(proveedor.id);
        marcarDuplicadosEnGrilla();
        actualizarPrecioVigenteFila($row);

        return true;
    }

    function validarCodigosUnicosEnGrilla() {
        var vistos = {};
        var duplicado = null;

        $('#tbody-articulo-proveedor tr.item-articulo-proveedor').each(function () {
            var $row = $(this);
            var clave = claveCatalogoFila($row);
            if (!clave) {
                return;
            }
            if (vistos[clave]) {
                duplicado = {
                    $row: $row,
                    codigo: ($row.find('input[name="ap_codigos_articulo_proveedor[]"]').val() || '').trim(),
                    nombre: $row.find('.nombreproveedor').val() || ''
                };
                return false;
            }
            vistos[clave] = true;
        });

        marcarDuplicadosEnGrilla();

        return duplicado;
    }

    function actualizarCeldaLista($row, listaId, titulo) {
        var $celda = $row.find('.ap-celda-lista');
        if (!$celda.length) {
            return;
        }
        if (listaId) {
            if (puedeConsultarLista) {
                $celda.html(
                    '<a href="' + urlListaConsulta(listaId) + '" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC ap-link-lista" title="Abrir lista: ' + titulo + '">'
                    + '<span class="badge badge-success px-1 ap-badge-lista"><i class="fa fa-list"></i></span></a>'
                );
            } else {
                $celda.html(
                    '<span class="badge badge-success px-1 tooltipsC ap-badge-lista" title="' + titulo + '"><i class="fa fa-list"></i></span>'
                );
            }
        } else {
            $celda.html(
                '<span class="badge badge-secondary px-1 tooltipsC ap-badge-lista" title="Sin lista de precios activa con este art&iacute;culo"><i class="fa fa-minus"></i></span>'
            );
        }
    }

    function actualizarPrecioVigenteFila($row) {
        var proveedorId = parseInt($row.find('.proveedor_id').val() || '0', 10);
        if (!articuloId || !proveedorId) {
            $row.find('.ap-moneda-vigente').val('—');
            $row.find('.ap-precio-vigente').val('—').attr('title', 'Sin precio en lista activa');
            $row.find('.ap-vigencia-lista').val('—');
            actualizarCeldaLista($row, 0, '');
            return;
        }

        var url = carpetaBase + '/stock/articulo/' + articuloId + '/precio-proveedor/' + proveedorId;
        $.get(url, function (data) {
            if (!data || data.error) {
                $row.find('.ap-moneda-vigente').val('—');
                $row.find('.ap-precio-vigente').val('—').attr('title', 'Sin precio en lista activa');
                $row.find('.ap-vigencia-lista').val('—');
                actualizarCeldaLista($row, 0, '');
                return;
            }

            $row.find('.ap-moneda-vigente').val(data.moneda_abreviatura || '—');
            $row.find('.ap-precio-vigente').val(formatearPrecio(data.precio)).attr(
                'title',
                data.fechavigencia ? 'Precio neto vigente al ' + data.fechavigencia : 'Precio neto vigente'
            );
            $row.find('.ap-vigencia-lista').val(data.fechavigencia || '—');

            var titulo = (data.lista_nombre || 'Lista de precios proveedor')
                + (data.fechavigencia ? ' (vig. ' + data.fechavigencia + ')' : '');
            actualizarCeldaLista($row, data.listaprecio_proveedor_id || 0, titulo);

            var $radio = $row.find('.ap-preferido');
            if ($radio.length && !$radio.val()) {
                $radio.val(proveedorId);
            }
        });
    }

    function sincronizarActivo($row) {
        var checked = $row.find('.ap-activo-check').is(':checked');
        $row.find('.ap-activo-val').val(checked ? '1' : '0');
    }

    $('#agrega_renglon_articulo_proveedor').on('click', function (event) {
        event.preventDefault();
        var tpl = document.getElementById('template-renglon-articulo-proveedor');
        var tbody = document.getElementById('tbody-articulo-proveedor');
        if (!tpl || !tbody) {
            return;
        }
        if (tpl.content) {
            tbody.appendChild(document.importNode(tpl.content, true));
        } else {
            var html = $(tpl).html();
            if (html) {
                $('#tbody-articulo-proveedor').append(html);
            }
        }
    });

    $(document).on('click', '.eliminar_articulo_proveedor', function (event) {
        event.preventDefault();
        var $tbody = $('#tbody-articulo-proveedor');
        var $rows = $tbody.find('tr.item-articulo-proveedor');
        if ($rows.length > 1) {
            var $row = $(this).closest('tr.item-articulo-proveedor');
            if ($row.find('.ap-preferido').is(':checked')) {
                $('input[name="ap_preferido_proveedor_id"]').prop('checked', false);
            }
            $row.remove();
            marcarDuplicadosEnGrilla();
        } else {
            var $row = $(this).closest('tr.item-articulo-proveedor');
            $row.find('input[type="text"], input[type="number"]').not('.nombreproveedor').val('');
            $row.find('.proveedor_id, .ap_linea_id').val('');
            $row.find('.ap-moneda-vigente, .ap-precio-vigente, .ap-vigencia-lista').val('—');
            $row.find('.nombreproveedor').val('');
            $row.find('select').val('');
            $row.find('input[name="ap_coeficientes_conversion[]"]').val('1');
            $row.find('.ap-activo-check').prop('checked', true);
            $row.find('.ap-activo-val').val('1');
            $row.find('.ap-preferido').prop('checked', false).val('');
            actualizarCeldaLista($row, 0, '');
            marcarDuplicadosEnGrilla();
        }
    });

    $(document).on('change', '.ap-activo-check', function () {
        sincronizarActivo($(this).closest('tr.item-articulo-proveedor'));
    });

    $(document).on('change', '#tbody-articulo-proveedor .codigoproveedor', function () {
        var $row = $(this).closest('tr.item-articulo-proveedor');
        var codigo = $(this).val();
        if (!codigo) {
            limpiarProveedorFila($row);
            return;
        }
        var url = carpetaBase + '/compras/leerproveedorporcodigo/' + encodeURIComponent(codigo);
        $.get(url, function (data) {
            if (data && data.id) {
                asignarProveedorFila($row, {
                    id: data.id,
                    nombre: data.nombre || '',
                    codigo: codigo
                });
            } else {
                limpiarProveedorFila($row);
            }
        });
    });

    $(document).on('click', '#tbody-articulo-proveedor .consultaproveedor', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        $filaProveedorModal = $(this).closest('tr.item-articulo-proveedor');
        if (typeof proveedorxcodigo !== 'undefined') {
            proveedorxcodigo = $filaProveedorModal.find('.proveedor_id');
        }
        if (typeof ptrproveedor_id !== 'undefined') {
            ptrproveedor_id = $filaProveedorModal.find('.proveedor_id');
        }
        if (typeof ptrnombreproveedor !== 'undefined') {
            ptrnombreproveedor = $filaProveedorModal.find('.nombreproveedor');
        }
        $('#consultaproveedorModal').modal('show');
    });

    $(document).on('input change', '#tbody-articulo-proveedor input[name="ap_codigos_articulo_proveedor[]"]', function () {
        marcarDuplicadosEnGrilla();
    });

    $(document).on('hidden.bs.modal', '#consultaproveedorModal', function () {
        if (!$filaProveedorModal || !$filaProveedorModal.length) {
            return;
        }

        var $row = $filaProveedorModal;
        var provId = parseInt($row.find('.proveedor_id').val() || '0', 10);
        if (!provId) {
            $filaProveedorModal = null;
            return;
        }

        $row.find('.ap-preferido').val(provId);
        marcarDuplicadosEnGrilla();
        actualizarPrecioVigenteFila($row);

        $filaProveedorModal = null;
    });

    $('#form-general').on('submit', function (event) {
        var duplicado = validarCodigosUnicosEnGrilla();
        if (duplicado) {
            event.preventDefault();
            avisarCodigoDuplicado(duplicado.codigo, duplicado.nombre);
            if (typeof mostrarSolapaArticulo === 'function') {
                mostrarSolapaArticulo(8);
            }
            duplicado.$row.find('input[name="ap_codigos_articulo_proveedor[]"]').focus();
            return false;
        }
    });

    marcarDuplicadosEnGrilla();
});
