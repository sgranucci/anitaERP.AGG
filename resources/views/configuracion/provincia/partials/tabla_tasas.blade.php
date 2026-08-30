@php
    $filas = $filas ?? collect();
    $tablaClass = $tablaClass ?? 'data';
    $tablaId = $tablaId ?? null;
    $mostrarColgroup = $mostrarColgroup ?? ($tablaClass === 'data');
@endphp
<table @if ($tablaId) id="{{ $tablaId }}" @endif class="{{ $tablaClass }}">
    @if ($mostrarColgroup)
    <colgroup>
        <col style="width: 5%;">
        <col style="width: 13%;">
        <col style="width: 5%;">
        <col style="width: 13%;">
        <col style="width: 6%;">
        <col style="width: 8%;">
        <col style="width: 8%;">
        <col style="width: 7%;">
        <col style="width: 12%;">
        <col style="width: 10%;">
        <col style="width: 13%;">
    </colgroup>
    @endif
    <thead>
        <tr>
            <th>Jur.</th>
            <th>Provincia</th>
            <th>Abrev.</th>
            <th>Condici&oacute;n IIBB</th>
            <th class="num">Tasa %</th>
            <th class="num">M&iacute;n. neto</th>
            <th class="num">M&iacute;n. perc.</th>
            <th class="num">M&iacute;n. coef. CM05</th>
            <th>Percibe</th>
            <th>Retiene</th>
            <th>Cuenta contable</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila->jurisdiccion }}</td>
                <td>{{ $fila->provincia }}</td>
                <td>{{ $fila->abreviatura }}</td>
                <td>{{ $fila->condicion }}</td>
                <td class="num">{{ number_format((float) $fila->tasa, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $fila->minimoneto, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $fila->minimopercepcion, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $fila->minimocoeficientecm05, 2, ',', '.') }}</td>
                <td>{{ $fila->empresas_percepcion }}</td>
                <td>{{ $fila->empresas_retencion }}</td>
                <td>{{ $fila->cuentas }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11">No hay provincias con tasas IIBB cargadas.</td>
            </tr>
        @endforelse
    </tbody>
</table>
