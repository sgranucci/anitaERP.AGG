$(function () {
    $('#pegar_excel_asiento').on('click', function () {
        $('#asiento_pegar_excel_aviso').empty();
        $('#modalAsientoPegarExcel').modal('show');
    });

    $('#modalAsientoPegarExcel').on('shown.bs.modal', function () {
        $('#asiento_pegar_excel_texto').trigger('focus');
    });

    $('#asiento_pegar_excel_aplicar').on('click', function () {
        var texto = $('#asiento_pegar_excel_texto').val() || '';
        decodificarPegadoAsiento(texto, true);
    });

    $(document).on('paste', '#cuenta-table, #asiento_pegar_excel_texto', function (e) {
        if (archivoImagenPortapapeles(e)) {
            return;
        }
        var clipboard = e.originalEvent && e.originalEvent.clipboardData
            ? e.originalEvent.clipboardData
            : window.clipboardData;
        if (!clipboard) {
            return;
        }
        var texto = clipboard.getData('text') || '';
        if (texto.indexOf('\n') < 0 && texto.indexOf('\t') < 0) {
            return;
        }
        e.preventDefault();
        decodificarPegadoAsiento(texto, false);
    });

    document.addEventListener('paste', function (e) {
        if (!$('#cuenta-table').length && !$('#modalAsientoPegarExcel').length) {
            return;
        }
        var archivo = archivoImagenPortapapeles(e);
        if (!archivo) {
            return;
        }
        e.preventDefault();
        mostrarImagenPegada(archivo);
        decodificarPegadoAsiento('', true, archivo);
    }, true);
});

function archivoImagenPortapapeles(e) {
    var clipboard = (e && e.clipboardData)
        || (e && e.originalEvent && e.originalEvent.clipboardData)
        || null;
    if (!clipboard) {
        return null;
    }

    var blob = null;
    if (clipboard.items && clipboard.items.length) {
        for (var i = 0; i < clipboard.items.length; i++) {
            var item = clipboard.items[i];
            if (item.kind === 'file' && item.type && item.type.indexOf('image/') === 0) {
                blob = item.getAsFile();
                if (blob) {
                    break;
                }
            }
        }
    }
    if (!blob && clipboard.files && clipboard.files.length) {
        for (var j = 0; j < clipboard.files.length; j++) {
            if (clipboard.files[j].type && clipboard.files[j].type.indexOf('image/') === 0) {
                blob = clipboard.files[j];
                break;
            }
        }
    }
    if (!blob) {
        return null;
    }

    var tipo = blob.type || 'image/png';
    var ext = 'png';
    if (tipo.indexOf('jpeg') >= 0 || tipo.indexOf('jpg') >= 0) {
        ext = 'jpg';
    } else if (tipo.indexOf('webp') >= 0) {
        ext = 'webp';
    } else if (tipo.indexOf('bmp') >= 0) {
        ext = 'bmp';
    } else if (tipo.indexOf('tif') >= 0) {
        ext = 'tif';
    }

    try {
        return new File([blob], 'asiento.' + ext, { type: tipo });
    } catch (err) {
        return blob;
    }
}

function mostrarImagenPegada(archivo) {
    var $img = $('#asiento_pegar_vista');
    if (!$img.length || !archivo || !window.URL || !URL.createObjectURL) {
        $('#modalAsientoPegarExcel').modal('show');
        return;
    }
    var anterior = $img.data('url');
    if (anterior) {
        URL.revokeObjectURL(anterior);
    }
    var url = URL.createObjectURL(archivo);
    asientoArchivoPegado = archivo;
    $img.attr('src', url).data('url', url).removeClass('d-none');
    $('#asiento_pegar_excel_aviso')
        .removeClass('text-danger text-success')
        .addClass('text-muted')
        .text('Leyendo la imagen…');
    $('#modalAsientoPegarExcel').modal('show');
}

function grillaAsientoTieneDatos() {
    var hay = false;
    $('#tbody-cuenta-table tr.item-cuenta').each(function () {
        var codigo = $.trim($(this).find('.codigocuentacontable').val() || '');
        var parseM = window.AsientoMontosFormato
            ? AsientoMontosFormato.parseDecimal.bind(AsientoMontosFormato)
            : function (v) { return parseFloat(v) || 0; };
        var debe = parseM($(this).find('.debe').val());
        var haber = parseM($(this).find('.haber').val());
        if (codigo !== '' || debe > 0.000001 || haber > 0.000001) {
            hay = true;
            return false;
        }
    });
    return hay;
}

var asientoArchivoPegado = null;

function decodificarPegadoAsiento(texto, desdeModal, archivoDirecto) {
    var inputArchivo = document.getElementById('asiento_pegar_archivo');
    var archivoElegido = inputArchivo && inputArchivo.files && inputArchivo.files[0] ? inputArchivo.files[0] : null;
    var archivo = archivoDirecto || archivoElegido || null;
    texto = (texto || '').trim();
    if (!archivo && !texto && asientoArchivoPegado) {
        archivo = asientoArchivoPegado;
    }
    if (!texto && !archivo) {
        alert('Suba el PDF o la imagen, o pegue el texto del asiento.');
        return;
    }

    var empresaId = parseInt($('#empresa_id').val(), 10) || 0;
    if (!empresaId) {
        alert('Indique la empresa antes de pegar el asiento.');
        return;
    }

    if (grillaAsientoTieneDatos()) {
        if (!window.confirm('Se reemplazan las líneas actuales del asiento por lo pegado.')) {
            return;
        }
    }

    var $aviso = $('#asiento_pegar_excel_aviso');
    $aviso.removeClass('text-danger text-success').addClass('text-muted').text(archivo ? 'Leyendo el archivo…' : 'Leyendo…');

    var fd = new FormData();
    fd.append('_token', $('#csrf_token').val());
    fd.append('empresa_id', empresaId);
    if (archivo) {
        fd.append('archivo', archivo);
    } else {
        fd.append('texto', texto);
    }

    $.ajax({
        url: (window.carpetaBase || '') + '/contable/asiento/pegar-excel',
        method: 'POST',
        dataType: 'json',
        data: fd,
        processData: false,
        contentType: false
    }).done(function (data) {
        if (!data || data.ok === false) {
            var msg = (data && data.mensaje) ? data.mensaje : 'No se pudo leer el pegado.';
            if (desdeModal) {
                $aviso.removeClass('text-muted text-success').addClass('text-danger').text(msg);
            } else {
                alert(msg);
            }
            return;
        }

        aplicarFilasPegadasAsiento(data.filas || []);

        var resumen = 'Se cargaron ' + (data.filas || []).length + ' línea(s). Debe '
            + (data.total_debe_texto || '') + ' / Haber ' + (data.total_haber_texto || '') + '.';
        var avisos = data.advertencias || [];
        if (avisos.length) {
            resumen += '\n\n' + avisos.join('\n');
        }
        if (desdeModal) {
            if (inputArchivo) {
                inputArchivo.value = '';
            }
            $('#modalAsientoPegarExcel').modal('hide');
        }
        alert(resumen);
    }).fail(function (xhr) {
        var msg = 'No se pudo leer el pegado.';
        if (xhr.responseJSON && xhr.responseJSON.mensaje) {
            msg = xhr.responseJSON.mensaje;
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
        }
        if (desdeModal) {
            $aviso.removeClass('text-muted text-success').addClass('text-danger').text(msg);
        } else {
            alert(msg);
        }
    });
}

function idMonedaPesos() {
    var encontrado = '';
    $('.moneda option').each(function () {
        var texto = $.trim($(this).text()).toUpperCase();
        if (texto === 'PES' || texto === 'ARS' || texto === '$' || texto === 'MN' || texto === 'PESOS') {
            encontrado = $(this).val();
            return false;
        }
    });
    if (!encontrado && $('.moneda option[value="1"]').length) {
        encontrado = '1';
    }
    return encontrado;
}

function aplicarFilasPegadasAsiento(filas) {
    var $tbody = $('#tbody-cuenta-table');
    var monedaPesos = idMonedaPesos();

    $tbody.empty();

    filas.forEach(function (fila) {
        $tbody.append($('#template-renglon-cuenta').html());
        var $tr = $tbody.find('tr.item-cuenta').last();
        var codigo = fila.codigo_cuenta || '';
        var cuentaId = fila.cuentacontable_id || '';

        $tr.find('.codigocuentacontable').val(codigo);
        $tr.find('.codigo_previo').val(cuentaId ? codigo : '');
        $tr.find('.cuentacontable_id, .cuentacontable_id_previa').val(cuentaId);
        $tr.find('.nombrecuentacontable').val(fila.cuenta_nombre || '');
        if (monedaPesos) {
            $tr.find('.moneda').val(monedaPesos);
        }
        $tr.find('.debe').val(fila.debe_texto || '');
        $tr.find('.haber').val(fila.haber_texto || '');
        if (monedaPesos && window.AsientoMontosFormato) {
            $tr.find('.cotizacion').val(AsientoMontosFormato.fmt(1));
        }
        $tr.find('.asiento-ta-detalle').val(fila.detalle || '');
        if (typeof asientoRefreshDetallePreview === 'function') {
            asientoRefreshDetallePreview($tr);
        }
        if (typeof actualizarLinkEditarCuentaContable === 'function') {
            actualizarLinkEditarCuentaContable($tr, cuentaId);
        }

        if (fila.manejaccosto === 'S' && cuentaId && typeof completarCentroCosto === 'function') {
            $tr.attr('data-manejaccosto', 'S');
            completarCentroCosto($tr.find('.codigocuentacontable'), cuentaId, fila.centrocosto_id || 0);
        } else if (cuentaId) {
            $tr.attr('data-manejaccosto', 'N');
            $tr.find('.centrocosto')
                .empty()
                .append('<option value="0" selected>Sin CC</option>')
                .attr('readonly', true);
            if (typeof sincronizarCentrocostoPrevio === 'function') {
                sincronizarCentrocostoPrevio($tr);
            }
        }

        if (window.AsientoMontosFormato) {
            AsientoMontosFormato.initEnContenedor($tr);
        }
    });

    if (typeof actualizaRenglonesCuenta === 'function') {
        actualizaRenglonesCuenta();
    }
    if (typeof activa_eventos === 'function') {
        activa_eventos(false);
    }
    if (typeof asientoAplicarMonedaDesdePrimera === 'function') {
        asientoAplicarMonedaDesdePrimera(false);
    }
    if (monedaPesos && typeof leeCotizacion === 'function') {
        leeCotizacion($tbody.find('tr.item-cuenta').first().find('.moneda'));
    }
    if (typeof sumaMonto === 'function') {
        sumaMonto();
    }
}
