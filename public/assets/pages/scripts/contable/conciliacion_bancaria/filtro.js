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
                solo_con_interbanking: 1,
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

        $('#consultacuentacajaModal').one('shown.bs.modal.concBanc', function () {
            var $input = $(this).find('#consultacuentacaja');
            $input.trigger('focus');
            buscarCuentacaja('');
        });

        $('#consultacuentacajaModal').modal('show');
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

    $(document).on('keydown', '#consultacuentacaja', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
        }
    });

    $(document).on('keyup', '#consultacuentacaja', function () {
        buscarCuentacaja($(this).val());
    });

    function limpiarCuentacajaSeleccionada() {
        $('#cuentacaja_id').val('');
        $('#nombre_cuentacaja').val('');
        $('#enganche-cuenta-wrapper').empty();
    }

    function asignarCuentacaja(data) {
        $('#cuentacaja_id').val(data.id);
        $('#codigo_cuentacaja').val(data.codigo || '');
        $('#nombre_cuentacaja').val(data.nombre || '');
        cargarEngancheCuentacaja();
    }

    function urlCuentacajaPorCodigo(codigo) {
        var plantilla = window.conciliacionBancariaCuentacajaPorCodigoUrl
            || (resolverCarpetaBase() + '/contable/conciliacion-bancaria/api/cuentacaja-por-codigo/__CODIGO__');

        return plantilla.replace('__CODIGO__', encodeURIComponent(String(codigo || '').trim()));
    }

    function leerCuentacajaPorCodigo(codigo) {
        var empresa_id = empresaId();
        if (!empresa_id) {
            return $.Deferred().reject({ error: 'Debe seleccionar empresa.' }).promise();
        }

        return $.ajax({
            url: urlCuentacajaPorCodigo(codigo),
            type: 'GET',
            dataType: 'json',
            data: { empresa_id: empresa_id },
        });
    }

    function buscarCuentacajaPorCodigo() {
        var codigo = String($('#codigo_cuentacaja').val() || '').trim();
        if (!codigo) {
            limpiarCuentacajaSeleccionada();
            return;
        }

        if (!empresaId()) {
            alert('Debe seleccionar empresa antes de ingresar el código de cuenta.');
            $('#codigo_cuentacaja').val('');
            return;
        }

        leerCuentacajaPorCodigo(codigo)
            .done(function (data) {
                if (data && data.id > 0) {
                    asignarCuentacaja(data);
                } else {
                    alert(data && data.error ? data.error : 'No hay cuenta de caja con Interbanking para ese código.');
                    limpiarCuentacajaSeleccionada();
                    $('#codigo_cuentacaja').focus().select();
                }
            })
            .fail(function (xhr) {
                var msg = 'No hay cuenta de caja con Interbanking para ese código.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                alert(msg);
                limpiarCuentacajaSeleccionada();
                $('#codigo_cuentacaja').focus().select();
            });
    }

    $(document).on('change', '#codigo_cuentacaja', function () {
        buscarCuentacajaPorCodigo();
    });

    // Enter en código se maneja arriba junto con F1.
    function cargarEngancheCuentacaja() {
        var cuentacaja_id = $('#cuentacaja_id').val();
        var empresa_id = empresaId();

        if (!cuentacaja_id || !empresa_id) {
            $('#enganche-cuenta-wrapper').empty();
            return;
        }

        $('#enganche-cuenta-wrapper').html(
            '<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Cargando enganche Interbanking…</div>'
        );

        $.ajax({
            url: window.conciliacionBancariaEngancheUrl || (resolverCarpetaBase() + '/contable/conciliacion-bancaria/api/enganche-cuentacaja'),
            type: 'GET',
            dataType: 'json',
            data: {
                empresa_id: empresa_id,
                cuentacaja_id: cuentacaja_id,
            },
        })
            .done(function (resp) {
                if (resp && resp.html) {
                    $('#enganche-cuenta-wrapper').html(resp.html);
                } else if (resp && resp.error) {
                    $('#enganche-cuenta-wrapper').html(
                        '<div class="alert alert-danger mb-0">' + resp.error + '</div>'
                    );
                } else {
                    $('#enganche-cuenta-wrapper').empty();
                }
            })
            .fail(function () {
                $('#enganche-cuenta-wrapper').html(
                    '<div class="alert alert-danger mb-0">No se pudo cargar el enganche de la cuenta.</div>'
                );
            });
    }

    $(document).on('click', '.eligeconsultacuentacaja', function () {
        var tr = $(this).closest('tr');
        $('#cuentacaja_id').val(tr.find('.cuentacaja_id').text().trim());
        $('#codigo_cuentacaja').val(tr.find('.codigo').text().trim());
        $('#nombre_cuentacaja').val(tr.find('.nombre').text().trim());
        $('#consultacuentacajaModal').modal('hide');
        cargarEngancheCuentacaja();
    });

    $('#aceptaconsultacuentacajaModal').on('click', function () {
        $('#consultacuentacajaModal').modal('hide');
    });

    $('#form-conciliacion-bancaria').on('submit', function () {
        var btn = document.getElementById('btn-consultar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando conciliación…';
        }
    });

    $('#empresa_id').on('change', function () {
        $('#cuentacaja_id').val('');
        $('#codigo_cuentacaja').val('');
        $('#nombre_cuentacaja').val('');
        $('#enganche-cuenta-wrapper').empty();
    });

    $(function () {
        if ($('#cuentacaja_id').val() && empresaId() && $('#enganche-cuenta-wrapper').is(':empty')) {
            cargarEngancheCuentacaja();
        }
    });
})();
