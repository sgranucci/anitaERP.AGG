@php
    $filas = $filas ?? [];
    $mostrarTotal = $mostrarTotal ?? true;
    $totales = $totales ?? null;
    $esExport = ! empty($esExport);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v, int $dec = 2) use ($esExport, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if (abs($n) < 0.0000001) {
            return '';
        }
        if ($esExport && $autoExcelNum) {
            return number_format($n, $dec, '.', '');
        }
        if ($esExport) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, $dec);
        }

        return number_format($n, $dec, ',', '.');
    };
    $fmtNumCero = function ($v, int $dec = 2) use ($esExport, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExport && $autoExcelNum) {
            return number_format($n, $dec, '.', '');
        }
        if ($esExport) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, $dec);
        }

        return number_format($n, $dec, ',', '.');
    };
@endphp
<table class="table table-bordered table-hover table-sm mb-0" id="{{ $tablaId ?? 'tabla-venta-maquinas' }}" style="font-size: 0.82rem;">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th class="text-right">M&aacute;quinas</th>
            <th class="text-right">Total On Line</th>
            <th class="text-right">Diferencia</th>
            <th class="text-right">Ef.+euros en $</th>
            <th class="text-right">Efectivo</th>
            <th class="text-right">Tarj. Visa</th>
            <th class="text-right">Tarj. Master</th>
            <th class="text-right">MEP</th>
            <th class="text-right">Total coin</th>
            <th class="text-right">Euros</th>
            <th class="text-right">Cot.Euro</th>
            <th class="text-right">Euros en $</th>
            <th class="text-right">D&oacute;lares</th>
            <th class="text-right">Cot.Dolar</th>
            <th class="text-right">Dolares en $</th>
            <th class="text-right">Caja trans. $</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            @php $esTot = ! empty($f['es_total']); @endphp
            <tr @if ($esTot) class="font-weight-bold" style="background:#f5f5f5;" @endif>
                <td>{{ $f['fecha_fmt'] ?? '' }}</td>
                <td class="text-right">{{ $fmtNumCero($f['maquinas'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNumCero($f['total_online'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNumCero($f['diferencia'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['efectivo_euro'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['efectivo'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['visa'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['master'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['mep'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['totalcoin'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['euros'] ?? 0) }}</td>
                <td class="text-right">{{ $esTot ? '' : $fmtNum($f['cot_euro'] ?? 0, 4) }}</td>
                <td class="text-right">{{ $fmtNum($f['euros_en_pesos'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['dolares'] ?? 0) }}</td>
                <td class="text-right">{{ $esTot ? '' : $fmtNum($f['cot_dolar'] ?? 0, 4) }}</td>
                <td class="text-right">{{ $fmtNum($f['dolares_en_pesos'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($f['caja_trans'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="17" class="text-center text-muted py-4">Sin jornadas con Completo en el rango.</td>
            </tr>
        @endforelse
        @if ($mostrarTotal && $totales && ($filas ?? []) !== [])
            <tr class="font-weight-bold" style="background:#f5f5f5;">
                <td>{{ $totales['fecha_fmt'] ?? 'Total' }}</td>
                <td class="text-right">{{ $fmtNumCero($totales['maquinas'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNumCero($totales['total_online'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNumCero($totales['diferencia'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['efectivo_euro'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['efectivo'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['visa'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['master'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['mep'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['totalcoin'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['euros'] ?? 0) }}</td>
                <td class="text-right"></td>
                <td class="text-right">{{ $fmtNum($totales['euros_en_pesos'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['dolares'] ?? 0) }}</td>
                <td class="text-right"></td>
                <td class="text-right">{{ $fmtNum($totales['dolares_en_pesos'] ?? 0) }}</td>
                <td class="text-right">{{ $fmtNum($totales['caja_trans'] ?? 0) }}</td>
            </tr>
        @endif
    </tbody>
</table>
