<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="10" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Movimientos Interbanking (persistidos)</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Fecha proceso</th>
            <th>Empresa</th>
            <th>Banco</th>
            <th>Cuenta</th>
            <th>Moneda</th>
            <th>Tipo API</th>
            <th>D/C</th>
            <th>Importe</th>
            <th>Descripción</th>
            <th>Comprobante</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($registros as $r)
            <tr>
                <td>{{ $r->process_date ? $r->process_date->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $r->empresa->nombre ?? '' }}</td>
                <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                <td>{{ $r->account_number }}</td>
                <td>{{ $r->currency }}</td>
                <td>{{ $r->movement_type }}</td>
                <td>{{ $r->debit_credit_type }}</td>
                <td>{{ (float) $r->amount }}</td>
                <td>{{ (string) ($r->code_description_bank ?? '') }}</td>
                <td>{{ $r->voucher_number !== null ? (string) $r->voucher_number : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
