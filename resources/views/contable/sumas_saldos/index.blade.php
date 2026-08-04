@extends("theme.$theme.layout")
@section('titulo')
    Balance de sumas y saldos
@endsection

@section('scripts')
<script>
(function () {
    function togglePeriodo() {
        var periodos = document.getElementById('modo_periodos').checked;
        document.getElementById('panel-periodos').style.display = periodos ? '' : 'none';
        document.getElementById('panel-rango').style.display = periodos ? 'none' : '';
        var aviso = document.getElementById('aviso-fuente');
        if (aviso) {
            aviso.textContent = periodos
                ? 'Fuente rápida: saldos mensuales. Si excluye cierre/inflación, se restan esos asientos del agregado.'
                : 'Fuente detallada: asientos del rango de fechas.';
        }
    }
    document.querySelectorAll('input[name="modo_periodo"]').forEach(function (el) {
        el.addEventListener('change', togglePeriodo);
    });
    togglePeriodo();

    var form = document.getElementById('form-sumas-saldos');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (! form.checkValidity()) {
                return;
            }
            var overlay = document.getElementById('sumas-saldos-overlay');
            if (overlay) {
                overlay.classList.remove('d-none');
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
            }
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
            }
        });
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('sumas-saldos-overlay');
        if (! overlay) return;
        var tit = document.getElementById('sumas-saldos-titulo');
        var sub = document.getElementById('sumas-saldos-subtitulo');
        if (tit && titulo) {
            tit.textContent = titulo;
        }
        if (sub && subtitulo) {
            sub.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('sumas-saldos-overlay');
        if (! overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
        var tit = document.getElementById('sumas-saldos-titulo');
        var sub = document.getElementById('sumas-saldos-subtitulo');
        if (tit) {
            tit.textContent = 'Calculando balance…';
        }
        if (sub) {
            sub.textContent = 'Puede demorar según el período y el modo elegido. No cierre la página.';
        }
    }
    window.addEventListener('pageshow', ocultarOverlay);

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
        a.download = filename || 'sumas_saldos';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function descargarExportacionSumasSaldos(href) {
        var lower = String(href).toLowerCase();
        var formato = 'archivo';
        if (lower.indexOf('/excel') !== -1) {
            formato = 'Excel';
        } else if (lower.indexOf('/pdf') !== -1) {
            formato = 'PDF';
        } else if (lower.indexOf('/csv') !== -1) {
            formato = 'CSV';
        }

        mostrarOverlay(
            'Exportando…',
            'Generando ' + formato + '… Puede tardar según el volumen. No cierre la página.'
        );

        if (window.__sumasSaldosExportAbort) {
            try { window.__sumasSaldosExportAbort.abort(); } catch (e) {}
        }
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        window.__sumasSaldosExportAbort = controller;

        if (window.__sumasSaldosExportSafetyTimer) {
            clearTimeout(window.__sumasSaldosExportSafetyTimer);
        }
        window.__sumasSaldosExportSafetyTimer = setTimeout(function () {
            ocultarOverlay();
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
            if (res.redirected && res.url && res.url.indexOf('listar-sumas-saldos') === -1) {
                throw new Error('No se pudo generar la exportación. Verifique los filtros y vuelva a consultar.');
            }
            if (! res.ok) {
                throw new Error('Error HTTP ' + res.status + ' al exportar.');
            }
            var fallback = 'sumas_saldos';
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
            if (pack.blob.type && pack.blob.type.indexOf('text/html') !== -1) {
                throw new Error('La sesión o el permiso fallaron al exportar. Recargue e intente de nuevo.');
            }
            dispararDescargaBlob(pack.blob, pack.filename);
            ocultarOverlay();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                ocultarOverlay();
                return;
            }
            ocultarOverlay();
            window.alert(err && err.message ? err.message : 'No se pudo descargar la exportación.');
        }).finally(function () {
            if (window.__sumasSaldosExportSafetyTimer) {
                clearTimeout(window.__sumasSaldosExportSafetyTimer);
                window.__sumasSaldosExportSafetyTimer = null;
            }
            window.__sumasSaldosExportAbort = null;
        });
    }

    // Export PDF/Excel/CSV: descarga sin navegar → ocultar banner al terminar (como mayor concepto).
    document.addEventListener('click', function (event) {
        var enlace = event.target && event.target.closest
            ? event.target.closest('a[href*="listar-sumas-saldos"]')
            : null;
        if (! enlace) {
            return;
        }
        var href = enlace.getAttribute('href') || enlace.href || '';
        if (! href) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        descargarExportacionSumasSaldos(href);
    }, true);
})();
</script>
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/sumas_saldos/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/sumas_saldos/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'sumas-saldos-overlay',
    'tituloId' => 'sumas-saldos-titulo',
    'subtituloId' => 'sumas-saldos-subtitulo',
    'titulo' => 'Calculando balance…',
    'subtitulo' => 'Puede demorar según el período y el modo elegido. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Balance de sumas y saldos</h3>
                <div class="card-tools">
                    <a href="{{ route('sumas_saldos') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('sumas_saldos') }}" id="form-sumas-saldos" class="mb-0" autocomplete="off">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Informe estilo Anita (l-sumsal) leído solo de AnitaERP.
                        <span id="aviso-fuente"></span>
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'sumas_saldos',
                        'id_prefix' => 'sys',
                        'col_label' => 'col-lg-2 text-right',
                    ])

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right requerido">Modo</label>
                        <div class="col-lg-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_periodos" value="periodos"
                                    {{ ($filtros['modo_periodo'] ?? 'periodos') === 'periodos' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_periodos">Por períodos (saldos mensuales)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_rango" value="rango"
                                    {{ ($filtros['modo_periodo'] ?? '') === 'rango' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_rango">Por rango de fechas (asientos)</label>
                            </div>
                        </div>
                    </div>

                    <div id="panel-periodos" class="form-group row">
                        <label class="col-lg-2 control-label text-right requerido">Desde / Hasta período</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-2">
                                    <select name="mes_desde" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes_desde'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="anio_desde" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio_desde'] ?? $anio_actual }}">
                                </div>
                                <div class="col-md-1 text-center pt-2">a</div>
                                <div class="col-md-2">
                                    <select name="mes_hasta" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes_hasta'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="anio_hasta" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio_hasta'] ?? $anio_actual }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-rango" class="form-group row" style="display:none;">
                        <label class="col-lg-2 control-label text-right requerido">Desde / Hasta fecha</label>
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
                        <label for="moneda_id" class="col-lg-2 control-label text-right requerido">Expresar en</label>
                        <div class="col-lg-4">
                            <select name="moneda_id" id="moneda_id" class="form-control" required>
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-muted small mb-0 mt-1">
                                En pesos: cada movimiento extranjero se convierte con la cotización de su asiento.
                                En moneda extranjera sin “solo origen”: se reexpresa vía pesos (cotiz. del asiento + TC del día del asiento).
                            </p>
                        </div>
                    </div>

                    @include('contable.sumas_saldos.partials.campo_rango_cuentas', [
                        'cuenta_desde_meta' => $cuenta_desde_meta ?? ['codigo' => '', 'nombre' => ''],
                        'cuenta_hasta_meta' => $cuenta_hasta_meta ?? ['codigo' => '', 'nombre' => ''],
                    ])

                    <div class="form-group row" id="bloque-inclusion-asientos">
                        <label for="modo_inclusion_asientos" class="col-lg-2 control-label text-right">Asientos cierre</label>
                        <div class="col-lg-4">
                            @php $modoInclusionSel = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'); @endphp
                            <select name="modo_inclusion_asientos" id="modo_inclusion_asientos" class="form-control" autocomplete="off">
                                <option value="sin_cierre_ni_inflacion" @selected($modoInclusionSel === 'sin_cierre_ni_inflacion')>
                                    Excluir cierre e inflación
                                </option>
                                <option value="sin_cierre" @selected($modoInclusionSel === 'sin_cierre')>Excluir solo cierre</option>
                                <option value="sin_inflacion" @selected($modoInclusionSel === 'sin_inflacion')>Excluir solo inflación</option>
                                <option value="todos" @selected($modoInclusionSel === 'todos')>Incluir todos</option>
                            </select>
                            <p class="text-muted small mb-0 mt-1">
                                En modo períodos: parte de saldos mensuales y resta los asientos excluidos
                                (leídos de asiento / asiento_movimiento).
                            </p>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label text-right">Opciones</label>
                        <div class="col-lg-9">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">
                                    Solo movimientos en moneda origen (sin convertir; submayor nativo)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filtro_cuentas" id="filtro_con_mov" value="con_movimiento"
                                    {{ ($filtros['filtro_cuentas'] ?? 'con_movimiento') === 'con_movimiento' ? 'checked' : '' }}>
                                <label class="form-check-label" for="filtro_con_mov">Solo cuentas con débitos/créditos en el período</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filtro_cuentas" id="filtro_todas" value="todas"
                                    {{ ($filtros['filtro_cuentas'] ?? '') === 'todas' ? 'checked' : '' }}>
                                <label class="form-check-label" for="filtro_todas">Todas las cuentas imputables del rango</label>
                            </div>
                        </div>
                    </div>

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
                    @if (! empty($advertencias))
                        <div class="alert alert-warning mx-3 mt-3 mb-0">
                            <ul class="mb-0">
                                @foreach ($advertencias as $adv)
                                    <li>{{ $adv }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-1 small">
                            <strong>Empresas:</strong> {{ $empresas_texto ?? '' }}
                            · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Expresado en:</strong> {{ $moneda->nombre ?? '' }} ({{ $moneda->abreviatura ?? '' }})
                            @if (! empty($fuente))
                                · <strong>Fuente:</strong> {{ $fuente === 'saldos_mes' ? 'saldos mensuales' : 'asientos' }}
                            @endif
                        </p>
                        <p class="mb-0 small text-muted">{{ $inclusion_asientos_texto ?? '' }}</p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_sumas_saldos',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        <div class="small mb-1 mb-md-0 text-md-right">
                            <span class="text-muted">Totales filtro:</span>
                            <strong>{{ (int) ($tot['cuentas'] ?? 0) }}</strong> cuentas
                            · Débitos <strong>{{ number_format((float) ($tot['debe'] ?? 0), 2, ',', '.') }}</strong>
                            · Créditos <strong>{{ number_format((float) ($tot['haber'] ?? 0), 2, ',', '.') }}</strong>
                            · Saldo per. <strong>{{ number_format((float) ($tot['saldo_periodo'] ?? 0), 2, ',', '.') }}</strong>
                            · Mes ant. <strong>{{ number_format((float) ($tot['saldo_mes_anterior'] ?? 0), 2, ',', '.') }}</strong>
                            · Ejercicio <strong>{{ number_format((float) ($tot['saldo_ejercicio'] ?? 0), 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                            <style>
                                #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
                            </style>
                            @include('contable.sumas_saldos.partials.tabla_datos', [
                                'filas' => $filas,
                                'totales' => $tot,
                                'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                                'multiempresa' => $multiempresa ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="px-3 py-2 d-flex justify-content-between align-items-center flex-wrap">
                            <div class="small text-muted">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                                @endif
                            </div>
                            <div>{{ $filas->links() }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.contable.modalconsultacuentacontable')
@endsection
