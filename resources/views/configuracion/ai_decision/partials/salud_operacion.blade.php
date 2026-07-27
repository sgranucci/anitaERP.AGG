@php
    $salud = $salud ?? ['items' => [], 'eventos_pendientes' => 0, 'decisiones_hoy' => 0, 'tasa_aceptacion_30d' => null];
    $eventosPendientes = $eventosPendientes ?? [];
@endphp
<div class="card card-outline card-secondary mb-3" id="ai-agente-evento-hitl"
     data-hitl-visto-url="{{ url('configuracion/ai-agente-eventos') }}">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-heartbeat"></i> Salud operativa IA
            <span class="badge badge-light ml-1">{{ (int) ($salud['decisiones_hoy'] ?? 0) }} decisión(es) hoy</span>
            <span class="badge badge-{{ ((int) ($salud['eventos_pendientes'] ?? 0)) > 0 ? 'warning' : 'success' }} ml-1">
                {{ (int) ($salud['eventos_pendientes'] ?? 0) }} evento(s) HITL pendiente(s)
            </span>
        </h3>
        <div class="card-tools">
            <a href="{{ route('ai_agente_evento', ['estado' => 'pendiente']) }}" class="btn btn-tool btn-sm">
                <i class="fa fa-list"></i> Cola agentes
            </a>
        </div>
    </div>
    <div class="card-body py-2">
        <div class="row">
            @foreach (($salud['items'] ?? []) as $item)
                <div class="col-md-4 col-lg-3 mb-2">
                    <div class="border rounded p-2 h-100 {{ !empty($item['ok']) ? 'border-success' : 'border-warning' }}">
                        <div class="small font-weight-bold">
                            @if (!empty($item['ok']))
                                <i class="fa fa-check-circle text-success"></i>
                            @else
                                <i class="fa fa-exclamation-triangle text-warning"></i>
                            @endif
                            {{ $item['etiqueta'] ?? '' }}
                        </div>
                        <div class="text-muted small">{{ $item['detalle'] ?? '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if (!empty($eventosPendientes))
            <h6 class="mt-2 mb-1">Cola HITL (pendientes recientes)</h6>
            @include('configuracion.ai_agente_evento.partials.tabla_cola', [
                'coleccion' => $eventosPendientes,
                'mostrarAcciones' => true,
            ])
        @endif
    </div>
</div>
