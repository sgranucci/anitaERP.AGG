<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="12" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="12"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Saldos Interbanking (histórico)</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Banco</th>
            <th>Moneda</th>
            <th>Cuenta</th>
            <th>Tipo</th>
            <th>Etiqueta</th>
            <th>Nombre</th>
            <th>Débitos día</th>
            <th>Créditos día</th>
            <th>Saldo día</th>
            <th>Balance actual (snapshot)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($registros as $r)
            <tr>
                <td>{{ $r->fecha->format('d/m/Y') }}</td>
                <td>{{ $r->empresa->nombre ?? '' }}</td>
                <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                <td>{{ $r->currency }}</td>
                <td>{{ $r->account_number }}</td>
                <td>{{ $r->account_type }}</td>
                <td>{{ $r->account_label }}</td>
                <td>{{ $r->account_name }}</td>
                <td>{{ (float) $r->total_debits }}</td>
                <td>{{ (float) $r->total_credits }}</td>
                <td>{{ (float) $r->day_balance }}</td>
                <td>{{ $r->current_operating_balance !== null ? (float) $r->current_operating_balance : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
