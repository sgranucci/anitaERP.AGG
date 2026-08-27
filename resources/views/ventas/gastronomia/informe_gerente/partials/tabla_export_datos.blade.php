@php
    $informe = $informe ?? [];
    $top20 = $informe['top20_articulos_costo'] ?? ['filas' => [], 'listas' => []];
    $listas = $top20['listas'] ?? [];
    $descuentos = $informe['facturas_por_descuento'] ?? ['filas' => [], 'sin_descuento' => []];
    $recDia = $informe['recepciones']['dia'] ?? ['filas' => []];
    $recMes = $informe['recepciones']['mes'] ?? ['filas' => []];
    $recFuente = $informe['recepciones']['fuente'] ?? 'erp';
    $recFuenteLabel = $recFuente === 'erp'
        ? 'ERP'
        : ($recFuente === 'hibrido' ? 'ERP + Anita' : 'Anita');
@endphp

{{-- Resumen --}}
<tr>
    <td colspan="8"><strong>Resumen</strong></td>
</tr>
<tr>
    <td colspan="2">Período</td>
    <td colspan="6">{{ $informe['periodo_label'] ?? $informe['fecha_jornada_label'] ?? '' }}</td>
</tr>
<tr>
    <td colspan="2">Total ventas (neto)</td>
    <td colspan="6">{{ number_format((float) ($informe['total_ventas_periodo'] ?? $informe['total_ventas_jornada'] ?? 0), 2, ',', '.') }}</td>
</tr>
@if (!empty($informe['waitry_sin_facturar']['total']))
    <tr>
        <td colspan="2">Waitry pagado s/facturar</td>
        <td colspan="6">{{ number_format((float) $informe['waitry_sin_facturar']['total'], 2, ',', '.') }}</td>
    </tr>
@endif

{{-- Top 10 cantidad --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Top 10 artículos — cantidad</strong></td>
</tr>
<tr>
    <th>#</th>
    <th>SKU</th>
    <th>Artículo</th>
    <th>Cantidad</th>
    <th>Importe</th>
    <th></th>
    <th></th>
    <th></th>
</tr>
@forelse (($informe['top10_cantidad'] ?? []) as $i => $fila)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $fila['sku'] ?? '' }}</td>
        <td>{{ $fila['descripcion'] ?? '' }}</td>
        <td>{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
        <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin ventas en el período.</td></tr>
@endforelse

{{-- Top 10 valor --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Top 10 artículos — valor</strong></td>
</tr>
<tr>
    <th>#</th>
    <th>SKU</th>
    <th>Artículo</th>
    <th>Cantidad</th>
    <th>Importe</th>
    <th></th>
    <th></th>
    <th></th>
</tr>
@forelse (($informe['top10_valor'] ?? []) as $i => $fila)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $fila['sku'] ?? '' }}</td>
        <td>{{ $fila['descripcion'] ?? '' }}</td>
        <td>{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
        <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin ventas en el período.</td></tr>
@endforelse

{{-- Ventas por turno --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Ventas por turno</strong></td>
</tr>
<tr>
    <th>#</th>
    <th></th>
    <th>Turno</th>
    <th>Cantidad</th>
    <th>Total</th>
    <th></th>
    <th></th>
    <th></th>
</tr>
@forelse (($informe['ventas_por_turno'] ?? []) as $i => $fila)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td></td>
        <td>{{ $fila['etiqueta'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad'] ?? 0) }}</td>
        <td>{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin datos de turno.</td></tr>
@endforelse

{{-- Punto de venta --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Ventas por punto de venta</strong></td>
</tr>
<tr>
    <th>PV</th>
    <th></th>
    <th>Nombre</th>
    <th>Facturas</th>
    <th>NC</th>
    <th>Waitry s/f</th>
    <th>Total neto</th>
    <th></th>
</tr>
@forelse (($informe['ventas_por_puntoventa'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['codigo'] ?? '' }}</td>
        <td></td>
        <td>{{ $fila['nombre'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad_facturas'] ?? 0) }}</td>
        <td>{{ (int) ($fila['cantidad_notas_credito'] ?? 0) }}</td>
        <td>{{ !empty($fila['waitry_sin_facturar']) ? number_format((float) $fila['waitry_sin_facturar'], 2, ',', '.') : '—' }}</td>
        <td>{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin puntos de venta.</td></tr>
@endforelse

{{-- Medio de pago --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Facturación por medio de pago — período</strong></td>
</tr>
<tr>
    <th>Código</th>
    <th></th>
    <th>Medio de pago</th>
    <th>Cobranzas</th>
    <th>%</th>
    <th>Total cobrado</th>
    <th></th>
    <th></th>
</tr>
@forelse (($informe['ventas_por_medio_pago'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['codigo'] !== '' ? $fila['codigo'] : '—' }}</td>
        <td></td>
        <td>{{ $fila['nombre'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad'] ?? 0) }}</td>
        <td>{{ number_format((float) ($fila['porcentaje'] ?? 0), 1, ',', '.') }}%</td>
        <td>{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin cobranzas con medio de pago en el período.</td></tr>
@endforelse

{{-- Medio de pago mes --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Facturación por medio de pago — mes {{ $informe['mes_jornada_label'] ?? '' }}</strong></td>
</tr>
<tr>
    <th>Código</th>
    <th></th>
    <th>Medio de pago</th>
    <th>Cobranzas</th>
    <th>%</th>
    <th>Total cobrado</th>
    <th></th>
    <th></th>
</tr>
@forelse (($informe['ventas_por_medio_pago_mes'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['codigo'] !== '' ? $fila['codigo'] : '—' }}</td>
        <td></td>
        <td>{{ $fila['nombre'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad'] ?? 0) }}</td>
        <td>{{ number_format((float) ($fila['porcentaje'] ?? 0), 1, ',', '.') }}%</td>
        <td>{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin cobranzas con medio de pago en el mes.</td></tr>
@endforelse

{{-- Descuentos --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Facturas por descuento</strong></td>
</tr>
<tr>
    <th>Código</th>
    <th></th>
    <th>Descuento</th>
    <th>Facturas</th>
    <th>Importe</th>
    <th></th>
    <th></th>
    <th></th>
</tr>
@foreach (($descuentos['filas'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['codigo'] ?? '' }}</td>
        <td></td>
        <td>{{ $fila['nombre'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad'] ?? 0) }}</td>
        <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
@endforeach
@if ((($descuentos['sin_descuento']['cantidad'] ?? 0) > 0))
    <tr>
        <td>—</td>
        <td></td>
        <td>Sin descuento</td>
        <td>{{ (int) $descuentos['sin_descuento']['cantidad'] }}</td>
        <td>{{ number_format((float) ($descuentos['sin_descuento']['importe'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
@endif
@if (($descuentos['filas'] ?? []) === [] && (($descuentos['sin_descuento']['cantidad'] ?? 0) === 0))
    <tr><td colspan="8">Sin facturas en el período.</td></tr>
@endif

{{-- Recepciones período --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Recepciones del período ({{ $recFuenteLabel }})</strong></td>
</tr>
<tr>
    <th>Proveedor</th>
    <th>Comprobante</th>
    <th>Fecha</th>
    <th>Est.</th>
    <th>Líneas</th>
    <th>Importe</th>
    <th></th>
    <th></th>
</tr>
@forelse (($recDia['filas'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['proveedor_nombre'] ?? $fila['proveedor'] ?? '' }}</td>
        <td>{{ $fila['comprobante'] ?? '' }}</td>
        <td>{{ $fila['fecha'] ?? '' }}</td>
        <td>{{ $fila['estado'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad_lineas'] ?? 0) }}</td>
        <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin recepciones en el período.</td></tr>
@endforelse

{{-- Recepciones mes --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8"><strong>Recepciones acumuladas del mes ({{ $recFuenteLabel }})</strong></td>
</tr>
<tr>
    <th>Proveedor</th>
    <th>Comprobante</th>
    <th>Fecha</th>
    <th>Est.</th>
    <th>Líneas</th>
    <th>Importe</th>
    <th></th>
    <th></th>
</tr>
@forelse (($recMes['filas'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['proveedor_nombre'] ?? $fila['proveedor'] ?? '' }}</td>
        <td>{{ $fila['comprobante'] ?? '' }}</td>
        <td>{{ $fila['fecha'] ?? '' }}</td>
        <td>{{ $fila['estado'] ?? '' }}</td>
        <td>{{ (int) ($fila['cantidad_lineas'] ?? 0) }}</td>
        <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td></td>
    </tr>
@empty
    <tr><td colspan="8">Sin recepciones en el mes.</td></tr>
@endforelse

{{-- Top 20 costos --}}
<tr><td colspan="8"></td></tr>
<tr>
    <td colspan="8">
        <strong>Top 20 artículos — precio y costo Anita</strong>
        @if (!empty($listas['lista_anterior']) && !empty($listas['lista_actual']))
            (listas {{ $listas['lista_anterior'] }} / {{ $listas['lista_actual'] }})
        @endif
    </td>
</tr>
<tr>
    <th>#</th>
    <th>SKU</th>
    <th>Artículo</th>
    <th>Cant.</th>
    <th>P. venta</th>
    <th>{{ $listas['mes_anterior_label'] ?? 'Mes ant.' }}</th>
    <th>{{ $listas['mes_actual_label'] ?? 'Mes act.' }}</th>
    <th>Δ costo %</th>
</tr>
@forelse (($top20['filas'] ?? []) as $fila)
    <tr>
        <td>{{ $fila['posicion'] ?? '' }}</td>
        <td>{{ $fila['sku'] ?? '' }}</td>
        <td>{{ $fila['descripcion'] ?? '' }}</td>
        <td>{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
        <td>{{ ($fila['precio_venta'] ?? null) !== null ? number_format((float) $fila['precio_venta'], 2, ',', '.') : '—' }}</td>
        <td>{{ ($fila['costo_mes_anterior'] ?? null) !== null ? number_format((float) $fila['costo_mes_anterior'], 2, ',', '.') : '—' }}</td>
        <td>{{ ($fila['costo_mes_actual'] ?? null) !== null ? number_format((float) $fila['costo_mes_actual'], 2, ',', '.') : '—' }}</td>
        <td>
            @if (($fila['pct_diferencia_costo'] ?? null) !== null)
                {{ $fila['pct_diferencia_costo'] > 0 ? '+' : '' }}{{ number_format((float) $fila['pct_diferencia_costo'], 2, ',', '.') }}%
            @else
                —
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="8">Sin ventas en el período.</td></tr>
@endforelse
