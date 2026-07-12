<table>
    @if(!empty($reservarFilaLogoExcel))
        <tr><td colspan="10" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="10"><strong style="font-size: 16px;">Flash histórico — detalle diario</strong></td></tr>
    <tr><td colspan="10">{{ $reporte['empresa']->nombre ?? '' }} &mdash; {{ $reporte['periodo'] ?? '' }}</td></tr>
    <tr><td colspan="10">Generado {{ date('d/m/Y H:i') }} &mdash; {{ $reporte['cantidad_dias'] ?? 0 }} día(s)</td></tr>
    <tr><td colspan="10"></td></tr>
    <tr>
        <th>Fecha</th>
        <th>Att</th>
        <th>Slot win</th>
        <th>Rul win</th>
        <th>Bingo res.</th>
        <th>AyB</th>
        <th>Estac.</th>
        <th>Gaming</th>
        <th>Revenues</th>
        <th>ID</th>
    </tr>
    @foreach($reporte['filas_diarias'] ?? [] as $dia)
    <tr>
        <td>{{ $dia['fecha'] }}</td>
        <td>{{ $dia['attendance'] ?? '' }}</td>
        <td>{{ number_format($dia['slot_win'], 2, '.', '') }}</td>
        <td>{{ number_format($dia['rul_win'], 2, '.', '') }}</td>
        <td>{{ number_format($dia['bingo_win'], 2, '.', '') }}</td>
        <td>{{ number_format((float) $dia['flash']->ayb, 2, '.', '') }}</td>
        <td>{{ number_format((float) $dia['flash']->estac, 2, '.', '') }}</td>
        <td>{{ number_format($dia['total_gaming'], 2, '.', '') }}</td>
        <td>{{ number_format($dia['total_revenues'], 2, '.', '') }}</td>
        <td>{{ $dia['id'] ?? '' }}</td>
    </tr>
    @endforeach
    @if(($reporte['cantidad_dias'] ?? 0) > 0)
    <tr><td colspan="10"></td></tr>
    <tr>
        <td><strong>TOTAL</strong></td>
        <td>{{ $reporte['attendance'] ?? '' }}</td>
        <td>{{ number_format($reporte['slot_win'], 2, '.', '') }}</td>
        <td>{{ number_format($reporte['rul_win'], 2, '.', '') }}</td>
        <td>{{ number_format($reporte['bingo_win'], 2, '.', '') }}</td>
        <td>{{ number_format((float) $reporte['flash']->ayb, 2, '.', '') }}</td>
        <td>{{ number_format((float) $reporte['flash']->estac, 2, '.', '') }}</td>
        <td>{{ number_format($reporte['total_gaming'], 2, '.', '') }}</td>
        <td>{{ number_format($reporte['total_revenues'], 2, '.', '') }}</td>
        <td></td>
    </tr>
    @endif
</table>
