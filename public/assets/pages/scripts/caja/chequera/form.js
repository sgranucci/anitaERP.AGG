(function () {
    var ns = '.chequeraForm';
    var modalAbriendo = false;

    function contenedorCampo() {
        return document.querySelector('#form-general .tm-cuentacaja-campo');
    }

    function modalAbierto() {
        var modal = document.getElementById('consultacuentacajaModal');
        return modalAbriendo || (modal && modal.classList.contains('show'));
    }

    function resolverCarpetaBase() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function limpiarCuentacajaSeleccionada() {
        var cont = contenedorCampo();
        if (!cont) {
            return;
        }
        var hidden = cont.querySelector('.cuentacaja_id');
        var codigo = cont.querySelector('.codigocuentacaja');
        var nombre = cont.querySelector('.descripcioncuentacaja');
        if (hidden) {
            hidden.value = '';
        }
        if (codigo) {
            codigo.value = '';
            codigo.removeAttribute('data-cuentacaja-invalido');
        }
        if (nombre) {
            nombre.value = '';
        }
        var link = cont.querySelector('.btn-link-editar-cuentacaja');
        if (link) {
            link.classList.add('d-none');
            link.setAttribute('href', '#');
        }
    }

    function asignarCuentacaja(data) {
        var cont = contenedorCampo();
        if (!cont || !data || !data.id) {
            return;
        }
        var hidden = cont.querySelector('.cuentacaja_id');
        var codigo = cont.querySelector('.codigocuentacaja');
        var nombre = cont.querySelector('.descripcioncuentacaja');
        if (hidden) {
            hidden.value = data.id;
        }
        if (codigo) {
            codigo.value = data.codigo || '';
            codigo.removeAttribute('data-cuentacaja-invalido');
        }
        if (nombre) {
            nombre.value = data.nombre || '';
        }
        var link = cont.querySelector('.btn-link-editar-cuentacaja');
        if (link) {
            link.setAttribute(
                'href',
                resolverCarpetaBase() + '/caja/cuentacaja/' + encodeURIComponent(String(data.id))
                    + '/editar?origen=modal_consulta&vista=consulta'
            );
            link.classList.remove('d-none');
        }
    }

    function avisar(msg) {
        var $modal = $('#consultacuentacajaModal');
        if ($modal.hasClass('show')) {
            $modal.modal('hide');
        }
        setTimeout(function () {
            alert(msg);
        }, 0);
    }

    function abrirModalConsulta() {
        modalAbriendo = true;
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').one('shown.bs.modal' + ns, function () {
            modalAbriendo = false;
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function leerCuentacajaPorCodigo(codigo) {
        return $.ajax({
            url: resolverCarpetaBase() + '/caja/cuentacaja/leercuentacajaporcodigo/'
                + encodeURIComponent(String(codigo || '').trim()),
            type: 'GET',
            dataType: 'json'
        });
    }

    function focoSiguiente(actual) {
        var campos = $('#form-general').find('input, select, textarea, button').filter(':visible:not([readonly]):not([disabled])');
        var idx = campos.index(actual);
        if (idx >= 0 && idx + 1 < campos.length) {
            campos.eq(idx + 1).trigger('focus');
        }
    }

    function resolverCuentacajaPorCodigo(alertar) {
        var cont = contenedorCampo();
        if (!cont || modalAbierto()) {
            return;
        }
        var $codigo = $(cont).find('.codigocuentacaja');
        var codigo = String($codigo.val() || '').trim();
        if (!codigo) {
            limpiarCuentacajaSeleccionada();
            return;
        }
        if ($codigo.attr('data-cuentacaja-invalido') === codigo) {
            return;
        }

        leerCuentacajaPorCodigo(codigo)
            .done(function (data) {
                if (data && parseInt(data.id, 10) > 0) {
                    asignarCuentacaja(data);
                    if (alertar) {
                        focoSiguiente($codigo.get(0));
                    }
                    return;
                }
                $codigo.attr('data-cuentacaja-invalido', codigo);
                if (alertar) {
                    avisar('No se encontró la cuenta de caja.');
                    $codigo.trigger('focus');
                }
            })
            .fail(function () {
                $codigo.attr('data-cuentacaja-invalido', codigo);
                if (alertar) {
                    avisar('No se encontró la cuenta de caja.');
                    $codigo.trigger('focus');
                }
            });
    }

    function actualizarCantidad() {
        var el = document.getElementById('chequera-cantidad');
        var desdeEl = document.getElementById('desdenumerocheque');
        var hastaEl = document.getElementById('hastanumerocheque');
        if (!el || !desdeEl || !hastaEl) {
            return;
        }
        var desde = String(desdeEl.value || '').replace(/\D+/g, '');
        var hasta = String(hastaEl.value || '').replace(/\D+/g, '');
        if (!desde || !hasta) {
            el.textContent = '';
            return;
        }
        try {
            var cantidad = (BigInt(hasta) - BigInt(desde)) + 1n;
            if (cantidad < 1n) {
                el.textContent = 'El hasta tiene que ser mayor o igual que el desde.';
                return;
            }
            el.textContent = 'Cantidad: ' + cantidad.toLocaleString('es-AR');
        } catch (e) {
            el.textContent = '';
        }
    }

    $(document).on('click' + ns, '#form-general .consultacuentacaja', function (e) {
        e.preventDefault();
        abrirModalConsulta();
    });

    $(document).on('keydown' + ns, '#form-general .codigocuentacaja', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            abrirModalConsulta();
            return;
        }
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            e.stopPropagation();
            resolverCuentacajaPorCodigo(true);
        }
    });

    $(document).on('blur' + ns, '#form-general .codigocuentacaja', function () {
        resolverCuentacajaPorCodigo(false);
    });

    $(document).on('input' + ns, '#form-general .codigocuentacaja', function () {
        $(this).removeAttr('data-cuentacaja-invalido');
    });

    $(document).on('click' + ns, '.eligeconsultacuentacaja', function () {
        if (!contenedorCampo()) {
            return;
        }
        var tr = $(this).closest('tr');
        var id = parseInt(String(tr.find('.cuentacaja_id').text() || '').trim(), 10);
        if (!(id > 0)) {
            return;
        }
        asignarCuentacaja({
            id: id,
            codigo: tr.find('.codigo').text().trim(),
            nombre: tr.find('.nombre').text().trim()
        });
        $('#consultacuentacajaModal').modal('hide');
        var next = document.getElementById('desdenumerocheque');
        if (next) {
            next.focus();
        }
    });

    $('#consultacuentacajaModal').on('hidden.bs.modal' + ns, function () {
        modalAbriendo = false;
    });

    $(document).on('input' + ns, '.chequera-nro', actualizarCantidad);
    actualizarCantidad();
})();
