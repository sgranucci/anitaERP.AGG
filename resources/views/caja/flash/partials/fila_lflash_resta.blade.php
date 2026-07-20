<tr>
    <td colspan="{{ $colspanIzq ?? 39 }}" class="text-right" style="font-style:italic;">
        Dev. MTD vs season / sin seasonality
    </td>
    <td class="text-right">{{ $fn($restaSeason['total'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaSeason['electronic'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaSeason['bingo'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaSeason['ayb'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaSeason['estac'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaBudget['total'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaBudget['electronic'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaBudget['bingo'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaBudget['ayb'] ?? 0) }}</td>
    <td class="text-right">{{ $fn($restaBudget['estac'] ?? 0) }}</td>
    <td></td>
    <td></td>
</tr>
