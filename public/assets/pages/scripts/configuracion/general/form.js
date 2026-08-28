(function () {
    var modalAbriendo = false;

    function resolverCarpetaBase() {
        if (typeof window.resolverCarpetaBaseApp === 'function') {
            return window.resolverCarpetaBaseApp();
        }
        if (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }

        return '';
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
        return document.querySelector('#tm_cuentacaja_fce_cbu') || document.querySelector('.tm-cuentacaja-campo');
    }

    function modalAbierto() {
        var modal = document.getElementById('consultacuentacajaModal');
        return modalAbriendo || (modal && modal.classList.contains('show'));
    }

    function setCbuPreview(cbu) {
        var preview = document.getElementById('fce_cbu_preview');
        if (preview) {
            preview.value = cbu || '';
        }
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
        setCbuPreview('');
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
        cont.querySelector('.codigocuentacaja').removeAttribute('data-cuentacaja-invalido');
        cont.querySelector('.descripcioncuentacaja').value = data.nombre || '';
        setCbuPreview(data.cbu || '');
        var link = cont.querySelector('.btn-link-editar-cuentacaja');
        if (link) {
            link.setAttribute(
                'href',
                resolverCarpetaBase() + '/caja/cuentacaja/' + encodeURIComponent(String(data.id)) + '/editar?origen=modal_consulta&vista=consulta'
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

    function buscarCuentacaja(consulta) {
        $.ajax({
            url: resolverCarpetaBase() + '/caja/cuentacaja/consultacuentacaja',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
            },
            data: {
                consulta: consulta || '',
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
        modalAbriendo = true;
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').one('shown.bs.modal.cfgGralCuenta', function () {
            modalAbriendo = false;
            $(this).find('#consultacuentacaja').trigger('focus');
            buscarCuentacaja('');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function leerCuentacajaPorCodigo(codigo) {
        return $.ajax({
            url: resolverCarpetaBase() + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(String(codigo || '').trim()),
            type: 'GET',
            dataType: 'json',
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
                        var next = document.getElementById('fce_cbu_preview');
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

    $(document).on('click', '#tm_cuentacaja_fce_cbu .consultacuentacaja', function (e) {
        e.preventDefault();
        abrirModalConsulta();
    });

    $(document).on('keydown', '#tm_cuentacaja_fce_cbu .codigocuentacaja', function (e) {
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            abrirModalConsulta();
            return;
        }
        if (e.keyCode === 13) {
            e.preventDefault();
            resolverCuentacajaPorCodigo(true);
        }
    });

    $(document).on('blur', '#tm_cuentacaja_fce_cbu .codigocuentacaja', function () {
        resolverCuentacajaPorCodigo(false);
    });

    $(document).on('input', '#tm_cuentacaja_fce_cbu .codigocuentacaja', function () {
        $(this).removeAttr('data-cuentacaja-invalido');
    });

    $(document).on('keydown', '#consultacuentacaja', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            var $primera = $('#datoscuentacaja .eligeconsultacuentacaja').first();
            if ($primera.length) {
                $primera.trigger('click');
            }
        }
    });

    $(document).on('keyup', '#consultacuentacaja', function () {
        buscarCuentacaja($(this).val());
    });

    $(document).on('click', '.eligeconsultacuentacaja', function () {
        var tr = $(this).closest('tr');
        var codigo = tr.find('.codigo').text().trim();
        var id = tr.find('.cuentacaja_id').text().trim();
        var nombre = tr.find('.nombre').text().trim();
        var cbu = tr.find('.cbu').text().trim();
        asignarCuentacaja({ id: id, codigo: codigo, nombre: nombre, cbu: cbu });
        $('#consultacuentacajaModal').modal('hide');
        if (codigo) {
            leerCuentacajaPorCodigo(codigo).done(function (data) {
                if (data && data.id > 0) {
                    asignarCuentacaja(data);
                }
            });
        }
    });

    $('#aceptaconsultacuentacajaModal').on('click', function () {
        var $primera = $('#datoscuentacaja .eligeconsultacuentacaja').first();
        if ($primera.length) {
            $primera.trigger('click');
            return;
        }
        $('#consultacuentacajaModal').modal('hide');
    });

    $('#consultacuentacajaModal').on('hidden.bs.modal', function () {
        modalAbriendo = false;
    });
})();
