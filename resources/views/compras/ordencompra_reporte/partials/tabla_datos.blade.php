@php
    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerArticulo = ! $paraPdf && ($puede_ver_articulo ?? false);
    $puedeVerRequisicion = ! $paraPdf && ($puede_ver_requisicion ?? false);
    $puedeVerCentrocosto = ! $paraPdf && ($puede_ver_centrocosto ?? false);
    $puedeVerOrdencompra = ! $paraPdf && ($puede_ver_ordencompra ?? false);
    $puedeVerProveedor = ! $paraPdf && ($puede_ver_proveedor ?? false);
    $puedeVerCapex = ! $paraPdf && ($puede_ver_capex ?? false);
    $puedeVerRecepcion = ! $paraPdf && ($puede_ver_recepcion ?? false);
    $colSpan = 37;
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
        <th>Tip</th>
        <th>N&uacute;mero</th>
        <th>N.Req.</th>
        <th>F.Aut.Req.</th>
        <th>Dif.</th>
        <th>Fecha</th>
        <th>F.Entr.</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Entreg.</th>
        <th class="text-right">Pen.Ent.</th>
        <th class="text-right">Fact.</th>
        <th class="text-right">P.Fact.</th>
        <th class="text-right">Importe</th>
        <th class="text-right">Tot.pend.</th>
        <th class="text-right">Tot.OC</th>
        <th>N.Recep.</th>
        <th class="text-right">Imp.Rec.</th>
        <th>F.Rec.</th>
        <th class="text-right">Sdo.Rec.</th>
        <th>N.Fact.</th>
        <th class="text-right">Imp.Fact.</th>
        <th>F.Fact.</th>
        <th class="text-right">Sdo.Fact.</th>
        <th>Nro.Cta.</th>
        <th>Cuenta contable</th>
        <th>C.Cos.</th>
        <th>CC.Dest</th>
        <th>CAPEX</th>
        <th>N.Pro.</th>
        <th>Proveedor</th>
        <th>Leyenda</th>
        <th>Motivo cierre</th>
        <th>Usuario</th>
        <th>Cond.Pago</th>
        <th>Empr.</th>
    </tr>
</thead>
@elseif ($cabecera_en_filas ?? false)
    <tr>
        <th>Art&iacute;culo</th>
        <th>Descripci&oacute;n</th>
        <th>Tip</th>
        <th>N&uacute;mero</th>
        <th>N.Req.</th>
        <th>F.Aut.Req.</th>
        <th>Dif.</th>
        <th>Fecha</th>
        <th>F.Entr.</th>
        <th>Cantidad</th>
        <th>Entreg.</th>
        <th>Pen.Ent.</th>
        <th>Fact.</th>
        <th>P.Fact.</th>
        <th>Importe</th>
        <th>Tot.pend.</th>
        <th>Tot.OC</th>
        <th>N.Recep.</th>
        <th>Imp.Rec.</th>
        <th>F.Rec.</th>
        <th>Sdo.Rec.</th>
        <th>N.Fact.</th>
        <th>Imp.Fact.</th>
        <th>F.Fact.</th>
        <th>Sdo.Fact.</th>
        <th>Nro.Cta.</th>
        <th>Cuenta contable</th>
        <th>C.Cos.</th>
        <th>CC.Dest</th>
        <th>CAPEX</th>
        <th>N.Pro.</th>
        <th>Proveedor</th>
        <th>Leyenda</th>
        <th>Motivo cierre</th>
        <th>Usuario</th>
        <th>Cond.Pago</th>
        <th>Empr.</th>
    </tr>
@endif
@if ($envoltorioTabla)
<tbody>
@endif
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'header_empresa')
            <tr class="{{ $paraPdf ? 'grupo' : 'font-weight-bold' }}" style="background-color:#d6eaf8;">
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#d6eaf8;font-weight:bold;" @endif>
                    Empresa: {{ $fila['nombreempresa'] ?? '' }}
                </td>
            </tr>
        @elseif (str_starts_with((string) $tipo, 'cabecera_'))
            <tr class="oc-reporte-grupo-cabecera {{ $paraPdf ? 'grupo' : 'font-weight-bold bg-light' }}"
                @if (! $paraPdf) data-grupo-id="{{ $fila['grupo_id'] ?? '' }}" style="cursor:pointer;" @endif>
                <td colspan="{{ $colSpan }}" @if ($paraPdf) style="background-color:#e9ecef;font-weight:bold;" @endif>
                    @if (! $paraPdf)
                        <i class="fa fa-chevron-down oc-reporte-grupo-icon mr-1"></i>
                    @endif
                    @if ($tipo === 'cabecera_pedido')
                        Pedido OC:
                        @if ($puedeVerOrdencompra && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                            <a href="{{ route('editar_ordencompra', array_merge(['id' => $fila['ordencompra_id']], $queryConsulta)) }}"
                               class="text-primary" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();">{{ $fila['numeroordencompra'] ?? '' }}</a>
                        @else
                            {{ $fila['numeroordencompra'] ?? '' }}
                        @endif
                        — {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'cabecera_proveedor')
                        Proveedor:
                        @if ($puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0)
                            <a href="{{ route('editar_proveedor', array_merge(['id' => $fila['proveedor_id']], $queryConsulta)) }}"
                               class="text-primary" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();">{{ $fila['proveedor_codigo'] ?? '' }}</a>
                        @else
                            {{ $fila['proveedor_codigo'] ?? '' }}
                        @endif
                        — {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'cabecera_proveedor_pedido')
                        Proveedor {{ $fila['proveedor_codigo'] ?? '' }} · Pedido
                        @if ($puedeVerOrdencompra && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                            <a href="{{ route('editar_ordencompra', array_merge(['id' => $fila['ordencompra_id']], $queryConsulta)) }}"
                               class="text-primary" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();">{{ $fila['numeroordencompra'] ?? '' }}</a>
                        @else
                            {{ $fila['numeroordencompra'] ?? '' }}
                        @endif
                        — {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'cabecera_articulo')
                        Art&iacute;culo:
                        @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                            <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                               class="text-primary" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();">{{ $fila['sku'] ?? '' }}</a>
                        @else
                            {{ $fila['sku'] ?? '' }}
                        @endif
                        — {{ $fila['articulo_descripcion'] ?? '' }}
                    @elseif ($tipo === 'cabecera_requisicion')
                        Requisici&oacute;n:
                        @if ($puedeVerRequisicion && (int) ($fila['requisicion_id'] ?? 0) > 0)
                            <a href="{{ route('editar_requisicion', array_merge(['id' => $fila['requisicion_id']], $queryConsulta)) }}"
                               class="text-primary" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();">{{ $fila['numerorequisicion'] ?? '' }}</a>
                        @else
                            {{ $fila['numerorequisicion'] ?? '' }}
                        @endif
                    @elseif ($tipo === 'cabecera_partida')
                        Partida: {{ $fila['partidagasto_codigo'] ?? '' }} — {{ $fila['partidagasto_detalle'] ?? '' }}
                    @elseif ($tipo === 'cabecera_capex')
                        CAPEX:
                        @if ($puedeVerCapex && (int) ($fila['capex_id'] ?? 0) > 0)
                            <a href="{{ route('editar_capex', array_merge(['id' => $fila['capex_id']], $queryConsulta)) }}"
                               class="text-primary" target="_blank" rel="noopener"
                               onclick="event.stopPropagation();">{{ $fila['capex_codigo'] ?? '' }}</a>
                        @else
                            {{ $fila['capex_codigo'] ?? '' }}
                        @endif
                        — {{ $fila['capex_nombre'] ?? '' }}
                    @elseif ($tipo === 'cabecera_agrupacion')
                        Agrupaci&oacute;n: {{ $fila['agrupacion_codigo'] ?? '' }}
                    @else
                        Grupo
                    @endif
                </td>
            </tr>
        @elseif (str_starts_with((string) $tipo, 'subtotal_'))
            <tr class="oc-reporte-grupo-subtotal {{ $paraPdf ? 'subtotal' : 'font-weight-bold' }}" style="background-color:#e9ecef;">
                <td colspan="9">
                    @if ($tipo === 'subtotal_pedido')
                        Total pedido {{ $fila['numeroordencompra'] ?? '' }}
                    @elseif ($tipo === 'subtotal_proveedor')
                        Total proveedor {{ $fila['proveedor_codigo'] ?? '' }} {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'subtotal_proveedor_pedido')
                        Total {{ $fila['proveedor_codigo'] ?? '' }} / OC {{ $fila['numeroordencompra'] ?? '' }}
                    @elseif ($tipo === 'subtotal_articulo')
                        Total art&iacute;culo {{ $fila['sku'] ?? '' }}
                    @elseif ($tipo === 'subtotal_requisicion')
                        Total requisici&oacute;n {{ $fila['numerorequisicion'] ?? '' }}
                    @elseif ($tipo === 'subtotal_partida')
                        Total partida {{ $fila['partidagasto_codigo'] ?? '' }}
                    @elseif ($tipo === 'subtotal_capex')
                        Total CAPEX {{ $fila['capex_codigo'] ?? '' }}
                    @elseif ($tipo === 'subtotal_agrupacion')
                        Total agrupaci&oacute;n {{ $fila['agrupacion_codigo'] ?? '' }}
                    @else
                        Subtotal
                    @endif
                </td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_facturado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente_fact'] ?? 0, 0) }}</td>
                <td></td>
                <td class="text-right">{{ $formatearNum($fila['total_importe_pendiente'] ?? 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_importe_oc'] ?? 0) }}</td>
                <td colspan="20"></td>
            </tr>
            @if (! $paraPdf)
                <tr class="oc-reporte-grupo-spacer"><td colspan="{{ $colSpan }}">&nbsp;</td></tr>
            @endif
        @elseif ($tipo === 'total_general')
            <tr class="{{ $paraPdf ? 'total' : 'font-weight-bold' }}" style="background-color:#d6eaf8;">
                <td colspan="9">
                    Total general
                    @if (($fila['total_ordenes'] ?? 0) > 0)
                        ({{ (int) $fila['total_ordenes'] }} OC)
                    @endif
                </td>
                <td class="text-right">{{ $formatearNum($fila['total_cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_facturado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente_fact'] ?? 0, 0) }}</td>
                <td></td>
                <td class="text-right">{{ $formatearNum($fila['total_importe_pendiente'] ?? 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_importe_oc'] ?? 0) }}</td>
                <td colspan="20"></td>
            </tr>
        @else
            <tr class="oc-reporte-grupo-detalle oc-reporte-grupo-{{ $fila['grupo_id'] ?? 0 }}">
                <td>
                    @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                        <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['sku'] ?? '' }}</a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                </td>
                <td><small>{{ $fila['descripcion'] ?? '' }}</small></td>
                <td>{{ $fila['tipo_comprobante'] ?? 'ORD' }}</td>
                <td>
                    @if ($puedeVerOrdencompra && (int) ($fila['ordencompra_id'] ?? 0) > 0)
                        <a href="{{ route('editar_ordencompra', array_merge(['id' => $fila['ordencompra_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['numeroordencompra'] ?? '' }}</a>
                    @else
                        {{ ($fila['numeroordencompra'] ?? 0) > 0 ? $fila['numeroordencompra'] : '' }}
                    @endif
                </td>
                <td>
                    @if ($puedeVerRequisicion && (int) ($fila['requisicion_id'] ?? 0) > 0)
                        <a href="{{ route('editar_requisicion', array_merge(['id' => $fila['requisicion_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['numerorequisicion'] ?? '' }}</a>
                    @else
                        {{ ($fila['numerorequisicion'] ?? 0) > 0 ? $fila['numerorequisicion'] : '' }}
                    @endif
                </td>
                <td>{{ $formatearFecha($fila['fecha_aprobacion_req'] ?? null) }}</td>
                <td class="text-right">
                    @if ($fila['dif_dias'] !== null)
                        {{ (int) $fila['dif_dias'] }}
                    @endif
                </td>
                <td>{{ $formatearFecha($fila['fecha'] ?? null) }}</td>
                <td>{{ $formatearFecha($fila['fecha_entrega'] ?? null) }}</td>
                <td class="text-right">{{ $formatearNum($fila['cantidad'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['entregado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['pendiente'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['facturado'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['pendiente_fact'] ?? 0, 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['importe'] ?? 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_pendiente'] ?? 0) }}</td>
                <td class="text-right">{{ $formatearNum($fila['total_oc'] ?? 0) }}</td>
                <td>
                    @if ($puedeVerRecepcion && (int) ($fila['recepcion_id'] ?? 0) > 0)
                        <a href="{{ route('editar_recepcion_proveedor', array_merge(['id' => $fila['recepcion_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['numero_recepcion'] ?? '' }}</a>
                    @else
                        {{ ($fila['numero_recepcion'] ?? 0) > 0 ? $fila['numero_recepcion'] : '' }}
                    @endif
                </td>
                <td class="text-right">
                    @if ((float) ($fila['importe_recepcion'] ?? 0) > 0)
                        {{ $formatearNum($fila['importe_recepcion'] ?? 0) }}
                    @endif
                </td>
                <td>{{ $formatearFecha($fila['fecha_recepcion'] ?? null) }}</td>
                <td class="text-right">
                    @if ((float) ($fila['saldo_pendiente_recepcion'] ?? 0) > 0)
                        {{ $formatearNum($fila['saldo_pendiente_recepcion'] ?? 0) }}
                    @endif
                </td>
                <td>{{ $fila['numero_factura'] ?? '' }}</td>
                <td class="text-right">
                    @if ((float) ($fila['importe_factura'] ?? 0) > 0)
                        {{ $formatearNum($fila['importe_factura'] ?? 0) }}
                    @endif
                </td>
                <td>{{ $formatearFecha($fila['fecha_factura'] ?? null) }}</td>
                <td class="text-right">
                    @if ((float) ($fila['saldo_pendiente_factura'] ?? 0) > 0)
                        {{ $formatearNum($fila['saldo_pendiente_factura'] ?? 0) }}
                    @endif
                </td>
                <td>{{ $fila['cuenta_codigo'] ?? '' }}</td>
                <td><small>{{ $fila['cuenta_nombre'] ?? '' }}</small></td>
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
                <td>
                    @if ($puedeVerCapex && (int) ($fila['capex_id'] ?? 0) > 0)
                        <a href="{{ route('editar_capex', array_merge(['id' => $fila['capex_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['capex_codigoproyecto'] ?? '' }}</a>
                    @else
                        {{ $fila['capex_codigoproyecto'] ?? '' }}
                    @endif
                    @if (! empty($fila['capex_nombre']))
                        <small> {{ $fila['capex_nombre'] }}</small>
                    @endif
                </td>
                <td>
                    @if ($puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0)
                        <a href="{{ route('editar_proveedor', array_merge(['id' => $fila['proveedor_id']], $queryConsulta)) }}"
                           class="text-primary" target="_blank" rel="noopener">{{ $fila['proveedor_codigo'] ?? '' }}</a>
                    @else
                        {{ $fila['proveedor_codigo'] ?? '' }}
                    @endif
                </td>
                <td><small>{{ $fila['proveedor_nombre'] ?? '' }}</small></td>
                <td><small>{{ $fila['leyenda'] ?? '' }}</small></td>
                <td><small>{{ $fila['motivo_cierre'] ?? '' }}</small></td>
                <td><small>{{ $fila['usuario_nombre'] ?? '' }}</small></td>
                <td><small>{{ $fila['condicionpago_nombre'] ?? '' }}</small></td>
                <td>{{ $fila['empresa_id'] ?? '' }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $colSpan }}" class="text-center text-muted py-4">Sin &oacute;rdenes de compra para los filtros indicados.</td>
        </tr>
    @endforelse
@if ($envoltorioTabla)
</tbody>
@endif
