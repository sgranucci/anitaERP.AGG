@extends("theme.$theme.layout")
@section('titulo')
    Auditoría de sesiones
@endsection

@section('styles')
<style>
    .auditoria-hero {
        background: #1B4F72;
        color: #fff;
        border-radius: 4px;
        padding: 1.1rem 1.25rem;
        margin-bottom: 1rem;
    }
    .auditoria-hero h3 {
        margin: 0 0 .35rem;
        font-size: 1.25rem;
        font-weight: 600;
    }
    .auditoria-hero p {
        margin: 0;
        opacity: .9;
        font-size: .92rem;
    }
    .auditoria-kpi {
        border: 1px solid #d5d8dc;
        border-radius: 4px;
        background: #fff;
        padding: .85rem 1rem;
        height: 100%;
    }
    .auditoria-kpi .kpi-label {
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #5d6d7e;
        margin-bottom: .25rem;
    }
    .auditoria-kpi .kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #1B4F72;
        line-height: 1.2;
    }
    .auditoria-kpi .kpi-hint {
        font-size: .78rem;
        color: #7f8c8d;
        margin-top: .2rem;
    }
    .auditoria-status-on {
        display: inline-block;
        background: #1e8449;
        color: #fff;
        padding: .15rem .55rem;
        border-radius: 3px;
        font-size: .78rem;
        font-weight: 600;
    }
    .auditoria-status-off {
        display: inline-block;
        background: #922b21;
        color: #fff;
        padding: .15rem .55rem;
        border-radius: 3px;
        font-size: .78rem;
        font-weight: 600;
    }
    .auditoria-log-box {
        background: #17202A;
        color: #D5D8DC;
        font-family: Consolas, Monaco, monospace;
        font-size: 12px;
        line-height: 1.45;
        padding: 1rem;
        border-radius: 4px;
        max-height: 520px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .auditoria-tabla thead th {
        background: #85C1E9;
        color: #17202A;
        border-color: #5dade2;
        font-size: .85rem;
        white-space: nowrap;
    }
    .auditoria-tabla tbody tr:nth-child(even) {
        background: #f5f5f5;
    }
    .badge-tipo-login { background: #1e8449; }
    .badge-tipo-logout { background: #922b21; }
    .badge-tipo-navegacion { background: #2471A3; }
    .auditoria-fav-chips { display: flex; flex-wrap: wrap; gap: .4rem; min-height: 2rem; }
    .auditoria-fav-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .25rem .65rem; border-radius: 3px;
        background: #f8f9f9; border: 1px solid #d5d8dc; color: #1B4F72;
        font-size: .85rem; text-decoration: none !important;
    }
    .auditoria-fav-chip:hover { background: #D6EAF8; border-color: #85C1E9; }
    .auditoria-fav-chip.is-active { background: #D6EAF8; border-color: #2471A3; font-weight: 600; }
    .auditoria-fav-chip .fa-thumbtack { color: #b0b0b0; font-size: .8rem; transform: rotate(45deg); }
    .auditoria-pin-btn {
        color: #95a5a6; border-color: #ced4da;
    }
    .auditoria-pin-btn .fa-thumbtack { transform: rotate(45deg); }
    .auditoria-pin-btn.is-pinned,
    .auditoria-pin-btn.is-pinned:hover {
        color: #1B4F72; background: #FCF3CF; border-color: #f6c453;
    }
    .auditoria-pin-btn.is-pinned .fa-thumbtack { color: #f6c453; }
</style>
@endsection

@section('contenido')
    @php
    $pestana = $filtros['pestana'] ?? 'navegacion';
    $tabla = $disco['tabla'] ?? [];
    $tablaAudits = $disco['tabla_audits'] ?? [];
    $procFiltro = $disco['proceso_filtro'] ?? [];
    $procGlobal = $disco['proceso_global'] ?? [];
    $archivosResumen = $disco['archivos_log'] ?? [];
    $habilitada = (bool) ($disco['bitacora_habilitada'] ?? false);
    $auditingOn = (bool) ($disco['auditing_habilitado'] ?? true);
@endphp

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="auditoria-hero">
            <div class="d-flex flex-wrap align-items-start justify-content-between">
                <div>
                    <h3>Auditoría de sesiones y logs</h3>
                    <p>
                        Tres capas: navegación de usuarios, cambios de datos de cualquier tabla (auditoría Eloquent)
                        y logs de archivo del servidor. La bitácora de navegación corre en background; no usa la cola de jobs.
                    </p>
                </div>
                <div class="mt-1">
                    @if ($habilitada)
                        <span class="auditoria-status-on">Bitácora activa</span>
                    @else
                        <span class="auditoria-status-off">Bitácora desconectada</span>
                    @endif
                </div>
            </div>
            <p class="mt-2 mb-0" style="opacity:.85;font-size:.82rem;">
                Kill-switch:
                <code style="color:#F9E79F;">BITACORA_ACCESO_HABILITADO=false</code>
                + <code style="color:#F9E79F;">php artisan config:clear</code>
                · Retención: {{ $disco['retencion_meses'] ?? 12 }} meses
                · Medido: {{ $disco['generado_en'] ?? '' }}
            </p>
        </div>

        {{-- Uso de disco (siempre visible al consultar el panel) --}}
        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2">
                <h3 class="card-title mb-0" style="font-size:1rem;">
                    <i class="fa fa-hdd"></i> Uso de disco y proceso
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="row">
                    <div class="col-md-3 col-6 mb-2">
                        <div class="auditoria-kpi">
                            <div class="kpi-label">Tabla bitácora (navegación)</div>
                            <div class="kpi-value">{{ $tabla['total_humano'] ?? '0 B' }}</div>
                            <div class="kpi-hint">
                                ~{{ number_format((int) ($tabla['filas'] ?? 0), 0, ',', '.') }} filas
                                · datos {{ $tabla['data_humano'] ?? '0 B' }}
                                · índices {{ $tabla['index_humano'] ?? '0 B' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="auditoria-kpi">
                            <div class="kpi-label">Tabla audits (cambios datos)</div>
                            <div class="kpi-value">{{ $tablaAudits['total_humano'] ?? '0 B' }}</div>
                            <div class="kpi-hint">
                                ~{{ number_format((int) ($tablaAudits['filas'] ?? 0), 0, ',', '.') }} filas
                                ·
                                @if ($auditingOn)
                                    grabación activa
                                @else
                                    grabación off
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="auditoria-kpi">
                            <div class="kpi-label">Logs de archivo</div>
                            <div class="kpi-value">{{ $archivosResumen['total_humano'] ?? '0 B' }}</div>
                            <div class="kpi-hint">
                                {{ (int) ($archivosResumen['cantidad'] ?? 0) }} archivo(s) en storage/logs
                                · canal {{ $archivosResumen['canal_default'] ?? '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <div class="auditoria-kpi">
                            <div class="kpi-label">Proceso navegación (filtro)</div>
                            <div class="kpi-value">
                                @if (($procFiltro['duracion_promedio_ms'] ?? null) !== null)
                                    {{ number_format($procFiltro['duracion_promedio_ms'], 0, ',', '.') }} ms
                                @else
                                    —
                                @endif
                            </div>
                            <div class="kpi-hint">
                                Avg request · mem
                                @if (($procFiltro['memoria_promedio_kb'] ?? null) !== null)
                                    {{ number_format($procFiltro['memoria_promedio_kb'] / 1024, 1, ',', '.') }} MB
                                @else
                                    —
                                @endif
                                · {{ number_format((int) ($procFiltro['total'] ?? 0), 0, ',', '.') }} eventos
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    @if ($pestana === 'navegacion')
                        La memoria pico es la del proceso PHP al atender cada request (no el disco). Global histórico
                        (aprox. o cache):
                        {{ number_format((int) ($procGlobal['total'] ?? 0), 0, ',', '.') }} eventos ·
                        avg
                        @if (($procGlobal['duracion_promedio_ms'] ?? null) !== null)
                            {{ number_format($procGlobal['duracion_promedio_ms'], 0, ',', '.') }} ms
                        @else
                            —
                        @endif
                        · tabla acumulada {{ $tabla['total_humano'] ?? '0 B' }}.
                    @else
                        Las métricas de proceso de navegación se calculan solo en la pestaña Navegación
                        (evitan escanear millones de filas al abrir logs o cambios de datos).
                    @endif
                </p>
            </div>
        </div>

        @include('includes.tabs-activas-estilos')
        <div class="card card-primary card-outline">
            <div class="card-header border-bottom-0 pb-0">
                <div class="tabs-activas">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $pestana === 'navegacion' ? 'active' : '' }}"
                               href="{{ route('auditoria_sesion', array_merge($filtrosQuery, ['pestana' => 'navegacion'])) }}">
                                <i class="fa fa-list"></i> Navegación
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $pestana === 'datos' ? 'active' : '' }}"
                               href="{{ route('auditoria_sesion', array_merge($filtrosQuery, ['pestana' => 'datos'])) }}">
                                <i class="fa fa-database"></i> Cambios de datos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $pestana === 'archivos' ? 'active' : '' }}"
                               href="{{ route('auditoria_sesion', array_merge($filtrosQuery, ['pestana' => 'archivos'])) }}">
                                <i class="fa fa-file-alt"></i> Logs de archivo
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                @if ($pestana === 'navegacion')
                    @include('configuracion.auditoria_sesion.partials.panel_navegacion')
                @elseif ($pestana === 'datos')
                    @include('configuracion.auditoria_sesion.partials.panel_datos')
                @else
                    @include('configuracion.auditoria_sesion.partials.panel_archivos')
                @endif
            </div>
        </div>
        @if (in_array($pestana, ['navegacion', 'datos'], true))
            @include('includes.admin.modalconsultausuario')
        @endif
    </div>
</div>
@endsection

@section('scripts')
@if (in_array(($filtros['pestana'] ?? ''), ['navegacion', 'datos'], true))
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/usuario/consulta.js')) }}"></script>
<script>
$(function () {
    if (typeof activa_eventos_consultausuario === 'function') {
        activa_eventos_consultausuario();
    }
});
</script>
@endif
@if (($filtros['pestana'] ?? '') === 'datos')
<script src="{{ asset('assets/pages/scripts/configuracion/auditoria_sesion/favoritos.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/auditoria_sesion/buscar_registro.js') }}"></script>
@endif
@endsection
