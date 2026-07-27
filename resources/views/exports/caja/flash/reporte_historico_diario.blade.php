@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Export\ExcelFormatoNumero;
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $fn = fn ($v, int $dec = 2) => F::nExcelFormato($v, $formatoNumero, $dec);
    $fp = fn ($v, int $dec = 2) => F::pctExcelFormato($v, $formatoNumero, $dec);
    $fe = fn ($v) => F::enteroExcelFormato($v, $formatoNumero);
    $filas = $reporte['filas_diarias'] ?? [];
    $budget = $reporte['budget_mes'] ?? [];
    $colCount = 52;
@endphp
<table>
    @if(!empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colCount }}" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="{{ $colCount }}"><strong style="font-size: 16px;">{{ $reporte['titulo'] ?? 'Consolidated Income' }}</strong></td></tr>
    <tr><td colspan="{{ $colCount }}">Generado {{ date('d/m/Y H:i') }}</td></tr>
    <tr>
        <td colspan="{{ $colCount }}">
            {{ $reporte['empresa']->nombre ?? '' }}
            &mdash; {{ $reporte['periodo'] ?? ($reporte['fecha'] ?? '') }}
            @if(!empty($reporte['through_day']))
                &mdash; Through day: {{ $reporte['through_day'] }}
            @endif
            &mdash; {{ $reporte['cantidad_dias'] ?? 0 }} día(s)
            &mdash; Season: {{ !empty($reporte['con_season']) ? 'Sí' : 'No' }}
        </td>
    </tr>
    <tr>
        <td colspan="{{ $colCount }}">
            Budget pos {{ $fe($budget['budget_pos'] ?? 0) }}
            | Total {{ $fn($budget['budget_total'] ?? 0) }}
            | Elec {{ $fn($budget['budget_electronic'] ?? 0) }}
            | Bingo {{ $fn($budget['budget_bingo'] ?? 0) }}
            | F&amp;B {{ $fn($budget['budget_ayb'] ?? 0) }}
            | Park {{ $fn($budget['budget_estac'] ?? 0) }}
        </td>
    </tr>
    <tr>
        <th>Day</th><th>Fecha</th><th>Custom</th>
        <th>Slot Units</th><th>Slot Coin in</th><th>Slot Drop</th><th>Slot OL Win</th><th>Slot %Coin</th><th>Slot %Drop</th><th>Slot /Cust</th><th>Slot /Unit</th>
        <th>Rul Seats</th><th>Rul Coin in</th><th>Rul Drop</th><th>Rul OL Win</th><th>Rul %Coin</th><th>Rul %Drop</th><th>Rul /Cust</th><th>Rul /Seat</th>
        <th>Win /Stand</th><th>El Pos</th>
        <th>Win Online</th><th>Win Financial</th><th>Diff</th>
        <th>Bingo Cards</th><th>Bingo Sales</th><th>Bingo Win</th><th>Bingo /Cust</th>
        <th>Gaming</th>
        <th>F&amp;B</th><th>F&amp;B /Cust</th>
        <th>Parking</th><th>Park /Cust</th>
        <th>Otros</th>
        <th>Net Revenues</th><th>Rev /Cust</th>
        <th>Pos OL</th><th>Pos vs Budg</th><th>Cust Budg</th><th>Cust Dev%</th>
        <th>Seas Tot%</th><th>Seas Elec%</th><th>Seas Bingo%</th><th>Seas F&amp;B%</th><th>Seas Park%</th>
        <th>NoSeas Tot%</th><th>NoSeas Elec%</th><th>NoSeas Bingo%</th><th>NoSeas F&amp;B%</th><th>NoSeas Park%</th>
        <th>Vehicles</th><th>Veh Budget</th>
    </tr>
    @foreach($filas as $dia)
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $dia, 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
    @endforeach
    @if(!empty($reporte['total_final']) && ($reporte['cantidad_dias'] ?? 0) > 0)
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $reporte['total_final'], 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
    @endif
    @if(!empty($reporte['mtd_average']) && ($reporte['cantidad_dias'] ?? 0) > 0)
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $reporte['mtd_average'], 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
    @endif
    @if(!empty($reporte['mtd_resta_season']))
    <tr>
        <td colspan="40">Dev. MTD vs season / budget</td>
        <td>{{ $fn($reporte['mtd_resta_season']['total'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_season']['electronic'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_season']['bingo'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_season']['ayb'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_season']['estac'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_budget']['total'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_budget']['electronic'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_budget']['bingo'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_budget']['ayb'] ?? 0) }}</td>
        <td>{{ $fn($reporte['mtd_resta_budget']['estac'] ?? 0) }}</td>
        <td></td><td></td>
    </tr>
    @endif

    @php $compMes = $reporte['comparativo_mes_ant'] ?? null; @endphp
    @if(!empty($compMes) && ($compMes['cantidad_dias'] ?? 0) > 0)
        <tr><td colspan="{{ $colCount }}"></td></tr>
        <tr><td colspan="{{ $colCount }}"><strong>Comparativo mes anterior {{ $compMes['periodo_label'] ?? '' }}</strong></td></tr>
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $compMes['total_final'] ?? [], 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $compMes['mtd_average'] ?? [], 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
    @endif

    @php $compAnio = $reporte['comparativo_anio_ant'] ?? null; @endphp
    @if(!empty($compAnio) && ($compAnio['cantidad_dias'] ?? 0) > 0)
        <tr><td colspan="{{ $colCount }}"></td></tr>
        <tr><td colspan="{{ $colCount }}"><strong>Comparativo año anterior {{ $compAnio['periodo_label'] ?? '' }}</strong></td></tr>
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $compAnio['total_final'] ?? [], 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $compAnio['mtd_average'] ?? [], 'fn' => $fn, 'fp' => $fp, 'fe' => $fe])
    @endif
</table>
