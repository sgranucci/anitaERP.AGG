@php
    $coberturaPanel = $cobertura ?? [];
    $cargasPanel = $ultimas_cargas ?? collect();
    $badgeEstado = [
        'ok' => 'badge-success',
        'error' => 'badge-danger',
        'en_proceso' => 'badge-warning',
    ];
@endphp

<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-fw fa-database"></i> Padrones cargados
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Contraer">
                <i class="fa fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <h6 class="text-muted mb-2">Período vigente por provincia</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered mb-1">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Provincia</th>
                        <th class="text-center">Cód.</th>
                        <th class="text-center">Jurisdicción</th>
                        <th class="text-center">Período cargado</th>
                        <th class="text-right">Registros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($coberturaPanel as $fila)
                        <tr>
                            <td>{{ $fila['provincia'] }}</td>
                            <td class="text-center">{{ $fila['codigo'] }}</td>
                            <td class="text-center">{{ $fila['jurisdiccion'] }}</td>
                            <td class="text-center">
                                {{ \Carbon\Carbon::parse($fila['desdefecha'])->format('d/m/Y') }}
                                @if (! empty($fila['hastafecha']))
                                    a {{ \Carbon\Carbon::parse($fila['hastafecha'])->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($fila['filas'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Todavía no hay padrones provinciales cargados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <small class="form-text text-muted">
                Corresponde a la tabla de tasas por provincia (Córdoba, Entre Ríos, Misiones, Santa Fe y Tucumán).
                CABA y ARBA usan tablas propias: su estado se ve en las últimas importaciones.
            </small>
        </div>

        <h6 class="text-muted mb-2">Últimas importaciones</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Padrón</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Período</th>
                        <th class="text-right">Registros</th>
                        <th class="text-center">Duración</th>
                        <th class="text-center">Fecha</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cargasPanel as $carga)
                        <tr>
                            <td>
                                {{ $carga->etiqueta }}
                                @if ($carga->origen === 'consola')
                                    <span class="badge badge-secondary">consola</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $badgeEstado[$carga->estado] ?? 'badge-secondary' }}">
                                    @if ($carga->enProceso())
                                        <i class="fa fa-spinner fa-spin"></i> En proceso
                                    @else
                                        {{ $carga->estado === 'ok' ? 'Finalizada' : 'Con error' }}
                                    @endif
                                </span>
                            </td>
                            <td class="text-center">{{ $carga->periodoEtiqueta() }}</td>
                            <td class="text-right">{{ number_format($carga->filas_insertadas, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if ($carga->segundos !== null)
                                    {{ $carga->segundos }} s
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="text-center">{{ $carga->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ optional($carga->usuarios)->nombre ?? '—' }}</td>
                        </tr>
                        @if ($carga->estado === 'error' && $carga->mensaje)
                            <tr>
                                <td colspan="7" class="text-danger small">
                                    <i class="fa fa-exclamation-triangle"></i> {{ $carga->mensaje }}
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                Todavía no se registró ninguna importación desde esta pantalla.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
