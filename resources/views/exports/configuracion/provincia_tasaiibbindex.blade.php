@php
    $colspan = 11;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Tasas y mínimos IIBB por provincia' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    {{ $subtitulo }}
                </td>
            </tr>
        @endif
        @if (! empty($resumen))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Provincias: {{ (int) ($resumen['provincias'] ?? 0) }}
                    &middot; Alícuotas: {{ (int) ($resumen['alicuotas'] ?? 0) }}
                </td>
            </tr>
        @endif
    </tbody>
    <thead>
        <tr>
            <th>Jur.</th>
            <th>Provincia</th>
            <th>Abrev.</th>
            <th>Condición IIBB</th>
            <th>Tasa %</th>
            <th>Mín. neto</th>
            <th>Mín. perc.</th>
            <th>Mín. coef. CM05</th>
            <th>Percibe</th>
            <th>Retiene</th>
            <th>Cuenta contable</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila->jurisdiccion }}</td>
                <td>{{ $fila->provincia }}</td>
                <td>{{ $fila->abreviatura }}</td>
                <td>{{ $fila->condicion }}</td>
                <td>{{ number_format((float) $fila->tasa, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $fila->minimoneto, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $fila->minimopercepcion, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $fila->minimocoeficientecm05, 2, ',', '.') }}</td>
                <td>{{ $fila->empresas_percepcion }}</td>
                <td>{{ $fila->empresas_retencion }}</td>
                <td>{{ $fila->cuentas }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
