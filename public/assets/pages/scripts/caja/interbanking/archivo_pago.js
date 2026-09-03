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
        return meta ? meta.getAttribute('content') : '';
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

    function aplicarCuenta(data) {
        var id = data && data.id ? String(data.id) : '';
        var codigo = data && data.codigo ? String(data.codigo) : '';
        var nombre = data && data.nombre ? String(data.nombre) : '';
        var cbu = String(data && data.cbu ? data.cbu : '').replace(/\D+/g, '');

        $('#cuentacaja_id').val(id);
        $('#codigo_cuentacaja').val(codigo);
        $('#nombre_cuentacaja').val(nombre);
        $('#cbu_origen').val(cbu);
        $('#cbu_origen_mostrar').val(cbu);
    }

    function limpiarCuenta() {
        aplicarCuenta({ id: '', codigo: '', nombre: '', cbu: '' });
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
                $('#datoscuentacaja').html('<tr><td colspan="10">Error al buscar cuentas de caja.</td></tr>');
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

        $('#consultacuentacajaModal').one('shown.bs.modal.ibArchivoPago', function () {
            var $input = $(this).find('#consultacuentacaja');
            $input.trigger('focus');
            buscarCuentacaja('');
        });

        $('#consultacuentacajaModal').modal('show');
    }

    function buscarCuentacajaPorCodigo() {
        var codigo = String($('#codigo_cuentacaja').val() || '').trim();
        if (!codigo) {
            limpiarCuenta();
            return;
        }
        if (!empresaId()) {
            alert('Debe seleccionar empresa antes de consultar cuentas de caja.');
            return;
        }

        var tpl = window.ibArchivoPagoCuentacajaPorCodigoUrl || '';
        var url = tpl.replace('__CODIGO__', encodeURIComponent(codigo));
        if (url.indexOf('?') === -1) {
            url += '?empresa_id=' + encodeURIComponent(empresaId());
        } else {
            url += '&empresa_id=' + encodeURIComponent(empresaId());
        }

        $.getJSON(url)
            .done(function (data) {
                if (!data || !data.id) {
                    alert(data && data.error ? data.error : 'No se encontró la cuenta de caja.');
                    limpiarCuenta();
                    return;
                }
                aplicarCuenta(data);
            })
            .fail(function (xhr) {
                var msg = 'No se encontró la cuenta de caja.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                alert(msg);
                limpiarCuenta();
            });
    }

    $(document).on('keydown', '#codigo_cuentacaja, #nombre_cuentacaja', function (e) {
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            if (!$('#consultacuentacajaModal').hasClass('show') && !$('#consultacuentacajaModal').is(':visible')) {
                abrirModalConsulta();
            }
            return;
        }

        if ($(this).is('#codigo_cuentacaja') && e.keyCode === 13) {
            e.preventDefault();
            buscarCuentacajaPorCodigo();
        }
    });

    $(document).on('click', '.consultacuentacaja', function (e) {
        e.preventDefault();
        abrirModalConsulta();
    });

    $(document).on('change', '#codigo_cuentacaja', function () {
        buscarCuentacajaPorCodigo();
    });

    $(document).on('keydown', '#consultacuentacaja', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
        }
    });

    $(document).on('keyup', '#consultacuentacaja', function () {
        if (!$('#consultacuentacajaModal').hasClass('show') && !$('#consultacuentacajaModal').is(':visible')) {
            return;
        }
        buscarCuentacaja($(this).val());
    });

    $(document).on('click', '.eligeconsultacuentacaja', function () {
        var tr = $(this).closest('tr');
        aplicarCuenta({
            id: tr.find('.cuentacaja_id').text().trim(),
            codigo: tr.find('.codigo').text().trim(),
            nombre: tr.find('.nombre').text().trim(),
            cbu: tr.find('.cbu').text().trim(),
        });
        $('#consultacuentacajaModal').modal('hide');
    });

    $('#aceptaconsultacuentacajaModal').on('click', function () {
        $('#consultacuentacajaModal').modal('hide');
    });

    $('#empresa_id').on('change', function () {
        limpiarCuenta();
    });

    $('#form-ib-archivo-pago').on('submit', function (e) {
        var id = parseInt($('#cuentacaja_id').val() || '0', 10) || 0;
        var cbu = String($('#cbu_origen').val() || '').replace(/\D+/g, '');
        if (id <= 0 || cbu.length !== 22) {
            e.preventDefault();
            alert('Elija una cuenta de caja con CBU de 22 dígitos (consulta F1).');
            abrirModalConsulta();
            return false;
        }
    });
})();
