@if (! empty($reservarFilaLogoExcel))
<tr><td colspan="14" style="height:52px;"></td></tr>
@endif
<tr>
    <td colspan="14"><strong>{{ $titulo }}</strong></td>
</tr>
<tr>
    <td colspan="14">Generado {{ date('d/m/Y H:i') }}</td>
</tr>
<tr>
    <td colspan="14">{{ $subtitulo }}</td>
</tr>
<tr>
    <td colspan="14">{{ count($filas) }} registros</td>
</tr>
<table>
<thead>
<tr>
    <th>Empr.</th>
    <th>N.Pro.</th>
    <th>Nombre</th>
    <th>Tip</th>
    <th>Comprobante</th>
    <th>F.Vto.</th>
    <th>M.Pago</th>
    <th>Detalle pago</th>
    <th>Mon.</th>
    <th>Total adeudado</th>
    <th>Leyenda</th>
    <th>Detalle del concepto</th>
    <th>Urgentes</th>
    <th>Fecha de pago</th>
</tr>
</thead>
<tbody>
@foreach ($filas as $fila)
<tr>
    <td>{{ $fila['empresa'] }}</td>
    <td>{{ $fila['proveedor_codigo'] }}</td>
    <td>{{ $fila['proveedor_nombre'] }}</td>
    <td>{{ $fila['tipo'] }}</td>
    <td>{{ $fila['comprobante'] }}</td>
    <td>{{ $fila['fecha_vto'] }}</td>
    <td>{{ $fila['medio_pago'] }}</td>
    <td>{{ $fila['detalle_pago'] }}</td>
    <td>{{ $fila['moneda'] }}</td>
    <td>{{ number_format((float) $fila['total_adeudado'], 2, '.', '') }}</td>
    <td>{{ $fila['leyenda'] }}</td>
    <td>{{ $fila['detalle_concepto'] }}</td>
    <td>{{ ! empty($fila['urgente']) ? 'SI' : '' }}</td>
    <td>{{ $fila['fecha_pago'] }}</td>
</tr>
@endforeach
</tbody>
</table>
