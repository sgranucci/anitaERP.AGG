(function () {
    'use strict';

    var overlay = document.getElementById('lsd-proceso-overlay');
    var tituloEl = document.getElementById('lsd-proceso-titulo');

    function mostrar(titulo) {
        if (!overlay) {
            return;
        }
        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    window.addEventListener('pageshow', ocultar);
    window.addEventListener('pagehide', ocultar);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            ocultar();
        }
    });

    var formGen = document.getElementById('form-generar-lsd');
    if (formGen) {
        formGen.addEventListener('submit', function (e) {
            if (!formGen.checkValidity()) {
                return;
            }
            var opt = selectLiq ? selectLiq.options[selectLiq.selectedIndex] : null;
            var tipoAfip = opt ? (opt.getAttribute('data-tipo-afip') || '') : '';
            if (opt && opt.getAttribute('data-presentada') === '1') {
                e.preventDefault();
                alert('Esta liquidación ya está presentada. Use rectificativa (RE) desde la presentación.');
                return;
            }
            if (window.lsdBloqueaMensual && (tipoAfip === 'M' || tipoAfip === 'Q')) {
                e.preventDefault();
                alert('ARCA exige generar primero las liquidaciones E del período:\n' + (window.lsdEPendientes || []).join('\n'));
                return;
            }
            mostrar('Generando archivo LSD…');
        });
    }

    var btnConceptos = document.getElementById('btn-exportar-conceptos-lsd');
    if (btnConceptos) {
        btnConceptos.addEventListener('click', function () {
            var sub = document.getElementById('lsd-proceso-subtitulo');
            if (sub) {
                sub.textContent = 'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.';
            }
            mostrar('Exportando TXT de conceptos…');
            window.addEventListener('focus', ocultar, { once: true });
            setTimeout(ocultar, 4000);
        });
    }

    var empresa = document.getElementById('empresa_id');
    var periodo = document.getElementById('periodo_generar');
    var selectLiq = document.getElementById('liquidacion_id');
    var fechaPago = document.getElementById('fecha_pago');

    function cargarLiquidaciones() {
        if (!selectLiq || !window.lsdLiquidacionesUrl) {
            return;
        }
        var empId = empresa ? empresa.value : '';
        var per = periodo ? periodo.value : '';
        selectLiq.innerHTML = '<option value="">Cargando…</option>';
        if (!empId || !per) {
            selectLiq.innerHTML = '<option value="">Seleccione período…</option>';
            return;
        }
        var url = window.lsdLiquidacionesUrl + '?empresa_id=' + encodeURIComponent(empId) + '&periodo=' + encodeURIComponent(per);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (filas) {
                selectLiq.innerHTML = '<option value="">Seleccione…</option>';
                (filas || []).forEach(function (f) {
                    var opt = document.createElement('option');
                    opt.value = f.id;
                    var marca = f.presentada ? ' [presentada]' : (f.generada ? ' [generada]' : '');
                    opt.textContent = (f.tipo_afip || '') + ' · #' + f.numero + ' ' + (f.descripcion || '') + ' — ' + f.tipo + ' (' + f.recibos + ' recibos)' + marca;
                    opt.setAttribute('data-tipo-afip', f.tipo_afip || '');
                    if (f.presentada) {
                        opt.setAttribute('data-presentada', '1');
                    }
                    if (f.fecha_pago) {
                        opt.setAttribute('data-fecha-pago', f.fecha_pago);
                    }
                    selectLiq.appendChild(opt);
                });
                if (!filas || !filas.length) {
                    selectLiq.innerHTML = '<option value="">No hay liquidaciones cerradas</option>';
                }
            })
            .catch(function () {
                selectLiq.innerHTML = '<option value="">Error al cargar</option>';
            });
    }

    if (selectLiq) {
        selectLiq.addEventListener('change', function () {
            var opt = selectLiq.options[selectLiq.selectedIndex];
            var fp = opt ? opt.getAttribute('data-fecha-pago') : '';
            if (fp && fechaPago && !fechaPago.value) {
                fechaPago.value = fp;
            }
        });
    }
    if (empresa) {
        empresa.addEventListener('change', cargarLiquidaciones);
    }
    if (periodo) {
        periodo.addEventListener('change', cargarLiquidaciones);
        periodo.addEventListener('blur', cargarLiquidaciones);
    }
    cargarLiquidaciones();
})();
