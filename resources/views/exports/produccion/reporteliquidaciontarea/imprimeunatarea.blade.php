
<tr>
 	<td>{{$data['numeroot']}}</td>
	<td>{{$data['tarea_id']}}</td>
	<td>{{$data['nombretarea']}}</td>
    <td>{{date("d/m/Y", strtotime($data['desdefecha']))}}</td>
	@if ($data['hastafecha'] != null)
		<td>{{date("d/m/Y", strtotime($data['hastafecha']))}}</td>
	@else
		<td></td>
	@endif
	<td>{{$data['nombrearticulo']}}</td>
	<td>{{$data['sku']}}</td>
	<td>{{$data['nombrecombinacion']}}</td>
	<td>{{$data['numeropedido']}}</td>
	<td align='right'>{{number_format($data['cantidad'], 0,'.','')}}</td>
	<td align='right'>{{number_format($data['costoporpar'], 2,'.','')}}</td>
	<td align='right'>{{number_format($data['cantidad']*$data['costoporpar'], 2,'.','')}}</td>
</tr>

