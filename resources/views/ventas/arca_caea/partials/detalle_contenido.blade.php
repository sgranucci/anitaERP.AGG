@php
    $origenLabel = match ($registro->origen) {
        'import_anita' => 'Anita (histórico)',
        'automatico' => 'Automático',
        'manual' => 'Manual',
        default => $registro->origen,
    };
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0">
        <tbody>
            <tr>
                <th class="text-nowrap" style="width:38%">Empresa</th>
                <td>{{ $registro->empresa->nombre ?? '—' }}</td>
            </tr>
            <tr>
                <th>CUIT</th>
                <td>{{ $registro->cuit }}</td>
            </tr>
            <tr>
                <th>Periodo / quincena</th>
                <td>{{ $registro->periodo }} / Q{{ $registro->orden }}</td>
            </tr>
            <tr>
                <th>CAEA</th>
                <td><code>{{ $registro->nro_caea ?? '—' }}</code></td>
            </tr>
            <tr>
                <th>Estado</th>
                <td>
                    @if ($registro->estado === 'ok')
                        <span class="badge badge-success">OK</span>
                    @elseif ($registro->estado === 'observacion')
                        <span class="badge badge-warning">Observaciones</span>
                    @elseif ($registro->estado === 'error')
                        <span class="badge badge-danger">Error</span>
                    @else
                        <span class="badge badge-secondary">{{ $registro->estado }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Vigencia</th>
                <td>
                    @if ($registro->fecha_vigencia_desde && $registro->fecha_vigencia_hasta)
                        {{ $registro->fecha_vigencia_desde->format('d/m/Y') }}
                        al {{ $registro->fecha_vigencia_hasta->format('d/m/Y') }}
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <th>Tope informe</th>
                <td>{{ $registro->fecha_tope_informe?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            <tr>
                <th>Proceso ARCA</th>
                <td>{{ $registro->fecha_proceso?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            <tr>
                <th>Origen</th>
                <td>{{ $origenLabel }}</td>
            </tr>
            <tr>
                <th>Solicitado por</th>
                <td>{{ $registro->solicitadoPor->nombre ?? '—' }}</td>
            </tr>
            @if ($registro->mensaje_error)
                <tr>
                    <th class="text-danger">Error</th>
                    <td class="text-danger small">{{ $registro->mensaje_error }}</td>
                </tr>
            @endif
            @if ($registro->observaciones)
                <tr>
                    <th>Observaciones ARCA</th>
                    <td class="small">{{ $registro->observaciones['texto'] ?? json_encode($registro->observaciones) }}</td>
                </tr>
            @endif
            <tr>
                <th>Actualizado</th>
                <td>{{ $registro->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
            </tr>
        </tbody>
    </table>
</div>

@if ($puedeReintentar ?? false)
    <form method="post" action="{{ route('arca_caea_reintentar', $registro->id) }}" class="mt-3 mb-0">
        @csrf
        <button type="submit" class="btn btn-warning btn-sm">
            <i class="fa fa-refresh"></i> Reintentar solicitud
        </button>
    </form>
@endif
