@extends("theme.$theme.layout")
@section('titulo')
    Posición financiera
@endsection

@section('styles')
<style>
    .posfin-wrap { overflow-x: auto; }
    .posfin-tabla { font-size: 12px; white-space: nowrap; }
    .posfin-tabla th.posfin-concepto,
    .posfin-tabla td.posfin-concepto {
        position: sticky;
        left: 0;
        z-index: 2;
        min-width: 200px;
        background: #fff;
    }
    .posfin-tabla thead th.posfin-concepto {
        z-index: 3;
        background: #85C1E9;
    }
    .posfin-tabla th.posfin-dia,
    .posfin-tabla td.posfin-dia,
    .posfin-tabla th.posfin-total-col,
    .posfin-tabla td.posfin-total-col {
        min-width: 72px;
    }
    .posfin-tabla tr.posfin-titulo td {
        background: #D6EAF8;
        color: #1B4F72;
        font-weight: 600;
    }
    .posfin-tabla tr.posfin-total td.posfin-concepto {
        background: #f5f5f5;
    }
    .posfin-tabla tr.posfin-informativo td,
    .posfin-tabla tr.posfin-informativo td.posfin-concepto {
        background: #FFF3CD !important;
        color: #664D03;
    }
    .posfin-informativo-aviso {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .posfin-auditoria-link {
        color: inherit;
        text-decoration: none;
        border-bottom: 1px dotted #7FB3D5;
        padding: 1px 2px;
        border-radius: 3px;
        transition: background-color .12s ease-in-out, color .12s ease-in-out;
    }
    .posfin-auditoria-link:hover,
    .posfin-auditoria-link:focus {
        color: #154360;
        background: #D6EAF8;
        border-bottom-color: #154360;
        text-decoration: none;
    }
    .posfin-tabla tr.posfin-informativo .posfin-auditoria-link:hover {
        color: #664D03;
        background: #FDEBD0;
    }
</style>
@endsection

@section('scripts')
<script>
(function () {
    var OVERLAY_ID = 'posfin-procesando-overlay';
    var TITULO_ID = 'posfin-procesando-titulo';
    var SUBTITULO_ID = 'posfin-procesando-subtitulo';

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay) {
            return;
        }
        var tituloEl = document.getElementById(TITULO_ID);
        var subtituloEl = document.getElementById(SUBTITULO_ID);
        if (tituloEl && titulo) {
            tituloEl.textContent = titulo;
        }
        if (subtituloEl && subtitulo) {
            subtituloEl.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    var form = document.getElementById('form-posicion-financiera');
    if (form) {
        form.addEventListener('submit', function () {
            if (! form.checkValidity()) {
                return;
            }
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
            }
            mostrarOverlay('Calculando posición financiera…', 'Puede demorar según el período. No cierre la página.');
        });
    }

    /**
     * Export PDF/Excel/CSV: la descarga no navega. Fetch + blob para ocultar
     * el banner ni bien termina (mismo patrón que mayor-concepto / sumas-saldos).
     */
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
        a.download = filename || 'posicion_financiera';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1500);
    }

    function descargarExportacionPosfin(href) {
        var lower = String(href).toLowerCase();
        var formato = 'archivo';
        if (lower.indexOf('/excel') !== -1) {
            formato = 'Excel';
        } else if (lower.indexOf('/pdf') !== -1) {
            formato = 'PDF';
        } else if (lower.indexOf('/csv') !== -1) {
            formato = 'CSV';
        }

        mostrarOverlay('Generando exportación…', 'Generando ' + formato + '… Puede tardar según el volumen. No cierre la página.');

        if (window.__posfinExportAbort) {
            try { window.__posfinExportAbort.abort(); } catch (e) {}
        }
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        window.__posfinExportAbort = controller;

        if (window.__posfinExportHideTimer) {
            clearTimeout(window.__posfinExportHideTimer);
        }
        window.__posfinExportHideTimer = setTimeout(function () {
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
            if (res.redirected && res.url && res.url.indexOf('listar-posicion-financiera') === -1) {
                throw new Error('No se pudo generar la exportación. Verifique los filtros y vuelva a consultar.');
            }
            if (! res.ok) {
                throw new Error('Error HTTP ' + res.status + ' al exportar.');
            }
            var fallback = 'posicion_financiera';
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
            if (window.__posfinExportHideTimer) {
                clearTimeout(window.__posfinExportHideTimer);
                window.__posfinExportHideTimer = null;
            }
            window.__posfinExportAbort = null;
        });
    }

    document.addEventListener('click', function (event) {
        var enlace = event.target && event.target.closest
            ? event.target.closest('a[href]')
            : null;
        if (! enlace || enlace.target === '_blank' || enlace.hasAttribute('download')) {
            return;
        }
        var href = enlace.href || enlace.getAttribute('href') || '';
        if (String(href).toLowerCase().indexOf('listar-posicion-financiera') === -1) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        descargarExportacionPosfin(href);
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            ocultarOverlay();
        }
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.posfin-auditoria-link');
        if (! link) {
            return;
        }
        event.preventDefault();

        var contenido = document.getElementById('posfin-auditoria-contenido');
        if (! contenido) {
            window.location.href = link.href;
            return;
        }

        contenido.innerHTML = '<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-info"></i><div class="mt-2">Buscando orígenes…</div></div>';
        $('#modal-posfin-auditoria').modal('show');

        fetch(link.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (! response.ok) {
                throw new Error('No se pudo cargar la auditoría.');
            }
            return response.text();
        }).then(function (html) {
            contenido.innerHTML = html;
        }).catch(function (error) {
            contenido.innerHTML = '<div class="alert alert-danger mb-0">' + error.message + '</div>';
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);
})();
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'posfin-procesando-overlay',
    'tituloId' => 'posfin-procesando-titulo',
    'subtituloId' => 'posfin-procesando-subtitulo',
    'titulo' => 'Calculando…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Posición financiera</h3>
                <div class="card-tools">
                    @include('includes.caja.boton-manual')
                    <a href="{{ route('posicion_financiera') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('posicion_financiera') }}" id="form-posicion-financiera" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Posición financiera del mes con una columna por día, cortada por unidad de negocio
                        (bingo, gastronomía, estacionamiento, vending y máquinas), más apertura de medios y egresos.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label text-right pr-2 requerido">Mes / Año</label>
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
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary" id="btn-consultar">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
            </form>
        </div>

        @if ($consultado)
            @if (! empty($errores_bridge))
                <div class="alert alert-warning">
                    <strong>Avisos del bridge Anita:</strong>
                    <ul class="mb-0">
                        @foreach ($errores_bridge as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">
                        Resultado
                        @if (($periodo_texto ?? '') !== '')
                            <small class="text-muted">({{ $periodo_texto }}{{ $empresa ? ' — '.$empresa->nombre : '' }})</small>
                        @endif
                    </h3>
                    <div class="card-tools">
                        @php
                            $periodoFinalizado = \Carbon\Carbon::createFromDate(
                                (int) ($filtros['anio'] ?? 0),
                                (int) ($filtros['mes'] ?? 0),
                                1
                            )->endOfMonth()->isBefore(\Carbon\Carbon::today());
                        @endphp
                        @if ($saldo_confirmado)
                            <span class="badge badge-success mr-2" title="Saldo final confirmado">
                                <i class="fa fa-lock"></i> Cierre confirmado
                            </span>
                            @if (can('anular-saldo-posicion-financiera', false))
                                <button type="button" class="btn btn-outline-danger btn-sm mr-2"
                                        data-toggle="modal" data-target="#modal-anular-saldo-posfin">
                                    <i class="fa fa-unlock"></i> Anular cierre
                                </button>
                            @endif
                        @elseif ($periodoFinalizado && can('confirmar-saldo-posicion-financiera', false))
                            <form method="post" action="{{ route('posicion_financiera_confirmar_saldo') }}" class="d-inline">
                                @csrf
                                @foreach (($filtrosQuery ?? []) as $clave => $valor)
                                    <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                                @endforeach
                                <button type="submit" class="btn btn-outline-success btn-sm mr-2"
                                        onclick="return confirm('¿Confirmar el saldo final de este período?');">
                                    <i class="fa fa-lock"></i> Confirmar saldo
                                </button>
                            </form>
                        @endif
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_posicion_financiera',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                </div>
                <div class="card-body table-responsive p-0 posfin-wrap">
                    @include('caja.posicion_financiera.partials.tabla_datos', [
                        'filas' => $filas,
                        'dias' => $dias ?? [],
                        'modo' => 'pantalla',
                        'auditoriaUrl' => route('posicion_financiera_auditoria'),
                        'auditoriaQuery' => $filtrosQuery ?? [],
                    ])
                </div>
                @if (count($filas) > 0)
                    <div class="card-footer clearfix small text-muted">
                        {{ count($filas) }} conceptos
                        @if ($saldo_inicial !== null)
                            · Saldo inicial {{ number_format((float) $saldo_inicial, 2, ',', '.') }}
                            ({{ ($saldo_inicial_origen ?? '') === 'erp' ? 'cierre ERP' : 'semilla Anita' }})
                        @endif
                        @if ($saldo_final !== null)
                            · Saldo final {{ number_format((float) $saldo_final, 2, ',', '.') }}
                        @endif
                        @if ($saldo_confirmado)
                            · Confirmado {{ optional($saldo_confirmado->confirmado_at)->format('d/m/Y H:i') }}
                            @if ($saldo_confirmado->confirmadoPor)
                                por {{ $saldo_confirmado->confirmadoPor->nombre }}
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            @if ($saldo_confirmado && can('anular-saldo-posicion-financiera', false))
                <div class="modal fade" id="modal-anular-saldo-posfin" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <form method="post" action="{{ route('posicion_financiera_anular_saldo', $saldo_confirmado->id) }}">
                                @csrf
                                @method('delete')
                                <div class="modal-header">
                                    <h5 class="modal-title">Anular cierre de posición financiera</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <label for="posfin-motivo-anulacion">Motivo</label>
                                    <textarea id="posfin-motivo-anulacion" name="motivo" class="form-control" required maxlength="255"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger">Anular cierre</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="modal fade" id="modal-posfin-auditoria" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-search-plus"></i> Auditoría del importe</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="posfin-auditoria-contenido"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
