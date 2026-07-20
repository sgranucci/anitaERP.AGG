@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    $vsS = $m['vs_season'] ?? [];
    $vsB = $m['vs_budget'] ?? [];
@endphp
<tr>
    <td>{{ $m['etiqueta'] ?? '' }}</td>
    <td class="text-right">{{ F::entero($m['custom'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['slot_ol_win'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['rul_ol_win'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['win_online'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['bingo_win'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['gaming'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['ayb'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['estac'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['otros'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($m['revenues'] ?? 0) }}</td>
    <td class="text-right">{{ isset($vsS['total']) ? $fp($vsS['total']) : '' }}</td>
    <td class="text-right">{{ isset($vsB['total']) ? $fp($vsB['total']) : '' }}</td>
</tr>
