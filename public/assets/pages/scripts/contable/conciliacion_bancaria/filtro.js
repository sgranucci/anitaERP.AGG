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

    $('#form-conciliacion-bancaria').on('submit', function (ev) {
        var form = this;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }
        mostrarOverlay('Conciliando…', 'Puede demorar según el período. No cierre la página.');
        var btn = document.getElementById('btn-consultar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando conciliación…';
        }
    });

    function overlayEl() {
        return document.getElementById('conciliacion-bancaria-overlay');
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = overlayEl();
        if (!overlay) return;
        if (titulo) document.getElementById('conciliacion-bancaria-titulo').textContent = titulo;
        if (subtitulo) document.getElementById('conciliacion-bancaria-subtitulo').textContent = subtitulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = overlayEl();
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    function nombreArchivo(disposition, fallback) {
        var match = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(disposition || '');
        var raw = match ? (match[1] || match[2] || '') : '';
        try {
            return decodeURIComponent(raw) || fallback;
        } catch (e) {
            return raw || fallback;
        }
    }

    document.addEventListener('click', function (ev) {
        var link = ev.target.closest('.js-export-conciliacion');
        if (!link) return;
        ev.preventDefault();
        mostrarOverlay('Exportando…', 'Armando el Excel. Pulse Esc para cancelar el aviso.');
        var ctrl = new AbortController();
        function alEsc(e) {
            if (e.key === 'Escape') {
                ctrl.abort();
                ocultarOverlay();
            }
        }
        window.addEventListener('keydown', alEsc);
        fetch(link.href, { credentials: 'same-origin', signal: ctrl.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                var tipo = res.headers.get('Content-Type') || '';
                if (!res.ok || tipo.indexOf('text/html') !== -1) {
                    return res.text().then(function () {
                        throw new Error('No se pudo generar el Excel. Volvé a conciliar e intentá de nuevo.');
                    });
                }
                return res.blob().then(function (blob) {
                    return { blob: blob, filename: nombreArchivo(res.headers.get('Content-Disposition'), 'conciliacion_bancaria.xlsx') };
                });
            })
            .then(function (pack) {
                var url = URL.createObjectURL(pack.blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = pack.filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                ocultarOverlay();
            })
            .catch(function (err) {
                ocultarOverlay();
                if (err && err.name === 'AbortError') return;
                alert(err.message || 'No se pudo exportar');
            })
            .finally(function () {
                window.removeEventListener('keydown', alEsc);
            });
    });

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('#btn-generar-gastos');
        if (!btn) return;
        var total = btn.getAttribute('data-total') || '';
        if (!confirm('Se va a grabar el egreso y el ICO por ' + total + '. ¿Continuar?')) return;
        btn.disabled = true;
        mostrarOverlay('Generando egreso e ICO…', 'No cierre la página.');
        var body = new URLSearchParams();
        body.set('empresa_id', btn.getAttribute('data-empresa'));
        body.set('cuentacaja_id', btn.getAttribute('data-cuenta'));
        body.set('mes', btn.getAttribute('data-mes'));
        body.set('anio', btn.getAttribute('data-anio'));
        fetch(btn.getAttribute('data-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            ocultarOverlay();
            if (!data.ok) throw new Error(data.error || 'No se pudo generar');
            alert(data.mensaje || 'Generado');
            if (data.url) window.open(data.url, '_blank', 'noopener');
        }).catch(function (err) {
            ocultarOverlay();
            btn.disabled = false;
            alert(err.message || 'No se pudo generar');
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);

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
