@php
    $formatear = static fn ($valor) => number_format((float) $valor, 2, ',', '.');
    $componentes = $auditoria['componentes'] ?? [];
    $registros = $auditoria['registros'] ?? [];
@endphp

<div class="alert alert-info py-2">
    <strong>{{ $auditoria['etiqueta'] ?? '' }}</strong>
    · {{ $auditoria['fecha'] ?? '' }}
    · <span class="font-weight-bold">{{ $formatear($auditoria['importe'] ?? 0) }}</span>
    <div class="small mt-1">
        Fuentes: {{ implode(' · ', $auditoria['fuentes'] ?? []) }}
    </div>
</div>

@if ($componentes !== [])
    <h6>Composición del cálculo</h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Operación</th>
                    <th>Concepto</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($componentes as $componente)
                    <tr>
                        <td class="text-center" style="width:70px;">{{ $componente['operacion'] ?? '+' }}</td>
                        <td>{{ $componente['etiqueta'] ?? '' }}</td>
                        <td class="text-right">{{ $formatear($componente['importe'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h6>{{ empty($auditoria['fecha_ymd']) ? 'Detalle mensual' : 'Registros de origen del día' }}</h6>
@if ($registros === [])
    <div class="text-muted">
        @if (empty($auditoria['fecha_ymd']))
            Para ver los registros individuales, abra el importe del día correspondiente.
        @else
            No hay un registro individual adicional: el importe surge de la fórmula o consolidación indicada arriba.
        @endif
    </div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fuente</th>
                    <th>Referencia</th>
                    <th>Detalle</th>
                    <th class="text-right">Importe</th>
                    <th class="text-center">Abrir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registros as $registro)
                    <tr>
                        <td>{{ $registro['fuente'] ?? '' }}</td>
                        <td>{{ $registro['referencia'] ?? '' }}</td>
                        <td>{{ $registro['detalle'] ?? '' }}</td>
                        <td class="text-right">{{ $formatear($registro['importe'] ?? 0) }}</td>
                        <td class="text-center">
                            @if (! empty($registro['url']))
                                <a href="{{ $registro['url'] }}" class="btn btn-outline-primary btn-xs"
                                   target="_blank" rel="noopener" title="Abrir el documento origen en otra solapa">
                                    <i class="fa fa-external-link-alt"></i> Abrir
                                </a>
                            @else
                                <span class="text-muted" title="Sin documento equivalente en ERP: el dato vive solo en Anita o falta permiso para consultarlo">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
