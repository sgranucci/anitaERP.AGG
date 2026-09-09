@extends("theme.$theme.layout")
@section('titulo')
    Bandeja de tickets
@endsection

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/mis-aprobaciones.css') }}?v={{ @filemtime(public_path('assets/css/mis-aprobaciones.css')) ?: time() }}">
<style>
    .anita-inbox-item-meta { display:flex; flex-wrap:wrap; gap:.35rem .75rem; font-size:.8rem; color:var(--ai-muted); margin-top:.35rem; }
    .anita-inbox-item-meta span { display:inline-flex; align-items:center; gap:.3rem; }
    .anita-inbox-badge-estado {
        display:inline-block; font-size:.72rem; font-weight:700; letter-spacing:.02em;
        padding:.2rem .55rem; border-radius:999px; background:rgba(12,59,82,.08); color:var(--ai-navy);
    }
    .anita-inbox-badge-estado.is-cola { background:rgba(234,88,12,.12); color:#c2410c; }
    .anita-inbox-badge-estado.is-mio { background:rgba(31,122,77,.12); color:#1f7a4d; }
    .anita-inbox-assign {
        display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; margin-top:.55rem;
    }
    .anita-inbox-assign select {
        min-width: 11rem; max-width: 16rem; height: 2rem; font-size: .8rem;
        border-radius: .4rem; border: 1px solid rgba(16,36,51,.18); padding: 0 .45rem;
    }
    .anita-inbox-item.is-atencion { --rail: var(--ai-warn); }
</style>
@endsection

@section('contenido')
@php
    $urlBandeja = route('consulta_bandeja_ticket');
    $puedeCola = !empty($puedeCola);
    $tabs = [];
    if ($puedeCola) {
        $tabs[] = ['valor' => 'cola', 'corto' => 'Cola', 'nombre' => 'Sin asignar', 'count' => $contadores['cola'] ?? 0];
    }
    $tabs[] = ['valor' => 'mios', 'corto' => 'Míos', 'nombre' => 'Mis tickets', 'count' => $contadores['mios'] ?? 0];
    if (!empty($puedeTodos)) {
        $tabs[] = ['valor' => 'todos', 'corto' => 'Todos', 'nombre' => 'Todos del área', 'count' => $contadores['todos'] ?? 0];
    }
    $puedeTomar = can('tomar-ticket', false);
    $puedeLiberar = can('liberar-ticket', false);
    $puedeAsignar = can('asignar-ticket-bandeja', false) && !empty($puedeTodos);
    $tecnicosPorArea = $tecnicosPorArea ?? [];
    $urgentes = collect($items ?? [])->where('urgencia', 'urgente')->count();
    $tabActivo = $tab ?? ($puedeCola ? 'cola' : 'mios');
@endphp
<div class="anita-inbox">
    @include('includes.mensaje')

    <header class="anita-inbox-hero">
        <div class="anita-inbox-hero-glow" aria-hidden="true"></div>
        <div class="anita-inbox-hero-grid">
            <div class="anita-inbox-hero-main">
                <p class="anita-inbox-brand">Anita · Tickets</p>
                <h1 class="anita-inbox-title">Bandeja de tickets</h1>
                <p class="anita-inbox-sub">
                    @if ($puedeCola)
                        Tus tickets asignados y la cola del área (donde el técnico toma el trabajo).
                        En Tecnología y otras áreas con asignación por administrador, usá <strong>Míos</strong>.
                    @else
                        Acá están los tickets que te asignaron. Abrí uno para trabajarlo o comentar.
                    @endif
                </p>
            </div>
            <div class="anita-inbox-stats" role="group" aria-label="Resumen">
                @if ($puedeCola)
                <div class="anita-inbox-stat is-total">
                    <span class="anita-inbox-stat-value">{{ $contadores['cola'] ?? 0 }}</span>
                    <span class="anita-inbox-stat-label">En cola</span>
                </div>
                @endif
                <div class="anita-inbox-stat{{ ! $puedeCola ? ' is-total' : '' }}">
                    <span class="anita-inbox-stat-value">{{ $contadores['mios'] ?? 0 }}</span>
                    <span class="anita-inbox-stat-label">Míos</span>
                </div>
                <div class="anita-inbox-stat{{ $urgentes > 0 ? ' is-urgent' : '' }}">
                    <span class="anita-inbox-stat-value">{{ $urgentes }}</span>
                    <span class="anita-inbox-stat-label">≥5 días</span>
                </div>
                @if (!empty($puedeTodos))
                    <div class="anita-inbox-stat">
                        <span class="anita-inbox-stat-value">{{ $contadores['todos'] ?? 0 }}</span>
                        <span class="anita-inbox-stat-label">Área</span>
                    </div>
                @endif
            </div>
        </div>
    </header>

    <div class="anita-inbox-toolbar">
        <div class="anita-inbox-filters" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;width:100%;">
            <div class="anita-inbox-field anita-inbox-field--segments">
                <span class="anita-inbox-field-label">Vista</span>
                <div class="anita-inbox-segments" role="tablist">
                    @foreach ($tabs as $seg)
                        @php
                            $activo = $tabActivo === $seg['valor'];
                            $qs = ['tab' => $seg['valor']];
                            if (($filtroQ ?? '') !== '') {
                                $qs['q'] = $filtroQ;
                            }
                        @endphp
                        <a href="{{ $urlBandeja.'?'.http_build_query($qs) }}"
                           class="anita-inbox-segment{{ $activo ? ' is-active' : '' }}"
                           role="tab"
                           aria-selected="{{ $activo ? 'true' : 'false' }}"
                           title="{{ $seg['nombre'] }}">
                            {{ $seg['corto'] }}
                            <span style="opacity:.75;margin-left:.25rem;">{{ $seg['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <form method="get" action="{{ $urlBandeja }}" class="anita-inbox-field anita-inbox-field--search" style="flex:1;min-width:12rem;">
                <input type="hidden" name="tab" value="{{ $tabActivo }}">
                <label for="filtro-q">Buscar</label>
                <input type="search" id="filtro-q" name="q" value="{{ $filtroQ }}"
                       placeholder="Nº, título, sala, usuario…" autocomplete="off">
            </form>
        </div>
    </div>

    @if (!empty($areasVacias))
        <div class="anita-inbox-empty">
            <p>No tenés ficha de técnico vinculada a tu usuario. Pedí al administrador que asocie tu usuario ERP en Técnicos (ticket).</p>
        </div>
    @elseif ($items->isEmpty())
        <div class="anita-inbox-empty">
            <p>
                @if ($tabActivo === 'cola')
                    No hay tickets sin asignar en la cola.
                @elseif ($tabActivo === 'mios')
                    No tenés tickets asignados en curso.
                @else
                    No hay tickets en curso en el área.
                @endif
            </p>
        </div>
    @else
        <div class="anita-inbox-list" role="list">
            @foreach ($items as $item)
                @php
                    $clsUrgencia = match ($item->urgencia ?? 'normal') {
                        'urgente' => 'is-urgent',
                        'atencion' => 'is-atencion is-warn',
                        default => '',
                    };
                    $tecnicosArea = $tecnicosPorArea[$item->areadestino_id] ?? [];
                @endphp
                <article class="anita-inbox-item {{ $clsUrgencia }}" role="listitem">
                    <div class="anita-inbox-rail" aria-hidden="true"></div>
                    <div class="anita-inbox-item-body" style="flex:1;min-width:0;">
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;">
                            <h2 class="anita-inbox-item-title" style="margin:0;font-size:1.05rem;">
                                <a href="{{ $item->url_detalle }}">#{{ $item->id }} — {{ $item->titulo }}</a>
                            </h2>
                            <span class="anita-inbox-badge-estado{{ $item->sin_tecnico ? ' is-cola' : ($item->es_mio ? ' is-mio' : '') }}">
                                {{ $item->estado }}
                            </span>
                        </div>
                        @if ($item->comentario !== '')
                            <p class="anita-inbox-item-detail" style="margin:.4rem 0 0;color:var(--ai-muted);font-size:.9rem;">
                                {{ \Illuminate\Support\Str::limit($item->comentario, 160) }}
                            </p>
                        @endif
                        <div class="anita-inbox-item-meta">
                            <span><i class="fa fa-calendar"></i> {{ $item->fecha_fmt }} · {{ $item->dias }}d</span>
                            <span><i class="fa fa-map-marker"></i> {{ $item->sala }}</span>
                            <span><i class="fa fa-user"></i> {{ $item->usuario }}</span>
                            <span><i class="fa fa-wrench"></i> {{ $item->areadestino }}</span>
                            @if (! $item->sin_tecnico)
                                <span><i class="fa fa-user-circle"></i> {{ $item->tecnico }}</span>
                            @endif
                        </div>

                        @if ($item->puede_asignar && $puedeAsignar && count($tecnicosArea) > 0)
                            <form action="{{ route('asigna_bandeja_ticket', ['id' => $item->id]) }}" method="POST" class="anita-inbox-assign">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $tabActivo }}">
                                <label class="sr-only" for="tecnico_{{ $item->id }}">Técnico</label>
                                <select name="tecnico_ticket_id" id="tecnico_{{ $item->id }}" required>
                                    <option value="">— Asignar técnico —</option>
                                    @foreach ($tecnicosArea as $tec)
                                        <option value="{{ $tec->id }}" @selected((int) ($item->tecnico_id ?? 0) === (int) $tec->id)>
                                            {{ $tec->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="anita-inbox-btn anita-inbox-btn--primary">
                                    {{ $item->sin_tecnico ? 'Asignar' : 'Reasignar' }}
                                </button>
                            </form>
                        @endif
                    </div>
                    <div class="anita-inbox-item-actions">
                        <a class="anita-inbox-btn anita-inbox-btn--ghost" href="{{ $item->url_detalle }}">Ver</a>
                        @if ($item->puede_tomar && $puedeTomar)
                            <form action="{{ route('toma_bandeja_ticket', ['id' => $item->id]) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="anita-inbox-btn anita-inbox-btn--primary">Tomar</button>
                            </form>
                        @endif
                        @if ($item->puede_liberar && $puedeLiberar)
                            <form action="{{ route('libera_bandeja_ticket', ['id' => $item->id]) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Liberar este ticket a la cola?');">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $tabActivo }}">
                                <button type="submit" class="anita-inbox-btn anita-inbox-btn--warn">Liberar</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
