@php
    $puedeVerRemesa = $puede_ver_remesa ?? false;
    $filasTabla = $filas ?? [];
@endphp
<table class="table table-bordered table-hover table-sm mb-0" id="tabla-remesa-reporte" style="font-size: 0.85rem;">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th>Remesa</th>
            <th>Mon</th>
            <th class="text-right">Cotizaci&oacute;n</th>
            <th class="text-right">Importe origen</th>
            <th class="text-right">Importe</th>
            <th>Estado</th>
            <th>Empr.</th>
            <th>Origen</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filasTabla as $fila)
            @php $tipoFila = $fila['tipo_fila'] ?? 'dato'; @endphp
            @if ($tipoFila === 'grupo')
                <tr class="remesa-reporte-grupo">
                    <td colspan="9"><strong>Cuenta: {{ $fila['cuenta_etiqueta'] ?? '' }}</strong></td>
                </tr>
            @elseif ($tipoFila === 'total_cuenta' || $tipoFila === 'total_general')
                <tr class="{{ $tipoFila === 'total_general' ? 'remesa-reporte-total-general' : 'remesa-reporte-total' }}">
                    <td colspan="4" class="text-right"><strong>{{ $fila['cuenta_etiqueta'] ?? 'Total' }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($fila['importe_origen'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td colspan="3"></td>
                </tr>
            @else
                <tr>
                    <td>{{ $fila['fecha'] ?? '' }}</td>
                    <td>
                        @if ($puedeVerRemesa && (int) ($fila['remesa_id'] ?? 0) > 0)
                            <a href="{{ route('editar_remesa', ['id' => $fila['remesa_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="text-primary" target="_blank" rel="noopener">
                                {{ $fila['remesa_nro'] ?? '' }}
                            </a>
                        @else
                            {{ $fila['remesa_nro'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['moneda'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cotizacion'] ?? 0), 4, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['importe_origen'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $fila['estado'] ?? '' }}</td>
                    <td>{{ $fila['empresa_id'] ?? '' }}</td>
                    <td>
                        <span class="badge {{ ($fila['fuente'] ?? '') === 'ERP' ? 'badge-info' : 'badge-secondary' }}">
                            {{ $fila['fuente'] ?? '' }}
                        </span>
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="9" class="text-center text-muted py-4">Sin remesas para los filtros aplicados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
