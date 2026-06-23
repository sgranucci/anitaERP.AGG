@php
    $paraPdf = $para_pdf ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerArticulo = ! $paraPdf && ($puede_ver_articulo ?? false);
    $puedeVerRequisicion = ! $paraPdf && ($puede_ver_requisicion ?? false);
    $puedeVerCentrocosto = ! $paraPdf && ($puede_ver_centrocosto ?? false);
    $formatearNum = static function ($v, $dec = 2) {
        $formatted = number_format((float) $v, $dec, ',', '.');
        if ($dec <= 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), ',');
    };
    $envoltorioTabla = ! ($solo_filas ?? false);
@endphp
@if ($envoltorioTabla && ! ($solo_body ?? false))
<thead>
    <tr>
        <th>Art&iacute;culo</th>
        <th>Descripci&oacute;n</th>
        <th>Art&iacute;culo pro.</th>
        <th>Requis.</th>
        <th>Fecha</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Entreg.</th>
        <th class="text-right">Pend.</th>
        <th class="text-right">Precio</th>
        <th>C.cos.</th>
        <th>Leyenda</th>
        <th>UID</th>
        <th>NPU</th>
        <th class="text-center">F/S</th>
        <th>Destino</th>
        <th>Estado</th>
        <th>Entrega parcial</th>
        <th>Fec. ent.</th>
        <th>Remito</th>
        <th>Responsable</th>
        <th>T&eacute;cnico</th>
        <th>Empresa</th>
    </tr>
</thead>
@elseif ($cabecera_en_filas ?? false)
    <tr>
        <th>Art&iacute;culo</th>
        <th>Descripci&oacute;n</th>
        <th>Art&iacute;culo pro.</th>
        <th>Requis.</th>
        <th>Fecha</th>
        <th>Cantidad</th>
        <th>Entreg.</th>
        <th>Pend.</th>
        <th>Precio</th>
        <th>C.cos.</th>
        <th>Leyenda</th>
        <th>UID</th>
        <th>NPU</th>
        <th>F/S</th>
        <th>Destino</th>
        <th>Estado</th>
        <th>Entrega parcial</th>
        <th>Fec. ent.</th>
        <th>Remito</th>
        <th>Responsable</th>
        <th>T&eacute;cnico</th>
        <th>Empresa</th>
    </tr>
@endif
@if ($envoltorioTabla)
<tbody>
@endif
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'cabecera_requisicion')
            <tr class="{{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}">
                <td colspan="22" @if ($paraPdf) style="background-color: #e9ecef; font-weight: bold;" @endif>
                    Requisici&oacute;n:
                    @if ($puedeVerRequisicion && (int) ($fila['requisicion_sala_id'] ?? 0) > 0)
                        <a href="{{ route('editar_requisicion_sala', array_merge(['id' => $fila['requisicion_sala_id']], $queryConsulta)) }}"
                           class="text-primary"
                           target="_blank"
                           rel="noopener">{{ $fila['numerorequisicion'] ?? '' }}</a>
                    @else
                        {{ $fila['numerorequisicion'] ?? '' }}
                    @endif
                </td>
            </tr>
        @elseif ($tipo === 'cabecera_usuario')
            <tr class="{{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}">
                <td colspan="22" @if ($paraPdf) style="background-color: #e9ecef; font-weight: bold;" @endif>
                    Usuario: {{ $fila['usuario_id'] ?? '' }}
                    {{ $fila['usuario_nombre'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipo === 'subtotal_requisicion')
            <tr class="{{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color: #e9ecef;">
                <td colspan="5">Total requisici&oacute;n {{ $fila['numerorequisicion'] ?? '' }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="15"></td>
            </tr>
            <tr><td colspan="22">&nbsp;</td></tr>
        @elseif ($tipo === 'subtotal_usuario')
            <tr class="{{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color: #e9ecef;">
                <td colspan="5">
                    Total usuario {{ $fila['usuario_id'] ?? '' }}
                    {{ $fila['usuario_nombre'] ?? '' }}
                </td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="15"></td>
            </tr>
            <tr><td colspan="22">&nbsp;</td></tr>
        @elseif ($tipo === 'total_general')
            <tr class="{{ $paraPdf ? 'total' : 'font-weight-bold' }}" style="background-color: #d6eaf8;">
                <td colspan="5">Total general</td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="15"></td>
            </tr>
        @else
            <tr>
                <td>
                    @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                        <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                           class="text-primary"
                           target="_blank"
                           rel="noopener">{{ $fila['sku'] ?? '' }}</a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                </td>
                <td><small>{{ $fila['descripcion'] ?? '' }}</small></td>
                <td><small>{{ $fila['articulo_proveedor'] ?? '' }}</small></td>
                <td>
                    @if ($puedeVerRequisicion && (int) ($fila['requisicion_sala_id'] ?? 0) > 0)
                        <a href="{{ route('editar_requisicion_sala', array_merge(['id' => $fila['requisicion_sala_id']], $queryConsulta)) }}"
                           class="text-primary"
                           target="_blank"
                           rel="noopener">{{ $fila['numerorequisicion'] ?? '' }}</a>
                    @else
                        {{ $fila['numerorequisicion'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if (! empty($fila['fecha']))
                        {{ \Carbon\Carbon::parse($fila['fecha'])->format('j/n/Y') }}
                    @endif
                </td>
                <td class="text-right">{{ $formatearNum($fila['cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['pendiente'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['precio'] ?? 0) }}</td>
                <td>
                    @if ($puedeVerCentrocosto && (int) ($fila['centrocosto_id'] ?? 0) > 0)
                        <a href="{{ route('editar_centrocosto', array_merge(['id' => $fila['centrocosto_id']], $queryConsulta)) }}"
                           class="text-primary"
                           target="_blank"
                           rel="noopener">{{ $fila['centrocosto_codigo'] ?? '' }}</a>
                    @else
                        {{ $fila['centrocosto_codigo'] ?? '' }}
                    @endif
                </td>
                <td><small>{{ $fila['leyenda'] ?? '' }}</small></td>
                <td>{{ $fila['uid'] ?? '' }}</td>
                <td>{{ $fila['numeroparte'] ?? '' }}</td>
                <td class="text-center">{{ $fila['fueradeservicio'] ?? 'N' }}</td>
                <td>{{ $fila['destino'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td><small>{{ $fila['entrega_parcial'] ?? '' }}</small></td>
                <td>{{ $fila['fecha_entrega'] ?? '' }}</td>
                <td>{{ $fila['numeroremito'] ?? '' }}</td>
                <td><small>{{ $fila['responsable'] ?? '' }}</small></td>
                <td><small>{{ $fila['tecnico'] ?? '' }}</small></td>
                <td><small>{{ $fila['nombreempresa'] ?? '' }}</small></td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="22" class="text-center text-muted py-4">Sin requisiciones para los filtros indicados.</td>
        </tr>
    @endforelse
@if ($envoltorioTabla)
</tbody>
@endif
