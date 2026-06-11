/**
 * Cambio de medio de pago en facturas del día (misma UX que cobranza del POS gastronomía).
 */
(function () {
    'use strict';

    var TOLERANCIA = 0.02;
    var ventaIdActual = null;
    var datosCarga = null;
    var empresaId = 0;
    var totalFacturaArs = 0;
    var cuentacajaxcodigo = null;
    var monedaAbrevPorId = { 1: 'ARS' };
    var cuentasPorId = {};

    function apiBase() {
        return (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/caja/estacionamiento/facturas-dia';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function toast(msg, tipo) {
        if (typeof window.toastr !== 'undefined') {
            window.toastr[tipo === 'warning' ? 'warning' : tipo === 'success' ? 'success' : 'error'](msg);
            return;
        }
        alert(msg);
    }

    function mostrarError(msg) {
        var el = document.getElementById('fd-cmp-error');
        if (!el) {
            return;
        }
        el.textContent = msg || 'Error';
        el.classList.remove('d-none');
    }

    function limpiarError() {
        var el = document.getElementById('fd-cmp-error');
        if (el) {
            el.classList.add('d-none');
            el.textContent = '';
        }
    }

    function htmlIconoMedio(cuenta) {
        if (!cuenta) {
            return '<i class="fa fa-cash-register text-secondary"></i>';
        }
        if (cuenta.icono === 'gastro-icon-mercadopago') {
            return '<span class="gastro-icon-mercadopago" aria-hidden="true"></span>';
        }
        var color = cuenta.icono_color || 'text-secondary';
        var icono = cuenta.icono || 'fa fa-cash-register';
        return '<i class="' + icono + ' ' + color + '"></i>';
    }

    function etiquetaCorta(cuenta) {
        return cuenta.etiqueta_boton || cuenta.codigo || cuenta.nombre || 'Medio';
    }

    function renderMediosRapidos(cuentas) {
        var wrap = document.getElementById('fd-cmp-medios-rapidos');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = '';
        cuentasPorId = {};
        (cuentas || []).forEach(function (c) {
            if (c && c.id) {
                cuentasPorId[String(c.id)] = c;
            }
        });
        if (!cuentas || !cuentas.length) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        cuentas.forEach(function (cuenta) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary fd-cmp-medio-rapido';
            btn.title = (cuenta.codigo ? cuenta.codigo + ' — ' : '') + (cuenta.nombre || '');
            btn.dataset.cuentacajaId = String(cuenta.id);
            btn.innerHTML = htmlIconoMedio(cuenta) + '<span>' + etiquetaCorta(cuenta) + '</span>';
            btn.addEventListener('click', function () {
                seleccionarMedioRapido(cuenta);
            });
            wrap.appendChild(btn);
        });
    }

    function filaDesdeTemplate() {
        var tpl = document.getElementById('fd-cmp-template-renglon-cuenta');
        if (!tpl || !tpl.content) {
            return null;
        }
        return tpl.content.firstElementChild.cloneNode(true);
    }

    function asignarCuentaEnFila(tr, cuenta) {
        if (!tr || !cuenta || !cuenta.id) {
            return;
        }
        var idInp = tr.querySelector('.cuentacaja_id');
        var codInp = tr.querySelector('.codigo');
        var nomInp = tr.querySelector('.nombre');
        var monIdInp = tr.querySelector('.moneda_id');
        var monLbl = tr.querySelector('.moneda-label');
        if (idInp) {
            idInp.value = cuenta.id;
        }
        if (codInp) {
            codInp.value = cuenta.codigo || '';
        }
        if (nomInp) {
            nomInp.value = cuenta.nombre || '';
        }
        var monId = cuenta.moneda_id || '';
        if (monIdInp) {
            monIdInp.value = monId;
        }
        if (monLbl) {
            monLbl.textContent = cuenta.moneda_abreviatura || monedaAbrevPorId[monId] || (monId ? String(monId) : '—');
        }
        actualizarIconoConsulta(tr, cuenta);
    }

    function actualizarIconoConsulta(tr, cuenta) {
        var btn = tr.querySelector('.fd-cmp-consulta');
        if (!btn) {
            return;
        }
        btn.innerHTML = htmlIconoMedio(cuenta);
    }

    function limpiarCuentaEnFila(tr) {
        asignarCuentaEnFila(tr, { id: '', codigo: '', nombre: '', moneda_id: '', moneda_abreviatura: '' });
    }

    async function leerCuentaPorCodigo(codigo) {
        if (!ventaIdActual) {
            return { id: 0 };
        }
        var enc = encodeURIComponent(String(codigo || '').trim());
        if (!enc) {
            return { id: 0 };
        }
        try {
            var r = await fetch(apiBase() + '/' + ventaIdActual + '/cuentacaja-por-codigo/' + enc, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            var body = await r.json();
            if (!r.ok) {
                return { id: 0, error: body.error || 'Cuenta no encontrada.' };
            }
            return body;
        } catch (e) {
            return { id: 0, error: 'No se pudo validar la cuenta de caja.' };
        }
    }

    function abrirConsultaCuentacaja(tr) {
        if (!empresaId) {
            toast('No se pudo determinar la empresa de la factura.', 'warning');
            return;
        }
        var emp = document.getElementById('empresa_id');
        var empFd = document.getElementById('fd-cmp-empresa-id');
        if (emp) {
            emp.value = empresaId;
        }
        if (empFd) {
            empFd.value = empresaId;
        }
        if (typeof window.ESTACIONAMIENTO === 'undefined') {
            window.ESTACIONAMIENTO = {};
        }
        window.ESTACIONAMIENTO.empresaId = empresaId;
        window.ESTACIONAMIENTO.usocuentacajaEstacionamientoId = datosCarga
            ? parseInt(datosCarga.usocuentacaja_estacionamiento_id, 10) || 0
            : 0;

        cuentacajaxcodigo = tr.querySelector('.cuentacaja_id');
        if (typeof $ === 'undefined') {
            return;
        }
        $('#consultacuentacajaModal').one('shown.bs.modal.fdCmp', function () {
            if (typeof buscar_datos_cuentacaja === 'function') {
                buscar_datos_cuentacaja('');
            }
            $(this).find('#consultacuentacaja').trigger('focus');
        });
        $('#consultacuentacajaModal').modal('show');
    }

    function wireFila(tr) {
        var btnConsulta = tr.querySelector('.fd-cmp-consulta');
        if (btnConsulta) {
            btnConsulta.addEventListener('click', function () {
                abrirConsultaCuentacaja(tr);
            });
        }

        var codInp = tr.querySelector('.codigo');
        if (codInp) {
            var buscarPorCodigo = async function () {
                var codigo = codInp.value.trim();
                if (!codigo) {
                    limpiarCuentaEnFila(tr);
                    sumarMontos();
                    return;
                }
                var data = await leerCuentaPorCodigo(codigo);
                if (data && data.id > 0) {
                    asignarCuentaEnFila(tr, data);
                    sumarMontos();
                } else {
                    toast(data.error || 'No existe cuenta de caja con ese código.', 'warning');
                    limpiarCuentaEnFila(tr);
                    codInp.focus();
                    codInp.select();
                    sumarMontos();
                }
            };
            codInp.addEventListener('change', function () {
                void buscarPorCodigo();
            });
            codInp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    void buscarPorCodigo();
                }
            });
        }
    }

    function seleccionarMedioRapido(cuenta) {
        if (!cuenta || !cuenta.id) {
            return;
        }
        var tbody = document.getElementById('fd-cmp-tbody-cuenta-table');
        if (!tbody) {
            return;
        }
        var filas = Array.from(tbody.querySelectorAll('tr'));
        var vacia = filas.find(function (tr) {
            return !(tr.querySelector('.cuentacaja_id')?.value || '').trim();
        });
        var tr = vacia || filas[0];
        if (!tr) {
            return;
        }
        asignarCuentaEnFila(tr, cuenta);
        sumarMontos();
        tr.querySelector('.codigo')?.focus();
    }

    function sumarMontos() {
        var totalCobranza = 0;
        document.querySelectorAll('#fd-cmp-tbody-cuenta-table tr').forEach(function (tr) {
            var monto = parseFloat(tr.getAttribute('data-monto-original') || tr.querySelector('.monto')?.value || '0');
            if (!Number.isNaN(monto)) {
                totalCobranza += monto;
            }
        });
        var wrap = document.getElementById('fd-cmp-totales-cobranza');
        if (!wrap) {
            return;
        }
        var html = '<div><strong>Total cobranza:</strong> ' + totalCobranza.toFixed(2) + '</div>';
        if (totalFacturaArs > 0) {
            var diff = Math.abs(totalCobranza - totalFacturaArs);
            var ok = diff < TOLERANCIA;
            html += '<div class="mt-1"><strong>Total factura:</strong> ' + totalFacturaArs.toFixed(2);
            if (ok) {
                html += ' <span class="text-success">✓</span>';
            } else {
                html += ' <span class="fd-cmp-total-diff">(diferencia ' + diff.toFixed(2) + ')</span>';
            }
            html += '</div>';
        }
        wrap.innerHTML = html;
    }

    function renderGrilla(data) {
        var tbody = document.getElementById('fd-cmp-tbody-cuenta-table');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        var cobranzaEtiquetada = {};

        (data.cobranzas || []).forEach(function (cob) {
            (cob.lineas || []).forEach(function (ln) {
                var tr = filaDesdeTemplate();
                if (!tr) {
                    return;
                }
                tr.setAttribute('data-linea-id', String(ln.id));
                tr.setAttribute('data-monto-original', String(ln.monto));

                var lbl = tr.querySelector('.fd-cmp-row-cobranza-label');
                if (lbl && !cobranzaEtiquetada[cob.cobranza_id]) {
                    lbl.textContent = 'Cobranza #' + cob.cobranza_id;
                    lbl.classList.remove('d-none');
                    cobranzaEtiquetada[cob.cobranza_id] = true;
                }

                var montoInp = tr.querySelector('.monto');
                if (montoInp) {
                    montoInp.value = Number(ln.monto).toFixed(2);
                }

                asignarCuentaEnFila(tr, {
                    id: ln.cuentacaja_id,
                    codigo: ln.codigo,
                    nombre: ln.nombre,
                    moneda_id: ln.moneda_id,
                    moneda_abreviatura: ln.moneda,
                    icono: ln.icono,
                    icono_color: ln.icono_color,
                    etiqueta_boton: ln.etiqueta_boton,
                });

                wireFila(tr);
                tbody.appendChild(tr);
            });
        });

        renderMediosRapidos(data.cuentas_caja || []);
        sumarMontos();
        document.getElementById('fd-cmp-guardar').disabled = false;
    }

    function cargarDatos(ventaId) {
        ventaIdActual = ventaId;
        datosCarga = null;
        limpiarError();
        document.getElementById('fd-cmp-form-wrap').classList.add('d-none');
        document.getElementById('fd-cmp-tbody-cuenta-table').innerHTML = '';
        document.getElementById('fd-cmp-medios-rapidos').innerHTML = '';
        document.getElementById('fd-cmp-guardar').disabled = true;
        document.getElementById('fd-cmp-loading').classList.remove('d-none');
        document.getElementById('fd-cmp-venta-total').textContent = '—';
        document.getElementById('fd-cmp-venta-codigo').textContent = '—';

        fetch(apiBase() + '/' + ventaId + '/medios-pago', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, body: j };
                });
            })
            .then(function (res) {
                document.getElementById('fd-cmp-loading').classList.add('d-none');
                if (!res.ok || !res.body.ok) {
                    mostrarError((res.body && res.body.error) || 'No se pudieron cargar los medios de pago.');
                    return;
                }
                datosCarga = res.body;
                empresaId = parseInt(res.body.empresa_id, 10) || 0;
                totalFacturaArs = parseFloat(res.body.venta_total) || 0;
                document.getElementById('fd-cmp-empresa-id').value = empresaId;
                document.getElementById('empresa_id').value = empresaId;
                document.getElementById('fd-cmp-venta-total').textContent = totalFacturaArs.toFixed(2).replace('.', ',');
                document.getElementById('fd-cmp-venta-codigo').textContent = res.body.venta_codigo || ('#' + ventaId);
                document.getElementById('fd-cmp-form-wrap').classList.remove('d-none');
                renderGrilla(res.body);
            })
            .catch(function () {
                document.getElementById('fd-cmp-loading').classList.add('d-none');
                mostrarError('Error de comunicación al cargar los datos.');
            });
    }

    function recolectarCambios() {
        var cambios = [];
        document.querySelectorAll('#fd-cmp-tbody-cuenta-table tr').forEach(function (tr) {
            var lineaId = parseInt(tr.getAttribute('data-linea-id'), 10);
            var monto = parseFloat(tr.getAttribute('data-monto-original'));
            var cuentaId = parseInt(tr.querySelector('.cuentacaja_id')?.value || '0', 10);
            if (!lineaId || !cuentaId) {
                return;
            }
            cambios.push({
                caja_movimiento_cuentacaja_id: lineaId,
                cuentacaja_id: cuentaId,
                monto: monto,
            });
        });
        return cambios;
    }

    function initConsultaModal() {
        if (typeof $ === 'undefined') {
            return;
        }
        $(document).off('click.fdCmpCuentaElige', '.eligeconsultacuentacaja');
        $(document).on('click.fdCmpCuentaElige', '.eligeconsultacuentacaja', function () {
            if (!cuentacajaxcodigo || !$('#modal-fd-cambiar-medio-pago').hasClass('show')) {
                return;
            }
            var trModal = $(this).parents('tr');
            var id = trModal.find('.cuentacaja_id').html();
            var nombre = trModal.find('.nombre').html();
            var codigo = trModal.find('.codigo').html();
            var moneda_id = trModal.find('.moneda_id').html();
            var tr = cuentacajaxcodigo.closest('tr');
            var cuentaId = parseInt(id, 10);
            var cuentaDesdeLista = cuentasPorId[String(cuentaId)] || null;
            asignarCuentaEnFila(tr, cuentaDesdeLista || {
                id: cuentaId,
                nombre: nombre,
                codigo: codigo,
                moneda_id: parseInt(moneda_id, 10),
                moneda_abreviatura: monedaAbrevPorId[moneda_id] || '',
            });
            sumarMontos();
            $('#consultacuentacajaModal').modal('hide');
            cuentacajaxcodigo = null;
        });
    }

    document.querySelectorAll('.js-fd-cambiar-medio-pago').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var ventaId = parseInt(btn.getAttribute('data-venta-id'), 10);
            if (!ventaId) {
                return;
            }
            if (typeof $ !== 'undefined') {
                $('#modal-fd-cambiar-medio-pago').modal('show');
            }
            cargarDatos(ventaId);
        });
    });

    var btnGuardar = document.getElementById('fd-cmp-guardar');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function () {
            if (!ventaIdActual || !datosCarga) {
                return;
            }
            limpiarError();
            var cambios = recolectarCambios();
            if (!cambios.length) {
                mostrarError('No hay líneas de cobranza para actualizar.');
                return;
            }
            for (var i = 0; i < cambios.length; i++) {
                if (!cambios[i].cuentacaja_id) {
                    mostrarError('Seleccione una cuenta de caja en todas las líneas (código o búsqueda).');
                    return;
                }
            }
            var totalCob = 0;
            cambios.forEach(function (c) {
                totalCob += parseFloat(c.monto) || 0;
            });
            if (Math.abs(totalCob - totalFacturaArs) >= TOLERANCIA) {
                mostrarError(
                    'La suma de cobranza (' +
                        totalCob.toFixed(2) +
                        ') no coincide con el total de la factura (' +
                        totalFacturaArs.toFixed(2) +
                        '). No se puede guardar.',
                );
                return;
            }

            btnGuardar.disabled = true;
            fetch(apiBase() + '/' + ventaIdActual + '/medios-pago', {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ cambios: cambios }),
            })
                .then(function (r) {
                    return r.json().then(function (j) {
                        return { ok: r.ok, body: j };
                    });
                })
                .then(function (res) {
                    btnGuardar.disabled = false;
                    if (!res.ok || !res.body.ok) {
                        mostrarError((res.body && res.body.error) || 'No se pudo guardar.');
                        return;
                    }
                    if (typeof $ !== 'undefined') {
                        $('#modal-fd-cambiar-medio-pago').modal('hide');
                    }
                    window.location.reload();
                })
                .catch(function () {
                    btnGuardar.disabled = false;
                    mostrarError('Error de comunicación al guardar.');
                });
        });
    }

    initConsultaModal();
})();
