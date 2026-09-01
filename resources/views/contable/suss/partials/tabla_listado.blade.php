@php
    $registros = $registros ?? [];
    $totales = $totales ?? [];
    $conciliacion = $conciliacion ?? [];
    $esExcel = ! empty($esExcel);
    $mostrarConciliacion = ! empty($conciliacion['habilitada']) && ! empty($conciliacion['items']);
    $fmtMontoExcel = \App\Support\Export\ExcelFormatoNumero::formateadorMonto(
        \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()
    );
@endphp

@if ($mostrarConciliacion)
    @if ($esExcel)
        <tr>
            <td colspan="7"><strong>Conciliación SUSS vs mayor contable</strong></td>
        </tr>
        <tr>
            <td><strong>Cód.</strong></td>
            <td><strong>Concepto</strong></td>
            <td><strong>Total SUSS</strong></td>
            <td><strong>Total mayor</strong></td>
            <td><strong>Diferencia</strong></td>
            <td><strong>Estado</strong></td>
            <td></td>
        </tr>
        @foreach ($conciliacion['items'] as $item)
            @php
                $totalIibb = $item['total_suss'] ?? $item['total_sicore'] ?? 0;
            @endphp
            <tr>
                <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                <td>{{ $item['nombre'] ?? '' }}</td>
                <td style="text-align:right;">{{ $fmtMontoExcel($totalIibb) }}</td>
                <td style="text-align:right;">{{ $fmtMontoExcel($item['total_mayor'] ?? 0) }}</td>
                <td style="text-align:right;">{{ $fmtMontoExcel($item['diferencia'] ?? 0) }}</td>
                <td>{{ ! empty($item['cuadra']) ? 'OK' : 'Diferencia' }}</td>
                <td></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="7"><strong>SUSS a presentar — detalle</strong></td>
        </tr>
    @else
        <h3 style="font-size:10px;margin:8px 0 4px;">Conciliación SUSS vs mayor contable</h3>
        <table class="data tabla-conciliacion" style="margin-bottom:10px;">
            <colgroup>
                <col style="width:8%;">
                <col style="width:32%;">
                <col style="width:16%;">
                <col style="width:16%;">
                <col style="width:16%;">
                <col style="width:12%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Cód.</th>
                    <th>Concepto</th>
                    <th class="num">Total SUSS</th>
                    <th class="num">Total mayor</th>
                    <th class="num">Dif.</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conciliacion['items'] as $item)
                    @php
                        $totalIibb = $item['total_suss'] ?? $item['total_sicore'] ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                        <td>{{ $item['nombre'] ?? '' }}</td>
                        <td class="num">{{ number_format((float) $totalIibb, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) ($item['total_mayor'] ?? 0), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) ($item['diferencia'] ?? 0), 2, ',', '.') }}</td>
                        <td>
                            @if (! empty($item['cuadra']))
                                OK
                            @else
                                Dif.
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <h3 style="font-size:10px;margin:8px 0 4px;">SUSS a presentar — detalle</h3>
    @endif
@endif

@if ($esExcel)
    <tr>
        <td><strong>Cert.</strong></td>
        <td><strong>Documento</strong></td>
        <td><strong>Razón</strong></td>
        <td><strong>Fecha</strong></td>
        <td><strong>Alícuota</strong></td>
        <td><strong>Base</strong></td>
        <td><strong>Importe</strong></td>
    </tr>
    @forelse ($registros as $reg)
        <tr>
            <td>{{ $reg['nro_cert'] ?? $reg['nro_comp'] ?? '' }}</td>
            <td>{{ $reg['nro_documento'] ?? '' }}</td>
            <td>{{ $reg['razon_social'] ?? '' }}</td>
            <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
            <td style="text-align:right;">{{ $fmtMontoExcel($reg['alicuota'] ?? 0) }}</td>
            <td style="text-align:right;">{{ $fmtMontoExcel($reg['base_calculo'] ?? 0) }}</td>
            <td style="text-align:right;">{{ $fmtMontoExcel($reg['importe'] ?? 0) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7">Sin registros en el período.</td>
        </tr>
    @endforelse
    @if (($totales['registros'] ?? 0) > 0)
        <tr>
            <td colspan="5"><strong>Totales ({{ $totales['registros'] }} reg.)</strong></td>
            <td style="text-align:right;"><strong>{{ $fmtMontoExcel($totales['base_calculo'] ?? 0) }}</strong></td>
            <td style="text-align:right;"><strong>{{ $fmtMontoExcel($totales['importe'] ?? 0) }}</strong></td>
        </tr>
    @endif
@else
    <table class="data tabla-detalle">
        <colgroup>
            <col style="width:10%;">
            <col style="width:14%;">
            <col style="width:28%;">
            <col style="width:12%;">
            <col style="width:10%;">
            <col style="width:13%;">
            <col style="width:13%;">
        </colgroup>
        <thead>
            <tr>
                <th>Cert.</th>
                <th>Documento</th>
                <th>Razón</th>
                <th>Fecha</th>
                <th class="num">Alícuota</th>
                <th class="num">Base</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $reg)
                <tr>
                    <td>{{ $reg['nro_cert'] ?? $reg['nro_comp'] ?? '' }}</td>
                    <td>{{ $reg['nro_documento'] ?? '' }}</td>
                    <td>{{ $reg['razon_social'] ?? '' }}</td>
                    <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($reg['alicuota'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($reg['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($reg['importe'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;">Sin registros en el período.</td>
                </tr>
            @endforelse
        </tbody>
        @if (($totales['registros'] ?? 0) > 0)
            <tfoot>
                <tr>
                    <td colspan="5"><strong>Totales ({{ $totales['registros'] }} reg.)</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($totales['base_calculo'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</strong></td>
                </tr>
            </tfoot>
        @endif
    </table>
@endif
