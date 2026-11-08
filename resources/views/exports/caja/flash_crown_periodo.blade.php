<table>
    <tr>
        <td colspan="18">
            <strong>Flash Crown — {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</strong>
            ({{ $perfil === 'finanzas' ? 'Finanzas' : 'Completa' }})
        </td>
    </tr>
    <tr>
        <th>Fecha</th>
        <th>Sala</th>
        <th>Att</th>
        <th>Drop</th>
        @if ($perfil !== 'finanzas')
            <th>Wheel</th>
            <th>Baccarat</th>
            <th>Blackjack</th>
            <th>Craps</th>
            <th>Caribbean</th>
            <th>Poker</th>
            <th>Torneo</th>
            <th>Master</th>
        @endif
        <th>Coin in</th>
        <th>Win slots</th>
        <th>Gaming</th>
        <th>F&amp;B</th>
        <th>Hotel</th>
        <th>Combined</th>
        <th>Anterior</th>
    </tr>
    @foreach ($filas as $fila)
        @php $m = $fila['metricas'] ?? []; @endphp
        <tr>
            <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
            <td>{{ $fila['codigo_sala'] ?? '' }} {{ $fila['nombre_sala'] ?? '' }}</td>
            <td>{{ (int) ($fila['att'] ?? 0) }}</td>
            <td>{{ (float) ($fila['cashdrop'] ?? 0) }}</td>
            @if ($perfil !== 'finanzas')
                <td>{{ (float) ($fila['wheel_r'] ?? 0) }}</td>
                <td>{{ (float) ($fila['baccarat_r'] ?? 0) }}</td>
                <td>{{ (float) ($fila['blackjack_r'] ?? 0) }}</td>
                <td>{{ (float) ($fila['crap_r'] ?? 0) }}</td>
                <td>{{ (float) ($fila['caribbean_r'] ?? 0) }}</td>
                <td>{{ (float) ($fila['poker_r'] ?? 0) }}</td>
                <td>{{ (float) ($fila['torneo_poker'] ?? 0) }}</td>
                <td>{{ (float) ($fila['master_cup'] ?? 0) }}</td>
            @endif
            <td>{{ (float) ($fila['coin_in'] ?? 0) }}</td>
            <td>{{ (float) ($fila['slot_r'] ?? 0) }}</td>
            <td>{{ (float) ($m['gaming'] ?? 0) }}</td>
            <td>{{ (float) ($fila['ayb'] ?? 0) }}</td>
            <td>{{ (float) ($fila['hotel'] ?? 0) }}</td>
            <td>{{ (float) ($m['combined'] ?? 0) }}</td>
            <td>{{ (float) ($fila['combined_anterior'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>
