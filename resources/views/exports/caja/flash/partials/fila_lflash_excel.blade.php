@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    $vsS = $m['vs_season'] ?? [];
    $vsB = $m['vs_budget'] ?? [];
@endphp
<tr>
    <td>{{ $m['etiqueta'] ?? ($m['dia_semana'] ?? '') }}</td>
    <td>{{ $m['fecha'] ?? '' }}</td>
    <td>{{ F::entero($m['custom'] ?? 0) }}</td>
    <td>{{ F::entero($m['slot_units'] ?? 0) }}</td>
    <td>{{ $fn($m['slot_coin_in'] ?? 0) }}</td>
    <td>{{ $fn($m['slot_drop'] ?? 0) }}</td>
    <td>{{ $fn($m['slot_ol_win'] ?? 0) }}</td>
    <td>{{ $fn($m['slot_pct_coin'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['slot_pct_drop'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['slot_win_cust'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['slot_win_unit'] ?? 0, 0) }}</td>
    <td>{{ F::entero($m['rul_units'] ?? 0) }}</td>
    <td>{{ $fn($m['rul_coin_in'] ?? 0) }}</td>
    <td>{{ $fn($m['rul_drop'] ?? 0) }}</td>
    <td>{{ $fn($m['rul_ol_win'] ?? 0) }}</td>
    <td>{{ $fn($m['rul_pct_coin'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['rul_pct_drop'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['rul_win_cust'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['rul_win_seat'] ?? 0, 0) }}</td>
    <td>{{ $fn($m['win_stand'] ?? 0, 0) }}</td>
    <td>{{ F::entero($m['el_positions'] ?? 0) }}</td>
    <td>{{ $fn($m['win_online'] ?? 0) }}</td>
    <td>{{ $fn($m['win_financial'] ?? 0) }}</td>
    <td>{{ $fn($m['win_diff'] ?? 0) }}</td>
    <td>{{ F::entero($m['bingo_carton'] ?? 0) }}</td>
    <td>{{ $fn($m['bingo_venta'] ?? 0) }}</td>
    <td>{{ $fn($m['bingo_win'] ?? 0) }}</td>
    <td>{{ $fn($m['bingo_win_cust'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['gaming'] ?? 0) }}</td>
    <td>{{ $fn($m['ayb'] ?? 0) }}</td>
    <td>{{ $fn($m['ayb_cust'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['estac'] ?? 0) }}</td>
    <td>{{ $fn($m['estac_cust'] ?? 0, 1) }}</td>
    <td>{{ $fn($m['otros'] ?? 0) }}</td>
    <td>{{ $fn($m['revenues'] ?? 0) }}</td>
    <td>{{ $fn($m['revenues_cust'] ?? 0, 1) }}</td>
    <td>{{ F::entero($m['pos_online'] ?? 0) }}</td>
    <td>{{ isset($m['pos_vs_budget']) ? $fn($m['pos_vs_budget'], 0) : '' }}</td>
    <td>{{ isset($m['customer_budget']) ? F::entero($m['customer_budget']) : '' }}</td>
    <td>{{ isset($m['customer_dev_pct']) ? $fp($m['customer_dev_pct']) : '' }}</td>
    <td>{{ isset($vsS['total']) ? $fp($vsS['total']) : '' }}</td>
    <td>{{ isset($vsS['electronic']) ? $fp($vsS['electronic']) : '' }}</td>
    <td>{{ isset($vsS['bingo']) ? $fp($vsS['bingo']) : '' }}</td>
    <td>{{ isset($vsS['ayb']) ? $fp($vsS['ayb']) : '' }}</td>
    <td>{{ isset($vsS['estac']) ? $fp($vsS['estac']) : '' }}</td>
    <td>{{ isset($vsB['total']) ? $fp($vsB['total']) : '' }}</td>
    <td>{{ isset($vsB['electronic']) ? $fp($vsB['electronic']) : '' }}</td>
    <td>{{ isset($vsB['bingo']) ? $fp($vsB['bingo']) : '' }}</td>
    <td>{{ isset($vsB['ayb']) ? $fp($vsB['ayb']) : '' }}</td>
    <td>{{ isset($vsB['estac']) ? $fp($vsB['estac']) : '' }}</td>
    <td>{{ F::entero($m['vehiculos'] ?? 0) }}</td>
    <td>{{ isset($m['vehiculos_budget']) ? F::entero($m['vehiculos_budget']) : '' }}</td>
</tr>
