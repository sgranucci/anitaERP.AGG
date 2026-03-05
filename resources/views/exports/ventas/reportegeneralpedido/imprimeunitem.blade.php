
<tr>
 	<td>{{$data['numeropedido']}}</td>
	<td>{{$data['estadopedido']}}</td>
    <td>{{date("d/m/Y", strtotime($data['fecha']))}}</td>
	<td>{{$data['numeroot']}}</td>
	<td>{{$data['nombrevendedor']}}</td>
	<td>{{$data['nombrecliente']}}</td>
	<td>{{$data['estadocliente']}}</td>
	<td>{{$data['nombrearticulo']}}</td>
	<td>{{$data['sku']}}</td>
	<td>{{$data['combinacion']}}</td>
	<td>{{$data['nombrefondo']}}</td>
	<td>{{$data['nombrecolorfondo']}}</td>
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php $flEncontro = false; @endphp
		@foreach($data['medidas'] as $medida)
			@if ($ii == $medida['medida'])
				<td align="right">{{number_format(floatval($medida['cantidad']), 0)}}</td>
				@php 
					$totalPares += $medida['cantidad']; 
					$flEncontro = true; 
				@endphp
			@endif
		@endforeach
		@if (!$flEncontro)
			<td></td>
		@endif
	@endfor
	<td>{{$totalPares}}</td>
</tr>

