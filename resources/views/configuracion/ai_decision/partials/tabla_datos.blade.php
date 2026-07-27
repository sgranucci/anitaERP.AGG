@if ($coleccion === null || $coleccion->count() === 0)
    <div class="alert alert-warning mb-0">No hay decisiones con los filtros indicados.</div>
@else
    <div class="table-responsive">
        <table id="tabla-paginada" class="table table-bordered table-striped table-sm">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Skill</th>
                    <th>Acción</th>
                    <th>Score</th>
                    <th>Latencia</th>
                    <th>Usuario</th>
                    <th>Resuelto por</th>
                    <th>Entidad</th>
                    <th>Modelo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coleccion as $fila)
                    <tr>
                        <td>{{ $fila->id }}</td>
                        <td>{{ optional($fila->created_at)->format('d/m/Y H:i') }}</td>
                        <td><small>{{ \App\Support\Configuracion\AiDecisionListadoFiltros::etiquetaSkill($fila->skill) }}</small></td>
                        <td>
                            @php $acc = $fila->accion; @endphp
                            <span class="badge
                                @if ($acc === 'confirmada') badge-success
                                @elseif ($acc === 'editada') badge-info
                                @elseif ($acc === 'descartada') badge-secondary
                                @elseif ($acc === 'error') badge-danger
                                @elseif ($acc === 'sugerida') badge-warning
                                @else badge-light
                                @endif">
                                {{ \App\Support\Configuracion\AiDecisionListadoFiltros::etiquetaAccion($acc) }}
                            </span>
                        </td>
                        <td>{{ $fila->score !== null ? number_format((float) $fila->score, 2) : '—' }}</td>
                        <td>{{ $fila->latencia_ms !== null ? number_format($fila->latencia_ms).' ms' : '—' }}</td>
                        <td><small>{{ $fila->usuario->nombre ?? '—' }}</small></td>
                        <td><small>{{ $fila->resolutor->nombre ?? '—' }}</small></td>
                        <td>
                            @if ($fila->entidad_id)
                                <small>{{ $fila->entidad_tipo }} #{{ $fila->entidad_id }}</small>
                            @else
                                —
                            @endif
                        </td>
                        <td><small>{{ $fila->model ?: ($fila->driver ?: '—') }}</small></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="text-muted">
            {{ $coleccion->firstItem() }}–{{ $coleccion->lastItem() }} de {{ $coleccion->total() }}
        </small>
        {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
    </div>
@endif
