var ptrMoneda_id;
var ptrCodigoMoneda;
var ptrNombreMoneda;

function buscar_datos_moneda(consulta) {
    $.ajax({
        url: carpetaBase + '/configuracion/moneda/consulta',
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: { consulta: consulta || '' },
    })
        .done(function (respuesta) {
            $('#datosmoneda').html((respuesta && respuesta.data) || '');
        })
        .fail(function () {
            $('#datosmoneda').html('<tr><td colspan="5">Error al consultar monedas</td></tr>');
        });
}

function apuntarMonedaDesde(target) {
    var $ctx = $(target).closest('.tm-moneda-campo, tr');
    ptrMoneda_id = $ctx.find('.moneda_id');
    ptrCodigoMoneda = $ctx.find('.codigomoneda');
    ptrNombreMoneda = $ctx.find('.nombreMoneda');
    return $ctx;
}

function codigoMonedaNormalizado(valor) {
    return String(valor || '').trim().toUpperCase();
}

function aplicarMonedaElegida(id, codigo, nombre) {
    if (!ptrMoneda_id || !ptrMoneda_id.length) {
        return;
    }
    ptrMoneda_id.val(id || '');
    if (ptrCodigoMoneda && ptrCodigoMoneda.length) {
        ptrCodigoMoneda.val(codigo || '');
        if (id && codigo) {
            ptrCodigoMoneda.data('ultima-valida', codigo);
            ptrCodigoMoneda.removeData('invalida');
        } else {
            ptrCodigoMoneda.removeData('ultima-valida');
        }
    }
    if (ptrNombreMoneda && ptrNombreMoneda.length) {
        ptrNombreMoneda.val(nombre || '');
    }
    ptrMoneda_id.trigger('change');
    var $link = ptrMoneda_id.closest('.tm-moneda-campo').find('.btn-link-editar-moneda');
    if ($link.length) {
        if (id) {
            $link.removeClass('d-none').attr(
                'href',
                carpetaBase + '/configuracion/moneda/' + id + '/editar?origen=modal_consulta&vista=consulta'
            );
        } else {
            $link.addClass('d-none').attr('href', '#');
        }
    }
}

function terminarLecturaMoneda(target, data, callbacks) {
    (callbacks || []).forEach(function (callback) {
        if (typeof callback === 'function') {
            callback(data);
        }
    });
    if (target) {
        target._monedaXhr = null;
        target._monedaXhrCodigo = '';
        target._monedaXhrCola = [];
    }
}

function leerMonedaPorCodigo(codigo, target, callback, opciones) {
    var texto = String(codigo || '').trim();
    var avisar = !opciones || opciones.avisar !== false;
    if (target) {
        apuntarMonedaDesde(target);
    }
    if (texto === '') {
        aplicarMonedaElegida('', '', '');
        if (typeof callback === 'function') {
            callback(null);
        }
        return;
    }

    var clave = codigoMonedaNormalizado(texto);
    if (target && target._monedaXhr && target._monedaXhrCodigo === clave) {
        target._monedaXhrCola = target._monedaXhrCola || [];
        target._monedaXhrCola.push(callback);
        if (avisar) {
            target._monedaXhrAvisar = true;
        }
        return;
    }

    var xhr = $.ajax({
        url: carpetaBase + '/configuracion/moneda/leer/' + encodeURIComponent(texto),
        type: 'GET',
        dataType: 'json',
    });

    if (target) {
        target._monedaXhr = xhr;
        target._monedaXhrCodigo = clave;
        target._monedaXhrAvisar = avisar;
        target._monedaXhrCola = [];
    }

    xhr.done(function (data) {
        var callbacks = [callback].concat((target && target._monedaXhrCola) || []);
        var debeAvisar = target ? !!target._monedaXhrAvisar : avisar;
        if (target && codigoMonedaNormalizado(target.value) !== clave) {
            terminarLecturaMoneda(target, null, callbacks);
            return;
        }
        if (target) {
            apuntarMonedaDesde(target);
        }
        if (!data || !data.id) {
            if (ptrMoneda_id && ptrMoneda_id.length) {
                ptrMoneda_id.val('');
            }
            if (ptrNombreMoneda && ptrNombreMoneda.length) {
                ptrNombreMoneda.val('');
            }
            if (target) {
                $(target).data('invalida', texto).removeData('ultima-valida');
            }
            if (debeAvisar) {
                $('#consultamonedaModal').modal('hide');
                setTimeout(function () {
                    alert('No se encontró la moneda «' + texto + '».');
                    if (target) {
                        $(target).trigger('focus');
                    }
                }, 0);
            }
            terminarLecturaMoneda(target, null, callbacks);
            return;
        }
        aplicarMonedaElegida(data.id, data.codigo || texto, data.nombre || '');
        terminarLecturaMoneda(target, data, callbacks);
    }).fail(function () {
        var callbacks = [callback].concat((target && target._monedaXhrCola) || []);
        var debeAvisar = target ? !!target._monedaXhrAvisar : avisar;
        if (debeAvisar) {
            $('#consultamonedaModal').modal('hide');
            setTimeout(function () {
                alert('Error al validar el código de moneda.');
            }, 0);
        }
        terminarLecturaMoneda(target, null, callbacks);
    });
}

function manejarTeclaCodigoMoneda(e) {
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('codigomoneda')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }

    if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(target).closest('.tm-moneda-campo, tr').find('.consultamoneda').first().trigger('click');
        return;
    }

    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();
    apuntarMonedaDesde(target);
    leerMonedaPorCodigo(target.value, target, null, { avisar: false });
}

var capturaTeclaCodigoMonedaActiva = false;

function activa_eventos_consultamoneda() {
    if (!capturaTeclaCodigoMonedaActiva) {
        document.addEventListener('keydown', manejarTeclaCodigoMoneda, true);
        capturaTeclaCodigoMonedaActiva = true;
    }

    $(document)
        .off('mousedown.consultaMoneda')
        .on('mousedown.consultaMoneda', '.consultamoneda', function () {
            $(this).closest('.tm-moneda-campo, tr').find('.codigomoneda').data('abriendo-modal', 1);
        });

    $(document)
        .off('click.consultaMoneda')
        .on('click.consultaMoneda', '.consultamoneda', function () {
            apuntarMonedaDesde(this);
            $('#consultamonedaModal').removeAttr('inert').css('display', '').modal('show');
        });

    $('#consultamonedaModal')
        .off('shown.bs.modal.consultaMoneda')
        .on('shown.bs.modal.consultaMoneda', function () {
            var $input = $('#consultamoneda');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
            buscar_datos_moneda($input.val());
        });

    $(document)
        .off('keyup.consultaMoneda')
        .on('keyup.consultaMoneda', '#consultamoneda', function (e) {
            if (e.key === 'Enter') {
                return;
            }
            buscar_datos_moneda($(this).val());
        });

    $(document)
        .off('keydown.consultaMonedaEnter')
        .on('keydown.consultaMonedaEnter', '#consultamoneda', function (e) {
            if (e.key !== 'Enter' && e.which !== 13) {
                return;
            }
            e.preventDefault();
            var $elegir = $('#datosmoneda .eligeconsultamoneda').first();
            if ($elegir.length) {
                $elegir.trigger('click');
            }
        });

    $('#aceptaconsultamonedaModal')
        .off('click.consultaMoneda')
        .on('click.consultaMoneda', function () {
            $('#consultamonedaModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultamoneda')
        .on('click.eligeconsultamoneda', '.eligeconsultamoneda', function () {
            var $tr = $(this).closest('tr');
            aplicarMonedaElegida(
                $tr.find('.id').text(),
                $tr.find('.codigo').text(),
                $tr.find('.nombre').text()
            );
            $('#consultamonedaModal').modal('hide');
        });

    $(document)
        .off('input.codigoMoneda')
        .on('input.codigoMoneda', '.codigomoneda', function () {
            var codigo = String(this.value || '').trim();
            var ultima = String($(this).data('ultima-valida') || '').trim();
            if (codigoMonedaNormalizado(codigo) === codigoMonedaNormalizado(ultima) && ultima !== '') {
                return;
            }
            $(this).removeData('ultima-valida').removeData('invalida');
            var $ctx = $(this).closest('.tm-moneda-campo, tr');
            $ctx.find('.moneda_id').val('');
            $ctx.find('.nombreMoneda').val('');
        });

    $(document)
        .off('blur.codigoMoneda')
        .on('blur.codigoMoneda', '.codigomoneda', function () {
            var target = this;
            if (target.readOnly || target.disabled) {
                return;
            }
            if ($(target).data('abriendo-modal') || $('#consultamonedaModal').hasClass('show')) {
                $(target).removeData('abriendo-modal');
                return;
            }
            apuntarMonedaDesde(target);
            var codigo = String(target.value || '').trim();
            if (codigo === '') {
                if (String(ptrMoneda_id.val() || '') !== '') {
                    aplicarMonedaElegida('', '', '');
                }
                return;
            }
            var ultima = String($(target).data('ultima-valida') || '');
            if (String(ptrMoneda_id.val() || '') !== ''
                && codigoMonedaNormalizado(ultima) === codigoMonedaNormalizado(codigo)) {
                return;
            }
            leerMonedaPorCodigo(codigo, target, null, { avisar: false });
        });
}
