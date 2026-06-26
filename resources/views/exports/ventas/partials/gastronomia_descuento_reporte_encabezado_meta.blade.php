@php
    $colspan = (int) ($colspan ?? 7);
@endphp
@if (! empty($reservarFilaLogoExcel))
    <tr>
        <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
    </tr>
@endif
<tr>
    <td colspan="{{ $colspan }}">
        <strong style="font-size: 16pt;">{{ $titulo ?? 'Reporte descuentos gastronomía' }}</strong>
    </td>
</tr>
<tr>
    <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
        Generado {{ date('d/m/Y H:i') }}
    </td>
</tr>
@if (! empty($subtitulo))
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
            {{ $subtitulo }}
        </td>
    </tr>
@endif
@if (! empty($resumen_totales))
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
            Unidades: {{ number_format((float) ($resumen_totales['unidades'] ?? 0), 4, ',', '.') }}
            &middot; Costo total: {{ number_format((float) ($resumen_totales['costo_total'] ?? 0), 2, ',', '.') }}
            &middot; Total venta: {{ number_format((float) ($resumen_totales['total_venta'] ?? 0), 2, ',', '.') }}
        </td>
    </tr>
@endif
@if (($total_bloques ?? 0) > 0)
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
            Selecciones con datos: {{ (int) $total_bloques }}
        </td>
    </tr>
@endif
