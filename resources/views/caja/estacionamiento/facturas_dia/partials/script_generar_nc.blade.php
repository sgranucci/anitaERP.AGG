{{--
    JS para flujo «Generar nota de crédito» desde el listado de facturas del día
    y desde el detalle (ver comprobante). Requiere modal_generar_nc en la misma vista.
--}}
<script>
(function () {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var token = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function refrescarCsrfToken() {
        return fetch('{{ url('csrf-token') }}', {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        }).then(function (r) {
            if (!r.ok) throw new Error('refresh-csrf-status-' + r.status);
            return r.json();
        }).then(function (j) {
            var nuevo = j && j.token ? String(j.token) : '';
            if (nuevo) {
                token = nuevo;
                if (csrfMeta) csrfMeta.setAttribute('content', nuevo);
            }
            return nuevo;
        });
    }

    function postNotaCreditoBody(ventaId, tokenActual, payload) {
        return fetch('{{ url('caja/estacionamiento/facturas-dia') }}/' + ventaId + '/generar-nota-credito', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': tokenActual,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        }).then(function (r) {
            return r.text().then(function (txt) {
                var body = null;
                try { body = txt ? JSON.parse(txt) : null; } catch (e) { body = null; }
                return { ok: r.ok, status: r.status, body: body, raw: txt };
            });
        });
    }

    var modalEl = document.getElementById('modal-fd-generar-nc');
    var btnConfirmar = document.getElementById('fd-nc-confirmar');
    var btnCancelar = document.getElementById('fd-nc-cancelar');
    var btnCerrarX = document.getElementById('fd-nc-cerrar-x');
    var btnConfirmarText = document.getElementById('fd-nc-confirmar-text');
    var btnConfirmarIcono = document.getElementById('fd-nc-confirmar-icono');
    var inputLeyenda = document.getElementById('fd-nc-leyenda');
    var textoCompro = document.getElementById('fd-nc-compro');
    var overlay = document.getElementById('fd-nc-procesando-overlay');
    var estado = { ventaId: null, codigo: '', btnDisparador: null, procesando: false };

    function mostrarOverlay(mostrar) {
        if (!overlay) return;
        if (mostrar) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        } else {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    function setProcesando(activo) {
        estado.procesando = !!activo;
        if (btnConfirmar) btnConfirmar.disabled = activo;
        if (btnCancelar) btnCancelar.disabled = activo;
        if (btnCerrarX) btnCerrarX.style.visibility = activo ? 'hidden' : '';
        if (inputLeyenda) inputLeyenda.disabled = activo;
        if (btnConfirmarText) btnConfirmarText.textContent = activo ? 'Procesando…' : 'Generar nota de crédito';
        if (btnConfirmarIcono) {
            btnConfirmarIcono.classList.toggle('fa-undo', !activo);
            btnConfirmarIcono.classList.toggle('fa-spinner', activo);
            btnConfirmarIcono.classList.toggle('fa-spin', activo);
        }
        mostrarOverlay(activo);
    }

    document.querySelectorAll('.js-fd-generar-nc').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (estado.procesando) return;
            var ventaId = btn.getAttribute('data-venta-id');
            var codigo = btn.getAttribute('data-codigo') || '';
            if (!ventaId || btn.disabled) return;
            estado.ventaId = ventaId;
            estado.codigo = codigo;
            estado.btnDisparador = btn;
            if (textoCompro) textoCompro.textContent = codigo || ('#' + ventaId);
            if (inputLeyenda) inputLeyenda.value = '';
            setProcesando(false);
            if (typeof $ !== 'undefined' && modalEl) {
                $('#modal-fd-generar-nc').one('shown.bs.modal', function () {
                    if (inputLeyenda) inputLeyenda.focus();
                });
                $('#modal-fd-generar-nc').modal('show');
            } else {
                ejecutarNotaCredito('');
            }
        });
    });

    function ejecutarNotaCredito(leyenda) {
        var ventaId = estado.ventaId;
        var btn = estado.btnDisparador;
        if (!ventaId) return;
        if (btn) btn.disabled = true;
        setProcesando(true);
        var payload = { leyenda: leyenda || '' };
        postNotaCreditoBody(ventaId, token, payload)
            .then(function (res) {
                if (res.status === 419) {
                    return refrescarCsrfToken().then(function (nuevo) {
                        if (!nuevo) return res;
                        return postNotaCreditoBody(ventaId, nuevo, payload);
                    });
                }
                return res;
            })
            .then(function (res) {
                if (res.ok && res.body && res.body.ok) {
                    if (typeof $ !== 'undefined' && modalEl) {
                        $('#modal-fd-generar-nc').modal('hide');
                    }
                    var txt = res.body.mensaje || 'Nota de crédito generada.';
                    if (typeof toastr !== 'undefined') {
                        if (res.body.warn) toastr.warning(res.body.warn);
                        toastr.success(txt);
                    } else {
                        alert((res.body.warn ? res.body.warn + '\n\n' : '') + txt);
                    }
                    setTimeout(function () { window.location.reload(); }, 900);
                } else {
                    var err = '';
                    if (res.body) {
                        err = res.body.error || res.body.mensaje || res.body.message || '';
                    }
                    if (!err && res.raw) {
                        err = String(res.raw).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 400);
                    }
                    if (!err) {
                        err = 'Error al generar la nota de crédito.';
                    }
                    err = 'HTTP ' + res.status + ': ' + err;
                    console.error('NC estacionamiento falló', res);
                    if (typeof toastr !== 'undefined') toastr.error(err, '', { timeOut: 12000, extendedTimeOut: 4000 });
                    else alert(err);
                }
            })
            .catch(function (e) {
                console.error('NC estacionamiento error de red', e);
                var msg = 'Error de comunicación al generar la nota de crédito.' + (e && e.message ? ' (' + e.message + ')' : '');
                if (typeof toastr !== 'undefined') toastr.error(msg);
                else alert(msg);
            })
            .finally(function () {
                if (btn) btn.disabled = false;
                setProcesando(false);
            });
    }

    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function (e) {
            e.preventDefault();
            if (estado.procesando) return;
            var leyenda = inputLeyenda ? String(inputLeyenda.value || '').trim() : '';
            ejecutarNotaCredito(leyenda);
        });
    }

    if (modalEl && typeof $ !== 'undefined') {
        $(modalEl).on('hide.bs.modal', function (e) {
            if (estado.procesando) {
                e.preventDefault();
            }
        });
    }
})();
</script>
