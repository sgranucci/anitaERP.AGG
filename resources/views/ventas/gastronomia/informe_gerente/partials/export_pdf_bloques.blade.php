@php
    $informe = $informe ?? [];
    $top20 = $informe['top20_articulos_costo'] ?? ['filas' => [], 'listas' => []];
    $listas = $top20['listas'] ?? [];
    $descuentos = $informe['facturas_por_descuento'] ?? ['filas' => [], 'sin_descuento' => []];
    $recDia = $informe['recepciones']['dia'] ?? ['filas' => [], 'importe_total' => 0, 'cantidad_comprobantes' => 0];
    $recMes = $informe['recepciones']['mes'] ?? ['filas' => [], 'importe_total' => 0, 'cantidad_comprobantes' => 0];
    $recFuente = $informe['recepciones']['fuente'] ?? 'erp';
    $recFuenteLabel = $recFuente === 'erp'
        ? 'ERP'
        : ($recFuente === 'hibrido' ? 'ERP + Anita' : 'Anita');
@endphp

<div class="resumen">
    <strong>Período:</strong> {{ $informe['periodo_label'] ?? $informe['fecha_jornada_label'] ?? '' }}
    &nbsp;·&nbsp;
    <strong>Total ventas (neto):</strong> ${{ number_format((float) ($informe['total_ventas_periodo'] ?? $informe['total_ventas_jornada'] ?? 0), 2, ',', '.') }}
    @if (!empty($informe['waitry_sin_facturar']['total']))
        &nbsp;·&nbsp;
        <strong>Waitry s/facturar:</strong> ${{ number_format((float) $informe['waitry_sin_facturar']['total'], 2, ',', '.') }}
    @endif
</div>

<h3 class="seccion">Top 10 artículos — cantidad</h3>
<table class="data">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($informe['top10_cantidad'] ?? []) as $i => $fila)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td class="text-right">{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">${{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin ventas en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">Top 10 artículos — valor</h3>
<table class="data">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($informe['top10_valor'] ?? []) as $i => $fila)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td class="text-right">{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">${{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin ventas en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">Ventas por turno</h3>
<table class="data">
    <thead>
        <tr>
            <th>Turno</th>
            <th class="text-right">Comprobantes</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($informe['ventas_por_turno'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['etiqueta'] ?? '' }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad'] ?? 0) }}</td>
                <td class="text-right">${{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Sin datos de turno.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">Ventas por punto de venta</h3>
<table class="data">
    <thead>
        <tr>
            <th>PV</th>
            <th>Nombre</th>
            <th class="text-right">Facturas</th>
            <th class="text-right">NC</th>
            <th class="text-right">Waitry s/f</th>
            <th class="text-right">Total neto</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($informe['ventas_por_puntoventa'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['codigo'] ?? '' }}</td>
                <td>{{ $fila['nombre'] ?? '' }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad_facturas'] ?? 0) }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad_notas_credito'] ?? 0) }}</td>
                <td class="text-right">
                    @if (!empty($fila['waitry_sin_facturar']))
                        ${{ number_format((float) $fila['waitry_sin_facturar'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-right">${{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin puntos de venta.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">Facturación por medio de pago</h3>
<table class="data">
    <thead>
        <tr>
            <th>Código</th>
            <th>Medio de pago</th>
            <th class="text-right">Cobranzas</th>
            <th class="text-right">%</th>
            <th class="text-right">Total cobrado</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($informe['ventas_por_medio_pago'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['codigo'] !== '' ? $fila['codigo'] : '—' }}</td>
                <td>{{ $fila['nombre'] ?? '' }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad'] ?? 0) }}</td>
                <td class="text-right">{{ number_format((float) ($fila['porcentaje'] ?? 0), 1, ',', '.') }}%</td>
                <td class="text-right">${{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin cobranzas con medio de pago en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">Facturas por descuento</h3>
<table class="data">
    <thead>
        <tr>
            <th>Código</th>
            <th>Descuento</th>
            <th class="text-right">Facturas</th>
            <th class="text-right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($descuentos['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['codigo'] ?? '' }}</td>
                <td>{{ $fila['nombre'] ?? '' }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad'] ?? 0) }}</td>
                <td class="text-right">${{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @endforeach
        @if ((($descuentos['sin_descuento']['cantidad'] ?? 0) > 0))
            <tr>
                <td>—</td>
                <td>Sin descuento</td>
                <td class="text-right">{{ (int) $descuentos['sin_descuento']['cantidad'] }}</td>
                <td class="text-right">${{ number_format((float) ($descuentos['sin_descuento']['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @endif
        @if (($descuentos['filas'] ?? []) === [] && (($descuentos['sin_descuento']['cantidad'] ?? 0) === 0))
            <tr><td colspan="4">Sin facturas en el período.</td></tr>
        @endif
    </tbody>
</table>

<h3 class="seccion">
    Recepciones del período ({{ $recFuenteLabel }})
    — {{ (int) ($recDia['cantidad_comprobantes'] ?? 0) }} comp. /
    ${{ number_format((float) ($recDia['importe_total'] ?? 0), 2, ',', '.') }}
</h3>
<table class="data">
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
        @forelse (($recDia['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['proveedor_nombre'] ?? $fila['proveedor'] ?? '' }}</td>
                <td>{{ $fila['comprobante'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad_lineas'] ?? 0) }}</td>
                <td class="text-right">${{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin recepciones en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">
    Recepciones acumuladas del mes ({{ $recFuenteLabel }})
    — {{ (int) ($recMes['cantidad_comprobantes'] ?? 0) }} comp. /
    ${{ number_format((float) ($recMes['importe_total'] ?? 0), 2, ',', '.') }}
</h3>
<table class="data">
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
        @forelse (($recMes['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['proveedor_nombre'] ?? $fila['proveedor'] ?? '' }}</td>
                <td>{{ $fila['comprobante'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td class="text-right">{{ (int) ($fila['cantidad_lineas'] ?? 0) }}</td>
                <td class="text-right">${{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin recepciones en el mes.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="seccion">
    Top 20 artículos — precio y costo Anita
    @if (!empty($listas['lista_anterior']) && !empty($listas['lista_actual']))
        (listas {{ $listas['lista_anterior'] }} / {{ $listas['lista_actual'] }})
    @endif
</h3>
<table class="data">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th class="text-right">Cant.</th>
            <th class="text-right">P. venta</th>
            <th class="text-right">{{ $listas['mes_anterior_label'] ?? 'Mes ant.' }}</th>
            <th class="text-right">{{ $listas['mes_actual_label'] ?? 'Mes act.' }}</th>
            <th class="text-right">Δ %</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($top20['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['posicion'] ?? '' }}</td>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td class="text-right">{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">
                    @if (($fila['precio_venta'] ?? null) !== null)
                        ${{ number_format((float) $fila['precio_venta'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-right">
                    @if (($fila['costo_mes_anterior'] ?? null) !== null)
                        ${{ number_format((float) $fila['costo_mes_anterior'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-right">
                    @if (($fila['costo_mes_actual'] ?? null) !== null)
                        ${{ number_format((float) $fila['costo_mes_actual'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-right">
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
    </tbody>
</table>
