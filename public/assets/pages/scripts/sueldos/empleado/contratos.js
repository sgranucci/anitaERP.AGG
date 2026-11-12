(function ($) {
    'use strict';

    var cargado = false;
    var carpeta = (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-contratos');
    }

    function aviso(mensaje, tipo) {
        if (!mensaje) {
            return;
        }
        var clase = tipo === 'error' ? 'alert-danger' : 'alert-success';
        var $box = $('<div class="alert ' + clase + ' alert-dismissible mt-2">' + $('<div>').text(mensaje).html() +
            '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
        host().prepend($box);
        setTimeout(function () { $box.alert('close'); }, 5000);
    }

    function pintar(resp) {
        if (resp && resp.html) {
            var $panel = host().find('#contratos-panel');
            if ($panel.length) {
                $panel.replaceWith(resp.html);
            } else {
                host().html(resp.html);
            }
        }
        if (typeof window.activa_eventos_consulta_maestro_sueldos === 'function') {
            window.activa_eventos_consulta_maestro_sueldos();
        }
        aviso(resp && resp.mensaje ? resp.mensaje : null, 'ok');
    }

    function cargarPanel() {
        var url = host().data('url');
        if (!url) {
            return;
        }
        $.get(url).done(function (resp) {
            host().html(resp.html || '');
            pintar({ html: null, mensaje: null });
            if (typeof window.focusSolapaEmpleado === 'function') {
                window.focusSolapaEmpleado('#tab-contratos');
            }
        }).fail(function () {
            host().html('<div class="alert alert-danger">No se pudo cargar la solapa de contratos.</div>');
        });
    }

    function emitir(data, doneFail) {
        var url = host().data('emitir-url');
        if (!url) {
            return;
        }
        data.push({ name: '_token', value: token() });
        data.push({ name: 'descargar', value: '1' });

        var overlay = document.getElementById('contrato-emision-overlay');
        if (overlay) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json, application/octet-stream'
            },
            body: (function () {
                var fd = new FormData();
                data.forEach(function (p) { fd.append(p.name, p.value); });
                return fd;
            })(),
            credentials: 'same-origin'
        }).then(function (res) {
            var ct = res.headers.get('Content-Type') || '';
            if (!res.ok) {
                return res.json().then(function (j) {
                    throw new Error((j && j.mensaje) || 'No se pudo emitir el documento.');
                }).catch(function (err) {
                    if (err instanceof Error && err.message) {
                        throw err;
                    }
                    throw new Error('No se pudo emitir el documento.');
                });
            }
            if (ct.indexOf('application/json') !== -1) {
                return res.json().then(function (j) {
                    if (j && j.ok === false) {
                        throw new Error(j.mensaje || 'Error al emitir');
                    }
                    pintar(j);
                });
            }
            return res.blob().then(function (blob) {
                var dispo = res.headers.get('Content-Disposition') || '';
                var match = /filename="?([^"]+)"?/.exec(dispo);
                var nombre = match ? match[1] : 'contrato.docx';
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = nombre;
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(a.href);
                    a.remove();
                }, 1000);
                aviso('Documento generado: ' + nombre, 'ok');
                cargado = false;
                cargarPanel();
            });
        }).catch(function (err) {
            aviso(err.message || 'Error al emitir', 'error');
            if (typeof doneFail === 'function') {
                doneFail(err);
            }
        }).finally(function () {
            if (overlay) {
                overlay.classList.add('d-none');
                overlay.style.display = '';
            }
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-contratos"]', function () {
        if (!cargado) {
            cargado = true;
            cargarPanel();
        }
    });

    $(document).on('submit', '#form-contrato-nuevo', function (e) {
        e.preventDefault();
        var url = host().data('url');
        if (!url) {
            return;
        }
        var data = $(this).serializeArray();
        data.push({ name: '_token', value: token() });
        $.post(url, data).done(function (resp) {
            pintar(resp);
        }).fail(function (xhr) {
            var msg = 'No se pudo guardar el contrato.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
            }
            aviso(msg, 'error');
        });
    });

    $(document).on('click', '.btn-contrato-borrar', function () {
        if (!confirm('¿Eliminar este contrato?')) {
            return;
        }
        var id = $(this).closest('tr').data('id');
        $.ajax({
            url: carpeta + '/sueldos/contrato-empleado/' + id,
            method: 'POST',
            data: { _token: token(), _method: 'DELETE' }
        }).done(pintar).fail(function () {
            aviso('No se pudo eliminar.', 'error');
        });
    });

    $(document).on('click', '.btn-emitir-contrato', function () {
        var contratoId = $(this).data('contrato-id');
        emitir([
            { name: 'tipo', value: 'CONTRATO' },
            { name: 'empleado_contrato_id', value: contratoId },
            { name: 'fecha_emision', value: (new Date()).toISOString().slice(0, 10) }
        ]);
    });

    $(document).on('submit', '#form-emitir-certificado', function (e) {
        e.preventDefault();
        emitir($(this).serializeArray());
    });
})(jQuery);
