@php
    $corr = $resultado['auditoria_correlatividad'] ?? ['habilitada' => false];
    $grupos = $corr['grupos'] ?? [];
    $totalSaltos = (int) ($corr['total_saltos'] ?? 0);
    $totalFaltantes = (int) ($corr['total_faltantes'] ?? 0);
    $puedeVerPuntoventa = $puede_ver_puntoventa ?? false;
    $puedeVerVenta = $puede_ver_venta ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@if (! empty($corr['habilitada']))
    <div class="px-3 py-3 border-bottom bg-white">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <h6 class="mb-1">Auditoría de correlatividad (numeración)</h6>
            @if ($totalSaltos === 0)
                <span class="badge badge-success">Sin saltos en el período</span>
            @else
                <span class="badge badge-danger">
                    {{ (int) ($corr['grupos_con_saltos'] ?? 0) }} PV/tipo con saltos
                    · {{ $totalFaltantes }} número(s) faltante(s)
                </span>
            @endif
        </div>
        <p class="small text-muted mb-2">
            Detecta saltos de numeración entre comprobantes consecutivos del período, agrupados por
            <strong>punto de venta</strong> y <strong>tipo de transacción</strong>.
            Si un número faltante existe en otra fecha, se indica para distinguir desorden de jornada vs. comprobante ausente.
        </p>

        @if ($totalSaltos === 0)
            <p class="small text-success mb-0">
                <i class="fa fa-check"></i>
                La numeración es correlativa dentro del rango de fechas consultado para todos los PV/tipos con más de un comprobante.
            </p>
        @else
            <div class="accordion" id="accordion-correlatividad">
                @foreach ($grupos as $idx => $grupo)
                    <div class="card card-outline card-warning mb-1">
                        <div class="card-header p-2" id="heading-corr-{{ $idx }}">
                            <button class="btn btn-link btn-sm text-left w-100 collapsed d-flex justify-content-between align-items-center"
                                type="button" data-toggle="collapse" data-target="#collapse-corr-{{ $idx }}"
                                aria-expanded="false" aria-controls="collapse-corr-{{ $idx }}">
                                <span>
                                    <strong>{{ $grupo['seccion_label'] ?? '' }}</strong>
                                    · PV
                                    @if ($puedeVerPuntoventa && (int) ($grupo['puntoventa_id'] ?? 0) > 0)
                                        <a href="{{ route('editar_puntoventa', array_merge(['id' => $grupo['puntoventa_id']], $queryConsulta)) }}"
                                           target="_blank" rel="noopener" class="text-primary">
                                            {{ $grupo['puntoventa_codigo'] ?? '' }}
                                        </a>
                                    @else
                                        {{ $grupo['puntoventa_codigo'] ?? '' }}
                                    @endif
                                    {{ $grupo['puntoventa_nombre'] ?? '' }}
                                    · <strong>{{ $grupo['tipo'] ?? '' }}</strong>
                                    <span class="badge badge-warning ml-1">{{ count($grupo['faltantes'] ?? []) }} faltante(s)</span>
                                </span>
                                <span class="text-muted small">
                                    {{ (int) ($grupo['min_numero'] ?? 0) }}–{{ (int) ($grupo['max_numero'] ?? 0) }}
                                    ({{ (int) ($grupo['cantidad_periodo'] ?? 0) }} en período)
                                </span>
                            </button>
                        </div>
                        <div id="collapse-corr-{{ $idx }}" class="collapse" data-parent="#accordion-correlatividad">
                            <div class="card-body p-2">
                                @foreach ($grupo['saltos'] ?? [] as $salto)
                                    <div class="mb-2 pb-2 border-bottom">
                                        <p class="small mb-1">
                                            Salto entre
                                            <strong>{{ $salto['comprobante_desde'] ?? '' }}</strong>
                                            ({{ (int) ($salto['desde'] ?? 0) }})
                                            y
                                            <strong>{{ $salto['comprobante_hasta'] ?? '' }}</strong>
                                            ({{ (int) ($salto['hasta'] ?? 0) }}):
                                        </p>
                                        <ul class="list-unstyled small mb-0">
                                            @foreach ($salto['faltantes'] ?? [] as $numFaltante)
                                                @php
                                                    $fuera = $grupo['faltantes_fuera_periodo'][$numFaltante] ?? null;
                                                    $sinRegistro = in_array($numFaltante, $grupo['faltantes_sin_registro'] ?? [], true);
                                                @endphp
                                                <li class="mb-1">
                                                    <span class="badge badge-danger">{{ str_pad((string) $numFaltante, 8, '0', STR_PAD_LEFT) }}</span>
                                                    @if ($fuera !== null)
                                                        <span class="text-info">
                                                            <i class="fa fa-calendar"></i>
                                                            existe fuera del período ({{ $fuera }})
                                                        </span>
                                                    @elseif ($sinRegistro)
                                                        <span class="text-danger font-weight-bold">
                                                            <i class="fa fa-times"></i>
                                                            sin registro en el sistema
                                                        </span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
