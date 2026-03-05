<tr>
 	<td colspan='12'>TOTAL FINAL</td>
	@php $totalLineaConsumo = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		<td align="right">{{number_format($totalConsumo['final'][$ii], 2)}}</td>
		@php $totalLineaConsumo += $totalConsumo['final'][$ii]; @endphp
	@endfor
	<td align="right">{{number_format(floatval($totalLineaConsumo), 2)}}</td>
	@php $totalLineaPares = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		<td align="right">{{number_format($totalPares['final'][$ii], 0)}}</td>
		@php $totalLineaPares += $totalPares['final'][$ii]; @endphp
	@endfor
	<td align="right">{{$totalLineaPares}}</td>
</tr>
