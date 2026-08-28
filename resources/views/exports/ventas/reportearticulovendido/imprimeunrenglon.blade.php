<tr style="font-family: Arial; font-size: 10pt;">
    <td style="border: 1px solid #D9D9D9;">{{ date('j/n/Y', strtotime($renglon['fecha'] ?? '')) }}</td>
    <td style="border: 1px solid #D9D9D9; text-align: center;">{{ $renglon['tipocomprobante'] }}</td>
    <td style="border: 1px solid #D9D9D9;">{{ $renglon['nrocomprobante'] }}</td>
    <td style="border: 1px solid #D9D9D9; text-align: center;">{{ $renglon['numerodespacho'] }}</td>
    <td style="border: 1px solid #D9D9D9; text-align: right;">{{ number_format($renglon['cantidad'], 0) }}</td>
    <td style="border: 1px solid #D9D9D9; text-align: right;">{{ number_format($acumuladoPares, 0) }} {{ $renglon['unidad'] }}</td>
    <td style="border: 1px solid #D9D9D9; text-align: center;">$</td>
    <td style="border: 1px solid #D9D9D9; text-align: right;">({{ number_format(abs($renglon['importe']), 2, '.', ',') }})</td>
    <td style="border: 1px solid #D9D9D9; text-align: center;">{{ $renglon['codigocliente'] }}</td>
    <td style="border: 1px solid #D9D9D9;">{{ $renglon['nombrecliente'] }}</td>
</tr>
