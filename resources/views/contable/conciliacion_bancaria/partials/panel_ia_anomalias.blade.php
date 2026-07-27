@php
    $aiScore = $resultado['ai_score'] ?? null;
    $aiAnomalias = $resultado['ai_anomalias'] ?? null;
    $aiAdvertencias = $resultado['ai_advertencias'] ?? [];
    $aiDecisionId = $resultado['ai_decision_id'] ?? null;
    $aiResumen = $resultado['ai_resumen'] ?? [];
    $aiPlan = is_array($resultado['ai_plan'] ?? null) ? $resultado['ai_plan'] : null;
    $aiPlanPasos = is_array($aiPlan['pasos'] ?? null) ? $aiPlan['pasos'] : [];
    $mostrarPanel = $aiScore !== null || (is_array($aiAnomalias) && count($aiAnomalias) > 0) || count($aiAdvertencias) > 0 || $aiPlanPasos !== [];
@endphp
@if ($mostrarPanel)
    @php
        $nAnom = is_array($aiAnomalias) ? count($aiAnomalias) : 0;
        $outline = $nAnom > 0 ? 'card-warning' : 'card-info';
    @endphp
    <div class="card card-outline {{ $outline }} mt-3 mb-0">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">
                <i class="fa fa-magic"></i> Revisión asistida (IA)
                @if ($aiScore !== null)
                    <span class="badge badge-secondary ml-1">score {{ number_format((float) $aiScore, 2, ',', '.') }}</span>
                @endif
                @if ($nAnom > 0)
                    <span class="badge badge-warning ml-1">{{ $nAnom }} anomalía(s)</span>
                @else
                    <span class="badge badge-success ml-1">Sin anomalías destacadas</span>
                @endif
            </h3>
            @if (can('listar-ai-decisiones', false))
                <div class="card-tools">
                    <a class="btn btn-tool text-primary" target="_blank" rel="noopener"
                       href="{{ route('ai_decision', ['skill' => 'sugerir_pares_conciliacion_bancaria', 'consultar' => 1]) }}"
                       title="Ver decisiones de esta skill en gobernanza">
                        Gobernanza IA
                        @if ($aiDecisionId)
                            <span class="text-muted">#{{ $aiDecisionId }}</span>
                        @endif
                    </a>
                </div>
            @endif
        </div>
        <div class="card-body py-2">
            @if (!empty($aiResumen))
                <p class="small text-muted mb-2">
                    Pares nuevos: {{ $aiResumen['pares_nuevos'] ?? 0 }}
                    · Pend. contables: {{ $aiResumen['pendientes_contables'] ?? 0 }}
                    · Pend. banco: {{ $aiResumen['pendientes_banco'] ?? 0 }}
                    · Candidatos cercanos: {{ $aiResumen['candidatos_cercanos'] ?? 0 }}
                </p>
            @endif

            @if ($nAnom > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead style="background:#85C1E9;color:#17202A">
                            <tr>
                                <th style="width:90px">Severidad</th>
                                <th style="width:180px">Código</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aiAnomalias as $anom)
                                @php
                                    $sev = (string) ($anom['severidad'] ?? 'media');
                                    $badge = $sev === 'alta' ? 'badge-danger' : ($sev === 'baja' ? 'badge-secondary' : 'badge-warning');
                                @endphp
                                <tr>
                                    <td><span class="badge {{ $badge }}">{{ $sev }}</span></td>
                                    <td><code>{{ $anom['codigo'] ?? '' }}</code></td>
                                    <td>{{ $anom['mensaje'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif (count($aiAdvertencias) > 0)
                <ul class="mb-0 pl-3">
                    @foreach ($aiAdvertencias as $adv)
                        <li>{{ $adv }}</li>
                    @endforeach
                </ul>
            @else
                <p class="mb-0 text-muted small">La corrida no presentó señales de revisión prioritaria.</p>
            @endif

            @if ($aiPlanPasos !== [])
                <div class="mt-3">
                    <p class="small font-weight-bold mb-1">
                        Plan agente (HITL)
                        @if (!empty($aiPlan['resumen']))
                            <span class="text-muted font-weight-normal">— {{ $aiPlan['resumen'] }}</span>
                        @endif
                    </p>
                    <ol class="small mb-0 pl-3">
                        @foreach ($aiPlanPasos as $paso)
                            <li class="mb-1">
                                <strong>{{ $paso['etiqueta'] ?? 'Paso' }}</strong>
                                @if (!empty($paso['motivo']))
                                    <span class="text-muted"> — {{ $paso['motivo'] }}</span>
                                @endif
                                @if (!empty($paso['frase']))
                                    <br><code class="text-primary">{{ $paso['frase'] }}</code>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    </div>
@endif
