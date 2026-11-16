@php
    $pct = static function (?float $tasa): string {
        return $tasa === null ? '—' : number_format($tasa * 100, 1).'%';
    };
@endphp
<div class="row mb-3">
    <div class="col-md-2 col-6 mb-2">
        <div class="small-box bg-info mb-0">
            <div class="inner p-3">
                <h4 class="mb-0">{{ $kpis['total'] }}</h4>
                <p class="mb-0">Decisiones</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="small-box bg-success mb-0">
            <div class="inner p-3">
                <h4 class="mb-0">{{ $pct($kpis['tasa_aceptacion']) }}</h4>
                <p class="mb-0">Aceptación</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="small-box bg-warning mb-0">
            <div class="inner p-3">
                <h4 class="mb-0">{{ $pct($kpis['tasa_edicion']) }}</h4>
                <p class="mb-0">Editadas / aceptadas</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="small-box bg-secondary mb-0">
            <div class="inner p-3">
                <h4 class="mb-0">{{ $pct($kpis['tasa_descarte']) }}</h4>
                <p class="mb-0">Descarte</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="small-box bg-primary mb-0">
            <div class="inner p-3">
                <h4 class="mb-0">{{ $kpis['pendientes'] }}</h4>
                <p class="mb-0">Pendientes</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="small-box bg-danger mb-0">
            <div class="inner p-3">
                <h4 class="mb-0">{{ $kpis['errores'] }}</h4>
                <p class="mb-0">Errores</p>
            </div>
        </div>
    </div>
</div>
<div class="row mb-3">
    <div class="col-md-6">
        <p class="mb-1">
            <strong>Score promedio:</strong>
            {{ $kpis['score_promedio'] !== null ? number_format($kpis['score_promedio'], 2) : '—' }}
            &nbsp;·&nbsp;
            <strong>Latencia promedio:</strong>
            {{ $kpis['latencia_promedio_ms'] !== null ? number_format($kpis['latencia_promedio_ms'], 0).' ms' : '—' }}
        </p>
        <p class="text-muted small mb-0">
            Aceptación = (confirmadas + editadas + auto) / resueltas.
            Pendientes = sugerencias sin confirmación ni descarte.
        </p>
        @php $hitl = $kpis['hitl_fab'] ?? null; @endphp
        @if (is_array($hitl))
            <p class="mb-0 mt-2">
                <strong>HITL desde FAB:</strong> {{ (int) ($hitl['total'] ?? 0) }}
                <span class="text-muted small">
                    (dep/cauc {{ (int) ($hitl['depositos'] ?? 0) }}
                    · COM {{ (int) ($hitl['recepciones'] ?? 0) }}
                    · TM {{ (int) ($hitl['transferencias'] ?? 0) }}
                    · PP {{ (int) ($hitl['propuestas'] ?? 0) }})
                </span>
            </p>
        @endif
    </div>
    <div class="col-md-6">
        @if (($kpis['por_skill'] ?? []) !== [])
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Skill</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Confirmadas</th>
                        <th class="text-right">Editadas</th>
                        <th class="text-right">Descartadas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kpis['por_skill'] as $filaSkill)
                        <tr>
                            <td>{{ \App\Support\Configuracion\AiDecisionListadoFiltros::etiquetaSkill($filaSkill['skill']) }}</td>
                            <td class="text-right">{{ $filaSkill['total'] }}</td>
                            <td class="text-right">{{ $filaSkill['confirmadas'] }}</td>
                            <td class="text-right">{{ $filaSkill['editadas'] }}</td>
                            <td class="text-right">{{ $filaSkill['descartadas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
