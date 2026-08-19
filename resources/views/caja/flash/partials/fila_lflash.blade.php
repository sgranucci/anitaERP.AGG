@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    $vsS = $m['vs_season'] ?? [];
    $vsB = $m['vs_budget'] ?? [];
    $bold = !empty($esTotal) ? 'font-weight:bold;' : '';
    $congelar = !empty($congelarColumnas);
@endphp
<tr class="{{ !empty($esTotal) ? 'fila-total-flash' : '' }}" style="{{ $bold }}">
    <td class="{{ $congelar ? 'col-fija-1' : '' }}">{{ $m['etiqueta'] ?? ($m['dia_semana'] ?? '') }}</td>
    <td class="{{ $congelar ? 'col-fija-2' : '' }}">{{ $m['fecha'] ?? '' }}</td>
    <td class="text-right">{{ F::entero($m['custom'] ?? 0) }}</td>
    <td class="text-right">{{ F::entero($m['slot_units'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['slot_coin_in'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['slot_drop'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['slot_ol_win'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['slot_pct_coin'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['slot_pct_drop'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['slot_win_cust'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['slot_win_unit'] ?? 0, 0) }}</td>
    <td class="text-right">{{ F::entero($m['rul_units'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['rul_coin_in'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['rul_drop'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['rul_ol_win'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['rul_pct_coin'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['rul_pct_drop'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['rul_win_cust'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['rul_win_seat'] ?? 0, 0) }}</td>
    <td class="text-right">{{ $fn($m['win_stand'] ?? 0, 0) }}</td>
    <td class="text-right">{{ F::entero($m['el_positions'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['win_online'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['win_financial'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['win_diff'] ?? 0) }}</td>
    <td class="text-right">{{ F::entero($m['bingo_carton'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['bingo_venta'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['bingo_win'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['bingo_win_cust'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['gaming'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['ayb'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['ayb_cust'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['estac'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['estac_cust'] ?? 0, 1) }}</td>
    <td class="text-right">{{ $fn($m['otros'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['revenues'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['revenues_cust'] ?? 0, 1) }}</td>
    <td class="text-right">{{ F::entero($m['pos_online'] ?? 0) }}</td>
    <td class="text-right">{{ isset($m['pos_vs_budget']) ? $fn($m['pos_vs_budget'], 0) : '' }}</td>
    <td class="text-right">{{ isset($m['customer_budget']) ? F::entero($m['customer_budget']) : '' }}</td>
    <td class="text-right">{{ isset($m['customer_dev_pct']) ? $fp($m['customer_dev_pct']) : '' }}</td>
    <td class="text-right">{{ isset($vsS['total']) ? $fp($vsS['total']) : '' }}</td>
    <td class="text-right">{{ isset($vsS['electronic']) ? $fp($vsS['electronic']) : '' }}</td>
    <td class="text-right">{{ isset($vsS['bingo']) ? $fp($vsS['bingo']) : '' }}</td>
    <td class="text-right">{{ isset($vsS['ayb']) ? $fp($vsS['ayb']) : '' }}</td>
    <td class="text-right">{{ isset($vsS['estac']) ? $fp($vsS['estac']) : '' }}</td>
    <td class="text-right">{{ isset($vsB['total']) ? $fp($vsB['total']) : '' }}</td>
    <td class="text-right">{{ isset($vsB['electronic']) ? $fp($vsB['electronic']) : '' }}</td>
    <td class="text-right">{{ isset($vsB['bingo']) ? $fp($vsB['bingo']) : '' }}</td>
    <td class="text-right">{{ isset($vsB['ayb']) ? $fp($vsB['ayb']) : '' }}</td>
    <td class="text-right">{{ isset($vsB['estac']) ? $fp($vsB['estac']) : '' }}</td>
    <td class="text-right">{{ F::entero($m['vehiculos'] ?? 0) }}</td>
    <td class="text-right">{{ isset($m['vehiculos_budget']) ? F::entero($m['vehiculos_budget']) : '' }}</td>
    @if(!empty($mostrarAcciones))
        <td class="text-center">
            @if(can('exportar-reporte-flash-caja', false) && !empty($m['id']))
                <a href="{{ route('flash_caja_reporte', ['id' => $m['id'], 'formato' => 'PDF']) }}" class="text-danger" target="_blank" rel="noopener" title="PDF d&iacute;a"><i class="fa fa-file-pdf-o"></i></a>
                <a href="{{ route('flash_caja_reporte', ['id' => $m['id'], 'formato' => 'EXCEL']) }}" class="text-success ml-1" title="Excel d&iacute;a"><i class="fa fa-file-excel-o"></i></a>
            @endif
        </td>
    @endif
</tr>
