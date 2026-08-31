@php
    $c = $cobertura ?? null;
    $modoActual = $modoAsiento ?? \App\Support\Sueldos\SueldosAsientoModoSupport::ERP;
    $empresaModoId = (int) ($filtros['empresa_id'] ?? 0);
    $puedeModo = can('actualizar-imputacion-concepto-sueldos', false);
@endphp
@if ($c)
<div class="card-body border-bottom py-2">
    @if ($empresaModoId > 0)
        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <strong class="small mb-0">Cómo arma el asiento esta empresa</strong>
            </div>
            <div class="card-body py-2">
                <form method="POST" action="{{ route('guardar_modo_asiento_sueldos') }}" class="mb-0">
                    @csrf
                    <input type="hidden" name="empresa_id" value="{{ $empresaModoId }}">
                    @foreach (\App\Support\Sueldos\SueldosAsientoModoSupport::opciones() as $valor => $op)
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="modo-asiento-{{ $valor }}" name="modo" value="{{ $valor }}"
                                   class="custom-control-input" @if ($modoActual === $valor) checked @endif
                                   @if (! $puedeModo) disabled @endif>
                            <label class="custom-control-label" for="modo-asiento-{{ $valor }}">
                                <strong>{{ $op['label'] }}</strong>
                                <span class="d-block text-muted small">{{ $op['ayuda'] }}</span>
                            </label>
                        </div>
                    @endforeach
                    @if ($puedeModo)
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            Guardar modo
                        </button>
                    @endif
                    <p class="small text-muted mb-0 mt-2">
                        El modo ERP es el de este sistema (un asiento, CC en todas las líneas).
                        Anita replica el histórico: un asiento por centro de costo y pasivos en CC 0.
                        No cambia corridas ya contabilizadas.
                    </p>
                </form>
            </div>
        </div>
    @endif
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <strong class="small">
            Cobertura de mapeo
            @if (! empty($c['ok_minimo']))
                <span class="badge badge-success ml-1">Lista para fase 1</span>
            @else
                <span class="badge badge-warning ml-1">{{ (int) $c['cantidad_faltantes'] }} pendiente(s)</span>
            @endif
        </strong>
        <span class="text-muted small">Patas fijas en Contable → Cuentas automáticas (grupo Sueldos). Fallback por tipo o overrides en esta grilla.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-2">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Pata fija</th>
                    <th>Cuenta</th>
                    <th class="text-center" style="width:70px">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($c['patas_fijas'] as $p)
                    <tr>
                        <td>{{ $p['descripcion'] }}</td>
                        <td>
                            @if (! empty($p['codigo']))
                                {{ $p['codigo'] }} — {{ $p['nombre'] }}
                            @else
                                <span class="text-muted">Sin cuenta</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($p['ok'])
                                <span class="badge badge-success">OK</span>
                            @else
                                <span class="badge badge-danger">Falta</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-2">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Tipo (fallback)</th>
                    <th>Origen</th>
                    <th>Debe</th>
                    <th>Haber</th>
                    <th class="text-center" style="width:70px">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($c['fallback_tipo'] as $t)
                    <tr>
                        <td>{{ $t['label'] }}</td>
                        <td>{{ $t['origen'] ?? '—' }}</td>
                        <td>{{ $t['debe_codigo'] ?? '' }}</td>
                        <td>{{ $t['haber_codigo'] ?? '' }}</td>
                        <td class="text-center">
                            @if ($t['ok'])
                                <span class="badge badge-success">OK</span>
                            @else
                                <span class="badge badge-danger">Falta</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (($c['conceptos_sin_cuenta'] ?? []) !== [])
        <p class="small text-danger mb-1">Conceptos usados en corridas cerradas sin cuenta resuelta:</p>
        <ul class="small mb-0">
            @foreach ($c['conceptos_sin_cuenta'] as $item)
                <li>
                    {{ str_pad((string) $item['codigo'], 4, '0', STR_PAD_LEFT) }}
                    — {{ $item['descripcion'] }}
                    ({{ $item['tipo'] }})
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endif
