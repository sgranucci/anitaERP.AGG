<tr>
 	<td>{{$ot['orden']}}</td>
	<td>{{$ot['numeroot']}}</td>
	<td>{{$ot['linea']}}</td>
	<td>{{$ot['sku']}}</td>
	<td>{{$ot['material']}}</td>
	<td>{{number_format(floatval($ot['pares']), 0)}}</td>
	<td>
	@foreach ($ot['nombrecliente'] as $cliente)
		{{$cliente}}
	@endforeach
	</td>
	<td></td>
	<td></td>
	<td></td>
</tr>

