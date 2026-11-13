(function ($) {
    'use strict';

    var cargado = false;
    var cargando = false;
    var carpeta = (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';

    function token() {
        return $('#form-general input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content') || '';
    }

    function host() {
        return $('#host-contratos');
    }

    function urlPanel() {
        var $h = host();
        return String($h.attr('data-url') || $h.data('url') || '').trim();
    }

    function urlEmitir() {
        var $h = host();
        return String($h.attr('data-emitir-url') || $h.data('emitirUrl') || '').trim();
    }

    function urlPreview() {
        var $h = host();
        return String($h.attr('data-preview-url') || $h.data('previewUrl') || '').trim();
    }

    /** Plantilla forzada (contratos) o la del form de certificado. */
    function plantillaIdEmision(desdeCert) {
        if (desdeCert) {
            return String($('#cert_plantilla_id').val() || '').trim();
        }
        return String($('#contrato_plantilla_emision').val() || '').trim();
    }

    var previewPayloadPendiente = null;

    function overlay(mostrar, titulo, subtitulo) {
        var el = document.getElementById('contrato-emision-overlay');
        if (!el) {
            return;
        }
        if (titulo) {
            var $t = $('#contrato-emision-titulo');
            if ($t.length) {
                $t.text(titulo);
            }
        }
        if (subtitulo) {
            var $s = $('#contrato-emision-subtitulo');
            if ($s.length) {
                $s.text(subtitulo);
            }
        }
        if (mostrar) {
            el.classList.remove('d-none');
            el.style.display = 'flex';
        } else {
            el.classList.add('d-none');
            el.style.display = '';
        }
    }

    function formDataFromPairs(data) {
        var fd = new FormData();
        data.forEach(function (p) { fd.append(p.name, p.value); });
        return fd;
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

    function modoNuevo() {
        var $form = $('#form-contrato-nuevo');
        if (!$form.length) {
            return;
        }
        $form[0].reset();
        $('#contrato_edit_id').val('');
        $('#contrato_categoria_id').val($('#contrato_categoria_id').data('default') || $('#contrato_categoria_id').val());
        $('#contrato_centrocosto_id').val($('#contrato_centrocosto_id').data('default') || $('#contrato_centrocosto_id').val());
        var $reemp = $form.find('.tm-empleado-sueldos-campo').first();
        $reemp.find('.empleado_sueldos_id').val('');
        $reemp.find('.codigoempleado_sueldos').val('');
        $reemp.find('.nombreempleado_sueldos').val('');
        $reemp.find('.btn-link-editar-empleado-sueldos').attr('href', '#').addClass('d-none');
        $('#contrato-form-titulo').text('Nuevo contrato');
        $('#btn-contrato-cancelar-edicion').addClass('d-none');
        $('#btn-contrato-guardar').removeClass('btn-success').addClass('btn-primary')
            .html('<i class="fa fa-plus"></i> <span>Agregar contrato</span>');
        $('#contratos-panel tbody tr').removeClass('table-warning');
    }

    function modoEdicion($tr) {
        var id = $tr.data('id');
        $('#contrato_edit_id').val(id);
        $('#contrato_desde_fecha').val($tr.attr('data-desde') || '');
        $('#contrato_hasta_fecha').val($tr.attr('data-hasta') || '');
        $('#contrato_modalidad').val(String($tr.attr('data-modalidad') || ''));
        $('#contrato_motivo').val(String($tr.attr('data-motivo') || ''));
        $('#contrato_dias').val($tr.attr('data-dias') || '');
        $('#contrato_liquidacion').val($tr.attr('data-liquidacion') || '');
        $('#contrato_comentario').val($tr.attr('data-comentario') || '');
        $('#contrato_categoria_id').val($tr.attr('data-categoria-id') || '');
        $('#contrato_centrocosto_id').val($tr.attr('data-centrocosto-id') || '');

        var $reemp = $('#form-contrato-nuevo .tm-empleado-sueldos-campo').first();
        var reempId = $tr.attr('data-reemplazo-id') || '';
        var reempLegajo = $tr.attr('data-reemplazo-legajo') || '';
        var reempNombre = $tr.attr('data-reemplazo-nombre') || '';
        $reemp.find('.empleado_sueldos_id').val(reempId);
        $reemp.find('.codigoempleado_sueldos').val(reempLegajo);
        $reemp.find('.nombreempleado_sueldos').val(reempNombre);
        var $link = $reemp.find('.btn-link-editar-empleado-sueldos');
        if (reempId) {
            $link.attr('href', carpeta + '/sueldos/empleado/' + reempId + '/editar?origen=modal_consulta&vista=consulta')
                .removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }

        $('#contrato-form-titulo').text('Modificar contrato #' + ($tr.find('td').first().text() || id));
        $('#btn-contrato-cancelar-edicion').removeClass('d-none');
        $('#btn-contrato-guardar').removeClass('btn-primary').addClass('btn-success')
            .html('<i class="fa fa-save"></i> <span>Actualizar contrato</span>');
        $('#contratos-panel tbody tr').removeClass('table-warning');
        $tr.addClass('table-warning');

        var form = document.getElementById('form-contrato-nuevo');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        $('#contrato_desde_fecha').trigger('focus');
    }

    function aplicarDefaultsForm() {
        var $cat = $('#contrato_categoria_id');
        var $cc = $('#contrato_centrocosto_id');
        if ($cat.length) {
            $cat.data('default', $cat.val());
        }
        if ($cc.length) {
            $cc.data('default', $cc.val());
        }
    }

    function pintar(resp) {
        if (resp && resp.html) {
            var $panel = host().find('#contratos-panel');
            if ($panel.length) {
                $panel.replaceWith(resp.html);
            } else {
                host().html(resp.html);
            }
            aplicarDefaultsForm();
        }
        aviso(resp && resp.mensaje ? resp.mensaje : null, 'ok');
    }

    function cargarPanel(forzar) {
        if (cargado && !forzar) {
            return;
        }
        if (cargando) {
            return;
        }
        var url = urlPanel();
        if (!url) {
            host().html('<div class="alert alert-danger">No hay URL de contratos en la solapa. Recargá el legajo.</div>');
            return;
        }
        cargando = true;
        $.get(url).done(function (resp) {
            var html = (resp && resp.html) ? resp.html : '';
            if (!html && typeof resp === 'string') {
                host().html('<div class="alert alert-danger">La solapa de contratos devolvió una respuesta inválida. Probá recargar.</div>');
                cargado = false;
                return;
            }
            host().html(html || '<div class="alert alert-warning">Sin contenido de contratos.</div>');
            aplicarDefaultsForm();
            cargado = true;
            if (typeof window.focusSolapaEmpleado === 'function') {
                window.focusSolapaEmpleado('#tab-contratos');
            }
        }).fail(function (xhr) {
            cargado = false;
            var msg = 'No se pudo cargar la solapa de contratos.';
            if (xhr && xhr.status) {
                msg += ' (HTTP ' + xhr.status + ')';
            }
            host().html(
                '<div class="alert alert-danger mb-2">' + msg + '</div>' +
                '<button type="button" class="btn btn-outline-primary btn-sm" id="btn-reintentar-contratos">' +
                '<i class="fa fa-redo"></i> Reintentar</button>'
            );
        }).always(function () {
            cargando = false;
        });
    }

    function intentarCargarSiVisible() {
        var $pane = $('#tab-contratos');
        if ($pane.length && $pane.hasClass('active')) {
            cargarPanel(false);
        }
    }

    function asegurarPlantillaEnPayload(data, desdeCert) {
        var tiene = data.some(function (p) {
            return p.name === 'contrato_plantilla_id' && String(p.value || '').trim() !== '';
        });
        if (tiene) {
            return data;
        }
        var id = plantillaIdEmision(!!desdeCert);
        if (id) {
            data.push({ name: 'contrato_plantilla_id', value: id });
        }
        return data;
    }

    function previsualizar(data, desdeCert) {
        var url = urlPreview();
        if (!url) {
            aviso('No hay URL de vista previa configurada.', 'error');
            return;
        }
        data = asegurarPlantillaEnPayload(data.slice(), desdeCert);
        data.push({ name: '_token', value: token() });
        previewPayloadPendiente = data.slice();

        overlay(true, 'Armando vista previa…', 'Leyendo la plantilla Word y aplicando datos del legajo.');

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formDataFromPairs(data),
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().then(function (j) {
                if (!res.ok || (j && j.ok === false)) {
                    throw new Error((j && j.mensaje) || 'No se pudo previsualizar.');
                }
                return j;
            });
        }).then(function (j) {
            $('#contrato-preview-titulo').text(j.titulo || 'Vista previa');
            $('#contrato-preview-plantilla').text(j.plantilla ? ('Plantilla: ' + j.plantilla) : '');
            var frame = document.getElementById('contrato-preview-frame');
            if (frame) {
                frame.srcdoc = j.html || '<p>Sin contenido</p>';
            }
            var $btnWord = $('#btn-contrato-preview-emitir-word');
            if (j.fuente_word) {
                $btnWord.removeClass('d-none');
            } else {
                $btnWord.addClass('d-none');
            }
            $('#contrato-preview-modal').modal('show');
        }).catch(function (err) {
            aviso(err.message || 'Error al previsualizar', 'error');
            previewPayloadPendiente = null;
        }).finally(function () {
            overlay(false);
        });
    }

    function formatoDePayload(data) {
        var f = 'pdf';
        data.forEach(function (p) {
            if (p.name === 'formato' && String(p.value || '').trim()) {
                f = String(p.value).trim().toLowerCase();
            }
        });
        return f === 'docx' ? 'docx' : 'pdf';
    }

    function emitir(data, doneFail, desdeCert) {
        var url = urlEmitir();
        if (!url) {
            aviso('No hay URL de emisión configurada.', 'error');
            return;
        }
        data = asegurarPlantillaEnPayload(data.slice(), desdeCert);
        var formato = formatoDePayload(data);
        var tieneFormato = data.some(function (p) { return p.name === 'formato'; });
        if (!tieneFormato) {
            data.push({ name: 'formato', value: formato });
        }
        data.push({ name: '_token', value: token() });
        data.push({ name: 'descargar', value: '1' });

        var etiqueta = formato === 'docx' ? 'Word' : 'PDF';
        overlay(true, 'Generando ' + etiqueta + '…', 'Espere mientras se arma el documento. No cierre la página.');

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/pdf, application/json, application/octet-stream'
            },
            body: formDataFromPairs(data),
            credentials: 'same-origin'
        }).then(function (res) {
            var ct = res.headers.get('Content-Type') || '';
            if (!res.ok) {
                return res.json().then(function (j) {
                    throw new Error((j && j.mensaje) || ('No se pudo emitir el ' + etiqueta + '.'));
                }).catch(function (err) {
                    if (err instanceof Error && err.message) {
                        throw err;
                    }
                    throw new Error('No se pudo emitir el ' + etiqueta + '.');
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
                var nombre = match ? match[1] : ('contrato.' + (formato === 'docx' ? 'docx' : 'pdf'));
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = nombre;
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(a.href);
                    a.remove();
                }, 1000);
                aviso(etiqueta + ' generado: ' + nombre, 'ok');
                cargarPanel(true);
                try {
                    $('#contrato-preview-modal').modal('hide');
                } catch (e) { /* ignore */ }
            });
        }).catch(function (err) {
            aviso(err.message || 'Error al emitir', 'error');
            if (typeof doneFail === 'function') {
                doneFail(err);
            }
        }).finally(function () {
            overlay(false);
        });
    }

    $(document).on('shown.bs.tab', 'a[href="#tab-contratos"]', function () {
        cargarPanel(false);
    });

    // Si el operador hace clic antes de que termine de cargar este script,
    // shown.bs.tab ya se perdió: reintentamos al click y al ready.
    $(document).on('click', 'a[href="#tab-contratos"]', function () {
        setTimeout(function () { intentarCargarSiVisible(); }, 0);
    });

    $(document).on('click', '#btn-reintentar-contratos', function () {
        host().html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Cargando contratos…</div>');
        cargarPanel(true);
    });

    $(function () {
        intentarCargarSiVisible();
        // Por si Bootstrap activa la solapa un instante después de ready
        setTimeout(intentarCargarSiVisible, 300);
    });

    $(document).on('submit', '#form-contrato-nuevo', function (e) {
        e.preventDefault();
        var editId = String($('#contrato_edit_id').val() || '').trim();
        var data = $(this).serializeArray().filter(function (p) {
            return p.name !== 'contrato_id';
        });
        data.push({ name: '_token', value: token() });

        if (editId) {
            data.push({ name: '_method', value: 'PUT' });
            $.ajax({
                url: carpeta + '/sueldos/contrato-empleado/' + editId,
                method: 'POST',
                data: data
            }).done(function (resp) {
                pintar(resp);
            }).fail(function (xhr) {
                var msg = 'No se pudo actualizar el contrato.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                aviso(msg, 'error');
            });
            return;
        }

        var url = urlPanel();
        if (!url) {
            return;
        }
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

    $(document).on('click', '.btn-contrato-editar', function () {
        modoEdicion($(this).closest('tr'));
    });

    $(document).on('click', '#btn-contrato-cancelar-edicion', function () {
        modoNuevo();
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

    function payloadContrato(contratoId, formato) {
        return [
            { name: 'tipo', value: 'CONTRATO' },
            { name: 'empleado_contrato_id', value: contratoId },
            { name: 'fecha_emision', value: (new Date()).toISOString().slice(0, 10) },
            { name: 'formato', value: formato || 'pdf' }
        ];
    }

    function conFormato(data, formato) {
        var out = data.slice().filter(function (p) { return p.name !== 'formato'; });
        out.push({ name: 'formato', value: formato || 'pdf' });
        return out;
    }

    $(document).on('click', '.btn-preview-contrato', function () {
        previsualizar(payloadContrato($(this).data('contrato-id'), 'pdf'), false);
    });

    $(document).on('click', '.btn-emitir-contrato', function () {
        var formato = String($(this).data('formato') || 'pdf');
        emitir(payloadContrato($(this).data('contrato-id'), formato), null, false);
    });

    $(document).on('click', '#btn-preview-certificado', function () {
        previsualizar($('#form-emitir-certificado').serializeArray(), true);
    });

    $(document).on('submit', '#form-emitir-certificado', function (e) {
        e.preventDefault();
        emitir(conFormato($(this).serializeArray(), 'pdf'), null, true);
    });

    $(document).on('click', '#btn-emitir-certificado-word', function () {
        emitir(conFormato($('#form-emitir-certificado').serializeArray(), 'docx'), null, true);
    });

    $(document).on('click', '#btn-contrato-preview-emitir', function () {
        if (!previewPayloadPendiente || !previewPayloadPendiente.length) {
            aviso('No hay datos de preview para emitir. Volvé a previsualizar.', 'error');
            return;
        }
        emitir(conFormato(previewPayloadPendiente, 'pdf'), null, false);
    });

    $(document).on('click', '#btn-contrato-preview-emitir-word', function () {
        if (!previewPayloadPendiente || !previewPayloadPendiente.length) {
            aviso('No hay datos de preview para emitir. Volvé a previsualizar.', 'error');
            return;
        }
        emitir(conFormato(previewPayloadPendiente, 'docx'), null, false);
    });
})(jQuery);
