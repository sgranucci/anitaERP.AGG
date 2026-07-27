<table>
@if (!empty($reservarFilaLogoExcel))
<tr>
    <td colspan="5" style="height: 52px;"></td>
</tr>
@endif
<tr>
    <td colspan="5"><strong style="font-size: 16pt;">Listado de gestiones de compra</strong></td>
</tr>
<thead>
<tr>
    <th>ID</th>
    <th>Cód. interno SIFAB</th>
    <th>Código</th>
    <th>Nombre</th>
    <th>Habilitado</th>
</tr>
</thead>
<tbody>
@foreach ($datas as $data)
<tr>
    <td>{{ $data->id }}</td>
    <td>{{ $data->codigo_interno_sifab }}</td>
    <td>{{ $data->codigo }}</td>
    <td>{{ $data->nombre }}</td>
    <td>{{ $data->habilitado ? 'Sí' : 'No' }}</td>
</tr>
@endforeach
</tbody>
</table>
