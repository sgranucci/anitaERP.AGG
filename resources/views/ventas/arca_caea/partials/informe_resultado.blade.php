@php
    $resultado = session('caea_informe_resultado');
@endphp
@if (is_array($resultado) && $resultado !== [])
    @php
        $ok = (bool) ($resultado['ok'] ?? false);
        $detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];
        $erroresAgrupados = is_array($detalle['errores_agrupados'] ?? null) ? $detalle['errores_agrupados'] : [];
        $erroresMuestra = is_array($detalle['errores_muestra'] ?? null) ? $detalle['errores_muestra'] : [];
        $alertClass = $ok && ($detalle['errores_lote'] ?? 0) == 0 ? 'alert-success' : ($ok ? 'alert-warning' : 'alert-danger');
        $icon = $ok && ($detalle['errores_lote'] ?? 0) == 0 ? 'fa-check' : ($ok ? 'fa-warning' : 'fa-times');
    @endphp
    <div class="alert {{ $alertClass }} alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4 class="alert-heading mb-2">
            <i class="icon fa {{ $icon }}"></i>
            Resultado presentación CAEA — {{ $resultado['empresa'] ?? '' }}
        </h4>
        <p class="mb-1">
            <strong>{{ $resultado['quincena'] ?? '' }}</strong>
        </p>
        <p class="mb-2">{{ $resultado['mensaje'] ?? '' }}</p>
        @if (($detalle['informados'] ?? 0) > 0 || ($detalle['errores_lote'] ?? 0) > 0 || ($detalle['pendientes_restantes'] ?? 0) > 0 || ($detalle['sincronizados_arca'] ?? 0) > 0)
            <p class="small text-muted mb-2">
                Informados en este lote: {{ (int) ($detalle['informados'] ?? 0) }}
                · Reconocidos en ARCA: {{ (int) ($detalle['sincronizados_arca'] ?? 0) }}
                · Errores en lote: {{ (int) ($detalle['errores_lote'] ?? 0) }}
                · Pendientes: {{ (int) ($detalle['pendientes_restantes'] ?? 0) }}
                · Errores acumulados: {{ (int) ($detalle['errores_total'] ?? 0) }}
                @if (($detalle['omitidos_hueco_numeracion'] ?? 0) > 0)
                    · Omitidos por hueco: {{ (int) $detalle['omitidos_hueco_numeracion'] }}
                @endif
            </p>
        @endif
        @if ($erroresAgrupados !== [])
            <div class="mt-2">
                <strong class="small">Errores ARCA (agrupados):</strong>
                <ul class="small mb-0 pl-3">
                    @foreach ($erroresAgrupados as $err)
                        <li>
                            @if (! empty($err['codigo']))
                                <code>[{{ $err['codigo'] }}]</code>
                            @endif
                            {{ \Illuminate\Support\Str::limit($err['mensaje'] ?? '', 180) }}
                            <span class="text-muted">({{ (int) ($err['cantidad'] ?? 0) }} comp.)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($erroresMuestra !== [])
            <div class="mt-2">
                <strong class="small">Errores ARCA (muestra):</strong>
                <ul class="small mb-0 pl-3">
                    @foreach ($erroresMuestra as $err)
                        <li>
                            PV {{ str_pad((string) ($err['pto_vta'] ?? ''), 5, '0', STR_PAD_LEFT) }}
                            #{{ $err['numero'] ?? '?' }}:
                            {{ \Illuminate\Support\Str::limit($err['mensaje'] ?? '', 160) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
