<tr style="background-color: #FF9900; font-family: Arial; font-size: 10pt; font-weight: bold;">
    <td colspan="4" style="border: 1px solid #CC7A00;">
        Total {{ $articulo['codigo'] }} {{ \Illuminate\Support\Str::limit($articulo['nombre'], 20, '') }}
    </td>
    <td style="border: 1px solid #CC7A00; text-align: right;">{{ number_format($articulo['total_cantidad'], 0) }}</td>
    <td style="border: 1px solid #CC7A00; text-align: right;">{{ number_format($articulo['total_cantidad_mov'], 0) }} Par</td>
    <td style="border: 1px solid #CC7A00; text-align: center;">$</td>
    <td style="border: 1px solid #CC7A00; text-align: right;">({{ number_format(abs($articulo['total_importe']), 2, '.', ',') }})</td>
    <td colspan="2" style="border: 1px solid #CC7A00;"></td>
</tr>
