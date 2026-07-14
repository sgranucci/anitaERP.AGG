@php
    use App\Support\Ventas\Waitry\WaitryInformeZTransmisionFaltanteSupport;
    $tfRaw = $transmisionFaltante ?? ($d['transmision_faltante_z'] ?? null);
    $tf = is_array($tfRaw) ? WaitryInformeZTransmisionFaltanteSupport::paraVista($tfRaw) : ['tiene_diferencias' => false];
@endphp
@if (! empty($tf['tiene_diferencias']))
    <div class="bloque transmision-faltante-z mt-3">
        <h2>Comandas no transmitidas a tiempo (ajuste Tesorería)</h2>
        <div class="warn" style="margin-bottom:8px;">
            Órdenes en la ventana de la jornada que no entraron al Informe Z del cierre
            (retraso / hueco de transmisión Waitry al snapshot de preview).
            El Z histórico <strong>no se modifica</strong>; sumar estos importes al presentar en Tesorería.
            @if (! empty($tf['calculado_en_fmt']))
                <span class="muted">Verificado {{ $tf['calculado_en_fmt'] }}.</span>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>Comanda</th>
                    <th>Medio</th>
                    <th class="num">Monto</th>
                    <th>Colocada</th>
                    <th>Tótem / mesa</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tf['comandas'] ?? [] as $comanda)
                    <tr>
                        <td>
                            {{ $comanda['display_id'] ?? '—' }}
                            @if (! empty($comanda['waitry_order_id']))
                                <span class="muted">(#{{ (int) $comanda['waitry_order_id'] }})</span>
                            @endif
                        </td>
                        <td>{{ $comanda['medio_label'] ?? ($comanda['tipo_medio'] ?? '—') }}</td>
                        <td class="num">$ {{ $comanda['monto_fmt'] ?? number_format((float) ($comanda['monto'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $comanda['placed_at_fmt'] ?? '—' }}</td>
                        <td>
                            {{ $comanda['waitry_layout_name'] ?? '' }}
                            @if (! empty($comanda['waitry_table_name']))
                                / {{ $comanda['waitry_table_name'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="margin-top:10px;" class="total-grande">
            <tbody>
                <tr>
                    <th style="width:45%;">Total Informe Z al cierre (histórico)</th>
                    <td class="num">$ {{ $tf['total_z_historico_fmt'] ?? number_format((float) ($tf['total_z_historico'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>+ No transmitidas a tiempo</th>
                    <td class="num">$ {{ $tf['total_faltante_fmt'] ?? number_format((float) ($tf['total_faltante'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr class="total-grande">
                    <th><strong>= Total Tesorería (con este ajuste)</strong></th>
                    <td class="num"><strong>$ {{ $tf['total_tesoreria_fmt'] ?? number_format((float) ($tf['total_tesoreria'] ?? 0), 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
