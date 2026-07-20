@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    $fn = [F::class, 'nExcel'];
    $fp = [F::class, 'pctExcel'];
    $colCount = 52;
    $budget = $reporte['budget_mes'] ?? [];
@endphp
<table>
    @if(!empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colCount }}" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="{{ $colCount }}"><strong style="font-size: 16px;">{{ $reporte['titulo'] ?? 'Consolidated Income' }}</strong></td></tr>
    <tr>
        <td colspan="{{ $colCount }}">
            {{ $reporte['empresa']->nombre ?? '' }}
            &mdash; {{ $reporte['fecha'] ?? '' }}
            &mdash; Generado {{ date('d/m/Y H:i') }}
        </td>
    </tr>
    <tr>
        <td colspan="{{ $colCount }}">
            Budget pos {{ F::entero($budget['budget_pos'] ?? 0) }}
            | Total {{ $fn($budget['budget_total'] ?? 0) }}
            | Elec {{ $fn($budget['budget_electronic'] ?? 0) }}
            | Bingo {{ $fn($budget['budget_bingo'] ?? 0) }}
            | F&B {{ $fn($budget['budget_ayb'] ?? 0) }}
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
        <th>F&B</th><th>F&B /Cust</th>
        <th>Parking</th><th>Park /Cust</th>
        <th>Otros</th>
        <th>Net Revenues</th><th>Rev /Cust</th>
        <th>Pos OL</th><th>Pos vs Budg</th><th>Cust Budg</th><th>Cust Dev%</th>
        <th>Seas Tot%</th><th>Seas Elec%</th><th>Seas Bingo%</th><th>Seas F&B%</th><th>Seas Park%</th>
        <th>NoSeas Tot%</th><th>NoSeas Elec%</th><th>NoSeas Bingo%</th><th>NoSeas F&B%</th><th>NoSeas Park%</th>
        <th>Vehicles</th><th>Veh Budget</th>
    </tr>
    @foreach($reporte['filas_diarias'] ?? [] as $dia)
        @include('exports.caja.flash.partials.fila_lflash_excel', ['m' => $dia, 'fn' => $fn, 'fp' => $fp])
    @endforeach
</table>
