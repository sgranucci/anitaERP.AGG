@php
    $pf = $transferencia_laboratorio_preflight ?? [];
    $aplicaTm = !empty($genera_transferencia_laboratorio) && !empty($pf['aplica']);
    $viableTm = $aplicaTm ? (bool) ($pf['viable'] ?? true) : null;
    $esCentroConsumo = $aplicaTm && !empty($pf['deposito_origen_es_centro_consumo']);
    $lineasProblema = collect($pf['lineas_detalle'] ?? [])->filter(static fn ($f) => empty($f['ok']))->values();
@endphp
@if($aplicaTm)
    @if($viableTm === false)
        <div class="alert alert-danger mb-3" role="alert">
            <strong><i class="fa fa-exclamation-triangle"></i> Transferencia al laboratorio no viable</strong>
            <p class="mb-2 mt-2">
                {{ $pf['mensaje_resumen'] ?? 'No hay saldo suficiente en el depósito de origen. La aprobación puede registrarse, pero la transferencia automática al laboratorio no se realizará.' }}
            </p>
            @if($lineasProblema->isNotEmpty())
                <p class="mb-1 small font-weight-bold">Ítems con saldo insuficiente:</p>
                <ul class="mb-0 small pl-3">
                    @foreach($lineasProblema as $fila)
                        <li>
                            <strong>{{ $fila['sku'] ?? '' }}</strong>
                            {{ $fila['descripcion'] ?? '' }}
                            — requerido: {{ number_format((float) ($fila['cantidad_requerida'] ?? 0), 4, ',', '.') }}
                            @if(isset($fila['saldo_disponible']))
                                / saldo: {{ number_format((float) $fila['saldo_disponible'], 4, ',', '.') }}
                            @else
                                / sin saldo en depósito
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <div class="alert alert-success mb-3" role="status">
            <strong><i class="fa fa-exchange"></i> Transferencia de stock</strong>
            <span class="d-block mt-1">
                Al confirmar se generará una transferencia de mercadería hacia
                <strong>{{ $deposito_laboratorio ?? 'depósito de laboratorio' }}</strong>
                (ítems reparación/devolución).
            </span>
            @if($esCentroConsumo)
                <span class="d-block mt-2 small">
                    <i class="fa fa-info-circle"></i>
                    {{ $pf['mensaje_informativo'] ?? 'El depósito de origen es centro de consumo: no se valida saldo y la transferencia se registrará igual.' }}
                </span>
            @endif
        </div>
    @endif
@endif
