<table>
    @if (! empty($logos) && empty($csv))
        <tr>
            <td colspan="20">
                @foreach ($logos as $logo)
                    {{ $logo }}
                @endforeach
            </td>
        </tr>
    @endif
    <tr>
        <td colspan="20"><strong>Flash Crown — {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</strong></td>
    </tr>
    <tr>
        <th>Sala</th>
        <th>Att</th>
        <th>Drop</th>
        <th>Wheel</th>
        <th>Baccarat</th>
        <th>Blackjack</th>
        <th>Multi</th>
        <th>Craps</th>
        <th>Caribbean</th>
        <th>Poker</th>
        <th>Super Peek</th>
        <th>Win mesas</th>
        <th>Coin in</th>
        <th>Win slots</th>
        <th>OL Coin in</th>
        <th>OL Win</th>
        <th>BS Coin in</th>
        <th>BS Win</th>
        <th>Gaming</th>
        <th>F&amp;B</th>
        <th>Hotel</th>
        <th>Combined</th>
        <th>Anterior</th>
        <th>Val.</th>
    </tr>
    @foreach ($filas as $fila)
        @php
            $m = $fila['metricas'] ?? [];
        @endphp
        <tr>
            <td>{{ $fila['codigo_sala'] ?? '' }} {{ $fila['nombre_sala'] ?? '' }}</td>
            <td>{{ (int) ($fila['att'] ?? 0) }}</td>
            <td>{{ (float) ($fila['cashdrop'] ?? 0) }}</td>
            <td>{{ (float) ($fila['wheel_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['baccarat_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['blackjack_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['multi_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['crap_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['caribbean_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['poker_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['super_peek_r'] ?? 0) }}</td>
            <td>{{ (float) ($m['win_mesas'] ?? 0) }}</td>
            <td>{{ (float) ($fila['coin_in'] ?? 0) }}</td>
            <td>{{ (float) ($fila['slot_r'] ?? 0) }}</td>
            <td>{{ (float) ($fila['ol_coin_in'] ?? 0) }}</td>
            <td>{{ (float) ($fila['ol_win'] ?? 0) }}</td>
            <td>{{ (float) ($fila['bs_coin_in'] ?? 0) }}</td>
            <td>{{ (float) ($fila['bs_win'] ?? 0) }}</td>
            <td>{{ (float) ($m['gaming'] ?? 0) }}</td>
            <td>{{ (float) ($fila['ayb'] ?? 0) }}</td>
            <td>{{ (float) ($fila['hotel'] ?? 0) }}</td>
            <td>{{ (float) ($m['combined'] ?? 0) }}</td>
            <td>{{ (float) ($fila['combined_anterior'] ?? 0) }}</td>
            <td>{{ ! empty($fila['validado']) ? 'Sí' : 'No' }}</td>
        </tr>
    @endforeach
</table>
