<tr>
 	<td colspan='12'>TOTAL FINAL</td>
	
	@php $totalPares = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		<td align="right">{{number_format($total['final'][$ii], 0)}}</td>
		@php $totalPares += $total['final'][$ii]; @endphp
	@endfor
	<td>{{$totalPares}}</td>
</tr>
