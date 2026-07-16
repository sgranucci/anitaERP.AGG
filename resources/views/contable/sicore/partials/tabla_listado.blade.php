@php
    $registros = $registros ?? [];
    $totales = $totales ?? [];
    $conciliacion = $conciliacion ?? [];
    $esExcel = ! empty($esExcel);
    $mostrarConciliacion = ! empty($conciliacion['habilitada']) && ! empty($conciliacion['items']);
@endphp

@if ($mostrarConciliacion)
    @if ($esExcel)
        <tr>
            <td colspan="9"><strong>Conciliaci&oacute;n SICORE vs mayor contable</strong></td>
        </tr>
        <tr>
            <td><strong>C&oacute;d.</strong></td>
            <td colspan="2"><strong>Concepto</strong></td>
            <td><strong>Total SICORE</strong></td>
            <td><strong>Total mayor</strong></td>
            <td><strong>Diferencia</strong></td>
            <td><strong>Estado</strong></td>
            <td><strong>Reg.</strong></td>
            <td></td>
        </tr>
        @foreach ($conciliacion['items'] as $item)
            <tr>
                <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                <td colspan="2">{{ $item['nombre'] ?? '' }}</td>
                <td style="text-align:right;">{{ number_format((float) ($item['total_sicore'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format((float) ($item['total_mayor'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format((float) ($item['diferencia'] ?? 0), 2, ',', '.') }}</td>
                <td>
                    @if (! empty($item['cuadra']))
                        Cuadra
                    @else
                        Diferencia
                    @endif
                </td>
                <td>{{ $item['registros'] ?? 0 }}</td>
                <td></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="9"><strong>SICORE a presentar — detalle</strong></td>
        </tr>
    @else
        <h3 style="font-size:11px;margin:10px 0 4px;">Conciliaci&oacute;n SICORE vs mayor contable</h3>
        <table class="data" style="margin-bottom:12px;">
            <thead>
                <tr>
                    <th>C&oacute;d.</th>
                    <th>Concepto</th>
                    <th class="num">Total SICORE</th>
                    <th class="num">Total mayor</th>
                    <th class="num">Diferencia</th>
                    <th>Estado</th>
                    <th>Reg.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conciliacion['items'] as $item)
                    <tr>
                        <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                        <td>{{ $item['nombre'] ?? '' }}</td>
                        <td class="num">{{ number_format((float) ($item['total_sicore'] ?? 0), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) ($item['total_mayor'] ?? 0), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) ($item['diferencia'] ?? 0), 2, ',', '.') }}</td>
                        <td>
                            @if (! empty($item['cuadra']))
                                Cuadra
                            @else
                                Diferencia
                            @endif
                        </td>
                        <td class="num">{{ $item['registros'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <h3 style="font-size:11px;margin:10px 0 4px;">SICORE a presentar — detalle</h3>
    @endif
@endif

@if ($esExcel)
    <tr>
        <td><strong>Reg.</strong></td>
        <td><strong>Imp.</strong></td>
        <td><strong>Proveedor</strong></td>
        <td><strong>Documento</strong></td>
        <td><strong>Raz&oacute;n social</strong></td>
        <td><strong>Fecha</strong></td>
        <td><strong>Base</strong></td>
        <td><strong>Importe</strong></td>
        <td><strong>Referencia</strong></td>
    </tr>
    @forelse ($registros as $reg)
        <tr>
            <td>{{ str_pad((string) ($reg['cod_regimen'] ?? ''), 3, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $reg['cod_impuesto'] ?? '' }}</td>
            <td>{{ $reg['codigo_proveedor'] ?? '' }}</td>
            <td>{{ $reg['nro_documento'] ?? '' }}</td>
            <td>{{ $reg['razon_social'] ?? '' }}</td>
            <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
            <td style="text-align:right;">{{ number_format((float) ($reg['base_calculo'] ?? 0), 2, ',', '.') }}</td>
            <td style="text-align:right;">{{ number_format((float) ($reg['importe'] ?? 0), 2, ',', '.') }}</td>
            <td>{{ $reg['referencia'] ?? '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="9">Sin registros en el per&iacute;odo.</td>
        </tr>
    @endforelse
    @if (($totales['registros'] ?? 0) > 0)
        <tr>
            <td colspan="6"><strong>Totales ({{ $totales['registros'] }} reg.)</strong></td>
            <td style="text-align:right;"><strong>{{ number_format((float) ($totales['base_calculo'] ?? 0), 2, ',', '.') }}</strong></td>
            <td style="text-align:right;"><strong>{{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</strong></td>
            <td></td>
        </tr>
    @endif
@else
    <table class="data">
        <thead>
            <tr>
                <th>Reg.</th>
                <th>Imp.</th>
                <th>Proveedor</th>
                <th>Documento</th>
                <th>Raz&oacute;n social</th>
                <th>Fecha</th>
                <th class="num">Base</th>
                <th class="num">Importe</th>
                <th>Referencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $reg)
                <tr>
                    <td>{{ str_pad((string) ($reg['cod_regimen'] ?? ''), 3, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $reg['cod_impuesto'] ?? '' }}</td>
                    <td>{{ $reg['codigo_proveedor'] ?? '' }}</td>
                    <td>{{ $reg['nro_documento'] ?? '' }}</td>
                    <td>{{ $reg['razon_social'] ?? '' }}</td>
                    <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($reg['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($reg['importe'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $reg['referencia'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">Sin registros en el per&iacute;odo.</td>
                </tr>
            @endforelse
        </tbody>
        @if (($totales['registros'] ?? 0) > 0)
            <tfoot>
                <tr>
                    <td colspan="6"><strong>Totales ({{ $totales['registros'] }} reg.)</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($totales['base_calculo'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
@endif
