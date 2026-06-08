(function () {
    'use strict';

    var carpetaBase = typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    var itemsActuales = [];
    var depositoCabeceraId = parseInt(window.recepcionProveedorDepositoCabeceraId, 10) || 0;

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function depositoCabeceraActivo() {
        return parseInt($('#recepcion_deposito_id').val(), 10) || depositoCabeceraId || 0;
    }

    function coefEfectivo(item) {
        var coefProv = parseFloat(item.coeficiente_proveedor || item.coeficienteconversion || 1) || 1;
        var coefArt = parseFloat(item.coeficiente_articulo || 1) || 1;
        if (!depositoCabeceraActivo() && item.es_deposito_formula && coefArt > 0) {
            return coefArt;
        }
        return coefProv;
    }

    function depositoLinea(item) {
        var cab = depositoCabeceraActivo();
        if (cab > 0) {
            return cab;
        }
        return parseInt(item.depositoentrega_id || item.deposito_id, 10) || 0;
    }

    function depositoLineaTexto(item) {
        var cab = depositoCabeceraActivo();
        if (cab > 0) {
            var descCab = $('#recepcion_deposito_id_descripcion').val() || ('ID ' + cab);
            return escHtml(descCab);
        }
        if (item.deposito_nombre) {
            return escHtml(item.deposito_nombre);
        }
        var depId = depositoLinea(item);
        return depId > 0 ? ('ID ' + depId) : '—';
    }

    function recalcularLinea($tr, item) {
        var cant = parseFloat($tr.find('.item-cantidad').val()) || 0;
        var coef = coefEfectivo(item);
        $tr.find('[name*="[coeficienteconversion]"]').val(coef);
        $tr.find('.item-cant-stock').text((cant * coef).toFixed(6));
    }

    function recalcularTodasLasLineas() {
        itemsActuales.forEach(function (item, idx) {
            var $tr = $('#tbody-items-recepcion tr[data-idx="' + idx + '"]');
            if ($tr.length) {
                recalcularLinea($tr, item);
                $tr.find('.item-deposito-texto').html(depositoLineaTexto(item));
                $tr.find('[name*="[deposito_id]"]').val(depositoLinea(item));
            }
        });
    }

    function renderItems(items) {
        itemsActuales = items || [];
        var $tbody = $('#tbody-items-recepcion');
        $tbody.empty();
        if (!itemsActuales.length) {
            return;
        }
        itemsActuales.forEach(function (item, idx) {
            var precioDiff = Math.abs(parseFloat(item.precio || 0) - parseFloat(item.precio_ordencompra || item.precio || 0)) >= 0.0001;
            var cantDiff = item.cantidad_oc > 0 && Math.abs(parseFloat(item.cantidad || 0) - parseFloat(item.cantidad_oc)) >= 0.0001;
            var rowClass = (precioDiff || cantDiff || item.tipo_linea === 'EXTRA' || item.tipo_linea === 'SUSTITUTO') ? 'table-warning' : '';
            var tipo = item.tipo_linea || 'OC';
            var coef = coefEfectivo(item);
            var cantStock = (parseFloat(item.cantidad || 0) * coef).toFixed(6);
            var depId = depositoLinea(item);
            var html = '<tr class="' + rowClass + '" data-idx="' + idx + '">';
            html += '<td>' + (idx + 1) + '</td>';
            html += '<td><span class="badge badge-secondary">' + escHtml(tipo) + '</span> ';
            if (item.maneja_parte_unica) {
                html += '<span class="badge badge-info" title="Al confirmar se generará un NPU por unidad">NPU</span> ';
            }
            if (!depositoCabeceraActivo() && item.es_deposito_formula) {
                var insumo = item.articulo_stock_sku ? (' → ' + escHtml(item.articulo_stock_sku)) : '';
                html += '<span class="badge badge-primary" title="Conversión a insumo vía SKU alternativo">Fórmula' + insumo + '</span> ';
            }
            html += escHtml(item.sku || item.descripcion || item.articulo_id) + '</td>';
            html += '<td class="text-right">' + (item.cantidad_oc != null ? item.cantidad_oc : '—') + '</td>';
            html += '<td><input type="number" step="0.000001" min="0" class="form-control form-control-sm item-cantidad" name="items[' + idx + '][cantidad]" value="' + (item.cantidad || 0) + '" ' + (window.recepcionProveedorSoloLectura ? 'readonly' : '') + '></td>';
            html += '<td><input type="number" step="0.000001" class="form-control form-control-sm" name="items[' + idx + '][coeficienteconversion]" value="' + coef + '" readonly></td>';
            html += '<td><span class="item-cant-stock">' + cantStock + '</span></td>';
            html += '<td><input type="number" step="0.000001" class="form-control form-control-sm" name="items[' + idx + '][precio_ordencompra]" value="' + (item.precio_ordencompra != null ? item.precio_ordencompra : '') + '" readonly></td>';
            html += '<td><input type="number" step="0.000001" min="0" class="form-control form-control-sm item-precio" name="items[' + idx + '][precio]" value="' + (item.precio || 0) + '" ' + (window.recepcionProveedorSoloLectura ? 'readonly' : '') + '></td>';
            html += '<td>' + (item.precio_lista_proveedor != null ? item.precio_lista_proveedor : '—') + '</td>';
            html += '<td class="item-deposito-texto">' + depositoLineaTexto(item) + '</td>';
            html += '<td><input type="text" class="form-control form-control-sm item-comentario-precio" name="items[' + idx + '][comentario_precio]" value="' + escHtml(item.comentario_precio || '') + '" ' + (window.recepcionProveedorSoloLectura ? 'readonly' : '') + ' placeholder="Oblig. si precio difiere"></td>';
            html += '<input type="hidden" name="items[' + idx + '][articulo_id]" value="' + item.articulo_id + '">';
            html += '<input type="hidden" name="items[' + idx + '][deposito_id]" value="' + depId + '">';
            html += '<input type="hidden" name="items[' + idx + '][tipo_linea]" value="' + escHtml(tipo) + '">';
            html += '<input type="hidden" name="items[' + idx + '][cantidad_oc]" value="' + (item.cantidad_oc != null ? item.cantidad_oc : '') + '">';
            html += '<input type="hidden" name="items[' + idx + '][ordencompra_articulo_id]" value="' + (item.ordencompra_articulo_id || '') + '">';
            html += '<input type="hidden" name="items[' + idx + '][ordencompra_articulo_sustituido_id]" value="' + (item.ordencompra_articulo_sustituido_id || '') + '">';
            html += '<input type="hidden" name="items[' + idx + '][moneda_id]" value="' + (item.moneda_id || 1) + '">';
            html += '<input type="hidden" name="items[' + idx + '][cotizacion]" value="' + (item.cotizacion || 1) + '">';
            html += '<input type="hidden" name="items[' + idx + '][descuento]" value="' + (item.descuento || 0) + '">';
            html += '<input type="hidden" name="items[' + idx + '][centrocosto_id]" value="' + (item.centrocosto_id || '') + '">';
            html += '<input type="hidden" name="items[' + idx + '][penvp_orden]" value="' + (item.orden || idx + 1) + '">';
            if (!window.recepcionProveedorSoloLectura && tipo === 'EXTRA') {
                html += '<td><button type="button" class="btn btn-xs btn-danger btn-quitar-linea" data-idx="' + idx + '"><i class="fa fa-trash"></i></button></td>';
            } else {
                html += '<td></td>';
            }
            html += '</tr>';
            $tbody.append(html);
        });
    }

    function cargarOc() {
        var numeroOc = parseInt($('#numero_oc_buscar').val(), 10);
        if (!numeroOc) {
            alert('Ingrese número de OC');
            return;
        }
        $.getJSON(carpetaBase + '/stock/recepcion-proveedor/api/precarga-oc', { numero_oc: numeroOc })
            .done(function (data) {
                $('#ordencompra_id').val(data.ordencompra_id);
                $('#proveedor_nombre').val(data.proveedor_nombre);
                if (data.empresa_id) {
                    $('#empresa_id').val(data.empresa_id);
                }
                renderItems(data.lineas);
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al cargar OC');
            });
    }

    function agregarLineaExtra() {
        var articuloId = prompt('ID artículo ERP para línea extra:');
        if (!articuloId) {
            return;
        }
        var cant = parseFloat(prompt('Cantidad recibida:', '1')) || 0;
        var precio = parseFloat(prompt('Precio unitario:', '0')) || 0;
        if (cant <= 0) {
            alert('Cantidad inválida');
            return;
        }
        var depId = depositoCabeceraActivo();
        if (depId <= 0) {
            depId = parseInt(prompt('Depósito ID (o configure depósito general en cabecera):', ''), 10) || 0;
            if (depId <= 0) {
                alert('Indique depósito general o ID de depósito para la línea extra.');
                return;
            }
        }
        itemsActuales.push({
            tipo_linea: 'EXTRA',
            articulo_id: parseInt(articuloId, 10),
            sku: 'ID ' + articuloId,
            cantidad: cant,
            cantidad_oc: null,
            precio: precio,
            precio_ordencompra: 0,
            coeficienteconversion: 1,
            coeficiente_proveedor: 1,
            coeficiente_articulo: 1,
            deposito_id: depId,
            depositoentrega_id: depId,
            es_deposito_formula: false,
            moneda_id: (itemsActuales[0] && itemsActuales[0].moneda_id) || 1,
            cotizacion: (itemsActuales[0] && itemsActuales[0].cotizacion) || 1,
            comentario_precio: precio > 0 ? 'Artículo extra no pedido en OC' : '',
        });
        renderItems(itemsActuales);
    }

    $(function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        window.onDepositoAplicadoEnFormulario = function () {
            depositoCabeceraId = depositoCabeceraActivo();
            renderItems(itemsActuales);
        };

        $('#recepcion_deposito_id').on('change', function () {
            depositoCabeceraId = depositoCabeceraActivo();
            renderItems(itemsActuales);
        });

        if (window.recepcionProveedorItemsInicial && window.recepcionProveedorItemsInicial.length) {
            renderItems(window.recepcionProveedorItemsInicial);
        }

        $('#btn-cargar-oc').on('click', cargarOc);
        $('#btn-agregar-extra').on('click', agregarLineaExtra);

        $(document).on('click', '.btn-quitar-linea', function () {
            var idx = parseInt($(this).data('idx'), 10);
            itemsActuales.splice(idx, 1);
            renderItems(itemsActuales);
        });

        $(document).on('input', '.item-cantidad, .item-precio', function () {
            var $tr = $(this).closest('tr');
            var idx = parseInt($tr.data('idx'), 10);
            var item = itemsActuales[idx];
            if (!item) {
                return;
            }
            recalcularLinea($tr, item);
            var precioOc = parseFloat($tr.find('[name*="precio_ordencompra"]').val()) || 0;
            var precioRec = parseFloat($tr.find('.item-precio').val()) || 0;
            if (precioOc > 0 && Math.abs(precioRec - precioOc) >= 0.0001) {
                $tr.addClass('table-warning');
            }
        });

        $('#archivo_ocr').on('change', function () {
            if (!window.recepcionProveedorId) {
                alert('Guarde la recepción en borrador antes de subir OCR.');
                return;
            }
            var fd = new FormData();
            fd.append('archivo', this.files[0]);
            fd.append('_token', $('input[name=_token]').val());
            $.ajax({
                url: carpetaBase + '/stock/recepcion-proveedor/' + window.recepcionProveedorId + '/ocr',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
            }).done(function (res) {
                alert('Archivo OCR registrado. Estado: ' + res.ocr_estado);
            }).fail(function (xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error OCR');
            });
        });
    });
})();
