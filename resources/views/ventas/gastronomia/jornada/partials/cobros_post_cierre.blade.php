@php
    $cpc = $cobrosPostCierre ?? ($d['cobros_post_cierre'] ?? null);
@endphp
@if (is_array($cpc) && ! empty($cpc['tiene_anomalias']))
    <div class="bloque cobros-post-cierre mt-3">
        <h2>Cobros en tótem posteriores al cierre de jornada</h2>
        <div class="warn" style="margin-bottom:8px;">
            Comandas colocadas dentro de la ventana operativa pero cobradas en Waitry después del cierre
            ({{ $cpc['cierre_jornada_en_fmt'] ?? '—' }}).
            No forman parte del Informe Z histórico del cierre; sí integran el total que Tesorería debe considerar
            para la facturación post-cierre CAEA.
        </div>

        <table>
            <thead>
                <tr>
                    <th>Comanda</th>
                    <th>Medio</th>
                    <th class="num">Monto</th>
                    <th>Colocada</th>
                    <th>Cobrada Waitry</th>
                    <th>Factura proceso</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cpc['comandas'] ?? [] as $comanda)
                    <tr>
                        <td>
                            {{ $comanda['display_id'] ?? '—' }}
                            @if (! empty($comanda['waitry_order_id']))
                                <span class="muted">(#{{ (int) $comanda['waitry_order_id'] }})</span>
                            @endif
                        </td>
                        <td>{{ $comanda['medio_etiqueta'] ?? '—' }}</td>
                        <td class="num">$ {{ number_format((float) ($comanda['total'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $comanda['placed_at_fmt'] ?? '—' }}</td>
                        <td>
                            {{ $comanda['cobro_en_fmt'] ?? '—' }}
                            @if (! empty($comanda['minutos_despues_cierre']))
                                <span class="muted">(+{{ (int) $comanda['minutos_despues_cierre'] }} min)</span>
                            @endif
                        </td>
                        <td>
                            @if (! empty($comanda['facturada_proceso']))
                                {{ $comanda['numero_comprobante'] ?? 'Sí' }}
                                @if (! empty($comanda['cierre_jornada_proceso_lote']))
                                    <span class="muted">(lote {{ (int) $comanda['cierre_jornada_proceso_lote'] }})</span>
                                @endif
                            @else
                                <span class="muted">Pendiente</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (! empty($cpc['por_medio']))
            <table style="margin-top:8px;">
                <thead>
                    <tr>
                        <th>Medio (post-cierre)</th>
                        <th class="num">Cant.</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cpc['por_medio'] as $medio)
                        <tr>
                            <td>{{ $medio['medio_etiqueta'] ?? '—' }}</td>
                            <td class="num">{{ (int) ($medio['cantidad'] ?? 0) }}</td>
                            <td class="num">$ {{ number_format((float) ($medio['total'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <table style="margin-top:10px;" class="total-grande">
            <tbody>
                <tr>
                    <th style="width:45%;">Total Informe Z al cierre (histórico)</th>
                    <td class="num">$ {{ number_format((float) ($cpc['total_cierre_historico'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>+ Cobros post-cierre en tótem</th>
                    <td class="num">$ {{ number_format((float) ($cpc['total_post_cierre'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr class="total-grande">
                    <th><strong>= Total Tesorería (cierre QR / facturación CAEA)</strong></th>
                    <td class="num"><strong>$ {{ number_format((float) ($cpc['total_tesoreria'] ?? 0), 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
