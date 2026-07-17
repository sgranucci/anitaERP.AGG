@php
    $f = $flash;
@endphp

<h2 style="font-size: 11px; margin: 12px 0 4px 0; color: #1A5276;">Gaming</h2>
<table class="data">
    <thead>
        <tr>
            <th></th>
            <th>Coin in</th>
            <th>Drop</th>
            <th>Win</th>
            <th>Cantidad</th>
            <th>Win on-line</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Slots</td>
            <td class="text-right">{{ number_format((float) $f->slot_coin_in, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($slot_drop, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($slot_win, 2, ',', '.') }}</td>
            <td class="text-right">{{ $f->cant_slots }}</td>
            <td class="text-right">{{ number_format((float) $f->win_ol_slot, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Ruleta electr.</td>
            <td class="text-right">{{ number_format((float) $f->rul_coin_in, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($rul_drop, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($rul_win, 2, ',', '.') }}</td>
            <td class="text-right">{{ $f->cant_rul }}</td>
            <td class="text-right">{{ number_format((float) $f->win_ol_rul, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<h2 style="font-size: 11px; margin: 12px 0 4px 0; color: #1A5276;">Bingo, AyB y estacionamiento</h2>
<table class="data">
    <thead>
        <tr>
            <th>Bingo cart.</th>
            <th>Bingo venta</th>
            <th>Bingo res.</th>
            <th>AyB</th>
            <th>Estac.</th>
            <th>Vending</th>
            <th>Veh&iacute;culos</th>
            <th>Show</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right">{{ $f->bingo_cant_carton }}</td>
            <td class="text-right">{{ number_format($bingo_venta, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($bingo_win, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $f->ayb, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $f->estac, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $f->vending, 2, ',', '.') }}</td>
            <td class="text-right">{{ $f->cant_vehic }}</td>
            <td class="text-right">{{ number_format((float) $f->show, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<p class="totales" style="font-weight: bold; font-size: 10px; margin-top: 8px;">
    Total gaming: {{ number_format($total_gaming, 2, ',', '.') }} &mdash;
    Total revenues: {{ number_format($total_revenues, 2, ',', '.') }}
    @if(($attendance ?? null) !== null)
        &mdash; Asistencia: {{ $attendance }}
    @endif
</p>
