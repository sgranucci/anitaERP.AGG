@extends("theme.$theme.layout")
@section('titulo')
    Mis aprobaciones
@endsection

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/mis-aprobaciones.css') }}?v={{ @filemtime(public_path('assets/css/mis-aprobaciones.css')) ?: time() }}">
@endsection

@section('contenido')
@php
    $mostrarFiltros = count($fuentesDisponibles ?? []) > 1
        || (($filtroFuente ?? '') === '' || ($filtroFuente ?? '') === 'arbol');
@endphp
<div class="anita-inbox">
    @include('includes.mensaje')

    <header class="anita-inbox-hero">
        <div class="anita-inbox-hero-glow" aria-hidden="true"></div>
        <div class="anita-inbox-hero-grid">
            <div class="anita-inbox-hero-main">
                <p class="anita-inbox-brand">Anita · Inbox</p>
                <h1 class="anita-inbox-title">Mis aprobaciones</h1>
                <p class="anita-inbox-sub">
                    Resolvé en un solo lugar lo que el correo solo te avisa.
                </p>
            </div>
            <div class="anita-inbox-stats" role="group" aria-label="Resumen de pendientes">
                <div class="anita-inbox-stat is-total">
                    <span class="anita-inbox-stat-value">{{ $totalPendientes }}</span>
                    <span class="anita-inbox-stat-label">En cola</span>
                </div>
                <div class="anita-inbox-stat{{ ($countUrgentes ?? 0) > 0 ? ' is-urgent' : '' }}">
                    <span class="anita-inbox-stat-value">{{ $countUrgentes ?? 0 }}</span>
                    <span class="anita-inbox-stat-label">Urgentes</span>
                </div>
                <div class="anita-inbox-stat{{ ($countAtencion ?? 0) > 0 ? ' is-warn' : '' }}">
                    <span class="anita-inbox-stat-value">{{ $countAtencion ?? 0 }}</span>
                    <span class="anita-inbox-stat-label">Atención</span>
                </div>
            </div>
        </div>
    </header>

    @if (($analytics['total'] ?? 0) > 0)
        @php
            $aging = $analytics['aging'] ?? ['fresco' => 0, 'medio' => 0, 'viejo' => 0];
            $agingTotal = max(1, (int) ($aging['fresco'] + $aging['medio'] + $aging['viejo']));
        @endphp
        <section class="anita-inbox-analytics" aria-label="Resumen de tu cola">
            <div class="anita-inbox-analytics-aging">
                <p class="anita-inbox-analytics-label">Antigüedad de tu cola</p>
                <div class="anita-inbox-aging-bar" role="img" aria-label="Distribución por antigüedad">
                    <span class="is-fresco" style="width: {{ round(100 * $aging['fresco'] / $agingTotal, 1) }}%"></span>
                    <span class="is-medio" style="width: {{ round(100 * $aging['medio'] / $agingTotal, 1) }}%"></span>
                    <span class="is-viejo" style="width: {{ round(100 * $aging['viejo'] / $agingTotal, 1) }}%"></span>
                </div>
                <ul class="anita-inbox-aging-legend">
                    <li><i class="is-fresco"></i> ≤1d · {{ $aging['fresco'] }}</li>
                    <li><i class="is-medio"></i> 2–4d · {{ $aging['medio'] }}</li>
                    <li><i class="is-viejo"></i> ≥5d · {{ $aging['viejo'] }}</li>
                </ul>
            </div>
            <div class="anita-inbox-analytics-side">
                @if (($analytics['monto_total'] ?? 0) > 0)
                    <p class="anita-inbox-analytics-metric">
                        <strong>{{ number_format($analytics['monto_total'], 2, ',', '.') }}</strong>
                        <span>Monto en cola</span>
                    </p>
                @endif
                @if (!empty($analytics['por_fuente']) && count($analytics['por_fuente']) > 1)
                    <p class="anita-inbox-analytics-fuentes">
                        @foreach ($analytics['por_fuente'] as $f)
                            <span>{{ $f['nombre'] }} · {{ $f['total'] }}</span>
                        @endforeach
                    </p>
                @endif
            </div>
        </section>
    @endif

    @if ($mostrarFiltros || (!empty($puedeLimpiarHuerfanos) && ($countHuerfanos ?? 0) > 0) || ($filtroQ ?? '') !== '' || ($filtroUrgencia ?? '') !== '' || !empty($filtroReemplazo) || ($filtroDiasMin ?? 0) > 0 || ($filtroMontoMin ?? 0) > 0)
    <div class="anita-inbox-toolbar">
        <div class="anita-inbox-filters">
            <button type="button" class="anita-inbox-filters-toggle js-filters-toggle" aria-expanded="false">
                <i class="fas fa-sliders-h" aria-hidden="true"></i> Filtros
            </button>
            @if (count($fuentesDisponibles ?? []) > 1)
                @php
                    $segmentosFuente = array_merge(
                        [['valor' => '', 'nombre' => 'Todas', 'corto' => 'Todas']],
                        array_map(function ($f) {
                            $corto = match ($f['valor'] ?? '') {
                                'arbol' => 'Árbol',
                                'indumentaria' => 'Indumentaria',
                                'salida_bienes' => 'Salida',
                                'asiento' => 'Asientos',
                                'transferencia' => 'Transferencias',
                                'ingreso_proveedor' => 'Ingresos',
                                default => $f['nombre'] ?? '',
                            };

                            return [
                                'valor' => $f['valor'],
                                'nombre' => $f['nombre'],
                                'corto' => $corto,
                            ];
                        }, $fuentesDisponibles)
                    );
                    $qsBaseExtra = [];
                    if (($filtroQ ?? '') !== '') {
                        $qsBaseExtra['q'] = $filtroQ;
                    }
                    if (($filtroUrgencia ?? '') !== '') {
                        $qsBaseExtra['urgencia'] = $filtroUrgencia;
                    }
                    if (!empty($filtroReemplazo)) {
                        $qsBaseExtra['reemplazo'] = '1';
                    }
                    if (($filtroDiasMin ?? 0) > 0) {
                        $qsBaseExtra['dias_min'] = (int) $filtroDiasMin;
                    }
                    if (($filtroMontoMin ?? 0) > 0) {
                        $qsBaseExtra['monto_min'] = $filtroMontoMin;
                    }
                @endphp
                <div class="anita-inbox-field anita-inbox-field--segments">
                    <span class="anita-inbox-field-label" id="filtro-fuente-label">Fuente</span>
                    <div class="anita-inbox-segments" role="tablist" aria-labelledby="filtro-fuente-label">
                        @foreach ($segmentosFuente as $seg)
                            @php
                                $activo = ($filtroFuente ?? '') === ($seg['valor'] ?? '');
                                $qs = $qsBaseExtra;
                                if (($seg['valor'] ?? '') !== '') {
                                    $qs['fuente'] = $seg['valor'];
                                }
                                if (
                                    ($filtroTipo ?? '') !== ''
                                    && (($seg['valor'] ?? '') === '' || ($seg['valor'] ?? '') === 'arbol')
                                ) {
                                    $qs['tipo'] = $filtroTipo;
                                }
                                $href = ($urlBandeja ?? url('mis-aprobaciones'));
                                if ($qs !== []) {
                                    $href .= '?'.http_build_query($qs);
                                }
                            @endphp
                            <a href="{{ $href }}"
                               class="anita-inbox-segment{{ $activo ? ' is-active' : '' }}"
                               role="tab"
                               aria-selected="{{ $activo ? 'true' : 'false' }}"
                               title="{{ $seg['nombre'] }}">
                                {{ $seg['corto'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="get" action="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-field anita-inbox-field--search" id="anita-inbox-search-form">
                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden', ['omitFiltro' => 'q'])
                <label for="filtro-q">Buscar</label>
                <input type="search"
                       id="filtro-q"
                       name="q"
                       value="{{ $filtroQ ?? '' }}"
                       placeholder="Nº, tipo, detalle, monto…"
                       autocomplete="off">
            </form>

            <div class="anita-inbox-filters-extra" id="anita-inbox-filters-extra">
            @if (($filtroFuente ?? '') === '' || ($filtroFuente ?? '') === 'arbol')
                <form method="get" action="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-field">
                    @include('configuracion.mis_aprobaciones_arbol._filtros_hidden', ['omitFiltro' => 'tipo'])
                    <label for="filtro-tipo">Tipo</label>
                    <select id="filtro-tipo" name="tipo" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach ($tiposArbol as $t)
                            <option value="{{ $t['valor'] }}" @if (($filtroTipo ?? '') === $t['valor']) selected @endif>
                                {{ $t['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form method="get" action="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-field">
                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden', ['omitFiltro' => 'urgencia'])
                <label for="filtro-urgencia">Urgencia</label>
                <select id="filtro-urgencia" name="urgencia" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <option value="urgente" @if (($filtroUrgencia ?? '') === 'urgente') selected @endif>Urgente</option>
                    <option value="atencion" @if (($filtroUrgencia ?? '') === 'atencion') selected @endif>Atención</option>
                    <option value="vencido" @if (($filtroUrgencia ?? '') === 'vencido') selected @endif>Vencido / SLA</option>
                    <option value="normal" @if (($filtroUrgencia ?? '') === 'normal') selected @endif>Normal</option>
                </select>
            </form>

            <form method="get" action="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-field">
                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden', ['omitFiltro' => 'dias_min'])
                <label for="filtro-dias">Antigüedad</label>
                <select id="filtro-dias" name="dias_min" onchange="this.form.submit()">
                    <option value="">Cualquiera</option>
                    <option value="2" @if ((int) ($filtroDiasMin ?? 0) === 2) selected @endif>≥ 2 días</option>
                    <option value="5" @if ((int) ($filtroDiasMin ?? 0) === 5) selected @endif>≥ 5 días</option>
                    <option value="7" @if ((int) ($filtroDiasMin ?? 0) === 7) selected @endif>≥ 7 días</option>
                </select>
            </form>

            <form method="get" action="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-field anita-inbox-field--monto">
                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden', ['omitFiltro' => 'monto_min'])
                <label for="filtro-monto">Monto mín.</label>
                <input type="number"
                       id="filtro-monto"
                       name="monto_min"
                       min="0"
                       step="1"
                       value="{{ ($filtroMontoMin ?? 0) > 0 ? $filtroMontoMin : '' }}"
                       placeholder="0"
                       onchange="this.form.submit()">
            </form>

            <form method="get" action="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-field anita-inbox-field--check">
                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden', ['omitFiltro' => 'reemplazo'])
                <label class="anita-inbox-check">
                    <input type="checkbox"
                           name="reemplazo"
                           value="1"
                           @if (!empty($filtroReemplazo)) checked @endif
                           onchange="this.form.submit()">
                    Solo reemplazo
                </label>
            </form>
            </div>

            @if (($filtroTipo ?? '') !== '' || ($filtroFuente ?? '') !== '' || ($filtroQ ?? '') !== '' || ($filtroUrgencia ?? '') !== '' || !empty($filtroReemplazo) || ($filtroDiasMin ?? 0) > 0 || ($filtroMontoMin ?? 0) > 0)
                <a href="{{ $urlBandeja ?? url('mis-aprobaciones') }}" class="anita-inbox-clear">Limpiar</a>
            @endif
        </div>
        @if (!empty($puedeLimpiarHuerfanos) && ($countHuerfanos ?? 0) > 0)
            <form method="post" action="{{ $urlLimpiarHuerfanos ?? url('mis-aprobaciones/limpiar-huerfanos') }}" class="anita-inbox-clean"
                  onsubmit="return confirm('¿Descartar {{ $countHuerfanos }} pendiente(s) cuyo comprobante ya no existe? Quedarán como Sin efecto.');">
                @csrf
                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden')
                <button type="submit" class="anita-inbox-btn anita-inbox-btn--warn">
                    Limpiar huérfanos · {{ $countHuerfanos }}
                </button>
            </form>
        @endif
    </div>
    @endif

    @if ($pendientes->isEmpty())
        <div class="anita-inbox-empty">
            <div class="anita-inbox-empty-mark" aria-hidden="true">
                <i class="fas fa-check"></i>
            </div>
            <h2>Inbox al día</h2>
            <p>
                No tenés nada pendiente
                @if (($filtroFuente ?? '') !== '' || ($filtroTipo ?? '') !== '' || ($filtroQ ?? '') !== '' || ($filtroUrgencia ?? '') !== '' || !empty($filtroReemplazo) || ($filtroDiasMin ?? 0) > 0 || ($filtroMontoMin ?? 0) > 0)
                    con este filtro
                @endif
                .
            </p>
        </div>
    @else
        <div class="anita-inbox-list" id="anita-inbox-list">
            @foreach ($pendientes as $idx => $p)
                @php
                    $urgencia = $p['urgencia'] ?? 'normal';
                    $urgenciaClass = match ($urgencia) {
                        'urgente' => 'is-urgent',
                        'atencion' => 'is-warn',
                        default => '',
                    };
                    $fuente = $p['fuente'] ?? 'arbol';
                    $fuenteClass = 'fuente-'.$fuente;
                    $dias = (int) ($p['dias_pendiente'] ?? 0);
                    $urgenciaLabel = match ($urgencia) {
                        'urgente' => 'Urgente',
                        'atencion' => 'Atención',
                        default => null,
                    };
                    $slaEstado = (string) ($p['sla_estado'] ?? '');
                    $slaTagClass = match ($slaEstado) {
                        'vencido' => 'anita-inbox-tag--sla-vencido',
                        'urgente' => 'anita-inbox-tag--sla-urgente',
                        'atencion' => 'anita-inbox-tag--sla-atencion',
                        'ok' => 'anita-inbox-tag--sla-ok',
                        default => '',
                    };
                    $bulkId = $fuente === 'arbol'
                        ? (int) ($p['movimiento_id'] ?? 0)
                        : (int) ($p['comprobante_id'] ?? 0);
                    $bulkable = ! empty($p['puede_aprobar'])
                        && ! empty($p['documento_existe'])
                        && empty($p['es_aviso_pago'])
                        && empty($p['es_reemplazo'])
                        && $bulkId > 0
                        && (
                            $fuente !== 'arbol'
                            || (float) ($p['monto'] ?? 0) <= (float) ($bulkMontoMaxArbol ?? 100000)
                        );
                @endphp
                <article class="anita-inbox-item {{ $urgenciaClass }} {{ $fuenteClass }}{{ empty($p['documento_existe']) ? ' is-orphan' : '' }}{{ !empty($p['es_reemplazo']) ? ' is-reemplazo' : '' }}"
                         style="--i: {{ $idx }}"
                         data-fuente="{{ $fuente }}"
                         data-tipo="{{ $p['tipo'] ?? '' }}">
                    <div class="anita-inbox-rail" aria-hidden="true"></div>

                    <div class="anita-inbox-select">
                        @if ($bulkable)
                            <input type="checkbox"
                                   class="js-bulk-check"
                                   value="{{ $bulkId }}"
                                   data-fuente="{{ $fuente }}"
                                   data-tipo="{{ $p['tipo'] ?? '' }}"
                                   data-monto="{{ (float) ($p['monto'] ?? 0) }}"
                                   aria-label="Seleccionar {{ $p['numero'] }}">
                        @endif
                    </div>

                    <div class="anita-inbox-item-type">
                        <span class="anita-inbox-chip">{{ $p['tipo'] }}</span>
                        <span class="anita-inbox-type-name">{{ $p['etiqueta_tipo'] }}</span>
                        @if ($fuente !== 'arbol')
                            <span class="anita-inbox-fuente">{{ $p['fuente_label'] ?? '' }}</span>
                        @endif
                    </div>

                    <div class="anita-inbox-item-body">
                        @if (!empty($p['reemplazo_de']))
                            <p class="anita-inbox-banner-repl">
                                <i class="fas fa-user-friends" aria-hidden="true"></i>
                                Actuás por {{ $p['reemplazo_de'] }}
                            </p>
                        @endif
                        <div class="anita-inbox-doc-row">
                            <div class="anita-inbox-doc">
                                <strong>
                                    @if (!empty($p['url_detalle']))
                                        <button type="button"
                                                class="anita-inbox-doc-link js-inbox-detalle"
                                                data-detalle-url="{{ $p['url_detalle'] }}">
                                            {{ $p['numero'] }}
                                        </button>
                                    @else
                                        {{ $p['numero'] }}
                                    @endif
                                </strong>
                                <span class="anita-inbox-ref">
                                    #{{ $p['comprobante_id'] }}
                                    @if ($fuente === 'arbol' || $fuente === 'indumentaria')
                                        · Nivel {{ $p['nivel'] }}
                                    @endif
                                </span>
                            </div>
                            @if (($p['monto'] ?? 0) > 0)
                                <div class="anita-inbox-amount">
                                    <span class="anita-inbox-amount-value">
                                        {{ number_format($p['monto'], 2, ',', '.') }}
                                    </span>
                                    @if (!empty($p['moneda_abrev']))
                                        <span class="anita-inbox-amount-cur">{{ $p['moneda_abrev'] }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="anita-inbox-meta">
                            @if (!empty($p['sla_label']))
                                <span class="anita-inbox-tag {{ $slaTagClass }}">{{ $p['sla_label'] }}</span>
                            @elseif ($urgenciaLabel)
                                <span class="anita-inbox-tag anita-inbox-tag--{{ $urgencia === 'urgente' ? 'urgent' : 'warn' }}">
                                    {{ $urgenciaLabel }}@if ($dias > 0) · {{ $dias }}d @endif
                                </span>
                            @elseif ($dias > 0)
                                <span class="anita-inbox-tag">{{ $dias }} día{{ $dias === 1 ? '' : 's' }}</span>
                            @endif
                            @if (!empty($p['subtitulo']))
                                <span class="anita-inbox-meta-text">{{ $p['subtitulo'] }}</span>
                            @endif
                            @if (!empty($p['fecha_envio']))
                                <span class="anita-inbox-meta-text">Desde {{ \Carbon\Carbon::parse($p['fecha_envio'])->format('d/m/Y H:i') }}</span>
                            @endif
                            @if (!empty($p['es_aviso_pago']))
                                <span class="anita-inbox-tag anita-inbox-tag--aviso">Aviso a pagadores</span>
                            @endif
                            @if (empty($p['documento_existe']))
                                <span class="anita-inbox-tag anita-inbox-tag--orphan">Documento inexistente</span>
                            @endif
                        </div>
                    </div>

                    <div class="anita-inbox-item-actions">
                        @if (!empty($p['url_detalle']))
                            <button type="button"
                                    class="anita-inbox-btn anita-inbox-btn--ghost js-inbox-detalle anita-inbox-btn--mobile-hide"
                                    data-detalle-url="{{ $p['url_detalle'] }}">
                                Detalle
                            </button>
                        @endif
                        @if (!empty($p['url_ver']))
                            <a href="{{ $p['url_ver'] }}"
                               class="anita-inbox-btn anita-inbox-btn--ghost anita-inbox-btn--mobile-hide"
                               @if ($fuente === 'arbol') target="_blank" rel="noopener" @endif>
                                Abrir
                            </a>
                        @endif

                        @if (!empty($p['puede_aprobar']) && !empty($p['url_aprobar']))
                            <form method="post" action="{{ $p['url_aprobar'] }}" class="anita-inbox-action-form" onsubmit="return confirm('¿Aprobar {{ $p['tipo'] }} {{ $p['numero'] }}?');">
                                @csrf
                                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden')
                                <button type="submit" class="anita-inbox-btn anita-inbox-btn--primary">
                                    Aprobar
                                </button>
                            </form>
                            @if (!empty($p['muestra_reenviar']) && !empty($p['url_reenviar']))
                                <form method="post" action="{{ $p['url_reenviar'] }}" class="anita-inbox-action-form anita-inbox-btn--mobile-hide" onsubmit="return confirm('¿Reenviar el mail de aprobación?');">
                                    @csrf
                                    @include('configuracion.mis_aprobaciones_arbol._filtros_hidden')
                                    <button type="submit" class="anita-inbox-btn anita-inbox-btn--icon" title="Reenviar correo" aria-label="Reenviar correo">
                                        <i class="fa fa-envelope"></i>
                                    </button>
                                </form>
                            @endif
                        @endif

                        @if (!empty($p['documento_existe']) && !empty($p['url_rechazar']))
                            <form method="post" action="{{ $p['url_rechazar'] }}" class="anita-inbox-action-form js-rechazar-form">
                                @csrf
                                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden')
                                <input type="hidden" name="observacion" value="" class="js-rechazar-obs">
                                <button type="submit" class="anita-inbox-btn anita-inbox-btn--danger">
                                    Rechazar
                                </button>
                            </form>
                        @elseif (!empty($p['muestra_descartar']) && !empty($p['url_descartar']))
                            <form method="post" action="{{ $p['url_descartar'] }}" class="anita-inbox-action-form"
                                  onsubmit="return confirm('¿Descartar este pendiente huérfano?');">
                                @csrf
                                @include('configuracion.mis_aprobaciones_arbol._filtros_hidden')
                                <button type="submit" class="anita-inbox-btn anita-inbox-btn--warn">
                                    Descartar
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="anita-inbox-bulkbar" id="anita-inbox-bulkbar" aria-live="polite">
            <p class="anita-inbox-bulkbar-count"><span id="anita-bulk-count">0</span> seleccionados</p>
            <div class="anita-inbox-bulkbar-actions">
                <button type="button" class="anita-inbox-btn anita-inbox-btn--ghost js-bulk-clear">Limpiar</button>
                <button type="button" class="anita-inbox-btn anita-inbox-btn--primary js-bulk-aprobar">Aprobar seleccionados</button>
            </div>
        </div>
        <form method="post" action="{{ $urlBulkAprobar ?? url('mis-aprobaciones/bulk-aprobar') }}" id="anita-bulk-form" hidden>
            @csrf
            @include('configuracion.mis_aprobaciones_arbol._filtros_hidden')
            <input type="hidden" name="fuente" id="anita-bulk-fuente" value="">
            <input type="hidden" name="tipo" id="anita-bulk-tipo" value="">
            <div id="anita-bulk-ids"></div>
        </form>
    @endif
</div>

<div class="anita-inbox-drawer" id="anita-inbox-drawer" hidden>
    <div class="anita-inbox-drawer-backdrop js-inbox-drawer-close" tabindex="-1"></div>
    <aside class="anita-inbox-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="anita-inbox-drawer-title">
        <header class="anita-inbox-drawer-head">
            <div>
                <p class="anita-inbox-drawer-kicker" id="anita-inbox-drawer-fuente">Detalle</p>
                <h2 class="anita-inbox-drawer-title" id="anita-inbox-drawer-title">Pendiente</h2>
            </div>
            <button type="button" class="anita-inbox-btn anita-inbox-btn--icon js-inbox-drawer-close" aria-label="Cerrar">
                <i class="fa fa-times"></i>
            </button>
        </header>
        <div class="anita-inbox-drawer-body" id="anita-inbox-drawer-body">
            <p class="anita-inbox-drawer-loading">Cargando…</p>
        </div>
        <footer class="anita-inbox-drawer-foot" id="anita-inbox-drawer-foot"></footer>
    </aside>
</div>

<div class="anita-inbox-modal" id="anita-rechazo-modal" hidden>
    <div class="anita-inbox-modal-backdrop js-rechazo-cancel" tabindex="-1"></div>
    <div class="anita-inbox-modal-panel" role="dialog" aria-modal="true" aria-labelledby="anita-rechazo-title">
        <h3 id="anita-rechazo-title">Motivo del rechazo</h3>
        <p>Indicá por qué rechazás este pendiente. Queda en la auditoría.</p>
        <textarea id="anita-rechazo-texto" maxlength="4000" placeholder="Motivo…"></textarea>
        <div class="anita-inbox-modal-actions">
            <button type="button" class="anita-inbox-btn anita-inbox-btn--ghost js-rechazo-cancel">Cancelar</button>
            <button type="button" class="anita-inbox-btn anita-inbox-btn--danger js-rechazo-ok">Confirmar rechazo</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var bulkMax = {{ (int) ($bulkMax ?? 20) }};
    var rechazoModal = document.getElementById('anita-rechazo-modal');
    var rechazoTexto = document.getElementById('anita-rechazo-texto');
    var rechazoPendingForm = null;

    function abrirRechazoModal(form) {
        rechazoPendingForm = form;
        if (rechazoTexto) rechazoTexto.value = '';
        if (rechazoModal) {
            rechazoModal.hidden = false;
            if (rechazoTexto) rechazoTexto.focus();
        }
    }
    function cerrarRechazoModal() {
        rechazoPendingForm = null;
        if (rechazoModal) rechazoModal.hidden = true;
    }
    function bindRechazoForms(root) {
        (root || document).querySelectorAll('.js-rechazar-form, .js-drawer-rechazar').forEach(function (form) {
            if (form.dataset.rechazoBound) return;
            form.dataset.rechazoBound = '1';
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                abrirRechazoModal(form);
            });
        });
    }
    if (rechazoModal) {
        rechazoModal.querySelectorAll('.js-rechazo-cancel').forEach(function (el) {
            el.addEventListener('click', cerrarRechazoModal);
        });
        var okBtn = rechazoModal.querySelector('.js-rechazo-ok');
        if (okBtn) {
            okBtn.addEventListener('click', function () {
                var motivo = (rechazoTexto && rechazoTexto.value || '').trim();
                if (!motivo) { alert('Indicá el motivo del rechazo.'); return; }
                if (!rechazoPendingForm) return;
                var input = rechazoPendingForm.querySelector('.js-rechazar-obs');
                if (input) input.value = motivo;
                var form = rechazoPendingForm;
                cerrarRechazoModal();
                form.submit();
            });
        }
    }
    bindRechazoForms(document);

    var toggle = document.querySelector('.js-filters-toggle');
    var extra = document.getElementById('anita-inbox-filters-extra');
    if (toggle && extra) {
        toggle.addEventListener('click', function () {
            var open = extra.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        if (window.matchMedia('(min-width: 641px)').matches) {
            extra.classList.add('is-open');
        }
    }

    var drawer = document.getElementById('anita-inbox-drawer');
    var body = document.getElementById('anita-inbox-drawer-body');
    var foot = document.getElementById('anita-inbox-drawer-foot');
    var title = document.getElementById('anita-inbox-drawer-title');
    var fuenteEl = document.getElementById('anita-inbox-drawer-fuente');
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    if (drawer) {
        function cerrarDrawer() {
            drawer.hidden = true;
            document.body.classList.remove('anita-inbox-drawer-open');
        }
        function abrirDrawer(url) {
            drawer.hidden = false;
            document.body.classList.add('anita-inbox-drawer-open');
            body.innerHTML = '<p class="anita-inbox-drawer-loading">Cargando…</p>';
            foot.innerHTML = '';
            title.textContent = 'Pendiente';
            fuenteEl.textContent = 'Detalle';
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j.ok) {
                        body.innerHTML = '<p class="anita-inbox-drawer-error">' + esc((res.j && res.j.error) || 'No se pudo cargar el detalle.') + '</p>';
                        return;
                    }
                    var d = res.j.detalle || {};
                    var item = d.item || {};
                    title.textContent = item.numero || 'Pendiente';
                    fuenteEl.textContent = item.fuente_label || item.fuente || 'Detalle';
                    var html = '';
                    if (d.banner_reemplazo) {
                        html += '<p class="anita-inbox-banner-repl anita-inbox-drawer-banner"><i class="fas fa-user-friends" aria-hidden="true"></i> ' + esc(d.banner_reemplazo) + '</p>';
                    }
                    if (d.sla_label) {
                        var slaClass = 'anita-inbox-tag';
                        if (d.sla_estado === 'vencido') slaClass += ' anita-inbox-tag--sla-vencido';
                        else if (d.sla_estado === 'urgente') slaClass += ' anita-inbox-tag--sla-urgente';
                        else if (d.sla_estado === 'atencion') slaClass += ' anita-inbox-tag--sla-atencion';
                        else if (d.sla_estado === 'ok') slaClass += ' anita-inbox-tag--sla-ok';
                        html += '<span class="' + slaClass + ' anita-inbox-drawer-sla">' + esc(d.sla_label) + '</span>';
                    }
                    html += '<dl class="anita-inbox-drawer-dl">';
                    (d.campos || []).forEach(function (c) {
                        html += '<div><dt>' + esc(c.label) + '</dt><dd>' + esc(c.valor) + '</dd></div>';
                    });
                    html += '</dl>';
                    if (d.historial && d.historial.length) {
                        html += '<h3 class="anita-inbox-drawer-h">Historial / auditoría</h3><ul class="anita-inbox-drawer-hist">';
                        d.historial.forEach(function (h) {
                            var meta = h.canal ? (' · <em class="anita-inbox-canal">' + esc(h.canal) + '</em>') : '';
                            html += '<li><span>' + esc(h.fecha) + meta + '</span><p>' + esc(h.texto) + '</p></li>';
                        });
                        html += '</ul>';
                    }
                    body.innerHTML = html;
                    var token = document.querySelector('meta[name="csrf-token"]');
                    var csrf = token ? token.getAttribute('content') : '';
                    var actions = '';
                    if (d.url_ver) {
                        actions += '<a class="anita-inbox-btn anita-inbox-btn--ghost" href="' + esc(d.url_ver) + '" target="_blank" rel="noopener">Abrir documento</a>';
                    }
                    if (d.puede_aprobar && d.url_aprobar) {
                        actions += '<form method="post" action="' + esc(d.url_aprobar) + '" class="anita-inbox-action-form anita-inbox-action-form--primary" onsubmit="return confirm(\'¿Aprobar este pendiente?\');">'
                            + '<input type="hidden" name="_token" value="' + esc(csrf) + '">'
                            + '<button type="submit" class="anita-inbox-btn anita-inbox-btn--primary">Aprobar</button></form>';
                    }
                    if (d.url_rechazar) {
                        actions += '<form method="post" action="' + esc(d.url_rechazar) + '" class="anita-inbox-action-form js-drawer-rechazar">'
                            + '<input type="hidden" name="_token" value="' + esc(csrf) + '">'
                            + '<input type="hidden" name="observacion" value="" class="js-rechazar-obs">'
                            + '<button type="submit" class="anita-inbox-btn anita-inbox-btn--danger">Rechazar</button></form>';
                    }
                    foot.innerHTML = actions;
                    bindRechazoForms(foot);
                })
                .catch(function () {
                    body.innerHTML = '<p class="anita-inbox-drawer-error">Error de red al cargar el detalle.</p>';
                });
        }
        document.querySelectorAll('.js-inbox-detalle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-detalle-url');
                if (url) abrirDrawer(url);
            });
        });
        drawer.querySelectorAll('.js-inbox-drawer-close').forEach(function (el) {
            el.addEventListener('click', cerrarDrawer);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (rechazoModal && !rechazoModal.hidden) cerrarRechazoModal();
                else if (!drawer.hidden) cerrarDrawer();
            }
        });
    }

    var searchInput = document.getElementById('filtro-q');
    if (searchInput && searchInput.form) {
        var searchTimer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { searchInput.form.submit(); }, 450);
        });
    }

    var bulkBar = document.getElementById('anita-inbox-bulkbar');
    var bulkCount = document.getElementById('anita-bulk-count');
    var bulkForm = document.getElementById('anita-bulk-form');
    var bulkFuente = document.getElementById('anita-bulk-fuente');
    var bulkTipo = document.getElementById('anita-bulk-tipo');
    var bulkIds = document.getElementById('anita-bulk-ids');
    function selectedChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.js-bulk-check:checked'));
    }
    function refreshBulkBar() {
        var checks = selectedChecks();
        if (bulkCount) bulkCount.textContent = String(checks.length);
        if (bulkBar) bulkBar.classList.toggle('is-visible', checks.length > 0);
    }
    document.querySelectorAll('.js-bulk-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (!cb.checked) { refreshBulkBar(); return; }
            var selected = selectedChecks().filter(function (el) { return el !== cb; });
            if (selected.length) {
                var first = selected[0];
                if (first.getAttribute('data-fuente') !== cb.getAttribute('data-fuente')
                    || first.getAttribute('data-tipo') !== cb.getAttribute('data-tipo')) {
                    cb.checked = false;
                    alert('La selección masiva debe ser de la misma fuente y tipo.');
                    return;
                }
            }
            if (selectedChecks().length > bulkMax) {
                cb.checked = false;
                alert('Máximo ' + bulkMax + ' ítems por lote.');
                return;
            }
            refreshBulkBar();
        });
    });
    var clearBtn = document.querySelector('.js-bulk-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.querySelectorAll('.js-bulk-check:checked').forEach(function (cb) { cb.checked = false; });
            refreshBulkBar();
        });
    }
    var aprobarBtn = document.querySelector('.js-bulk-aprobar');
    if (aprobarBtn && bulkForm) {
        aprobarBtn.addEventListener('click', function () {
            var checks = selectedChecks();
            if (!checks.length) return;
            var fuente = checks[0].getAttribute('data-fuente') || '';
            var tipo = checks[0].getAttribute('data-tipo') || '';
            for (var i = 0; i < checks.length; i++) {
                if (checks[i].getAttribute('data-fuente') !== fuente || checks[i].getAttribute('data-tipo') !== tipo) {
                    alert('La selección masiva debe ser de la misma fuente y tipo.');
                    return;
                }
            }
            if (fuente === 'arbol' && !tipo) {
                alert('Para el árbol, filtrá o seleccioná un mismo tipo.');
                return;
            }
            if (!confirm('¿Aprobar ' + checks.length + ' pendiente(s) de ' + (tipo || fuente) + '?')) return;
            bulkFuente.value = fuente;
            bulkTipo.value = tipo;
            bulkIds.innerHTML = '';
            checks.forEach(function (cb) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                bulkIds.appendChild(input);
            });
            bulkForm.submit();
        });
    }
})();
</script>
@endsection
