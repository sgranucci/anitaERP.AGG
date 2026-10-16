@php
    $colspan = 14;
@endphp
<table>
    <tr>
        <td colspan="{{ $colspan }}" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'Control de ganancias' }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if (! empty($stats))
        <tr>
            <td colspan="{{ $colspan }}">
                Evaluados: {{ (int) ($stats['proveedores_evaluados'] ?? 0) }}
                · Filas: {{ (int) ($stats['filas'] ?? 0) }}
                · Discrepancias: {{ (int) ($stats['discrepancias'] ?? 0) }}
                · Consultas ARCA: {{ (int) ($stats['consultas_arca'] ?? 0) }}
            </td>
        </tr>
    @endif
    <tr>
        <th>CUIT</th>
        <th>Razón social</th>
        <th>Condición IVA ABM</th>
        <th>Segmento</th>
        <th>Estado</th>
        <th>Cód. ret. actual</th>
        <th>Retención actual</th>
        <th>Id actividad ARCA</th>
        <th>Actividad principal ARCA</th>
        <th>Cód. ret. propuesto</th>
        <th>Retención propuesta</th>
        <th>Padrón OK</th>
        <th>Discrepancia</th>
        <th>Observaciones</th>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['cuit'] ?? '' }}</td>
            <td>{{ $fila['razon_social'] ?? '' }}</td>
            <td>{{ $fila['condicioniva_nombre'] ?? '' }}</td>
            <td>{{ $fila['segmento_etiqueta'] ?? '' }}</td>
            <td>{{ $fila['estado'] ?? '' }}</td>
            <td>{{ $fila['retencion_actual_codigo'] ?? '' }}</td>
            <td>{{ $fila['retencion_actual_nombre'] ?? '' }}</td>
            <td>{{ $fila['actividad_id'] ?? '' }}</td>
            <td>{{ $fila['actividad_descripcion'] ?? '' }}</td>
            <td>{{ $fila['retencion_propuesta_codigo'] ?? '' }}</td>
            <td>{{ $fila['retencion_propuesta_nombre'] ?? '' }}</td>
            <td>{{ ($fila['padron_ok'] ?? null) === null ? '—' : (! empty($fila['padron_ok']) ? 'Sí' : 'No') }}</td>
            <td>{{ ! empty($fila['discrepancia']) ? 'Sí' : 'No' }}</td>
            <td>{{ $fila['observaciones'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
