(function ($) {
    'use strict';

    var claveUsoModal = null;

    function filtrarPreviewPorEmpresa() {
        var empresaId = String($('#filtro_empresa_preview').val() || '');
        $('.remesa-config-grupo').each(function () {
            var visibles = 0;
            $(this).find('tr.remesa-config-fila').each(function () {
                var filaEmp = String($(this).data('empresa-id') || '');
                var mostrar = !empresaId || filaEmp === '' || filaEmp === empresaId;
                $(this).toggle(mostrar);
                if (mostrar) {
                    visibles += 1;
                }
            });
            var $vacio = $(this).find('tr.remesa-config-filtro-vacio');
            if (visibles === 0 && $(this).find('tr.remesa-config-fila').length > 0) {
                if (!$vacio.length) {
                    $(this).find('tbody').append(
                        '<tr class="remesa-config-filtro-vacio"><td colspan="8" class="text-muted">' +
                        'Ninguna cuenta de esta empresa (ni compartidas) en el preview filtrado.</td></tr>'
                    );
                } else {
                    $vacio.show();
                }
            } else {
                $vacio.remove();
            }
        });
    }

    function parseConsultaCuentacajaHtml(respuesta) {
        var html = respuesta;
        try {
            var parsed = typeof respuesta === 'string' ? JSON.parse(respuesta) : respuesta;
            if (parsed && typeof parsed.data === 'string') {
                html = parsed.data;
            }
        } catch (e) {
            html = String(respuesta || '').replace(/\\/g, '');
        }
        return html;
    }

    // Sobrescribe el pintado del modal compartido para aceptar JSON {data: html}.
    window.buscar_datos_cuentacaja = function (consulta) {
        var empresa_id = $('#filtro_empresa_preview').val()
            || (claveUsoModal
                ? $('.remesa-config-grupo[data-clave="' + claveUsoModal + '"] .remesa-config-empresa-add').val()
                : '')
            || '';

        $.ajax({
            url: carpetaBase + '/caja/cuentacaja/consultacuentacaja',
            type: 'POST',
            dataType: 'text',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                consulta: consulta || '',
                empresa_id: empresa_id,
                usocuentacaja_id: '',
                excluir_cuentas_solo_automaticas: 0
            }
        })
            .done(function (respuesta) {
                $('#datoscuentacaja').html(parseConsultaCuentacajaHtml(respuesta));
            })
            .fail(function () {
                console.log('error consulta cuentacaja remesa config');
            });
    };

    function esTeclaF1(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function abrirModalConsultaDesde($origen) {
        var $grupo = $origen.closest('.remesa-config-grupo');
        claveUsoModal = $grupo.data('clave')
            || $origen.data('clave')
            || null;
        $('#consultacuentacaja').val('');
        $('#datoscuentacaja').html('');
        $('#consultacuentacajaModal').modal('show');
        buscar_datos_cuentacaja('');
    }

    $(document).on('change', '#filtro_empresa_preview', filtrarPreviewPorEmpresa);

    $(document).on('click', '.remesa-config-abrir-modal', function () {
        abrirModalConsultaDesde($(this));
    });

    // F1 en el input de código = mismo efecto que "Buscar cuenta".
    $(document).on('keydown', '.remesa-config-codigo', function (e) {
        if (!esTeclaF1(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        abrirModalConsultaDesde($(this));
    });

    $(document).on('click', '.eligeconsultacuentacaja', function () {
        if (!claveUsoModal || !window.REMESA_CONFIG) {
            return;
        }
        var $tr = $(this).closest('tr');
        var id = parseInt($tr.children().eq(0).text(), 10) || 0;
        if (id <= 0) {
            alert('No se pudo leer el ID de la cuenta.');
            return;
        }

        $.ajax({
            url: window.REMESA_CONFIG.urlAgregar,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': window.REMESA_CONFIG.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            data: {
                clave: claveUsoModal,
                cuentacaja_id: id
            }
        })
            .done(function () {
                claveUsoModal = null;
                $('#consultacuentacajaModal').modal('hide');
                window.location.reload();
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.mensaje)
                    || 'No se pudo vincular la cuenta.';
                alert(msg);
            });
    });
})(jQuery);
