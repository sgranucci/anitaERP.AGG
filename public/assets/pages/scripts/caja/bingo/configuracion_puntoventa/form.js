(function () {
    function resolverCarpetaBase() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }

        return '';
    }

    function empresaId() {
        var el = document.getElementById('empresa_id');
        return el ? String(el.value || '').trim() : '';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content') || '';
        }
        var input = document.querySelector('#form-general input[name="_token"]');
        return input ? input.value : '';
    }

    function parseConsultaResponse(respuesta) {
        if (respuesta && typeof respuesta === 'object' && respuesta.data !== undefined) {
            return respuesta.data;
        }
        if (typeof respuesta === 'string') {
            try {
                return JSON.parse(respuesta).data || '';
            } catch (e) {
                return respuesta;
            }
        }

        return '';
    }

    function contenedorCampo() {
        return document.querySelector('.tm-cuentacaja-campo');
    }

    function limpiarCuentacajaSeleccionada() {
        var cont = contenedorCampo();
        if (!cont) {
            return;
        }
        cont.querySelector('.cuentacaja_id').value = '';
        cont.querySelector('.codigocuentacaja').value = '';
        cont.querySelector('.descripcioncuentacaja').value = '';
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
        cont.querySelector('.cuentacaja_id').value = data.id;
        cont.querySelector('.codigocuentacaja').value = data.codigo || '';
        cont.querySelector('.descripcioncuentacaja').value = data.nombre || '';
        var link = cont.querySelector('.btn-link-editar-cuentacaja');
        if (link) {
            var base = resolverCarpetaBase();
            link.setAttribute(
                'href',
                base + '/caja/cuentacaja/' + encodeURIComponent(String(data.id)) + '/editar?origen=modal_consulta&vista=consulta'
            );
            link.classList.remove('d-none');
        }
    }

    function buscarCuentacaja(consulta) {
        var empresa_id = empresaId();
        if (!empresa_id) {
            alert('Debe seleccionar empresa antes de consultar cuentas de caja.');
            return;
        }

        $.ajax({
            url: resolverCarpetaBase() + '/caja/cuentacaja/consultacuentacaja',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
            },
            data: {
                consulta: consulta || '',
                empresa_id: empresa_id,
            },
        })
            .done(function (respuesta) {
                $('#datoscuentacaja').html(parseConsultaResponse(respuesta));
            })
            .fail(function () {
                $('#datoscuentacaja').html('<tr><td colspan="9">Error al buscar cuentas de caja.</td></tr>');
            });
    }

    function abrirModalConsulta() {
        if (!empresaId()) {
            alert('Debe seleccionar empresa antes de consultar cuentas de caja.');
            return;
        }

        $('#consultaempresacaja_id').val(empresaId());
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');

        $('#consultacuentacajaModal').one('shown.bs.modal.bingoCfgCuenta', function () {
            var $input = $(this).find('#consultacuentacaja');
            $input.trigger('focus');
            buscarCuentacaja('');
        });

        $('#consultacuentacajaModal').modal('show');
    }

    function urlCuentacajaPorCodigo(codigo) {
        return resolverCarpetaBase()
            + '/caja/cuentacaja/leercuentacajaporcodigo/'
            + encodeURIComponent(String(codigo || '').trim());
    }

    function leerCuentacajaPorCodigo(codigo) {
        var empresa_id = empresaId();
        if (!empresa_id) {
            return $.Deferred().reject({ responseJSON: { error: 'Debe seleccionar empresa.' } }).promise();
        }

        return $.ajax({
            url: urlCuentacajaPorCodigo(codigo),
            type: 'GET',
            dataType: 'json',
            data: { empresa_id: empresa_id },
        });
    }

    function buscarCuentacajaPorCodigo() {
        var cont = contenedorCampo();
        if (!cont) {
            return;
        }
        var codigo = String(cont.querySelector('.codigocuentacaja').value || '').trim();
        if (!codigo) {
            limpiarCuentacajaSeleccionada();
            return;
        }

        if (!empresaId()) {
            alert('Debe seleccionar empresa antes de ingresar el c\u00f3digo de cuenta.');
            cont.querySelector('.codigocuentacaja').value = '';
            return;
        }

        leerCuentacajaPorCodigo(codigo)
            .done(function (data) {
                if (data && data.id > 0) {
                    asignarCuentacaja(data);
                } else {
                    alert('No se encontr\u00f3 cuenta de caja para esa empresa.');
                    limpiarCuentacajaSeleccionada();
                    cont.querySelector('.codigocuentacaja').focus();
                }
            })
            .fail(function (xhr) {
                var msg = 'No se encontr\u00f3 cuenta de caja para esa empresa.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                alert(msg);
                limpiarCuentacajaSeleccionada();
                cont.querySelector('.codigocuentacaja').focus();
            });
    }

    $(document).on('click', '.tm-cuentacaja-campo .consultacuentacaja', function (e) {
        e.preventDefault();
        abrirModalConsulta();
    });

    $(document).on('keydown', '#consultacuentacaja', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
        }
    });

    $(document).on('keyup', '#consultacuentacaja', function () {
        buscarCuentacaja($(this).val());
    });

    $(document).on('click', '.eligeconsultacuentacaja', function () {
        var tr = $(this).closest('tr');
        asignarCuentacaja({
            id: tr.find('.cuentacaja_id').text().trim(),
            codigo: tr.find('.codigo').text().trim(),
            nombre: tr.find('.nombre').text().trim(),
        });
        $('#consultacuentacajaModal').modal('hide');
    });

    $('#aceptaconsultacuentacajaModal').on('click', function () {
        $('#consultacuentacajaModal').modal('hide');
    });

    $(document).on('change', '.tm-cuentacaja-campo .codigocuentacaja', function () {
        buscarCuentacajaPorCodigo();
    });

    $(document).on('keydown', '.tm-cuentacaja-campo .codigocuentacaja', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            buscarCuentacajaPorCodigo();
        }
    });

    $('#empresa_id').on('change', function () {
        limpiarCuentacajaSeleccionada();
    });
})();
