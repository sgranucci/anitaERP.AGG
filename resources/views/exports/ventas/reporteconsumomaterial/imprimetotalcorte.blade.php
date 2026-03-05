<tr>
 	<td colspan='12'>TOTAL {{$nombreCorteActual}}</td>
	
	@php $totalLineaConsumo = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		<td align="right">
			{{number_format($totalConsumo[$tipolistado][$ii], 2)}}
		</td>
		@php $totalLineaConsumo += $totalConsumo[$tipolistado][$ii]; @endphp
	@endfor
	<td align="right">{{number_format(floatval($totalLineaConsumo), 2)}}</td>
	@php $totalLineaPares = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		<td align="right">
			{{number_format($totalPares[$tipolistado][$ii], 0)}}
		</td>
		@php $totalLineaPares += $totalPares[$tipolistado][$ii]; @endphp
	@endfor
	<td align="right">{{$totalLineaPares}}</td>
</tr>


