@php
    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerComp = ! $paraPdf && ($puede_ver_comprobante ?? false);
    $puedeVerProv = ! $paraPdf && ($puede_ver_proveedor ?? false);
    $puedeVerAsiento = ! $paraPdf && ($puede_ver_asiento ?? false);
    $puedeVerPago = ! $paraPdf && ($puede_ver_pagoproveedor ?? false);
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Comprobante</th>
        <th>Proveedor</th>
        <th>Empresa</th>
        <th>Mon.</th>
        <th class="text-right">Cotiz.</th>
        <th class="text-right">Total orig.</th>
        <th class="text-right">Total $</th>
        <th class="text-right">Esperado $</th>
        <th class="text-right">AP MN $</th>
        <th class="text-right">AP ME $</th>
        <th class="text-right">Anticipo $</th>
        <th class="text-right">Imputado $</th>
        <th class="text-right">Diferencia</th>
        <th>Alertas</th>
    </tr>
</thead>
<tbody>
@forelse ($filas ?? [] as $fila)
    @php
        $ok = ! empty($fila['ok']);
        $filaClass = (! $ok && ! $paraPdf && ! $paraExcel) ? 'table-warning' : '';
    @endphp
    <tr class="{{ $filaClass }}">
        <td>
            {{ $fila['fecha'] ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}
        </td>
        <td>{{ $fila['tipo_etiqueta'] ?? '' }}</td>
        <td>
            @php
                $etiqueta = $fila['comprobante_etiqueta'] ?? '';
                $compId = (int) ($fila['comprobante_id'] ?? 0);
                $pagoId = (int) ($fila['pagoproveedor_id'] ?? 0);
            @endphp
            @if ($puedeVerComp && $compId > 0 && ($fila['tipo'] ?? '') === 'comprobante')
                <a href="{{ route('editar_comprobante_proveedor', array_merge(['id' => $compId], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $etiqueta }}</a>
            @elseif ($puedeVerPago && $pagoId > 0 && ($fila['tipo'] ?? '') === 'opa')
                <a href="{{ route('editar_pagoproveedor', array_merge(['id' => $pagoId], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $etiqueta }}</a>
            @else
                {{ $etiqueta }}
            @endif
            @if ($puedeVerAsiento && (int) ($fila['asiento_id'] ?? 0) > 0)
                <br>
                <a href="{{ route('editar_asiento', array_merge(['id' => $fila['asiento_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">As. {{ $fila['numeroasiento'] ?? $fila['asiento_id'] }}</a>
            @elseif (! empty($fila['numeroasiento']))
                <br><small>As. {{ $fila['numeroasiento'] }}</small>
            @endif
        </td>
        <td>
            @if ($puedeVerProv && (int) ($fila['proveedor_id'] ?? 0) > 0)
                <a href="{{ route('editar_proveedor', array_merge(['id' => $fila['proveedor_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['codigo_proveedor'] ?? '' }}</a>
            @else
                {{ $fila['codigo_proveedor'] ?? '' }}
            @endif
            @if (! empty($fila['nombre_proveedor']))
                <br><small>{{ $fila['nombre_proveedor'] }}</small>
            @endif
        </td>
        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
        <td>{{ $fila['moneda'] ?? '' }}</td>
        <td class="text-right">{{ number_format((float) ($fila['cotizacion'] ?? 0), 4, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['total_origen'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['total_ars'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['esperado_ars'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['ap_mn_ars'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['ap_me_ars'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['anticipo_ars'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['imputado_ars'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right {{ $ok ? '' : 'text-danger font-weight-bold' }}">
            {{ number_format((float) ($fila['diferencia'] ?? 0), 2, ',', '.') }}
        </td>
        <td class="{{ $ok ? 'text-success' : 'text-danger' }}">
            {{ $fila['alertas_texto'] ?? ($ok ? 'OK' : '') }}
        </td>
    </tr>
@empty
    <tr>
        <td colspan="16" class="text-center text-muted">No hay documentos en el per&iacute;odo.</td>
    </tr>
@endforelse
</tbody>
