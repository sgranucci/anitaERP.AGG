@php
    $conceptosCount = (int) $data->columnas->sum(fn ($c) => $c->conceptos->count());
    $ultimaEjec = $data->ejecuciones->first();
@endphp
<style>
    .rsd-studio-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    .rsd-studio-kpis {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }
    .rsd-studio-kpi {
        min-width: 72px;
    }
    .rsd-studio-kpi .n {
        font-size: 1.25rem;
        font-weight: 600;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
        color: #17202A;
    }
    .rsd-studio-kpi .l {
        font-size: .75rem;
        color: #6c757d;
    }
    .rsd-studio-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(280px, .85fr);
        gap: 12px;
        align-items: stretch;
    }
    .rsd-papel {
        background: #fbfcfc;
        border: 1px solid #d5d8dc;
        border-radius: .25rem;
        min-height: 320px;
        max-height: clamp(420px, 68vh, 720px);
        overflow: auto;
        padding: 12px;
    }
    .rsd-papel.compacto {
        padding: 6px;
    }
    .rsd-papel table {
        width: 100%;
        border-collapse: collapse;
        font-size: .8rem;
        font-variant-numeric: tabular-nums;
        background: #fff;
    }
    .rsd-papel thead th {
        background: #85C1E9;
        color: #17202A;
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 6px 8px;
        border: 1px solid #aed6f1;
        white-space: nowrap;
    }
    .rsd-papel tbody td {
        padding: 5px 8px;
        border: 1px solid #e5e8e8;
    }
    .rsd-papel th.rsd-col-activa,
    .rsd-papel td.rsd-col-activa {
        outline: 2px solid #2e86c1;
        outline-offset: -2px;
        background: #ebf5fb;
    }
    .rsd-papel-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 8px;
        min-height: 1.5rem;
    }
    .rsd-papel-chips .badge {
        font-weight: 500;
    }
    @media (max-width: 1199.98px) {
        .rsd-studio-layout {
            grid-template-columns: 1fr;
        }
        .rsd-papel {
            max-height: 360px;
        }
    }
</style>

<div class="rsd-studio-bar">
    <div>
        <div class="font-weight-bold text-dark">
            Estudio · {{ $data->codigo }} — {{ $data->titulo }}
        </div>
        <div class="small text-muted">
            {{ $tiposListado[$data->tipo] ?? $data->tipo }}
            @if($data->asociado_codigo)
                · asociado {{ $data->asociado_codigo }}
            @endif
            · v{{ $data->version_actual }}
            @if($data->owner)
                · owner {{ $data->owner->nombre }}
            @elseif($data->owner_id ?? null)
                · owner #{{ $data->owner_id }}
            @endif
        </div>
    </div>
    <div class="rsd-studio-kpis">
        <div class="rsd-studio-kpi">
            <div class="n">{{ $data->columnas->count() }}</div>
            <div class="l">Columnas</div>
        </div>
        <div class="rsd-studio-kpi">
            <div class="n">{{ $conceptosCount }}</div>
            <div class="l">Conceptos</div>
        </div>
        <div class="rsd-studio-kpi">
            <div class="n">{{ $ultimaEjec?->cantidad_filas ?? '—' }}</div>
            <div class="l">Últ. filas</div>
        </div>
    </div>
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary" id="rsd-toggle-papel" title="Vista del papel">
            Papel
        </button>
        <a href="{{ route('ejecutar_reporte_sueldos_definible', ['id' => $data->id]) }}" class="btn btn-outline-success">
            <i class="fa fa-play"></i> Ejecutar
        </a>
        <a href="{{ route('dashboard_reporte_sueldos_definible', ['id' => $data->id]) }}" class="btn btn-outline-primary">
            <i class="fa fa-chart-bar"></i> Dashboard
        </a>
        <a href="{{ route('reporte_sueldos_definible') }}" class="btn btn-outline-info">
            <i class="fa fa-reply-all"></i>
        </a>
    </div>
</div>

<div class="rsd-studio-layout">
    <div class="rsd-studio-editor">
        @include('sueldos.reporte_definible.partials.columnas_conceptos')
    </div>
    <div class="rsd-studio-preview">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong>Preview</strong>
                <span class="small text-muted" id="rsd-preview-fuente">Estructura viva</span>
            </div>
            <div class="card-body p-2">
                <div class="rsd-papel-chips" id="rsd-preview-chips"></div>
                <div class="rsd-papel" id="rsd-preview-papel">
                    <table>
                        <thead>
                            <tr id="rsd-preview-thead"></tr>
                        </thead>
                        <tbody id="rsd-preview-tbody"></tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0 mt-2">
                    Muestra de la &uacute;ltima ejecuci&oacute;n (hasta 12 filas). No recalcula liquidaci&oacute;n.
                </p>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="rsd-preview-url">@json(route('preview_estructura_reporte_sueldos_definible', ['id' => $data->id]))</script>
