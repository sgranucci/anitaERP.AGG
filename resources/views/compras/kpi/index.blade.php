@extends("theme.$theme.layout")
@section('titulo')
KPIs de Compras
@endsection

@section('contenido')
@php
    $proceso = $tablero['proceso'] ?? [];
    $metas = $tablero['metas'] ?? [];
    $ciclo = $proceso['ciclo_rq_oc'] ?? [];
    $gestion = $proceso['gestion_oc'] ?? [];
    $circuito = $proceso['circuito_com'] ?? [];
    $abiertas = $proceso['oc_abiertas'] ?? [];
    $prod = $tablero['productividad'] ?? [];
    $metaCiclo = (float) ($metas['ciclo_dias'] ?? 2);
    $metaGestion = (float) ($metas['gestion_oc_dias'] ?? 2);
    $metaPct = (float) ($metas['pct_oc_abiertas'] ?? 10);

    $badgeMeta = function (bool $cumple) {
        return $cumple
            ? '<span class="badge badge-success">Cumple meta</span>'
            : '<span class="badge badge-danger">Fuera de meta</span>';
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i> KPIs de Compras
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('consultar_ordencompra') }}" class="btn btn-outline-info btn-sm" title="Volver a órdenes de compra">
                        <i class="fa fa-reply-all"></i> Volver a OC
                    </a>
                </div>
            </div>

            @include('compras.ordencompra.partials.filtros_externos', [
                'rutaIndex' => 'consultar_kpi_compras',
                'filtrosQuery' => $filtrosQuery ?? [],
            ])

            <div class="card-body border-bottom py-3">
                <form method="get" action="{{ route('consultar_kpi_compras') }}" class="form-inline flex-wrap">
                    @if (($filtros['empresa_scope'] ?? 'una') === 'todas')
                        <input type="hidden" name="empresa_todas" value="1">
                    @elseif (!empty($filtros['empresa_id']))
                        <input type="hidden" name="empresa_id" value="{{ (int) $filtros['empresa_id'] }}">
                    @endif
                    <label class="mr-2 mb-2" for="fecha_desde">Desde</label>
                    <input type="date" class="form-control form-control-sm mr-3 mb-2" id="fecha_desde"
                           name="fecha_desde" value="{{ $fecha_desde }}">
                    <label class="mr-2 mb-2" for="fecha_hasta">Hasta</label>
                    <input type="date" class="form-control form-control-sm mr-3 mb-2" id="fecha_hasta"
                           name="fecha_hasta" value="{{ $fecha_hasta }}">
                    <button type="submit" class="btn btn-info btn-sm mb-2">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </form>
                <p class="text-muted small mb-0 mt-2">
                    Período {{ date('d/m/Y', strtotime($fecha_desde)) }} → {{ date('d/m/Y', strtotime($fecha_hasta)) }}.
                    Metas: ciclo y gestión de OC &lt; {{ number_format($metaCiclo, 0, ',', '.') }} días;
                    % OC abiertas &lt; {{ number_format($metaPct, 0, ',', '.') }}%.
                </p>
            </div>

            <div class="card-body">
                <h5 class="mb-3"><i class="fas fa-cogs"></i> KPIs de proceso</h5>
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 {{ (($ciclo['cumple_meta'] ?? false) || ($ciclo['muestra'] ?? 0) === 0) ? 'bg-light' : '' }}"
                             @if (($ciclo['muestra'] ?? 0) > 0 && empty($ciclo['cumple_meta'])) style="background:#fdecea;" @endif>
                            <div class="text-muted small">Ciclo de compra</div>
                            <div class="small text-muted mb-1">RQ aprobada → OC emitida</div>
                            <div class="h4 mb-1">
                                @if (($ciclo['muestra'] ?? 0) > 0)
                                    {{ number_format((float) $ciclo['promedio_dias'], 1, ',', '.') }} d
                                @else
                                    —
                                @endif
                            </div>
                            <div class="small">Meta &lt; {{ number_format($metaCiclo, 0, ',', '.') }} d · n={{ (int) ($ciclo['muestra'] ?? 0) }}</div>
                            @if (($ciclo['muestra'] ?? 0) > 0)
                                <div class="mt-1">{!! $badgeMeta(!empty($ciclo['cumple_meta'])) !!}</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 {{ (($gestion['cumple_meta'] ?? false) || ($gestion['muestra'] ?? 0) === 0) ? 'bg-light' : '' }}"
                             @if (($gestion['muestra'] ?? 0) > 0 && empty($gestion['cumple_meta'])) style="background:#fdecea;" @endif>
                            <div class="text-muted small">Gestión de OC</div>
                            <div class="small text-muted mb-1">Carga → emisión</div>
                            <div class="h4 mb-1">
                                @if (($gestion['muestra'] ?? 0) > 0)
                                    {{ number_format((float) $gestion['promedio_dias'], 1, ',', '.') }} d
                                @else
                                    —
                                @endif
                            </div>
                            <div class="small">Meta &lt; {{ number_format($metaGestion, 0, ',', '.') }} d · n={{ (int) ($gestion['muestra'] ?? 0) }}</div>
                            @if (($gestion['muestra'] ?? 0) > 0)
                                <div class="mt-1">{!! $badgeMeta(!empty($gestion['cumple_meta'])) !!}</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Circuito hasta COM</div>
                            <div class="small text-muted mb-1">RQ aprobada → 1ª recepción</div>
                            <div class="h4 mb-1">
                                @if (($circuito['muestra'] ?? 0) > 0)
                                    {{ number_format((float) $circuito['promedio_dias'], 1, ',', '.') }} d
                                @else
                                    —
                                @endif
                            </div>
                            <div class="small">n={{ (int) ($circuito['muestra'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 {{ !empty($abiertas['cumple_meta']) ? 'bg-light' : '' }}"
                             @if (empty($abiertas['cumple_meta'])) style="background:#fdecea;" @endif>
                            <div class="text-muted small">% OC abiertas</div>
                            <div class="small text-muted mb-1">Con saldo pendiente / total</div>
                            <div class="h4 mb-1">
                                {{ number_format((float) ($abiertas['porcentaje'] ?? 0), 1, ',', '.') }}%
                            </div>
                            <div class="small">
                                {{ number_format((int) ($abiertas['abiertas'] ?? 0), 0, ',', '.') }}
                                /
                                {{ number_format((int) ($abiertas['total'] ?? 0), 0, ',', '.') }}
                                · Meta &lt; {{ number_format($metaPct, 0, ',', '.') }}%
                            </div>
                            <div class="mt-1">{!! $badgeMeta(!empty($abiertas['cumple_meta'])) !!}</div>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3"><i class="fas fa-user-tie"></i> KPIs de productividad</h5>
                <div class="row mb-3">
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">OC gestionadas (período)</div>
                            <div class="h4 mb-0">{{ number_format((int) ($prod['total_oc'] ?? 0), 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Productividad del área</div>
                            <div class="small text-muted mb-1">OC / comprador</div>
                            <div class="h4 mb-0">
                                {{ number_format((float) ($prod['productividad_area_oc_por_comprador'] ?? 0), 1, ',', '.') }}
                            </div>
                            <div class="small">{{ (int) ($prod['compradores'] ?? 0) }} comprador(es)</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Ahorro generado</div>
                            <div class="h4 mb-0">
                                $ {{ number_format((float) ($prod['ahorro_total'] ?? 0), 2, ',', '.') }}
                            </div>
                            <div class="small">
                                {{ number_format((float) ($prod['ahorro_pct_area'] ?? 0), 1, ',', '.') }}%
                                sobre base con precio original
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Comprador</th>
                                <th class="text-right">OC gestionadas</th>
                                <th class="text-right">Ahorro $</th>
                                <th class="text-right">Ahorro %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($tablero['productividad_tabla'] ?? []) as $fila)
                                <tr>
                                    <td>{{ $fila['comprador'] ?? '—' }}</td>
                                    <td class="text-right">{{ $fila['oc'] ?? '0' }}</td>
                                    <td class="text-right">{{ $fila['ahorro'] ?? '—' }}</td>
                                    <td class="text-right">{{ $fila['pct_ahorro'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        Sin OC ni ahorro en el período / empresa seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0">
                    Solo usuarios con rol
                    {{ implode(', ', $prod['roles_comprador'] ?? ['Enc-compras', 'Op-Compras']) }}
                    (configurable en COMPRAS_KPI_ROLES_COMPRADOR).
                    Las OC importadas desde Anita se listan como comprador
                    <strong>ANITA</strong>
                    y no distorsionan el promedio OC/comprador ERP.
                    Ahorro: (precio original − precio) × cantidad; se atribuye al creador de la OC vinculada a la RQ (o al de la RQ si aún no hay OC), siempre que sea rol de compras.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
