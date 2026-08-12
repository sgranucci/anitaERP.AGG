@if (! empty($reservarFilaLogoExcel))
<tr><td colspan="6"></td></tr>
@endif
<tr>
    <td colspan="6" style="font-weight:bold;font-size:14px;">Propuestas de pagos</td>
</tr>
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Fecha</th>
    <th>Empresa</th>
    <th>Estado</th>
    <th>Monto</th>
    <th>Detalle</th>
</tr>
</thead>
<tbody>
@foreach($datas as $fila)
<tr>
    <td>{{ $fila->id }}</td>
    <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
    <td>{{ $fila->nombreempresa ?? ($fila->empresas->nombre ?? '') }}</td>
    <td>{{ $fila->estado }}</td>
    <td>{{ number_format((float)$fila->monto_total, 2, ',', '.') }}</td>
    <td>{{ $fila->detalle }}</td>
</tr>
@endforeach
</tbody>
</table>
