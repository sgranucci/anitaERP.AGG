<tr>
 	<td>{{$data['numeropedido']}}</td>
	<td>{{$data['estadopedido']}}</td>
    <td>{{date("d/m/Y", strtotime($data['fecha']))}}</td>
	<td>{{$data['numeroot']}}</td>
	<td>{{$data['nombrevendedor']}}</td>
	<td>{{$data['nombrecliente']}}</td>
	<td>{{$data['nombrearticulo']}}</td>
	<td>{{$data['sku']}}</td>
	<td>{{$data['combinacion']}}</td>
	<td>{{$data['nombrefondo']}}</td>
	<td>{{$data['nombrematerialcapellada']}}</td>
	<td>{{$data['nombrecolor']}}</td>
	@php $totalLineaConsumo = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php $flEncontro = false; @endphp
		@foreach($data['medidas'] as $medida)
			@if ($ii == $medida['medida'])
				<td align="right">{{number_format(floatval($medida['consumo']), 2)}}</td>
				@php 
					$totalLineaConsumo += $medida['consumo']; 
					$flEncontro = true; 
				@endphp
			@endif
		@endforeach
		@if (!$flEncontro)
			<td></td>
		@endif
	@endfor
	<td align="right">{{number_format(floatval($totalLineaConsumo), 2)}}</td>
	
	@php $totalLineaPares = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		@php $flEncontro = false; @endphp
		@foreach($data['medidas'] as $medida)
			@if ($ii == $medida['medida'])
				<td align="right">{{number_format(floatval($medida['cantidad']), 0)}}</td>
				@php 
					$totalLineaPares += $medida['cantidad']; 
					$flEncontro = true; 
				@endphp
			@endif
		@endforeach
		@if (!$flEncontro)
			<td></td>
		@endif
	@endfor
	<td align="right">{{$totalLineaPares}}</td>
</tr>

