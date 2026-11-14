@php
    use App\Support\Caja\Flash\FlashCrownNumeroSupport as N;
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Export\ExcelFormatoNumero;

    $excel = ! empty($modo_excel);
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
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
                @if ($excel)
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['att'], $formatoNumero, 0) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['cashdrop'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($m['win_mesas'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['coin_in'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['slot_r'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['ol_win'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['bs_win'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['ad_win'] ?? 0, $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($m['gaming'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['ayb'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right;">{{ F::nExcelFormato($fila['hotel'], $formatoNumero, 2) }}</td>
                    <td style="text-align:right; font-weight:700;">{{ F::nExcelFormato($m['combined'], $formatoNumero, 2) }}</td>
                @else
                    <td style="text-align:right;">{!! N::html($fila['att'], 0) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['cashdrop']) !!}</td>
                    <td style="text-align:right;">{!! N::html($m['win_mesas']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['coin_in']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['slot_r']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['ol_win']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['bs_win']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['ad_win'] ?? 0) !!}</td>
                    <td style="text-align:right;">{!! N::html($m['gaming']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['ayb']) !!}</td>
                    <td style="text-align:right;">{!! N::html($fila['hotel']) !!}</td>
                    <td style="text-align:right;">{!! N::html($m['combined'], 2, true) !!}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
