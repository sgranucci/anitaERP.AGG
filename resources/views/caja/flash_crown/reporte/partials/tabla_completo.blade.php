@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Export\ExcelFormatoNumero;

    $excel = ! empty($modo_excel);
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $fn = $excel
        ? static fn ($v, int $dec = 2) => F::nExcelFormato($v, $formatoNumero, $dec)
        : static fn ($v, int $dec = 2) => F::n($v, $dec);
@endphp
<table cellspacing="0" cellpadding="4" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:12px; color:#17202A; width:100%;">
    <thead>
        <tr>
            @foreach (['Sala', 'Att', 'Drop', 'Win mesas', 'Coin in', 'Win slots', 'OL Win', 'BS Win', 'AD Win', 'Gaming', 'F&B', 'Hotel', 'Combined'] as $titulo)
                <th style="background:#85C1E9; color:#17202A; text-align:{{ $titulo === 'Sala' ? 'left' : 'right' }}; white-space:nowrap;">
                    {{ $titulo }}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($informe['filas'] as $fila)
            @php $m = $fila['metricas']; @endphp
            <tr>
                <td style="padding:2px 8px;">{{ $fila['codigo'] }} {{ $fila['nombre'] }}</td>
                <td style="text-align:right;">{{ $fn($fila['att'], 0) }}</td>
                <td style="text-align:right;">{{ $fn($fila['cashdrop'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($m['win_mesas'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['coin_in'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['slot_r'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['ol_win'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['bs_win'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['ad_win'] ?? 0, 2) }}</td>
                <td style="text-align:right;">{{ $fn($m['gaming'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['ayb'], 2) }}</td>
                <td style="text-align:right;">{{ $fn($fila['hotel'], 2) }}</td>
                <td style="text-align:right; font-weight:700;">{{ $fn($m['combined'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
