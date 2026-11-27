/**
 * Filtros, consulta de proveedores (F1 / lupa / modal) y exportación con overlay
 * del reporte "Proveedores con clientes de servicios".
 */
(function ($) {
    'use strict';

    var FORM = '#form-proveedor-servicio-reporte';
    var CAMPO = '#psr-proveedor-campo';
    var abortExport = null;
    var timerSeguridad = null;

    function normalizar(valor) {
        return String(valor || '').trim();
    }

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function pantallaActiva() {
        return $(FORM).length > 0;
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('psr-overlay');
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('psr-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('psr-overlay-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('psr-overlay');
        if (timerSeguridad) {
            clearTimeout(timerSeguridad);
            timerSeguridad = null;
        }
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreDesdeDisposition(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (!match) {
            return fallback;
        }
        var raw = (match[1] || match[2] || match[3] || '').trim();
        try {
            return decodeURIComponent(raw.replace(/['"]/g, ''));
        } catch (e) {
            return raw.replace(/['"]/g, '') || fallback;
        }
    }

    function dispararDescargaBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'proveedores_servicios';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function descargarExportacion(href) {
        var lower = String(href).toLowerCase();
        var formato = 'archivo';
        var fallback = 'proveedores_servicios';
        if (lower.indexOf('/excel') !== -1) {
            formato = 'Excel';
            fallback += '.xlsx';
        } else if (lower.indexOf('/pdf') !== -1) {
            formato = 'PDF';
            fallback += '.pdf';
        } else if (lower.indexOf('/csv') !== -1) {
            formato = 'CSV';
            fallback += '.csv';
        }

        mostrarOverlay('Exportando…', 'Generando ' + formato + '… Pulse Esc para cerrar este aviso.');

        if (abortExport) {
            try {
                abortExport.abort();
            } catch (e) {}
        }
        abortExport = typeof AbortController !== 'undefined' ? new AbortController() : null;
        timerSeguridad = setTimeout(ocultarOverlay, 600000);

        fetch(href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: abortExport ? abortExport.signal : undefined,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.blob().then(function (blob) {
                    return {
                        blob: blob,
                        filename: nombreDesdeDisposition(res.headers.get('Content-Disposition'), fallback),
                    };
                });
            })
            .then(function (pack) {
                dispararDescargaBlob(pack.blob, pack.filename);
                ocultarOverlay();
            })
            .catch(function (err) {
                ocultarOverlay();
                if (err && err.name === 'AbortError') {
                    return;
                }
                window.alert((err && err.message) || 'No se pudo exportar');
            });
    }

    function resolverProveedores() {
        var $campo = $(CAMPO);
        var valor = normalizar($campo.find('.codigoproveedor').val());
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

    function agregarProveedor(codigo) {
        var $inp = $(CAMPO).find('.codigoproveedor');
        var actual = normalizar($inp.val());
        var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        var codigoStr = String(codigo).trim();

        if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
            codigos.push(codigoStr);
        }
        $inp.val(codigos.join(','));
        resolverProveedores();
    }

    function buscarProveedoresModal(consulta) {
        $.ajax({
            url: carpetaBase + '/compras/proveedor/consultaproveedor',
            type: 'POST',
            dataType: 'HTML',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
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

    function abrirModalProveedor() {
        buscarProveedoresModal(normalizar($(CAMPO).find('.codigoproveedor').val()));
        $('#consultaproveedorModal').modal('show');
    }

    function atajoF1(e) {
        if (!esTeclaF1(e) || !pantallaActiva()) {
            return;
        }
        if (e.target && e.target.classList && e.target.classList.contains('codigoproveedor')) {
            e.preventDefault();
            e.stopPropagation();
            abrirModalProveedor();
        }
    }

    function activaEventos() {
        var $form = $(FORM);
        if (!$form.length) {
            return;
        }

        resolverProveedores();

        $form.on('submit', function () {
            if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                return;
            }
            mostrarOverlay('Consultando…', 'Puede demorar según el volumen. No cierre la página.');
        });

        $(document)
            .off('click.psrexport', 'a[href*="listar-proveedor-servicio-reporte"]')
            .on('click.psrexport', 'a[href*="listar-proveedor-servicio-reporte"]', function (e) {
                e.preventDefault();
                descargarExportacion(this.href);
            });

        $(document)
            .off('change.psr', CAMPO + ' .codigoproveedor')
            .on('change.psr', CAMPO + ' .codigoproveedor', resolverProveedores);

        $(document)
            .off('click.psr', CAMPO + ' .consultaproveedor-psr')
            .on('click.psr', CAMPO + ' .consultaproveedor-psr', function (e) {
                e.preventDefault();
                abrirModalProveedor();
            });

        $('#consultaproveedorModal')
            .off('shown.bs.modal.psr')
            .on('shown.bs.modal.psr', function () {
                if (!pantallaActiva()) {
                    return;
                }
                var valor = normalizar($(CAMPO).find('.codigoproveedor').val());
                $('#consultaproveedor').val(valor);
                buscarProveedoresModal(valor);
                $(this).find('#consultaproveedor').focus();
            });

        $(document)
            .off('keyup.psr', '#consultaproveedor')
            .on('keyup.psr', '#consultaproveedor', function () {
                if (pantallaActiva()) {
                    buscarProveedoresModal(normalizar($(this).val()));
                }
            });

        $(document)
            .off('click.psr', '.eligeconsultaproveedor')
            .on('click.psr', '.eligeconsultaproveedor', function (e) {
                if (!pantallaActiva()) {
                    return;
                }
                e.stopImmediatePropagation();
                var codigo = $(this).closest('tr').find('.codigo').first().text().trim();
                if (codigo !== '') {
                    agregarProveedor(codigo);
                }
                $('#consultaproveedorModal').modal('hide');
                return false;
            });

        document.removeEventListener('keydown', atajoF1, true);
        document.addEventListener('keydown', atajoF1, true);
    }

    $(document).ready(activaEventos);
    window.addEventListener('pageshow', ocultarOverlay);
    window.addEventListener('pagehide', ocultarOverlay);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            if (abortExport) {
                try {
                    abortExport.abort();
                } catch (e) {}
            }
            ocultarOverlay();
        }
    });
})(jQuery);
