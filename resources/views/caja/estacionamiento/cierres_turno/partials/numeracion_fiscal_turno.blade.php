@php
    $numeracion = $numeracion ?? ($d['numeracion_fiscal'] ?? []);
    $filasNumeracion = $numeracion['filas'] ?? [];
    $modoWeb = (bool) ($modo_web ?? false);
@endphp
@if (count($filasNumeracion) > 0)
@if ($modoWeb)
<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2"><strong>Numeración fiscal del turno (esta PC)</strong></div>
    <div class="card-body py-2">
        <p class="text-muted small mb-2">
            Último número emitido en el turno por punto de venta CAE/CAEA configurado en la terminal.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
@else
<h2>Numeración fiscal del turno (esta PC)</h2>
<p class="muted" style="font-size:11px; margin:0 0 8px;">
    Último número emitido en el turno por punto de venta CAE/CAEA configurado en la terminal.
</p>
<table>
@endif
    <thead>
        <tr>
            <th>Modo</th>
            <th>Punto de venta</th>
            <th class="{{ $modoWeb ? 'text-right' : 'num' }}">Último ticket</th>
            <th class="{{ $modoWeb ? 'text-right' : 'num' }}">Tickets emitidos</th>
            <th class="{{ $modoWeb ? 'text-right' : 'num' }}">Última nota de crédito</th>
            <th class="{{ $modoWeb ? 'text-right' : 'num' }}">NC emitidas</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filasNumeracion as $fila)
        <tr>
            <td><strong>{{ $fila['rol_etiqueta'] ?? '' }}</strong></td>
            <td>
                PV {{ $fila['puntoventa_codigo'] ?? '—' }}
                @if (! empty($fila['puntoventa_nombre']))
                    — {{ $fila['puntoventa_nombre'] }}
                @endif
            </td>
            <td class="{{ $modoWeb ? 'text-right' : 'num' }}">
                @if (! empty($fila['ultimo_ticket']))
                    {{ number_format((int) $fila['ultimo_ticket'], 0, ',', '.') }}
                @else
                    —
                @endif
            </td>
            <td class="{{ $modoWeb ? 'text-right' : 'num' }}">{{ (int) ($fila['cantidad_tickets'] ?? 0) }}</td>
            <td class="{{ $modoWeb ? 'text-right' : 'num' }}">
                @if (! empty($fila['ultimo_nota_credito']))
                    {{ number_format((int) $fila['ultimo_nota_credito'], 0, ',', '.') }}
                @else
                    —
                @endif
            </td>
            <td class="{{ $modoWeb ? 'text-right' : 'num' }}">{{ (int) ($fila['cantidad_notas_credito'] ?? 0) }}</td>
        </tr>
        @endforeach
    </tbody>
@if ($modoWeb)
            </table>
        </div>
    </div>
</div>
@else
</table>
@endif
@endif
