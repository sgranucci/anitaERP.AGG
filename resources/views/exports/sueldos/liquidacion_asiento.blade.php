@php
    $payload = $payload ?? [];
    $filas = $filas ?? ($payload['filas'] ?? []);
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $colspan = 7;
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($n == 0.0) {
            return '';
        }
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }

        return number_format($n, 2, ',', '.');
    };
    $meta = 'Empresa: '.($payload['empresa_nombre'] ?? '—')
        .' | Corrida: '.($payload['liquidacion_numero'] ?? '')
        .' | Período: '.($payload['periodo_label'] ?? '')
        .' | Estado: '.($payload['estado_label'] ?? '')
        .' | Modo: '.($payload['modo_label'] ?? '')
        .' | N° asiento: '.($payload['numeros_asiento_label'] ?? '—')
        .' | Generado '.date('d/m/Y H:i');
@endphp
<table>
    @if ($esExcel && $reservarFilaLogoExcel)
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}">
            <strong>{{ $payload['titulo'] ?? 'Asiento de liquidación' }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">{{ $meta }}</td>
    </tr>
    <thead>
        <tr>
            <th>N&deg; asiento</th>
            <th>Grupo / CC</th>
            <th>Cuenta</th>
            <th>Nombre</th>
            <th>Centro de costo</th>
            <th>Debe</th>
            <th>Haber</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>{{ $f['numeroasiento'] ?? '' }}</td>
                <td>{{ $f['grupo'] ?? '' }}</td>
                <td>{{ $f['cuenta_codigo'] ?? '' }}</td>
                <td>{{ $f['cuenta_nombre'] ?? '' }}</td>
                <td>{{ $f['centrocosto'] ?? '' }}</td>
                <td>{{ $fmtNum($f['debe'] ?? 0) }}</td>
                <td>{{ $fmtNum($f['haber'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}">Sin renglones de asiento.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="5"><strong>Totales</strong></td>
            <td><strong>{{ $fmtNum($payload['total_debe'] ?? 0) }}</strong></td>
            <td><strong>{{ $fmtNum($payload['total_haber'] ?? 0) }}</strong></td>
        </tr>
    </tbody>
</table>
