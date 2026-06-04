@php
    $mostrarError = ! empty($error) && empty($solo_si_error ?? false);
    $filas = $bloque['filas'] ?? [];
    $metaSistema = $recepciones_meta['sistema_anita'] ?? null;
    $metaEmpresa = $recepciones_meta['empresa_anita'] ?? null;
    $metaCentroCosto = $recepciones_meta['centro_costo_codigo'] ?? null;
@endphp
@if ($mostrarError)
    <div class="p-3 text-warning small">
        <strong>Anita:</strong> {{ $error }}
        @if ($metaSistema)
            <br><span class="text-muted">Base: {{ $metaSistema }}@if($metaEmpresa) · empresa Anita {{ $metaEmpresa }}@endif</span>
        @endif
    </div>
@elseif (! empty($error) && ! empty($solo_si_error))
    {{-- error ya mostrado en bloque día --}}
@endif
<div class="px-3 py-2 small border-bottom bg-light">
    Comprobantes: <strong>{{ $bloque['cantidad_comprobantes'] ?? 0 }}</strong>
    — Importe: <strong>${{ number_format($bloque['importe_total'] ?? 0, 2, ',', '.') }}</strong>
    @if (! empty($recepciones_meta['sistema_anita']) && empty($error))
        <span class="text-muted d-block d-md-inline mt-1 mt-md-0">
            Anita · base {{ $recepciones_meta['sistema_anita'] }}
            @if (! empty($recepciones_meta['empresa_anita']))
                · empresa {{ $recepciones_meta['empresa_anita'] }}
            @endif
            @if (! empty($metaCentroCosto))
                · CC {{ $metaCentroCosto }}
            @endif
        </span>
    @endif
</div>
<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>Proveedor</th>
            <th>Comprobante</th>
            <th>Fecha</th>
            <th>Est.</th>
            <th class="text-right">Líneas</th>
            <th class="text-right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['proveedor_nombre'] ?? $fila['proveedor'] }}</td>
                <td>{{ $fila['comprobante'] }}</td>
                <td>{{ $fila['fecha'] }}</td>
                <td>{{ $fila['estado'] }}</td>
                <td class="text-right">{{ $fila['cantidad_lineas'] }}</td>
                <td class="text-right">${{ number_format($fila['importe'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">Sin recepciones en el período.</td></tr>
        @endforelse
    </tbody>
</table>
