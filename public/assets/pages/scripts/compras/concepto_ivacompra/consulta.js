var ptrConceptoIvacompraId = $();
var ptrCodigoConceptoIvacompra = $();
var ptrNombreConceptoIvacompra = $();

function urlAppComprasConceptoIva(path) {
    var base = (typeof window.carpetaBase === 'string') ? window.carpetaBase.replace(/\/$/, '') : '';
    var p = String(path || '');
    if (p.charAt(0) !== '/') {
        p = '/' + p;
    }
    return base + p;
}

function tipotransaccionCompraIdConsultaConcepto() {
    var v = parseInt(String($('#tipotransaccion_compra_id').val() || '0'), 10);
    return v > 0 ? v : 0;
}

function actualizarAvisoModalConceptoIva(tipoId) {
    var $aviso = $('#consultaconcepto_ivacompra-aviso');
    if (!$aviso.length) {
        return;
    }
    if (tipoId > 0) {
        $aviso.text('Filtrado por tipo de comprobante id ' + tipoId + '.');
    } else {
        $aviso.text('Seleccione el tipo de comprobante en Datos principales.');
    }
}

function buscar_datos_concepto_ivacompra(consulta) {
    var tipoId = tipotransaccionCompraIdConsultaConcepto();
    actualizarAvisoModalConceptoIva(tipoId);
    $('#datosconcepto_ivacompra').html('<tr><td colspan="5" class="text-muted">Buscando…</td></tr>');

    $.ajax({
        url: urlAppComprasConceptoIva('/compras/concepto_ivacompra/consulta'),
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || '',
            tipotransaccion_compra_id: tipoId,
            _token: $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (respuesta) {
            var html = (respuesta && respuesta.data) ? respuesta.data : '';
            if (!html) {
                html = '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>';
            }
            $('#datosconcepto_ivacompra').html(html);
        })
        .fail(function () {
            $('#datosconcepto_ivacompra').html('<tr><td colspan="5" class="text-danger">Error al consultar conceptos</td></tr>');
        });
}

function aplicarConceptoIvacompraEnFila(data) {
    if (!data || !data.id) {
        return;
    }
    if (ptrConceptoIvacompraId && ptrConceptoIvacompraId.length) {
        ptrConceptoIvacompraId.val(String(data.id));
    }
    if (ptrCodigoConceptoIvacompra && ptrCodigoConceptoIvacompra.length) {
        ptrCodigoConceptoIvacompra.val(String(data.codigo || ''));
        ptrCodigoConceptoIvacompra.data('codigo-resuelto', String(data.codigo || ''));
    }
    if (ptrNombreConceptoIvacompra && ptrNombreConceptoIvacompra.length) {
        ptrNombreConceptoIvacompra.val(String(data.nombre || ''));
    }
    $(document).trigger('cp:concepto-ivacompra-elegido', [data]);
}

function resolverConceptoIvacompraPorCodigo($input) {
    var codigo = String($input.val() || '').trim();
    var $row = $input.closest('tr.item-concepto');
    var $hid = $row.find('.concepto_ivacompra_id');
    var $nombre = $row.find('.nombre_concepto_ivacompra');

    if (codigo === '') {
        $hid.val('');
        $nombre.val('');
        $(document).trigger('cp:concepto-ivacompra-elegido');
        return;
    }

    var tipoId = tipotransaccionCompraIdConsultaConcepto();
    if (tipoId <= 0) {
        alert('Seleccione primero el tipo de comprobante.');
        $input.val('');
        $hid.val('');
        $nombre.val('');
        return;
    }

    $.ajax({
        url: urlAppComprasConceptoIva('/compras/concepto_ivacompra/resolver'),
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            valor: codigo,
            tipotransaccion_compra_id: tipoId,
            _token: $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (res) {
            if (!res || !res.ok) {
                alert((res && res.mensaje) ? res.mensaje : 'Concepto no encontrado');
                $input.val('');
                $hid.val('');
                $nombre.val('');
                return;
            }
            ptrConceptoIvacompraId = $hid;
            ptrCodigoConceptoIvacompra = $input;
            ptrNombreConceptoIvacompra = $nombre;
            aplicarConceptoIvacompraEnFila(res);
        })
        .fail(function (xhr) {
            var msg = 'Concepto no encontrado para este tipo de comprobante';
            if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) {
                msg = xhr.responseJSON.mensaje;
            }
            alert(msg);
            $input.val('');
            $hid.val('');
            $nombre.val('');
        });
}

function fijarPtrsConceptoDesdeBoton($btn) {
    var $row = $btn.closest('tr.item-concepto');
    ptrConceptoIvacompraId = $row.find('.concepto_ivacompra_id');
    ptrCodigoConceptoIvacompra = $row.find('.codigo_concepto_ivacompra');
    ptrNombreConceptoIvacompra = $row.find('.nombre_concepto_ivacompra');
}

function activa_eventos_consultaconcepto_ivacompra() {
    if (activa_eventos_consultaconcepto_ivacompra._listo) {
        return;
    }
    activa_eventos_consultaconcepto_ivacompra._listo = true;

    $(document).on('click', '.consultaconcepto_ivacompra', function (event) {
        event.preventDefault();
        fijarPtrsConceptoDesdeBoton($(this));
        var tipoId = tipotransaccionCompraIdConsultaConcepto();
        if (tipoId <= 0) {
            alert('Seleccione primero el tipo de comprobante.');
            return;
        }
        $('#consultaconcepto_ivacompra').val('');
        $('#consultaconcepto_ivacompraModal').modal('show');
        buscar_datos_concepto_ivacompra('');
    });

    $('#consultaconcepto_ivacompraModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultaconcepto_ivacompraModal').on('click', function () {
        $('#consultaconcepto_ivacompraModal').modal('hide');
    });

    $(document).on('keyup', '#consultaconcepto_ivacompra', function () {
        buscar_datos_concepto_ivacompra($(this).val());
    });

    $(document).on('click', '.eligeconsultaconcepto_ivacompra', function () {
        var $tr = $(this).closest('tr');
        aplicarConceptoIvacompraEnFila({
            id: parseInt(String($tr.find('.concepto_ivacompra_id_celda').text() || '0'), 10),
            codigo: String($tr.find('.codigo').text() || '').trim(),
            nombre: String($tr.find('.nombre').text() || '').trim()
        });
        $('#consultaconcepto_ivacompraModal').modal('hide');
    });

    $(document).on('keydown', '.codigo_concepto_ivacompra', function (e) {
        var key = e.which || e.keyCode;
        if (key === 112) { // F1
            e.preventDefault();
            fijarPtrsConceptoDesdeBoton($(this));
            var tipoId = tipotransaccionCompraIdConsultaConcepto();
            if (tipoId <= 0) {
                alert('Seleccione primero el tipo de comprobante.');
                return;
            }
            $('#consultaconcepto_ivacompra').val('');
            $('#consultaconcepto_ivacompraModal').modal('show');
            buscar_datos_concepto_ivacompra('');
            return;
        }
        if (key === 13) {
            e.preventDefault();
            resolverConceptoIvacompraPorCodigo($(this));
        }
    });

    $(document).on('blur', '.codigo_concepto_ivacompra', function () {
        var $input = $(this);
        var codigo = String($input.val() || '').trim();
        var $row = $input.closest('tr.item-concepto');
        var $hid = $row.find('.concepto_ivacompra_id');
        if (codigo === '') {
            $hid.val('');
            $row.find('.nombre_concepto_ivacompra').val('');
            $input.removeData('codigo-resuelto');
            $(document).trigger('cp:concepto-ivacompra-elegido');
            return;
        }
        if (String($input.data('codigo-resuelto') || '') === codigo && parseInt(String($hid.val() || '0'), 10) > 0) {
            return;
        }
        resolverConceptoIvacompraPorCodigo($input);
    });
}

$(function () {
    if ($('#form-comprobante-proveedor').length || $('#consultaconcepto_ivacompraModal').length) {
        activa_eventos_consultaconcepto_ivacompra();
    }
});
