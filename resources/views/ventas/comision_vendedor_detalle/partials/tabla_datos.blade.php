@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $formatearExcel = static fn ($v) => number_format((float) $v, 2, '.', '');
    $puedeVerVenta = $puede_ver_venta ?? false;
    $puedeVerCliente = $puede_ver_cliente ?? false;
    $puedeVerVendedor = $puede_ver_vendedor ?? false;
    $paraPdf = $para_pdf ?? false;
    $paraExcel = $para_excel ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
<thead>
    <tr>
        <th>Fecha</th>
        <th>N.Cli.</th>
        <th>Cliente</th>
        <th>Tip</th>
        <th>Nro.Comp.</th>
        <th class="text-right">Gravado</th>
        <th class="text-right">Porc.</th>
        <th class="text-right">Comisi&oacute;n</th>
        <th>C.U.I.T.</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'header_vendedor')
            <tr class="font-weight-bold" style="background-color: #85C1E9; color: #17202A;">
                <td colspan="9">
                    @if (! $paraPdf && ! $paraExcel && $puedeVerVendedor && (int) ($fila['vendedor_id'] ?? 0) > 0)
                        <a href="{{ route('editar_vendedor', array_merge(['id' => $fila['vendedor_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary font-weight-bold">
                            {{ $fila['descripcion'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['descripcion'] ?? '' }}
                    @endif
                    <span class="ml-2 small font-weight-normal">
                        {{ number_format((float) ($fila['porcentaje'] ?? 0), 2, ',', '.') }}%
                        {{ $fila['aplica_sobre'] ?? '' }}
                    </span>
                </td>
            </tr>
        @elseif ($tipo === 'total_vendedor' || $tipo === 'total_final')
            <tr class="font-weight-bold" style="background-color: {{ $tipo === 'total_final' ? '#AED6F1' : '#D6EAF8' }};">
                <td colspan="5">{{ $fila['descripcion'] ?? '' }}</td>
                <td class="text-right">
                    @if ($paraExcel)
                        {{ $formatearExcel($fila['gravado'] ?? 0) }}
                    @else
                        {{ $formatear($fila['gravado'] ?? 0) }}
                    @endif
                </td>
                <td></td>
                <td class="text-right">
                    @if ($paraExcel)
                        {{ $formatearExcel($fila['comision'] ?? 0) }}
                    @else
                        {{ $formatear($fila['comision'] ?? 0) }}
                    @endif
                </td>
                <td></td>
            </tr>
        @else
            <tr>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>
                    @if (! $paraPdf && ! $paraExcel && $puedeVerCliente && (int) ($fila['cliente_id'] ?? 0) > 0)
                        <a href="{{ route('editar_cliente', array_merge(['id' => $fila['cliente_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['cliente_codigo'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['cliente_codigo'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>
                    @if (! $paraPdf && ! $paraExcel && $puedeVerVenta && (int) ($fila['venta_id'] ?? 0) > 0)
                        <a href="{{ route('editar_factura', array_merge(['id' => $fila['venta_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['comprobante'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['comprobante'] ?? '' }}
                    @endif
                </td>
                <td class="text-right">
                    @if ($paraExcel)
                        {{ $formatearExcel($fila['gravado'] ?? 0) }}
                    @else
                        {{ $formatear($fila['gravado'] ?? 0) }}
                    @endif
                </td>
                <td class="text-right">
                    @if ($paraExcel)
                        {{ $formatearExcel($fila['porcentaje'] ?? 0) }}
                    @else
                        {{ $formatear($fila['porcentaje'] ?? 0) }}
                    @endif
                </td>
                <td class="text-right">
                    @if ($paraExcel)
                        {{ $formatearExcel($fila['comision'] ?? 0) }}
                    @else
                        {{ $formatear($fila['comision'] ?? 0) }}
                    @endif
                </td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="9" class="text-center text-muted">Sin movimientos en el per&iacute;odo.</td>
        </tr>
    @endforelse
</tbody>
