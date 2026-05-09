@php
    $presupuestos = $data->requisicion_presupuestos ?? collect();
@endphp
@if ($presupuestos->isNotEmpty())
    <h2>Presupuestos cotizados (proveedores)</h2>
    @foreach ($presupuestos as $pres)
        @php
            $prov = $pres->proveedores;
            $lineas = $pres->requisicion_presupuesto_articulos ?? collect();
            $totalPres = 0.0;
            foreach ($lineas as $l) {
                $ra = $l->requisicion_articulo;
                $c = $ra ? (float) $ra->cantidad : 0.0;
                $totalPres += $c * (float) $l->precio_unitario;
            }
        @endphp
        <h3 style="font-size: 10px; margin: 10px 0 4px 0; border-bottom: 1px solid #999; padding-bottom: 2px;">
            Presupuesto #{{ $pres->id }} — {{ $prov->codigo ?? '' }} {{ $prov->nombre ?? '—' }}
            — Fecha: {{ $pres->fecha ? date('d/m/Y', strtotime($pres->fecha)) : '—' }}
            — Estado: {{ $pres->estado ?? '—' }}
        </h3>
        <table>
            <tr>
                <td class="lbl" style="width:14%;">Cond. entrega</td>
                <td class="bloque-texto">{{ $pres->condiciones_entrega ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Cond. compra</td>
                <td class="bloque-texto">{{ $pres->condiciones_compra ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Cond. pago</td>
                <td class="bloque-texto">{{ $pres->condiciones_pago ?: '—' }}</td>
            </tr>
        </table>
        <table class="items">
            <thead>
                <tr>
                    <th class="cen">#</th>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th class="num">Cant.</th>
                    <th class="num">P. unit. cotiz.</th>
                    <th>Mon.</th>
                    <th class="num">Subtotal</th>
                    <th>Obs.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lineas as $ix => $l)
                    @php
                        $ra = $l->requisicion_articulo;
                        $art = $ra ? $ra->articulos : null;
                        $cant = $ra ? (float) $ra->cantidad : 0.0;
                        $pu = (float) $l->precio_unitario;
                        $sub = $cant * $pu;
                        $mon = $ra && $ra->monedas ? $ra->monedas->abreviatura : '—';
                    @endphp
                    <tr>
                        <td class="cen">{{ $ix + 1 }}</td>
                        <td>{{ $art->sku ?? '—' }}</td>
                        <td>{{ $art->descripcion ?? '—' }}</td>
                        <td class="num">{{ number_format($cant, 4, ',', '.') }}</td>
                        <td class="num">{{ number_format($pu, 4, ',', '.') }}</td>
                        <td>{{ $mon }}</td>
                        <td class="num">{{ number_format($sub, 2, ',', '.') }}</td>
                        <td class="bloque-texto">{{ $l->observacion ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="6" style="text-align:right;">Total cotización presupuesto #{{ $pres->id }}</td>
                    <td class="num">{{ number_format($totalPres, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        @if ($pres->requisicion_presupuesto_archivos && $pres->requisicion_presupuesto_archivos->count())
            <p class="muted" style="margin: 4px 0 8px 0;">Archivos adjuntos al presupuesto: {{ $pres->requisicion_presupuesto_archivos->count() }}.</p>
        @endif
    @endforeach
@endif
