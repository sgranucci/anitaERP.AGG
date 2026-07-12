(function ($) {
    'use strict';

    function escHtml(val) {
        return String(val == null ? '' : val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function selectorCampoItem(idx, campo) {
        return '#tbody-items-recepcion tr.item-recepcion-linea[data-idx="' + idx + '"] [name="items[' + idx + '][' + campo + ']"]';
    }

    function actualizarCampoItem(idx, campo, valor) {
        var $campo = $(selectorCampoItem(idx, campo));
        if ($campo.length) {
            $campo.val(valor == null ? '' : valor);
        }
    }

    function actualizarItemsActuales(idx, datos) {
        if (typeof window.itemsActualesRecepcion === 'undefined' || !window.itemsActualesRecepcion[idx]) {
            return;
        }
        var item = window.itemsActualesRecepcion[idx];
        item.ocr_codigo_proveedor = datos.codigo || '';
        item.ocr_descripcion_proveedor = datos.nombre || '';
        item.ocr_codigobarra = datos.barra || '';
        item.ocr_unidad_compra = datos.unidadLabel || '';
        item.um_compra = datos.unidadLabel || item.um_compra || 'bulto';
        item.coeficienteconversion = datos.coeficiente;
        item.coeficiente_proveedor = datos.coeficiente;
    }

    function htmlSelectUnidades(unidades, seleccionada) {
        var html = '<select class="form-control form-control-sm modal-ap-unidad">';
        html += '<option value="">—</option>';
        (unidades || []).forEach(function (u) {
            var id = parseInt(u.id, 10);
            html += '<option value="' + id + '"' + (id === seleccionada ? ' selected' : '') + '>'
                + escHtml(u.abreviatura || u.nombre || id) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function badgeAccion(accion) {
        var cls = 'badge-secondary';
        if (accion === 'crear') {
            cls = 'badge-success';
        } else if (accion === 'completar' || accion === 'complementar') {
            cls = 'badge-info';
        } else if (accion === 'sin_codigo') {
            cls = 'badge-warning';
        } else if (accion === 'conflicto') {
            cls = 'badge-danger';
        }
        return '<span class="badge ' + cls + ' modal-ap-accion-label"></span>';
    }

    function renderFilaModal(linea, unidades) {
        var editable = !!linea.editable;
        var idx = parseInt(linea.form_idx, 10);
        var umId = parseInt(linea.unidadmedida_compra_id, 10) || 0;
        var coef = parseFloat(linea.coeficiente_conversion);
        if (!(coef > 0)) {
            coef = 1;
        }

        var html = '<tr class="modal-ap-linea" data-form-idx="' + idx + '" data-editable="' + (editable ? '1' : '0') + '">';
        html += '<td class="align-middle">' + (idx + 1) + '</td>';
        html += '<td class="align-middle"><strong>' + escHtml(linea.sku) + '</strong><br><small class="text-muted">'
            + escHtml(linea.descripcion_erp) + '</small></td>';
        html += '<td class="align-middle">' + badgeAccion(linea.accion);
        if (linea.mensaje) {
            html += '<br><small class="text-danger modal-ap-mensaje">' + escHtml(linea.mensaje) + '</small>';
        }
        html += '</td>';
        html += '<td class="align-middle"><input type="text" class="form-control form-control-sm modal-ap-codigo" maxlength="100" value="'
            + escHtml(linea.codigo_articulo_proveedor || '') + '" ' + (editable ? '' : 'readonly') + '></td>';
        html += '<td class="align-middle"><input type="text" class="form-control form-control-sm modal-ap-nombre" maxlength="255" value="'
            + escHtml(linea.nombre_articulo_proveedor || '') + '" ' + (editable ? '' : 'readonly') + '></td>';
        html += '<td class="align-middle"><input type="text" class="form-control form-control-sm modal-ap-barra" maxlength="50" value="'
            + escHtml(linea.codigobarra || '') + '" ' + (editable ? '' : 'readonly') + '></td>';
        html += '<td class="align-middle">';
        if (editable) {
            html += htmlSelectUnidades(unidades, umId);
        } else {
            html += '<span>' + escHtml(linea.unidadmedida_compra_label || '—') + '</span>';
        }
        html += '</td>';
        html += '<td class="align-middle text-right"><input type="number" step="0.000001" min="0" class="form-control form-control-sm modal-ap-coef text-right" value="'
            + coef + '" title="Cantidad en UM compra por cada 1 ' + escHtml(linea.unidadmedida_stock_label || 'UM stock') + ' (opcional)" '
            + (editable ? '' : 'readonly') + '></td>';
        html += '<td class="align-middle text-muted"><span class="modal-ap-um-stock font-weight-bold">'
            + escHtml(linea.unidadmedida_stock_label || '—') + '</span></td>';
        html += '</tr>';

        return html;
    }

    function abrirModal(lineas, unidades) {
        var $tbody = $('#tbody-modal-articulo-proveedor');
        $tbody.empty();
        lineas.forEach(function (linea) {
            var $row = $(renderFilaModal(linea, unidades));
            $row.find('.modal-ap-accion-label').text(linea.accion_label || linea.accion || '');
            $tbody.append($row);
        });
        $('#modalRecepcionArticuloProveedor').modal('show');
    }

    function aplicarModalAlFormulario() {
        $('#tbody-modal-articulo-proveedor tr.modal-ap-linea').each(function () {
            var $tr = $(this);
            var idx = parseInt($tr.data('form-idx'), 10);
            if (isNaN(idx)) {
                return;
            }
            var codigo = ($tr.find('.modal-ap-codigo').val() || '').trim();
            var nombre = ($tr.find('.modal-ap-nombre').val() || '').trim();
            var barra = ($tr.find('.modal-ap-barra').val() || '').trim();
            var umId = parseInt($tr.find('.modal-ap-unidad').val(), 10) || 0;
            var umLabel = umId > 0 ? $tr.find('.modal-ap-unidad option:selected').text().trim() : '';
            var coef = parseFloat($tr.find('.modal-ap-coef').val());
            if (!(coef > 0)) {
                coef = 1;
            }

            actualizarCampoItem(idx, 'ocr_codigo_proveedor', codigo);
            actualizarCampoItem(idx, 'ocr_descripcion_proveedor', nombre);
            actualizarCampoItem(idx, 'ocr_codigobarra', barra);
            actualizarCampoItem(idx, 'ocr_unidad_compra', umLabel);
            actualizarCampoItem(idx, 'coeficienteconversion', coef);
            if (umId > 0) {
                actualizarCampoItem(idx, 'unidadmedida_id', umId);
            }

            actualizarItemsActuales(idx, {
                codigo: codigo,
                nombre: nombre,
                barra: barra,
                unidadLabel: umLabel,
                coeficiente: coef
            });
            if (typeof window.recepcionProveedorRefrescarLinea === 'function') {
                window.recepcionProveedorRefrescarLinea(idx);
            }
        });
    }

    function confirmarEnvioDevolucion($form) {
        if ($form.find('[name="tipo"]').val() !== 'DEVOLUCION') {
            return true;
        }

        return window.confirm('¿Confirmar devolución? Generará salida de stock.');
    }

    function reportarPrimerCampoInvalido(formEl) {
        if (!formEl || typeof formEl.querySelectorAll !== 'function') {
            return false;
        }

        var campos = formEl.querySelectorAll('input, select, textarea');
        for (var i = 0; i < campos.length; i++) {
            if (campos[i].disabled) {
                continue;
            }
            if (!campos[i].checkValidity()) {
                if (typeof campos[i].reportValidity === 'function') {
                    campos[i].reportValidity();
                }
                return true;
            }
        }

        return false;
    }

    function enviarFormularioNativo($form) {
        if (!confirmarEnvioDevolucion($form)) {
            return;
        }

        var el = $form[0];
        if (!el) {
            alert('No se encontró el formulario de recepción.');
            return;
        }

        if (reportarPrimerCampoInvalido(el)) {
            window.recepcionProveedorEnviandoTrasModal = false;
            return;
        }

        window.recepcionProveedorEnviandoTrasModal = true;

        if (typeof el.requestSubmit === 'function') {
            el.requestSubmit();
            return;
        }

        var $btn = $form.find('[type="submit"]').first();
        if ($btn.length) {
            $btn[0].click();
            return;
        }

        el.submit();
    }

    function solicitarPreviewYDecidir($form) {
        var url = window.recepcionProveedorPreviewCatalogoUrl;
        if (!url) {
            enviarFormularioNativo($form);
            return;
        }

        var $btn = $form.find('[type="submit"]').first();
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.requiere_modal || !res.lineas || !res.lineas.length) {
                enviarFormularioNativo($form);
                return;
            }
            abrirModal(res.lineas, res.unidades || []);
            $form.data('recepcion-form-pendiente', true);
        }).fail(function (xhr) {
            var msg = 'No se pudo validar el catálogo proveedor.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                msg = xhr.responseJSON.error;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            alert(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    $(function () {
        var $form = $('#form-recepcion-proveedor');
        if (!$form.length || !window.recepcionProveedorModalCatalogoHabilitado) {
            return;
        }

        $form.on('submit.recepcionArticuloProveedor', function (e) {
            if (window.recepcionProveedorEnviandoTrasModal) {
                window.recepcionProveedorEnviandoTrasModal = false;
                return true;
            }
            e.preventDefault();
            solicitarPreviewYDecidir($form);
            return false;
        });

        $('#modalRecepcionArticuloProveedor').on('keydown', 'input, select', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $('#btn-modal-articulo-proveedor-confirmar').trigger('click');
            }
        });

        $('#btn-modal-articulo-proveedor-confirmar').on('click', function () {
            var $btnConfirmar = $(this);
            aplicarModalAlFormulario();

            var $modal = $('#modalRecepcionArticuloProveedor');
            $btnConfirmar.prop('disabled', true);

            $modal.one('hidden.bs.modal', function () {
                $btnConfirmar.prop('disabled', false);
                enviarFormularioNativo($form);
            });
            $modal.modal('hide');
        });
    });
}(jQuery));
