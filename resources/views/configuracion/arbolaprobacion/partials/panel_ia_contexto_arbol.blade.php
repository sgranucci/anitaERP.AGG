@php
    $panel = $ai_contexto_arbol ?? null;
    $parrafos = is_array($panel['ai_parrafos'] ?? null) ? $panel['ai_parrafos'] : [];
    $advertencias = is_array($panel['ai_advertencias'] ?? null) ? $panel['ai_advertencias'] : [];
    $ctx = is_array($panel['contexto'] ?? null) ? $panel['contexto'] : null;
    $score = $panel['ai_score'] ?? null;
    $decisionId = $panel['ai_decision_id'] ?? null;
    $mostrar = $panel !== null && (count($parrafos) > 0 || count($advertencias) > 0 || $ctx);
@endphp
@if ($mostrar)
    <div class="card card-outline card-info portal-card mb-3" id="panel-ia-arbol">
        <div class="card-header py-2">
            <h3 class="card-title mb-0 h6">
                <i class="fa fa-magic"></i> Ayuda para firmar (IA)
                @if ($score !== null)
                    <span class="badge badge-secondary ml-1">score {{ number_format((float) $score, 2, ',', '.') }}</span>
                @endif
            </h3>
            @if ($decisionId && function_exists('can') && can('listar-ai-decisiones', false))
                <div class="card-tools">
                    <a class="btn btn-tool text-primary" target="_blank" rel="noopener"
                       href="{{ route('ai_decision', ['skill' => 'explicar_contexto_arbol_aprobacion', 'consultar' => 1]) }}">
                        Gobernanza #{{ $decisionId }}
                    </a>
                </div>
            @endif
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">Solo lectura: no aprueba ni cambia el árbol. Usá los botones del portal / ERP para firmar.</p>

            @if (count($advertencias) > 0)
                <div class="alert alert-warning py-2 mb-2">
                    @foreach ($advertencias as $adv)
                        <div>{{ $adv }}</div>
                    @endforeach
                </div>
            @endif

            @if (count($parrafos) > 0)
                <ul class="mb-2 pl-3">
                    @foreach ($parrafos as $p)
                        <li>{{ $p }}</li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($ctx['capex_excesos']))
                <div class="table-responsive mb-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead style="background:#85C1E9;color:#17202A">
                            <tr>
                                <th>CAPEX</th>
                                <th>Período</th>
                                <th class="text-right">Asignado</th>
                                <th class="text-right">Comprometido</th>
                                <th class="text-right">Esta línea</th>
                                <th class="text-right">Excedente</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ctx['capex_excesos'] as $ex)
                                <tr>
                                    <td>{{ $ex['capex_nombre'] ?? ('#'.$ex['capex_id']) }}</td>
                                    <td>{{ $ex['periodo'] ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($ex['asignado'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($ex['comprometido'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($ex['monto_linea'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right"><strong>{{ number_format((float) ($ex['excedente'] ?? 0), 2, ',', '.') }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
