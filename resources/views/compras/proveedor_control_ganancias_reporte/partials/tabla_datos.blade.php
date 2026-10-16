<thead class="thead-light">
    <tr>
        <th>CUIT</th>
        <th>Razón social</th>
        <th>Condición IVA ABM</th>
        <th>Cód. ret. actual</th>
        <th>Actividad ARCA</th>
        <th>Cód. ret. propuesto</th>
        <th>Padrón</th>
        <th>Obs.</th>
    </tr>
</thead>
<tbody>
@forelse ($filas as $fila)
    <tr class="{{ ! empty($fila['discrepancia']) ? 'table-warning' : '' }}">
        <td class="text-nowrap">{{ $fila['cuit'] ?? '' }}</td>
        <td>
            @if (! empty($puede_ver_proveedor) && ! empty($fila['proveedor_id']))
                <a href="{{ route('editar_proveedor', $fila['proveedor_id']) }}" target="_blank" rel="noopener">
                    {{ $fila['razon_social'] ?? '' }}
                </a>
            @else
                {{ $fila['razon_social'] ?? '' }}
            @endif
            <div class="small text-muted">{{ $fila['codigo'] ?? '' }} · {{ $fila['segmento_etiqueta'] ?? '' }}</div>
        </td>
        <td>
            {{ $fila['condicioniva_nombre'] ?? '' }}
            @if (($fila['condicioniva_ok_mono'] ?? null) === false)
                <span class="badge badge-danger">≠ esperado</span>
            @endif
        </td>
        <td class="text-nowrap">
            {{ $fila['retencion_actual_codigo'] ?? '' }}
            <div class="small text-muted">{{ $fila['retencion_actual_nombre'] ?? '' }}</div>
        </td>
        <td>
            @if (! empty($fila['actividad_id']))
                <span class="text-nowrap">{{ $fila['actividad_id'] }}</span>
            @endif
            <div class="small">{{ $fila['actividad_descripcion'] ?? '—' }}</div>
        </td>
        <td class="text-nowrap">
            {{ $fila['retencion_propuesta_codigo'] !== '' ? $fila['retencion_propuesta_codigo'] : '—' }}
            <div class="small text-muted">{{ $fila['retencion_propuesta_nombre'] ?? '' }}</div>
        </td>
        <td class="text-nowrap">
            @if (($fila['padron_ok'] ?? null) === null)
                <span class="text-muted">—</span>
            @elseif (! empty($fila['padron_ok']))
                <span class="text-success">OK</span>
            @else
                <span class="text-danger">Error</span>
            @endif
        </td>
        <td class="small">{{ $fila['observaciones'] ?? '' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="text-center text-muted py-3">Sin filas para los filtros aplicados.</td>
    </tr>
@endforelse
</tbody>
