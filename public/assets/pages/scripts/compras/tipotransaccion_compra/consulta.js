var ptrTipotransaccionCompra_id;
var ptrAbreviaturaTipotransaccionCompra;
var ptrNombreTipotransaccionCompra;

function buscar_datos_tipotransaccion_compra(consulta) {
    var payload = {
        consulta: consulta || '',
    };
    if (typeof window.payloadExtraConsultaTipotransaccionCompra === 'function') {
        $.extend(payload, window.payloadExtraConsultaTipotransaccionCompra());
    }

    $.ajax({
        url: carpetaBase + '/compras/tipotransaccion_compra/consultatipotransaccion',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: payload,
    })
        .done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            } else if (respuesta && typeof respuesta.data === 'string') {
                html = respuesta.data;
            }
            $('#datostipotransaccioncompra').html(html);
        })
        .fail(function () {
            $('#datostipotransaccioncompra').html('<tr><td colspan="7">Error al consultar tipos de comprobante</td></tr>');
        });
}

$(document).on('keyup', '#consultatipotransaccioncompra', function () {
    buscar_datos_tipotransaccion_compra($(this).val());
});

var capturaEnterAbreviaturaTipotransaccionCompraActiva = false;

function apuntarTipotransaccionCompraDesde(target) {
    var $ctx = $(target).closest('.tm-tipotransaccion-compra-campo, tr');
    ptrTipotransaccionCompra_id = $ctx.find('.tipotransaccion_compra_id');
    ptrAbreviaturaTipotransaccionCompra = $ctx.find('.abreviaturatipotransaccioncompra');
    ptrNombreTipotransaccionCompra = $ctx.find('.nombretipotransaccioncompra');
    return $ctx;
}

function abreviaturaTipotransaccionCompraNormalizada(valor) {
    return String(valor || '').trim().toUpperCase();
}

function marcarAbreviaturaTipotransaccionCompraValida($abrev, abreviatura) {
    if ($abrev && $abrev.length && abreviatura) {
        $abrev.data('ultima-valida', abreviatura);
        $abrev.removeData('invalida');
    }
}

function marcarAbreviaturasTipotransaccionCompraIniciales() {
    $('.abreviaturatipotransaccioncompra').each(function () {
        var $abrev = $(this);
        if ($abrev.data('ultima-valida')) {
            return;
        }
        var id = String($abrev.closest('.tm-tipotransaccion-compra-campo, tr').find('.tipotransaccion_compra_id').val() || '');
        var abrev = String($abrev.val() || '').trim();
        if (id !== '' && abrev !== '') {
            $abrev.data('ultima-valida', abrev);
        }
    });
}

function abreviaturaTipotransaccionCompraPendiente(input) {
    if (!input || input.readOnly || input.disabled) {
        return false;
    }
    var $input = $(input);
    var $id = $input.closest('.tm-tipotransaccion-compra-campo, tr').find('.tipotransaccion_compra_id');
    var abrev = String(input.value || '').trim();
    var ultima = String($input.data('ultima-valida') || '').trim();
    if (input._tipoCompraXhr) {
        return true;
    }
    if (abrev === '') {
        return String($id.val() || '') !== '';
    }
    return String($id.val() || '') === ''
        || abreviaturaTipotransaccionCompraNormalizada(ultima) !== abreviaturaTipotransaccionCompraNormalizada(abrev);
}

function aplicarTipotransaccionCompraElegido(id, abreviatura, nombre) {
    if (ptrTipotransaccionCompra_id && ptrTipotransaccionCompra_id.length) {
        var prev = String(ptrTipotransaccionCompra_id.val() || '');
        ptrTipotransaccionCompra_id.val(id || '');
        if (ptrAbreviaturaTipotransaccionCompra && ptrAbreviaturaTipotransaccionCompra.length) {
            ptrAbreviaturaTipotransaccionCompra.val(abreviatura || '');
            if (id && abreviatura) {
                marcarAbreviaturaTipotransaccionCompraValida(ptrAbreviaturaTipotransaccionCompra, abreviatura);
            } else {
                ptrAbreviaturaTipotransaccionCompra.removeData('ultima-valida');
            }
        }
        if (ptrNombreTipotransaccionCompra && ptrNombreTipotransaccionCompra.length) {
            ptrNombreTipotransaccionCompra.val(nombre || '');
        }
        var $ctx = ptrTipotransaccionCompra_id.closest('.tm-tipotransaccion-compra-campo');
        var $link = $ctx.find('.btn-link-editar-tipotransaccion-compra');
        if ($link.length) {
            if (id) {
                $link.removeClass('d-none').attr(
                    'href',
                    carpetaBase + '/compras/tipotransaccion_compra/' + id + '/editar?origen=modal_consulta&vista=consulta'
                );
            } else {
                $link.addClass('d-none').attr('href', '#');
            }
        }
        if (String(id || '') !== prev) {
            $(document).trigger('cp:tipotransaccion-compra-elegido', [parseInt(id || '0', 10) || 0]);
        }
    }
}

function terminarLecturaTipotransaccionCompra(target, data, callbacks) {
    (callbacks || []).forEach(function (callback) {
        if (typeof callback === 'function') {
            callback(data);
        }
    });
    if (target) {
        target._tipoCompraXhr = null;
        target._tipoCompraXhrAbrev = '';
        target._tipoCompraXhrCola = [];
    }
}

function leerTipotransaccionCompraPorAbreviatura(abreviatura, target, callback, opciones) {
    var abrev = String(abreviatura || '').trim();
    var avisar = !opciones || opciones.avisar !== false;
    if (target) {
        apuntarTipotransaccionCompraDesde(target);
    }
    if (abrev === '') {
        aplicarTipotransaccionCompraElegido('', '', '');
        if (typeof callback === 'function') {
            callback(null);
        }
        return;
    }

    var abrevClave = abreviaturaTipotransaccionCompraNormalizada(abrev);
    if (target && target._tipoCompraXhr && target._tipoCompraXhrAbrev === abrevClave) {
        target._tipoCompraXhrCola = target._tipoCompraXhrCola || [];
        target._tipoCompraXhrCola.push(callback);
        if (avisar) {
            target._tipoCompraXhrAvisar = true;
        }
        return;
    }

    var payload = {};
    if (typeof window.payloadExtraConsultaTipotransaccionCompra === 'function') {
        $.extend(payload, window.payloadExtraConsultaTipotransaccionCompra());
    }

    var xhr = $.ajax({
        url: carpetaBase + '/compras/tipotransaccion_compra/leer/' + encodeURIComponent(abrev),
        type: 'GET',
        dataType: 'json',
        data: payload,
    });

    if (target) {
        target._tipoCompraXhr = xhr;
        target._tipoCompraXhrAbrev = abrevClave;
        target._tipoCompraXhrAvisar = avisar;
        target._tipoCompraXhrCola = [];
    }

    xhr.done(function (data) {
            var callbacks = [callback].concat((target && target._tipoCompraXhrCola) || []);
            var debeAvisar = target ? !!target._tipoCompraXhrAvisar : avisar;
            if (target && abreviaturaTipotransaccionCompraNormalizada(target.value) !== abrevClave) {
                terminarLecturaTipotransaccionCompra(target, null, callbacks);
                return;
            }
            if (target) {
                apuntarTipotransaccionCompraDesde(target);
            }
            if (!data || !data.id) {
                if (debeAvisar) {
                    alert('No se encontró el tipo de comprobante «' + abrev + '».');
                }
                if (ptrTipotransaccionCompra_id && ptrTipotransaccionCompra_id.length) {
                    ptrTipotransaccionCompra_id.val('');
                }
                if (ptrNombreTipotransaccionCompra && ptrNombreTipotransaccionCompra.length) {
                    ptrNombreTipotransaccionCompra.val('');
                }
                if (target) {
                    $(target).data('invalida', abrev).removeData('ultima-valida');
                    if (debeAvisar) {
                        $(target).trigger('focus');
                    }
                }
                terminarLecturaTipotransaccionCompra(target, null, callbacks);
                return;
            }
            aplicarTipotransaccionCompraElegido(data.id, data.abreviatura || abrev, data.nombre || '');
            terminarLecturaTipotransaccionCompra(target, data, callbacks);
        })
        .fail(function () {
            var callbacks = [callback].concat((target && target._tipoCompraXhrCola) || []);
            var debeAvisar = target ? !!target._tipoCompraXhrAvisar : avisar;
            if (debeAvisar) {
                alert('Error al validar la abreviatura del tipo de comprobante.');
            }
            terminarLecturaTipotransaccionCompra(target, null, callbacks);
        });
}

function resolverTipotransaccionCompraAntesDeEnviar(input) {
    return new Promise(function (resolve) {
        if (!input || input.readOnly || input.disabled) {
            resolve(true);
            return;
        }
        apuntarTipotransaccionCompraDesde(input);
        var abrev = String(input.value || '').trim();
        if (abrev === '') {
            aplicarTipotransaccionCompraElegido('', '', '');
            resolve(true);
            return;
        }
        if (!abreviaturaTipotransaccionCompraPendiente(input)) {
            resolve(true);
            return;
        }
        leerTipotransaccionCompraPorAbreviatura(abrev, input, function (data) {
            resolve(!!(data && data.id));
        }, { avisar: true });
    });
}

function manejarEnterAbreviaturaTipotransaccionCompra(e) {
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('abreviaturatipotransaccioncompra')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }

    if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(target).closest('.tm-tipotransaccion-compra-campo, tr').find('.consultatipotransaccioncompra').first().trigger('click');
        return;
    }

    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    apuntarTipotransaccionCompraDesde(target);

    leerTipotransaccionCompraPorAbreviatura(target.value, target, function (data) {
        if (data && typeof window.afterTipotransaccionCompraEnterOk === 'function') {
            window.afterTipotransaccionCompraEnterOk(data, target);
        }
    });
}

function activarCapturaEnterAbreviaturaTipotransaccionCompra() {
    if (capturaEnterAbreviaturaTipotransaccionCompraActiva) {
        return;
    }
    document.addEventListener('keydown', manejarEnterAbreviaturaTipotransaccionCompra, true);
    capturaEnterAbreviaturaTipotransaccionCompraActiva = true;
}

var capturaSubmitTipotransaccionCompraActiva = false;

function activarCapturaSubmitTipotransaccionCompra() {
    if (capturaSubmitTipotransaccionCompraActiva) {
        return;
    }
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelectorAll) {
            return;
        }
        if (form.dataset.tipotransaccionCompraListo === '1') {
            delete form.dataset.tipotransaccionCompraListo;
            return;
        }
        var pendientes = [];
        form.querySelectorAll('.abreviaturatipotransaccioncompra').forEach(function (input) {
            if (abreviaturaTipotransaccionCompraPendiente(input)) {
                pendientes.push(input);
            }
        });
        if (!pendientes.length) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        var cadena = Promise.resolve(true);
        pendientes.forEach(function (input) {
            cadena = cadena.then(function (ok) {
                if (!ok) {
                    return false;
                }
                return resolverTipotransaccionCompraAntesDeEnviar(input);
            });
        });
        cadena.then(function (ok) {
            if (!ok) {
                return;
            }
            form.dataset.tipotransaccionCompraListo = '1';
            if (typeof form.requestSubmit === 'function') {
                try {
                    form.requestSubmit(submitter || undefined);
                    return;
                } catch (err) {
                    form.requestSubmit();
                    return;
                }
            }
            form.submit();
        });
    }, true);
    capturaSubmitTipotransaccionCompraActiva = true;
}

function activa_eventos_consultatipotransaccioncompra() {
    activarCapturaEnterAbreviaturaTipotransaccionCompra();
    activarCapturaSubmitTipotransaccionCompra();
    marcarAbreviaturasTipotransaccionCompraIniciales();

    $(document)
        .off('mousedown.consultaTipotransaccionCompra')
        .on('mousedown.consultaTipotransaccionCompra', '.consultatipotransaccioncompra', function () {
            $(this).closest('.tm-tipotransaccion-compra-campo, tr')
                .find('.abreviaturatipotransaccioncompra')
                .data('abriendo-modal', 1);
        });

    $('.consultatipotransaccioncompra')
        .off('click.consultaTipotransaccionCompra')
        .on('click.consultaTipotransaccionCompra', function () {
            var $btn = $(this);
            apuntarTipotransaccionCompraDesde($btn);

            $('#consultatipotransaccioncompraModal')
                .removeAttr('inert')
                .css('display', '')
                .modal('show');
        });

    $('#consultatipotransaccioncompraModal')
        .off('shown.bs.modal.consultaTipotransaccionCompra')
        .on('shown.bs.modal.consultaTipotransaccionCompra', function () {
            $(this).removeAttr('inert');
            var $input = $('#consultatipotransaccioncompra');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
            buscar_datos_tipotransaccion_compra($input.val());
        });

    $('#aceptaconsultatipotransaccioncompraModal')
        .off('click.consultaTipotransaccionCompra')
        .on('click.consultaTipotransaccionCompra', function () {
            $('#consultatipotransaccioncompraModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultatipotransaccioncompra')
        .on('click', '.eligeconsultatipotransaccioncompra', function () {
            var $tr = $(this).parents('tr');
            aplicarTipotransaccionCompraElegido(
                $tr.find('.id').html(),
                $tr.find('.abreviatura').html(),
                $tr.find('.nombre').html()
            );
            $('#consultatipotransaccioncompraModal').modal('hide');
        });

    $(document)
        .off('input.abreviaturaTipotransaccionCompra')
        .on('input.abreviaturaTipotransaccionCompra', '.abreviaturatipotransaccioncompra', function () {
            var abrev = String(this.value || '').trim();
            var ultima = String($(this).data('ultima-valida') || '').trim();
            if (abreviaturaTipotransaccionCompraNormalizada(abrev) === abreviaturaTipotransaccionCompraNormalizada(ultima) && ultima !== '') {
                return;
            }
            $(this).removeData('ultima-valida');
            $(this).removeData('invalida');
            var $ctx = $(this).closest('.tm-tipotransaccion-compra-campo, tr');
            var $id = $ctx.find('.tipotransaccion_compra_id');
            if (String($id.val() || '') !== '') {
                $id.val('');
            }
            $ctx.find('.nombretipotransaccioncompra').val('');
        });

    $(document)
        .off('blur.abreviaturaTipotransaccionCompra')
        .on('blur.abreviaturaTipotransaccionCompra', '.abreviaturatipotransaccioncompra', function () {
            var target = this;
            if (target.readOnly || target.disabled) {
                return;
            }
            if ($(target).data('abriendo-modal') || $('#consultatipotransaccioncompraModal').hasClass('show')) {
                $(target).removeData('abriendo-modal');
                return;
            }
            apuntarTipotransaccionCompraDesde(target);
            var actualId = String(ptrTipotransaccionCompra_id.val() || '');
            var abrev = String(target.value || '').trim();
            if (abrev === '') {
                if (actualId !== '') {
                    aplicarTipotransaccionCompraElegido('', '', '');
                }
                return;
            }
            var ultima = String(ptrAbreviaturaTipotransaccionCompra.data('ultima-valida') || '');
            if (actualId !== '' && abreviaturaTipotransaccionCompraNormalizada(ultima) === abreviaturaTipotransaccionCompraNormalizada(abrev)) {
                return;
            }
            leerTipotransaccionCompraPorAbreviatura(abrev, target, null, { avisar: false });
        });
}
