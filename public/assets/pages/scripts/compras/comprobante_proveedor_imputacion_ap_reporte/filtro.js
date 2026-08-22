/**
 * Filtros e overlay del informe comprobantes vs imputación AP.
 */
(function ($) {
    'use strict';

    function iapNormalizar(valor) {
        return String(valor || '').trim();
    }

    function iapEsTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function iapPantallaActiva() {
        return $('#form-imputacion-ap').length > 0;
    }

    function iapMostrarOverlay(titulo) {
        var overlay = document.getElementById('iap-reporte-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('iap-reporte-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function iapOcultarOverlay() {
        var overlay = document.getElementById('iap-reporte-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function iapResolverProveedores($campo) {
        var valor = iapNormalizar($campo.find('.codigoproveedor').val());
        var $meta = $campo.find('.metaproveedor');

        if (valor === '') {
            $meta.val('Todos los proveedores');
            return;
        }

        if (valor.indexOf(',') >= 0 || valor.indexOf(';') >= 0) {
            var codigos = valor.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            $meta.val(codigos.length > 1
                ? 'Lista proveedores (' + codigos.length + '): ' + codigos.join(', ')
                : 'Lista proveedores');
            return;
        }

        $meta.val(valor);
    }

    function iapAgregarProveedor(codigo) {
        var $campo = $('#iap-reporte-proveedor-campo');
        var $inp = $campo.find('.codigoproveedor');
        var actual = iapNormalizar($inp.val());
        var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        var codigoStr = String(codigo).trim();

        if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
            codigos.push(codigoStr);
        }

        $inp.val(codigos.join(','));
        iapResolverProveedores($campo);
    }

    function iapBuscarProveedoresModal(consulta) {
        $.ajax({
            url: carpetaBase + '/compras/proveedor/consultaproveedor',
            type: 'POST',
            dataType: 'HTML',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: { consulta: consulta },
        })
            .done(function (respuesta) {
                var html = '';
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta;
                }
                $('#datosproveedor').html(html);
            })
            .fail(function () {
                $('#datosproveedor').html('<tr><td colspan="6">Error al consultar proveedores</td></tr>');
            });
    }

    function iapAbrirModalProveedor() {
        var valor = $('#iap-reporte-proveedor-campo .codigoproveedor').val().trim();
        iapBuscarProveedoresModal(valor);
        $('#consultaproveedorModal').modal('show');
    }

    function iapAtajoF1Handler(e) {
        if (!iapEsTeclaF1(e) || !iapPantallaActiva()) {
            return;
        }
        var target = e.target;
        if (!target || !target.classList) {
            return;
        }
        if (target.classList.contains('codigoproveedor')) {
            e.preventDefault();
            e.stopPropagation();
            iapAbrirModalProveedor();
        }
    }

    function activaEventosImputacionApFiltro() {
        var $form = $('#form-imputacion-ap');
        if (!$form.length) {
            return;
        }

        iapResolverProveedores($('#iap-reporte-proveedor-campo'));

        $form.on('submit', function () {
            if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                return;
            }
            iapMostrarOverlay('Comparando comprobantes contra el asiento…');
        });

        $(document)
            .off('click.iapreporte', 'a[href*="listar-comprobante-proveedor-imputacion-ap"]')
            .on('click.iapreporte', 'a[href*="listar-comprobante-proveedor-imputacion-ap"]', function () {
                iapMostrarOverlay('Exportando…');
            });

        $(document)
            .off('change.iapreporte', '#iap-reporte-proveedor-campo .codigoproveedor')
            .on('change.iapreporte', '#iap-reporte-proveedor-campo .codigoproveedor', function () {
                iapResolverProveedores($('#iap-reporte-proveedor-campo'));
            });

        $(document)
            .off('click.iapreporte', '#iap-reporte-proveedor-campo .consultaproveedor-iap')
            .on('click.iapreporte', '#iap-reporte-proveedor-campo .consultaproveedor-iap', function (e) {
                e.preventDefault();
                iapAbrirModalProveedor();
            });

        $('#consultaproveedorModal')
            .off('shown.bs.modal.iapreporte')
            .on('shown.bs.modal.iapreporte', function () {
                if (!$('#form-imputacion-ap').length) {
                    return;
                }
                var valor = $('#iap-reporte-proveedor-campo .codigoproveedor').val().trim();
                $('#consultaproveedor').val(valor);
                iapBuscarProveedoresModal(valor);
                $(this).find('#consultaproveedor').focus();
            });

        $(document)
            .off('keyup.iapreporte', '#consultaproveedor')
            .on('keyup.iapreporte', '#consultaproveedor', function () {
                if (!$('#form-imputacion-ap').length) {
                    return;
                }
                iapBuscarProveedoresModal($(this).val().trim());
            });

        $(document)
            .off('click.iapreporte', '.eligeconsultaproveedor')
            .on('click.iapreporte', '.eligeconsultaproveedor', function (e) {
                if (!$('#form-imputacion-ap').length) {
                    return;
                }
                e.stopImmediatePropagation();

                var $trModal = $(this).closest('tr');
                var codigo = $trModal.find('.codigo').first().text().trim();

                if (codigo !== '') {
                    iapAgregarProveedor(codigo);
                }

                $('#consultaproveedorModal').modal('hide');
                return false;
            });

        document.removeEventListener('keydown', iapAtajoF1Handler, true);
        document.addEventListener('keydown', iapAtajoF1Handler, true);
    }

    $(document).ready(activaEventosImputacionApFiltro);
    window.addEventListener('pageshow', iapOcultarOverlay);
})(jQuery);
