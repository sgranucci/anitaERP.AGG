<table>
    @if(!empty($reservarFilaLogoExcel))
        <tr><td colspan="6" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="6"><strong style="font-size: 16px;">{{ !empty($historico) ? 'Flash Report (histórico)' : 'Flash Report' }}</strong></td></tr>
    <tr><td colspan="6">{{ $reporte['empresa']->nombre ?? '' }} &mdash; {{ $reporte['fecha'] ?? '' }}</td></tr>
    <tr><td colspan="6">Generado {{ date('d/m/Y H:i') }}</td></tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <th>Sección</th>
        <th>Coin in</th>
        <th>Drop</th>
        <th>Win</th>
        <th>Cantidad</th>
        <th>Win OL</th>
    </tr>
    @php $f = $reporte['flash']; @endphp
    <tr>
        <td>Slots</td>
        <td>{{ number_format((float) $f->slot_coin_in, 2, '.', '') }}</td>
        <td>{{ number_format($reporte['slot_drop'], 2, '.', '') }}</td>
        <td>{{ number_format($reporte['slot_win'], 2, '.', '') }}</td>
        <td>{{ $f->cant_slots }}</td>
        <td>{{ number_format((float) $f->win_ol_slot, 2, '.', '') }}</td>
    </tr>
    <tr>
        <td>Ruleta electr.</td>
        <td>{{ number_format((float) $f->rul_coin_in, 2, '.', '') }}</td>
        <td>{{ number_format($reporte['rul_drop'], 2, '.', '') }}</td>
        <td>{{ number_format($reporte['rul_win'], 2, '.', '') }}</td>
        <td>{{ $f->cant_rul }}</td>
        <td>{{ number_format((float) $f->win_ol_rul, 2, '.', '') }}</td>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <th>Bingo cart.</th>
        <th>Bingo venta</th>
        <th>Bingo res.</th>
        <th>AyB</th>
        <th>Estac.</th>
        <th>Vehículos</th>
    </tr>
    <tr>
        <td>{{ $f->bingo_cant_carton }}</td>
        <td>{{ number_format($reporte['bingo_venta'], 2, '.', '') }}</td>
        <td>{{ number_format($reporte['bingo_win'], 2, '.', '') }}</td>
        <td>{{ number_format((float) $f->ayb, 2, '.', '') }}</td>
        <td>{{ number_format((float) $f->estac, 2, '.', '') }}</td>
        <td>{{ $f->cant_vehic }}</td>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <td colspan="3"><strong>Total gaming</strong></td>
        <td colspan="3">{{ number_format($reporte['total_gaming'], 2, '.', '') }}</td>
    </tr>
    <tr>
        <td colspan="3"><strong>Total revenues</strong></td>
        <td colspan="3">{{ number_format($reporte['total_revenues'], 2, '.', '') }}</td>
    </tr>
    <tr>
        <td colspan="3"><strong>Asistencia</strong></td>
        <td colspan="3">{{ $reporte['attendance'] ?? '' }}</td>
    </tr>
</table>
