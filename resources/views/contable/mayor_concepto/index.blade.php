@extends("theme.$theme.layout")
@section('titulo')
    Mayor por concepto
@endsection

@section('scripts')
<script>
(function () {
    var OVERLAY_ID = 'mayor-concepto-procesando-overlay';
    var TITULO_ID = 'mayor-concepto-procesando-titulo';
    var SUBTITULO_ID = 'mayor-concepto-procesando-subtitulo';
    var TITULO_CONSULTA = 'Procesando mayor por concepto…';
    var SUBTITULO_CONSULTA = 'Puede tardar varios minutos según el período. No cierre ni recargue la página.';
    var TITULO_EXPORT = 'Generando exportación…';
    var SUBTITULO_EXPORT = 'Por favor espere. El PDF o Excel puede tardar según el volumen.';

    function mostrarProcesoOverlay(titulo, subtitulo) {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay) {
            return;
        }

        var tituloEl = document.getElementById(TITULO_ID);
        var subtituloEl = document.getElementById(SUBTITULO_ID);
        if (tituloEl) {
            tituloEl.textContent = titulo || TITULO_CONSULTA;
        }
        if (subtituloEl) {
            subtituloEl.textContent = subtitulo || SUBTITULO_CONSULTA;
        }

        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.__mayorConceptoOverlayShownAt = Date.now();

        if (window.__mayorConceptoOverlayHintTimer) {
            clearTimeout(window.__mayorConceptoOverlayHintTimer);
        }
        window.__mayorConceptoOverlayHintTimer = setTimeout(function () {
            if (subtituloEl && overlay.getAttribute('aria-hidden') === 'false') {
                subtituloEl.textContent = 'Sigue en curso… Si tarda demasiado, pulse Esc o recargue con F5 (el banner no implica que el servidor siga trabajando).';
            }
        }, 90000);
    }

    function ocultarProcesoOverlay() {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay) {
            return;
        }

        if (window.__mayorConceptoOverlayHintTimer) {
            clearTimeout(window.__mayorConceptoOverlayHintTimer);
            window.__mayorConceptoOverlayHintTimer = null;
        }

        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function esUrlProcesoMayorConcepto(href) {
        if (! href || href === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }

        try {
            var url = new URL(href, window.location.origin);
            var path = url.pathname.toLowerCase();

            return path.indexOf('listar-mayor-concepto') !== -1
                || (path.indexOf('mayor-concepto') !== -1 && url.searchParams.get('consultar') === '1');
        } catch (e) {
            var lower = String(href).toLowerCase();
            return lower.indexOf('listar-mayor-concepto') !== -1
                || (lower.indexOf('mayor-concepto') !== -1 && lower.indexOf('consultar=1') !== -1);
        }
    }

    function togglePeriodo() {
        var mes = document.getElementById('modo_mes').checked;
        document.getElementById('panel-mes').style.display = mes ? '' : 'none';
        document.getElementById('panel-rango').style.display = mes ? 'none' : '';
    }
    document.querySelectorAll('input[name="modo_periodo"]').forEach(function (el) {
        el.addEventListener('change', togglePeriodo);
    });
    togglePeriodo();

    var form = document.getElementById('form-mayor-concepto');
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // empresa_ids[] ya van por checkboxes marcados (FormData).
            // Solo sincronizar Consolidar desde el botón (evita desfase hidden vs UI).
            var btnConsolPre = form.querySelector('.btn-toggle-consolidar-empresas');
            var inputConsolPre = form.querySelector('input[name="consolidar_empresas"]');
            if (btnConsolPre && inputConsolPre) {
                inputConsolPre.value = btnConsolPre.classList.contains('btn-success') ? '1' : '0';
            }

            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando… (puede tardar varios minutos)';
            }
            mostrarProcesoOverlay(TITULO_CONSULTA, SUBTITULO_CONSULTA);

            function actualizarProgreso(texto) {
                var subtituloEl = document.getElementById(SUBTITULO_ID);
                if (subtituloEl) {
                    subtituloEl.textContent = texto;
                }
            }

            function restaurarBoton() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-search"></i> Consultar';
                }
            }

            function procesarPaso(empresaIdx) {
                var fd = new FormData(form);
                fd.set('ajax', '1');
                fd.set('empresa_idx', String(empresaIdx));

                // Sincronizar Consolidar desde el botón (evita desfase hidden vs UI).
                var btnConsol = form.querySelector('.btn-toggle-consolidar-empresas');
                var inputConsol = form.querySelector('input[name="consolidar_empresas"]');
                if (btnConsol && inputConsol) {
                    var activo = btnConsol.classList.contains('btn-success');
                    inputConsol.value = activo ? '1' : '0';
                    fd.set('consolidar_empresas', activo ? '1' : '0');
                }

                return fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }).then(function (res) {
                    if (res.status === 419) {
                        throw new Error('Sesión expirada. Recargue la página (F5) e intente de nuevo.');
                    }
                    if (res.status === 404 || res.status === 502 || res.status === 504) {
                        throw new Error('El servidor cortó el request por tiempo o carga (HTTP ' + res.status + '). Reintente; si persiste, consulte de a una empresa o un mes a la vez.');
                    }
                    if (! res.ok) {
                        return res.json().catch(function () { return {}; }).then(function (body) {
                            throw new Error((body && body.mensaje) || ('Error HTTP ' + res.status));
                        });
                    }
                    return res.json();
                }).then(function (data) {
                    if (! data || ! data.ok) {
                        throw new Error((data && data.mensaje) || 'No se pudo generar el reporte.');
                    }
                    if (data.done) {
                        actualizarProgreso(data.mensaje || 'Listo. Abriendo reporte…');
                        window.location.href = data.redirect;
                        return;
                    }
                    actualizarProgreso(
                        (data.mensaje || ('Procesando empresa ' + data.procesada + '/' + data.total))
                        + ' — no cierre esta ventana.'
                    );
                    return procesarPaso(data.empresa_idx);
                });
            }

            procesarPaso(0).catch(function (err) {
                ocultarProcesoOverlay();
                restaurarBoton();
                window.alert(err && err.message ? err.message : 'Error al generar el mayor por concepto.');
            });
        });
    }

    document.addEventListener('click', function (event) {
        var enlace = event.target && event.target.closest
            ? event.target.closest('a[href]')
            : null;

        if (! enlace || enlace.target === '_blank' || enlace.hasAttribute('download')) {
            return;
        }

        var href = enlace.getAttribute('href') || enlace.href || '';
        if (! esUrlProcesoMayorConcepto(href)) {
            return;
        }

        // Export PDF/Excel/CSV: descarga sin navegar → hay que ocultar el banner al terminar.
        if (href.toLowerCase().indexOf('listar-mayor-concepto') !== -1) {
            event.preventDefault();
            event.stopPropagation();
            descargarExportacionMayorConcepto(href);
        }
    }, true);

    function nombreArchivoDesdeContentDisposition(disposition, fallback) {
        if (! disposition) {
            return fallback;
        }
        var match = /filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;]+)/i.exec(disposition);
        if (! match) {
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
        a.download = filename || 'mayor_por_concepto';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function descargarExportacionMayorConcepto(href) {
        var lower = String(href).toLowerCase();
        var formato = 'archivo';
        if (lower.indexOf('/excel') !== -1) {
            formato = 'Excel';
        } else if (lower.indexOf('/pdf') !== -1) {
            formato = 'PDF';
        } else if (lower.indexOf('/csv') !== -1) {
            formato = 'CSV';
        }

        mostrarProcesoOverlay(
            TITULO_EXPORT,
            'Generando ' + formato + '… Puede tardar según el volumen. No cierre la página.'
        );

        if (window.__mayorConceptoExportAbort) {
            try { window.__mayorConceptoExportAbort.abort(); } catch (e) {}
        }
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        window.__mayorConceptoExportAbort = controller;

        // Red de seguridad: si el fetch queda colgado, no dejar el banner eterno.
        if (window.__mayorConceptoExportSafetyTimer) {
            clearTimeout(window.__mayorConceptoExportSafetyTimer);
        }
        window.__mayorConceptoExportSafetyTimer = setTimeout(function () {
            ocultarProcesoOverlay();
        }, 600000);

        fetch(href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': '*/*',
            },
        }).then(function (res) {
            if (res.status === 419) {
                throw new Error('Sesión expirada. Recargue la página (F5) e intente de nuevo.');
            }
            if (res.redirected && res.url && res.url.indexOf('listar-mayor-concepto') === -1) {
                // Redirect al index (filtros incompletos / sin formato): salir sin archivo.
                throw new Error('No se pudo generar la exportación. Verifique los filtros y vuelva a consultar.');
            }
            if (! res.ok) {
                throw new Error('Error HTTP ' + res.status + ' al exportar.');
            }
            var fallback = 'mayor_por_concepto';
            if (formato === 'Excel') {
                fallback += '.xlsx';
            } else if (formato === 'PDF') {
                fallback += '.pdf';
            } else if (formato === 'CSV') {
                fallback += '.csv';
            }
            var filename = nombreArchivoDesdeContentDisposition(
                res.headers.get('Content-Disposition'),
                fallback
            );
            return res.blob().then(function (blob) {
                return { blob: blob, filename: filename };
            });
        }).then(function (pack) {
            if (! pack || ! pack.blob || pack.blob.size === 0) {
                throw new Error('La exportación vino vacía. Reintente.');
            }
            // Si el servidor devolvió HTML de error/login, no descargar basura.
            if (pack.blob.type && pack.blob.type.indexOf('text/html') !== -1) {
                throw new Error('La sesión o el permiso fallaron al exportar. Recargue e intente de nuevo.');
            }
            dispararDescargaBlob(pack.blob, pack.filename);
            ocultarProcesoOverlay();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                ocultarProcesoOverlay();
                return;
            }
            ocultarProcesoOverlay();
            window.alert(err && err.message ? err.message : 'No se pudo descargar la exportación.');
        }).finally(function () {
            if (window.__mayorConceptoExportSafetyTimer) {
                clearTimeout(window.__mayorConceptoExportSafetyTimer);
                window.__mayorConceptoExportSafetyTimer = null;
            }
            window.__mayorConceptoExportAbort = null;
        });
    }

    window.addEventListener('pageshow', function () {
        ocultarProcesoOverlay();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            ocultarProcesoOverlay();
        }
    });

    // Si el GET se aborta (timeout proxy / navegación cancelada), sacar el banner.
    window.addEventListener('pagehide', function () {
        ocultarProcesoOverlay();
    });
    window.addEventListener('focus', function () {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay || overlay.getAttribute('aria-hidden') === 'true') {
            return;
        }
        // Tras 3+ minutos con banner y sin navegación, sugerir Esc (request probablemente muerto).
        if (window.__mayorConceptoOverlayShownAt && (Date.now() - window.__mayorConceptoOverlayShownAt) > 180000) {
            var subtituloEl = document.getElementById(SUBTITULO_ID);
            if (subtituloEl) {
                subtituloEl.textContent = 'El proceso no respondió a tiempo. Pulse Esc o F5 e intente de nuevo (idealmente una empresa, o menos rango).';
            }
        }
    });

    function actualizarQueryParamEnlace(enlace, modo) {
        if (! enlace || ! enlace.href) {
            return;
        }
        try {
            var url = new URL(enlace.href, window.location.origin);
            if (modo === 'cuenta_concepto') {
                url.searchParams.set('agrupacion_resumen', modo);
            } else {
                url.searchParams.delete('agrupacion_resumen');
            }
            enlace.href = url.toString();
        } catch (e) {
            // ignorar enlaces mal formados
        }
    }

    function normalizarFormatoExcel(formato) {
        if (formato === 'intl') {
            return 'intl';
        }
        if (formato === 'ar') {
            return 'ar';
        }
        return 'auto';
    }

    function actualizarFormatoExcelEnEnlaces(formato) {
        document.querySelectorAll('#mayor-concepto-exportar a').forEach(function (enlace) {
            if (! enlace || ! enlace.href) {
                return;
            }
            try {
                var url = new URL(enlace.href, window.location.origin);
                if (formato === 'ar' || formato === 'intl') {
                    url.searchParams.set('excel_formato_numero', formato);
                } else {
                    // auto = comportamiento por defecto: sin parámetro
                    url.searchParams.delete('excel_formato_numero');
                }
                enlace.href = url.toString();
            } catch (e) {
                // ignorar
            }
        });
    }

    function marcarBotonesFormatoExcel(formato) {
        ['auto', 'ar', 'intl'].forEach(function (f) {
            var btn = document.getElementById('btn-excel-formato-' + f);
            if (btn) {
                btn.classList.toggle('btn-secondary', formato === f);
                btn.classList.toggle('btn-outline-secondary', formato !== f);
            }
        });
    }

    window.cambiarFormatoExcelNumero = function (formato) {
        formato = normalizarFormatoExcel(formato);
        var input = document.getElementById('excel_formato_numero');
        if (input) {
            input.value = formato;
        }
        marcarBotonesFormatoExcel(formato);
        actualizarFormatoExcelEnEnlaces(formato);
        try {
            var urlActual = new URL(window.location.href);
            if (formato === 'ar' || formato === 'intl') {
                urlActual.searchParams.set('excel_formato_numero', formato);
            } else {
                urlActual.searchParams.delete('excel_formato_numero');
            }
            window.history.replaceState({}, '', urlActual.toString());
        } catch (e) {
            // ignorar
        }
        try {
            window.localStorage.setItem('mayor_concepto_excel_formato_numero', formato);
        } catch (e) {
            // ignorar
        }
    };

    document.addEventListener('click', function (event) {
        var btn = event.target && event.target.closest
            ? event.target.closest('#btn-excel-formato-auto, #btn-excel-formato-ar, #btn-excel-formato-intl')
            : null;
        if (! btn) {
            return;
        }
        event.preventDefault();
        window.cambiarFormatoExcelNumero(btn.getAttribute('data-formato') || 'auto');
    });

    // Preferir query/localStorage al cargar (sin invalidar cache del reporte).
    (function initFormatoExcel() {
        var actual = 'auto';
        var input = document.getElementById('excel_formato_numero');
        if (input && input.value) {
            actual = normalizarFormatoExcel(input.value);
        }
        try {
            var url = new URL(window.location.href);
            if (url.searchParams.has('excel_formato_numero')) {
                actual = normalizarFormatoExcel(url.searchParams.get('excel_formato_numero'));
            } else {
                var ls = window.localStorage.getItem('mayor_concepto_excel_formato_numero');
                if (ls === 'intl' || ls === 'ar' || ls === 'auto') {
                    actual = ls;
                }
            }
        } catch (e) {
            // ignorar
        }
        window.cambiarFormatoExcelNumero(actual);
    })();

    window.cambiarAgrupacionResumen = function (modo) {
        var input = document.getElementById('agrupacion_resumen');
        if (! input) {
            return;
        }
        input.value = modo;

        var tablaConcepto = document.getElementById('resumen-tabla-concepto-cuenta');
        var tablaCuenta = document.getElementById('resumen-tabla-cuenta-concepto');
        if (tablaConcepto) {
            tablaConcepto.style.display = modo === 'concepto_cuenta' ? '' : 'none';
        }
        if (tablaCuenta) {
            tablaCuenta.style.display = modo === 'cuenta_concepto' ? '' : 'none';
        }

        document.querySelectorAll('[onclick*="cambiarAgrupacionResumen"]').forEach(function (btn) {
            var esActivo = btn.getAttribute('onclick').indexOf("'" + modo + "'") !== -1;
            btn.classList.toggle('btn-primary', esActivo);
            btn.classList.toggle('btn-outline-primary', !esActivo);
        });

        document.querySelectorAll('#mayor-concepto-exportar a, .pagination a').forEach(function (enlace) {
            actualizarQueryParamEnlace(enlace, modo);
        });

        var inputDetalle = document.getElementById('agrupacion_resumen_detalle');
        if (inputDetalle) {
            inputDetalle.value = modo;
        }

        try {
            var urlActual = new URL(window.location.href);
            if (modo === 'cuenta_concepto') {
                urlActual.searchParams.set('agrupacion_resumen', modo);
            } else {
                urlActual.searchParams.delete('agrupacion_resumen');
            }
            window.history.replaceState({}, '', urlActual.toString());
        } catch (e) {
            // ignorar
        }
    };
})();
</script>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Mayor por concepto</h3>
                <div class="card-tools">
                    <a href="{{ route('mayor_concepto') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="post" action="{{ route('mayor_concepto_consultar') }}" id="form-mayor-concepto" class="mb-0">
                @csrf
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Imputación contable por concepto de gasto (motor Anita). Los importes se expresan en la moneda elegida
                        según cotización de cada movimiento.
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'mayor_concepto',
                        'id_prefix' => 'mco',
                        'col_label' => 'col-lg-2 text-right',
                    ])

                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Período</label>
                        <div class="col-lg-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_mes" value="mes"
                                    {{ ($filtros['modo_periodo'] ?? 'mes') === 'mes' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_mes">Mes completo</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_rango" value="rango"
                                    {{ ($filtros['modo_periodo'] ?? '') === 'rango' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_rango">Rango de fechas</label>
                            </div>
                        </div>
                    </div>

                    <div id="panel-mes" class="form-group row">
                        <label class="col-lg-2 control-label requerido">Mes / Año</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio'] ?? $anio_actual }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-rango" class="form-group row" style="display:none;">
                        <label class="col-lg-2 control-label requerido">Desde / Hasta</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <input type="date" name="fecha_desde" class="form-control"
                                        value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha_hasta" class="form-control"
                                        value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="moneda_id" class="col-lg-2 control-label requerido">Expresar en</label>
                        <div class="col-lg-4">
                            <select name="moneda_id" id="moneda_id" class="form-control" required>
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label">Filtro moneda</label>
                        <div class="col-lg-9">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">
                                    Solo movimientos en moneda origen (equivalente Anita «Origen»)
                                </label>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="agrupacion_resumen" id="agrupacion_resumen"
                        value="{{ $filtros['agrupacion_resumen'] ?? 'concepto_cuenta' }}">
                    <input type="hidden" name="excel_formato_numero" id="excel_formato_numero"
                        value="{{ \App\Support\Contable\MayorConceptoExcelFormatoNumero::normalizar($filtros['excel_formato_numero'] ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()) }}">

                    <div class="form-group row mb-0 mt-3">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                @php $tot = $totales ?? []; @endphp
                <div class="card-body p-0 border-top">
                    @if (!empty($errores_bridge))
                        <div class="alert alert-warning mx-3 mt-3 mb-0">
                            <strong>Advertencias bridge Anita:</strong>
                            <ul class="mb-0">
                                @foreach ($errores_bridge as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-1 small">
                            <strong>Empresa{{ count($filtros['empresa_ids'] ?? []) > 1 ? 's' : '' }}:</strong>
                            {{ $empresas_texto ?? ($empresa->nombre ?? '—') }}
                            @if (count($filtros['empresa_ids'] ?? []) > 1)
                                · <strong>Modo:</strong>
                                @if ($filtros['consolidar_empresas'] ?? true)
                                    consolidado
                                @else
                                    un reporte por empresa
                                @endif
                            @endif
                            · <strong>Período:</strong> {{ $periodo_texto }}
                            · <strong>Expresado en:</strong> {{ $moneda->nombre ?? '' }} ({{ $moneda->abreviatura ?? '' }})
                            @if (! empty($filtros['solo_moneda_origen']))
                                — solo moneda origen
                            @endif
                        </p>
                        <p class="mb-0 small text-muted">
                            {{ $tot['stats']['subdiario_filas'] ?? 0 }} movimientos subdiario ·
                            {{ $tot['stats']['ctamov_filas'] ?? 0 }} ctamov ·
                            {{ $tot['stats']['auxpag_filas'] ?? 0 }} auxpag ·
                            {{ $tot['stats']['operaciones_procesadas'] ?? 0 }} operaciones procesadas
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0 d-flex flex-wrap align-items-center" id="mayor-concepto-exportar">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_mayor_concepto',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                            @php
                                $formatoExcelUi = \App\Support\Contable\MayorConceptoExcelFormatoNumero::normalizar(
                                    $filtros['excel_formato_numero'] ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()
                                );
                            @endphp
                            <div class="ml-2 d-inline-flex align-items-center small text-muted"
                                 title="Formato de n&uacute;meros al exportar Excel/CSV">
                                <span class="mr-1">N&uacute;m.</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Formato num&eacute;rico Excel">
                                    <button type="button"
                                        id="btn-excel-formato-auto"
                                        class="btn {{ $formatoExcelUi === 'auto' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                                        data-formato="auto"
                                        title="Autom&aacute;tico: cada PC lo muestra seg&uacute;n su configuraci&oacute;n regional">
                                        Auto
                                    </button>
                                    <button type="button"
                                        id="btn-excel-formato-ar"
                                        class="btn {{ $formatoExcelUi === 'ar' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                                        data-formato="ar"
                                        title="Forzar Argentina: 1.234,56">
                                        AR
                                    </button>
                                    <button type="button"
                                        id="btn-excel-formato-intl"
                                        class="btn {{ $formatoExcelUi === 'intl' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                                        data-formato="intl"
                                        title="Forzar internacional: 1,234.56">
                                        INTL
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="small mb-1 mb-md-0 text-md-right">
                            @php
                                $totVis = $totales_visibles ?? null;
                                $tot = $totales ?? [];
                            @endphp
                            @if (! empty($filtro_detalle_activo) && ! empty($totVis))
                                <span class="text-muted">Detalle filtrado:</span>
                                <strong>{{ (int) ($totVis['cantidad_filas'] ?? 0) }}</strong> líneas
                                · Debe <strong>{{ number_format((float) ($totVis['total_debe'] ?? 0), 2, ',', '.') }}</strong>
                                · Haber <strong>{{ number_format((float) ($totVis['total_haber'] ?? 0), 2, ',', '.') }}</strong>
                                <span class="text-muted">(de {{ (int) ($tot['cantidad_filas'] ?? 0) }} del período)</span>
                            @else
                                <span class="text-muted">Totales filtro:</span>
                                <strong>{{ (int) ($tot['cantidad_filas'] ?? 0) }}</strong> líneas
                                · Debe <strong>{{ number_format((float) ($tot['total_debe'] ?? 0), 2, ',', '.') }}</strong>
                                · Haber <strong>{{ number_format((float) ($tot['total_haber'] ?? 0), 2, ',', '.') }}</strong>
                            @endif
                        </div>
                    </div>

                    @php
                        $filasVista = $filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                            ? $filas->items()
                            : ($filas ?? []);
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasVista);
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    @include('contable.mayor_concepto.partials.resumen_totales', [
                        'resumen' => $resumen ?? [],
                        'resumen_por_cuenta' => $resumen_por_cuenta ?? [],
                        'agrupacion_resumen' => $agrupacion_resumen ?? 'concepto_cuenta',
                        'puede_ver_asiento' => $puede_ver_asiento ?? false,
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                        'puede_ver_concepto' => $puede_ver_concepto ?? false,
                    ])

                    @include('contable.mayor_concepto.partials.conciliacion_asientos_panel', [
                        'auditoria_panel' => $auditoria_panel ?? null,
                        'puede_ver_asiento' => $puede_ver_asiento ?? false,
                    ])

                    @include('contable.mayor_concepto.partials.filtros_detalle', [
                        'filtros' => $filtros ?? [],
                        'filtrosQuery' => $filtrosQuery ?? [],
                        'filtrosQueryBase' => $filtrosQueryBase ?? [],
                        'filtro_detalle_activo' => $filtro_detalle_activo ?? false,
                        'filtros_detalle_texto' => $filtros_detalle_texto ?? [],
                    ])

                    <div class="px-3 pt-2 pb-1">
                        <h6 class="mb-0 font-weight-bold">Detalle de movimientos</h6>
                        <small class="text-muted">Imputaciones con subtotal por cuenta y total por concepto (como mayor Anita)</small>
                    </div>

                    <style>
                        #tabla-mayor-concepto thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-mayor-concepto thead th { font-weight: 600; border-color: #7fb3d5; }
                        #tabla-mayor-concepto tbody tr.fila-total-cuenta { background-color: #e9ecef !important; }
                        #tabla-mayor-concepto tbody tr.fila-total-concepto { background-color: #ced4da !important; }
                        #tabla-mayor-concepto a.text-primary {
                            color: #007bff !important;
                            font-weight: 600;
                            text-decoration: underline;
                        }
                        #tabla-mayor-concepto a.text-primary:hover {
                            color: #0056b3 !important;
                        }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-mayor-concepto" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('contable.mayor_concepto.partials.tabla_datos', [
                                'filas' => $filasVista,
                                'multiempresa' => $multiempresa ?? false,
                                'puede_ver_asiento' => $puede_ver_asiento ?? false,
                                'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                                'puede_ver_concepto' => $puede_ver_concepto ?? false,
                                'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                                'puede_ver_capex' => $puede_ver_capex ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} registros
                                @elseif (! empty($filtro_detalle_activo))
                                    Sin coincidencias para el filtro de detalle.
                                    @if (trim((string) ($filtros['filtro_nro_asiento'] ?? '')) !== '')
                                        Revise el N. asiento (ej. 5263579, no 52603579) y que la empresa del consulta sea la correcta.
                                    @endif
                                @else
                                    Sin registros
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'mayor-concepto-procesando-overlay',
    'tituloId' => 'mayor-concepto-procesando-titulo',
    'subtituloId' => 'mayor-concepto-procesando-subtitulo',
    'titulo' => 'Procesando mayor por concepto…',
    'subtitulo' => 'Puede tardar varios minutos según el período. No cierre ni recargue la página.',
])
@endsection
