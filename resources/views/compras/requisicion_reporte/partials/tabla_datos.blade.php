@php
    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerArticulo = ! $paraPdf && ($puede_ver_articulo ?? false);
    $puedeVerRequisicion = ! $paraPdf && ($puede_ver_requisicion ?? false);
    $puedeVerCentrocosto = ! $paraPdf && ($puede_ver_centrocosto ?? false);
    $puedeVerOrdencompra = ! $paraPdf && ($puede_ver_ordencompra ?? false);
    $colSpan = 34;
    $formatearNum = static function ($v, $dec = 2) use ($paraExcel) {
        if ($paraExcel && $dec === 2) {
            return (float) $v;
        }

        $formatted = number_format((float) $v, $dec, ',', '.');
        if ($dec <= 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), ',');
    };
    $formatearFecha = static function ($fecha) {
        if ($fecha === null || trim((string) $fecha) === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('j/n/Y');
        } catch (\Throwable) {
            return '';
        }
    };
    $envoltorioTabla = ! ($solo_filas ?? false);
@endphp
@if ($envoltorioTabla && ! ($solo_body ?? false))
<thead>
    <tr>
        <th>Art&iacute;culo</th>
        <th>Descripci&oacute;n</th>
        <th>Agr.</th>
        <th>Requis.</th>
        <th>Nro.OC</th>
        <th>Fecha</th>
        <th>F.Entr.</th>
        <th>UMD</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Entreg.</th>
        <th class="text-right">Pendien.</th>
        <th class="text-right">Importe</th>
        <th class="text-right">Total</th>
        <th>Mon</th>
        <th class="text-right">Unidades</th>
        <th>UMD</th>
        <th>Estado Requis.</th>
        <th>N.Pro.</th>
        <th>Nombre</th>
        <th>C.cos.</th>
        <th>CC.Dest</th>
        <th>Proyecto CAPEX</th>
        <th>Leyenda</th>
        <th>F.aprob.</th>
        <th>Proveedor OC</th>
        <th>Nombre usuario</th>
        <th class="text-center">U</th>
        <th>Motivo Urgencia</th>
        <th class="text-right">Precio Original</th>
        <th class="text-right">Porc.Ahorro</th>
        <th class="text-right">Monto Ahorro</th>
        <th>Motivo ahorro</th>
        <th>Usuario ahorro</th>
        <th>Empr.</th>
    </tr>
</thead>
@elseif ($cabecera_en_filas ?? false)
    <tr>
        <th>Art&iacute;culo</th>
        <th>Descripci&oacute;n</th>
        <th>Agr.</th>
        <th>Requis.</th>
        <th>Nro.OC</th>
        <th>Fecha</th>
        <th>F.Entr.</th>
        <th>UMD</th>
        <th>Cantidad</th>
        <th>Entreg.</th>
        <th>Pendien.</th>
        <th>Importe</th>
        <th>Total</th>
        <th>Mon</th>
        <th>Unidades</th>
        <th>UMD</th>
        <th>Estado Requis.</th>
        <th>N.Pro.</th>
        <th>Nombre</th>
        <th>C.cos.</th>
        <th>CC.Dest</th>
        <th>Proyecto CAPEX</th>
        <th>Leyenda</th>
        <th>F.aprob.</th>
        <th>Proveedor OC</th>
        <th>Nombre usuario</th>
        <th>U</th>
        <th>Motivo Urgencia</th>
        <th>Precio Original</th>
        <th>Porc.Ahorro</th>
        <th>Monto Ahorro</th>
        <th>Motivo ahorro</th>
        <th>Usuario ahorro</th>
        <th>Empr.</th>
    </tr>
@endif
@if ($envoltorioTabla)
<tbody>
@endif
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'cabecera_requisicion')
            <tr class="req-reporte-grupo-cabecera {{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}"
                @if (! $paraPdf) data-grupo-id="{{ $fila['grupo_id'] ?? '' }}" style="cursor:pointer;" @endif>
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#e9ecef;font-weight:bold;" @endif>
                    @if (! $paraPdf)
                        <i class="fa fa-chevron-down req-reporte-grupo-icon mr-1"></i>
                    @endif
                    Requisici&oacute;n:
                    @if ($puedeVerRequisicion && (int) ($fila['requisicion_id'] ?? 0) > 0)
                        <a href="{{ route('editar_requisicion', array_merge(['id' => $fila['requisicion_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener"
                           onclick="event.stopPropagation();">{{ $fila['numerorequisicion'] ?? '' }}</a>
                    @else
                        {{ $fila['numerorequisicion'] ?? '' }}
                    @endif
                </td>
            </tr>
        @elseif ($tipo === 'cabecera_usuario')
            <tr class="req-reporte-grupo-cabecera {{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}"
                @if (! $paraPdf) data-grupo-id="{{ $fila['grupo_id'] ?? '' }}" style="cursor:pointer;" @endif>
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#e9ecef;font-weight:bold;" @endif>
                    @if (! $paraPdf)
                        <i class="fa fa-chevron-down req-reporte-grupo-icon mr-1"></i>
                    @endif
                    Usuario: {{ $fila['usuario_id'] ?? '' }} {{ $fila['usuario_nombre'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipo === 'cabecera_articulo')
            <tr class="req-reporte-grupo-cabecera {{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}"
                @if (! $paraPdf) data-grupo-id="{{ $fila['grupo_id'] ?? '' }}" style="cursor:pointer;" @endif>
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#e9ecef;font-weight:bold;" @endif>
                    @if (! $paraPdf)
                        <i class="fa fa-chevron-down req-reporte-grupo-icon mr-1"></i>
                    @endif
                    Art&iacute;culo:
                    @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                        <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener"
                           onclick="event.stopPropagation();">{{ $fila['sku'] ?? '' }}</a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                    — {{ $fila['articulo_descripcion'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipo === 'cabecera_centrocosto')
            <tr class="req-reporte-grupo-cabecera {{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}"
                @if (! $paraPdf) data-grupo-id="{{ $fila['grupo_id'] ?? '' }}" style="cursor:pointer;" @endif>
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#e9ecef;font-weight:bold;" @endif>
                    @if (! $paraPdf)
                        <i class="fa fa-chevron-down req-reporte-grupo-icon mr-1"></i>
                    @endif
                    Centro de costo destino:
                    @if ($puedeVerCentrocosto && (int) ($fila['centrocostodestino_id'] ?? 0) > 0)
                        <a href="{{ route('editar_centrocosto', array_merge(['id' => $fila['centrocostodestino_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener"
                           onclick="event.stopPropagation();">{{ $fila['centrocosto_destino_codigo'] ?? '' }}</a>
                    @else
                        {{ $fila['centrocosto_destino_codigo'] ?? '' }}
                    @endif
                </td>
            </tr>
        @elseif ($tipo === 'subtotal_requisicion')
            <tr class="req-reporte-grupo-subtotal {{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color:#e9ecef;">
                <td colspan="8">Total requisici&oacute;n {{ $fila['numerorequisicion'] ?? '' }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="2" class="text-right">{{ $formatearNum($fila['total_importe'] ?? 0) }}</td>
                <td colspan="21"></td>
            </tr>
            @if (! $paraPdf)
                <tr class="req-reporte-grupo-spacer"><td colspan="{{ $colSpan }}">&nbsp;</td></tr>
            @endif
        @elseif ($tipo === 'subtotal_usuario')
            <tr class="req-reporte-grupo-subtotal {{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color:#e9ecef;">
                <td colspan="8">
                    Total usuario {{ $fila['usuario_id'] ?? '' }} {{ $fila['usuario_nombre'] ?? '' }}
                </td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="2" class="text-right">{{ $formatearNum($fila['total_importe'] ?? 0) }}</td>
                <td colspan="21"></td>
            </tr>
            @if (! $paraPdf)
                <tr class="req-reporte-grupo-spacer"><td colspan="{{ $colSpan }}">&nbsp;</td></tr>
            @endif
        @elseif ($tipo === 'subtotal_articulo')
            <tr class="req-reporte-grupo-subtotal {{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color:#e9ecef;">
                <td colspan="8">
                    Total art&iacute;culo {{ $fila['sku'] ?? '' }}
                </td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="2" class="text-right">{{ $formatearNum($fila['total_importe'] ?? 0) }}</td>
                <td colspan="21"></td>
            </tr>
            @if (! $paraPdf)
                <tr class="req-reporte-grupo-spacer"><td colspan="{{ $colSpan }}">&nbsp;</td></tr>
            @endif
        @elseif ($tipo === 'subtotal_centrocosto')
            <tr class="req-reporte-grupo-subtotal {{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color:#e9ecef;">
                <td colspan="8">
                    Total CC destino {{ $fila['centrocosto_destino_codigo'] ?? '' }}
                </td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="2" class="text-right">{{ $formatearNum($fila['total_importe'] ?? 0) }}</td>
                <td colspan="21"></td>
            </tr>
            @if (! $paraPdf)
                <tr class="req-reporte-grupo-spacer"><td colspan="{{ $colSpan }}">&nbsp;</td></tr>
            @endif
        @elseif ($tipo === 'total_general')
            <tr class="{{ $paraPdf ? 'total' : 'font-weight-bold' }}" style="background-color:#d6eaf8;">
                <td colspan="8">Total general</td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td colspan="2" class="text-right">{{ $formatearNum($fila['total_importe'] ?? 0) }}</td>
                <td colspan="21"></td>
            </tr>
        @else
            <tr class="req-reporte-grupo-detalle req-reporte-grupo-{{ $fila['grupo_id'] ?? 0 }}">
                <td>
                    @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                        <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['sku'] ?? '' }}</a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                </td>
                <td><small>{{ $fila['descripcion'] ?? '' }}</small></td>
                <td>{{ $fila['agrupacion'] ?? '' }}</td>
                <td>
                    @if ($puedeVerRequisicion && (int) ($fila['requisicion_id'] ?? 0) > 0)
                        <a href="{{ route('editar_requisicion', array_merge(['id' => $fila['requisicion_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['numerorequisicion'] ?? '' }}</a>
                    @else
                        {{ $fila['numerorequisicion'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($puedeVerOrdencompra && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                        <a href="{{ route('editar_ordencompra', array_merge(['id' => $fila['ordencompra_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['numeroordencompra'] ?? '' }}</a>
                    @else
                        {{ ($fila['numeroordencompra'] ?? 0) > 0 ? $fila['numeroordencompra'] : '' }}
                    @endif
                </td>
                <td>{{ $formatearFecha($fila['fecha'] ?? null) }}</td>
                <td>{{ $formatearFecha($fila['fecha_entrega'] ?? null) }}</td>
                <td>{{ $fila['umd'] ?? '' }}</td>
                <td class="text-right">{{ $formatearNum($fila['cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['pendiente'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['importe'] ?? 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total'] ?? 0) }}</td>
                <td>{{ $fila['moneda'] ?? '' }}</td>
                <td class="text-right">
                    @if ((float) ($fila['unidades'] ?? 0) > 0)
                        {{ $formatearNum($fila['unidades'] ?? 0, 0) }}
                    @endif
                </td>
                <td>{{ $fila['umd_alternativa'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
                <td><small>{{ $fila['proveedor_nombre'] ?? '' }}</small></td>
                <td>
                    @if ($puedeVerCentrocosto && (int) ($fila['centrocosto_id'] ?? 0) > 0)
                        <a href="{{ route('editar_centrocosto', array_merge(['id' => $fila['centrocosto_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['centrocosto_codigo'] ?? '' }}</a>
                    @else
                        {{ $fila['centrocosto_codigo'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($puedeVerCentrocosto && (int) ($fila['centrocostodestino_id'] ?? 0) > 0)
                        <a href="{{ route('editar_centrocosto', array_merge(['id' => $fila['centrocostodestino_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['centrocosto_destino_codigo'] ?? '' }}</a>
                    @else
                        {{ $fila['centrocosto_destino_codigo'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['proyecto_capex'] ?? '' }}</td>
                <td><small>{{ $fila['leyenda'] ?? '' }}</small></td>
                <td>{{ $formatearFecha($fila['fecha_aprobacion'] ?? null) }}</td>
                <td><small>{{ $fila['proveedor_oc_nombre'] ?? '' }}</small></td>
                <td><small>{{ $fila['usuario_nombre'] ?? '' }}</small></td>
                <td class="text-center">{{ $fila['urgente'] ?? 'N' }}</td>
                <td><small>{{ $fila['motivo_urgencia'] ?? '' }}</small></td>
                <td class="text-right">{{ $formatearNum($fila['precio_original'] ?? 0) }}</td>
                <td class="text-right">
                    @if ($fila['porc_ahorro'] !== null)
                        @if ($paraExcel)
                            {{ (float) $fila['porc_ahorro'] }}
                        @else
                            {{ $formatearNum($fila['porc_ahorro']) }}%
                        @endif
                    @endif
                </td>
                <td class="text-right">
                    @if ((float) ($fila['monto_ahorro'] ?? 0) > 0)
                        {{ $formatearNum($fila['monto_ahorro'] ?? 0) }}
                    @endif
                </td>
                <td><small>{{ $fila['motivo_ahorro'] ?? '' }}</small></td>
                <td><small>{{ $fila['usuario_ahorro'] ?? '' }}</small></td>
                <td>{{ $fila['empresa_id'] ?? '' }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $colSpan }}" class="text-center text-muted py-4">Sin requisiciones para los filtros indicados.</td>
        </tr>
    @endforelse
@if ($envoltorioTabla)
</tbody>
@endif
