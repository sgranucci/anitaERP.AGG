@php
    $resumen = $preview['resumen'] ?? [];
    $filas = $preview['filas'] ?? [];
    $advertencias = $preview['advertencias'] ?? [];
    $limitePantalla = (int) config('ventas_transferencia_crown.preview_filas_pantalla', 300);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $filasPantalla = $limitePantalla > 0 && $totalFilas > $limitePantalla
        ? array_slice(is_array($filas) ? $filas : $filas->all(), 0, $limitePantalla)
        : $filas;
@endphp

<div class="row mb-3">
    <div class="col-md-3 col-6">
        <div class="small-box bg-info mb-2">
            <div class="inner">
                <h3>{{ (int) ($resumen['leidos'] ?? 0) }}</h3>
                <p>Leídos</p>
            </div>
            <div class="icon"><i class="fa fa-list"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-success mb-2">
            <div class="inner">
                <h3>{{ (int) ($resumen['nuevos'] ?? 0) }}</h3>
                <p>Para generar</p>
            </div>
            <div class="icon"><i class="fa fa-check"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-primary mb-2">
            <div class="inner">
                <h3>{{ (int) ($resumen['enganchar'] ?? 0) }}</h3>
                <p>Enganchar asiento</p>
            </div>
            <div class="icon"><i class="fa fa-link"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-secondary mb-2">
            <div class="inner">
                <h3>{{ (int) ($resumen['existen'] ?? 0) }}</h3>
                <p>Ya con asiento</p>
            </div>
            <div class="icon"><i class="fa fa-files-o"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-warning mb-2">
            <div class="inner">
                <h3>{{ number_format((float) ($resumen['total_nuevos'] ?? 0), 2, ',', '.') }}</h3>
                <p>Total a generar</p>
            </div>
            <div class="icon"><i class="fa fa-dollar"></i></div>
        </div>
    </div>
</div>

@foreach ($advertencias as $aviso)
    <div class="alert alert-light border small mb-2">{{ $aviso }}</div>
@endforeach

@if ((int) ($resumen['errores'] ?? 0) > 0)
    <div class="alert alert-warning">
        Hay {{ (int) $resumen['errores'] }} comprobantes que no se pueden generar. El motivo está en la columna Aviso.
    </div>
@endif

@if ($totalFilas > $limitePantalla)
    <div class="alert alert-light border small mb-2">
        Se muestran las primeras {{ $limitePantalla }} de {{ number_format($totalFilas, 0, ',', '.') }} comprobantes.
        Al generar se procesan todos los del preview.
    </div>
@endif

<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Comprobante</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th class="text-right">Gravado</th>
                <th class="text-right">IVA</th>
                <th class="text-right">Total</th>
                <th>Asiento</th>
                <th>Estado</th>
                <th>Aviso</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filasPantalla as $fila)
                @php
                    $estado = (string) ($fila['estado'] ?? '');
                    $badge = match ($estado) {
                        'nuevo' => 'badge-success',
                        'enganchar' => 'badge-primary',
                        'existe' => 'badge-secondary',
                        default => 'badge-danger',
                    };
                    $etiqueta = match ($estado) {
                        'nuevo' => 'Nuevo',
                        'enganchar' => 'Enganchar',
                        'existe' => 'Ya está',
                        default => 'Error',
                    };
                    $gravado = (float) ($fila['gravado21'] ?? 0) + (float) ($fila['gravado105'] ?? 0) + (float) ($fila['gravado27'] ?? 0);
                    $iva = (float) ($fila['iva21'] ?? 0) + (float) ($fila['iva105'] ?? 0) + (float) ($fila['iva27'] ?? 0);
                    $signo = str_starts_with((string) ($fila['tipo'] ?? ''), 'NC') ? -1 : 1;
                @endphp
                <tr>
                    <td class="text-nowrap">
                        {{ $fila['tipo'] ?? '' }} {{ $fila['letra'] ?? '' }}
                        {{ str_pad((string) (int) ($fila['sucursal'] ?? 0), 5, '0', STR_PAD_LEFT) }}-{{ str_pad((string) (int) ($fila['numero'] ?? 0), 8, '0', STR_PAD_LEFT) }}
                        @if (! empty($fila['tipo_anita']) && ($fila['tipo_anita'] ?? '') !== ($fila['tipo'] ?? ''))
                            <div class="small text-muted">En origen: {{ $fila['tipo_anita'] }}</div>
                        @endif
                        @if (! empty($fila['tipo_afip']))
                            <div class="small text-muted">AFIP {{ (int) $fila['tipo_afip'] }}</div>
                        @endif
                    </td>
                    <td class="text-nowrap">{{ ! empty($fila['fecha']) ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($signo * $gravado, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($signo * $iva, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($signo * (float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
                    <td>
                        @if (($fila['asiento_origen'] ?? '') === 'subdiario')
                            <span class="badge badge-info">Subdiario</span>
                        @elseif (($fila['lineas_asiento'] ?? []) !== [])
                            <span class="badge badge-primary">A generar</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $badge }}">{{ $etiqueta }}</span></td>
                    <td class="small">{{ $fila['mensaje'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted">No hay comprobantes en el rango.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
