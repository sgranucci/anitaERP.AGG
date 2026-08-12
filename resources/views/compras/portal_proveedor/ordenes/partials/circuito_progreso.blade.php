@php
    $circuito = $circuito ?? null;
@endphp
@if ($circuito)
<div class="portal-circuito mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-2">
        <div>
            <h5 class="mb-1"><i class="fa fa-sitemap"></i> Circuito del documento</h5>
            <p class="text-muted small mb-0">{{ $circuito['resumen'] ?? '' }}</p>
        </div>
        <div class="text-right">
            <span class="small text-muted d-block">Avance</span>
            <strong style="color:#1B4F72;font-size:1.25rem;">{{ (int) ($circuito['progreso_pct'] ?? 0) }}%</strong>
        </div>
    </div>
    <div class="progress mb-3" style="height:10px;">
        <div class="progress-bar"
             role="progressbar"
             style="width: {{ (int) ($circuito['progreso_pct'] ?? 0) }}%; background:#2471A3;"
             aria-valuenow="{{ (int) ($circuito['progreso_pct'] ?? 0) }}"
             aria-valuemin="0"
             aria-valuemax="100"></div>
    </div>
    <div class="portal-circuito-steps">
        @foreach ($circuito['etapas'] ?? [] as $i => $etapa)
            @php
                $estado = $etapa['estado'] ?? 'pendiente';
                $cls = match ($estado) {
                    'completo' => 'paso-completo',
                    'en_curso' => 'paso-en-curso',
                    default => 'paso-pendiente',
                };
                $icon = match ($estado) {
                    'completo' => 'fa-check',
                    'en_curso' => 'fa-spinner',
                    default => 'fa-circle-o',
                };
            @endphp
            <div class="portal-circuito-paso {{ $cls }}">
                <div class="paso-nodo">
                    <i class="fa {{ $icon }}"></i>
                </div>
                @if ($i < count($circuito['etapas']) - 1)
                    <div class="paso-linea"></div>
                @endif
                <div class="paso-texto">
                    <div class="paso-titulo">{{ $etapa['titulo'] }}</div>
                    <div class="paso-detalle">{{ $etapa['detalle'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
