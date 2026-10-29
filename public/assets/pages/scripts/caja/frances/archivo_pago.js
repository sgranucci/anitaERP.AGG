(function () {
    var modalAbriendo = false;
    var ns = '.francesCc';
    var formSelector = '#form-frances-archivo-pago';

    window.empresaIdConsultaCuentacajaOverride = function () {
        var form = document.querySelector(formSelector);
        if (!form) {
            return '';
        }
        var emp = form.querySelector('#empresa_id');
        var id = parseInt(String(emp ? emp.value : '0'), 10);
        return id > 0 ? String(id) : '';
    };

    function resolverCarpetaBase() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function contenedorCampo() {
        return document.querySelector(formSelector + ' .tm-cuentacaja-campo');
    }

    function modalAbierto() {
        var modal = document.getElementById('consultacuentacajaModal');
        return modalAbriendo || (modal && modal.classList.contains('show'));
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
        $('#consultacuentacajaModal').one('shown.bs.modal.francesCc', function () {
            modalAbriendo = false;
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function leerCuentacajaPorCodigo(codigo) {
        var data = {};
        var empresaId = window.empresaIdConsultaCuentacajaOverride();
        if (empresaId) {
            data.empresa_id = empresaId;
        }
        return $.ajax({
            url: resolverCarpetaBase() + '/caja/cuentacaja/leercuentacajaporcodigo/'
                + encodeURIComponent(String(codigo || '').trim()),
            type: 'GET',
            dataType: 'json',
            data: data,
        });
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
                if (data && data.id > 0) {
                    asignarCuentacaja(data);
                    if (alertar) {
                        var next = document.querySelector(formSelector + ' button[type="submit"]');
                        if (next) {
                            next.focus();
                        }
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

    $(document).on('click' + ns, formSelector + ' .consultacuentacaja', function (e) {
        e.preventDefault();
        abrirModalConsulta();
    });

    $(document).on('keydown' + ns, formSelector + ' .codigocuentacaja', function (e) {
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

    $(document).on('blur' + ns, formSelector + ' .codigocuentacaja', function () {
        resolverCuentacajaPorCodigo(false);
    });

    $(document).on('input' + ns, formSelector + ' .codigocuentacaja', function () {
        $(this).removeAttr('data-cuentacaja-invalido');
    });

    $(document).on('click' + ns, '.eligeconsultacuentacaja', function () {
        var tr = $(this).closest('tr');
        var id = parseInt(String(tr.find('.cuentacaja_id').text() || '').trim(), 10);
        if (!(id > 0)) {
            return;
        }
        asignarCuentacaja({
            id: id,
            codigo: tr.find('.codigo').text().trim(),
            nombre: tr.find('.nombre').text().trim(),
        });
        $('#consultacuentacajaModal').modal('hide');
        var next = document.querySelector(formSelector + ' button[type="submit"]');
        if (next) {
            next.focus();
        }
    });

    $('#consultacuentacajaModal').on('hidden.bs.modal' + ns, function () {
        modalAbriendo = false;
    });

    $(document).on('submit' + ns, formSelector, function (e) {
        var cont = contenedorCampo();
        var hidden = cont ? cont.querySelector('.cuentacaja_id') : null;
        if (!hidden || !String(hidden.value || '').trim()) {
            e.preventDefault();
            var codigo = cont ? cont.querySelector('.codigocuentacaja') : null;
            if (codigo) {
                codigo.focus();
            }
            avisar('Indique la cuenta de caja.');
        }
    });
})();
