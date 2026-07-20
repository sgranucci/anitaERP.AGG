@php
    $esExcel = ! empty($esExcel);
    $registros = $registros ?? collect();
    $subtitulo = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($registros) ? count($registros) : 0).' registro(s)';
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtMonto = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }
        return number_format($n, 2, ',', '.');
    };
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="11" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="11"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Transferencias Interbanking (persistidas)</h2></td>
        </tr>
        <tr>
            <td colspan="11"><strong>{{ $subtitulo }}</strong></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Fecha solicitud</th>
            <th>Empresa</th>
            <th>Banco</th>
            <th>Cuenta filtro</th>
            <th>Tipo</th>
            <th>Importe</th>
            <th>Moneda</th>
            <th>Cuenta débito</th>
            <th>Cuenta crédito</th>
            <th>Cód. validación</th>
            <th>ID transferencia</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($registros as $r)
            <tr>
                <td>{{ $r->request_date ? $r->request_date->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $r->empresa->nombre ?? '' }}</td>
                <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                <td>{{ $r->debit_account_number ?? '' }}</td>
                <td>{{ (string) ($r->transfer_type_description ?? $r->transfer_type_code ?? '') }}</td>
                <td>{{ $fmtMonto($r->amount) }}</td>
                <td>{{ $r->currency ?? '' }}</td>
                <td>{{ $r->debit_account ?? '' }}</td>
                <td>{{ $r->credit_account ?? '' }}</td>
                <td>{{ $r->validation_code ?? '' }}</td>
                <td>{{ $r->transfer_id !== null ? (string) $r->transfer_id : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
