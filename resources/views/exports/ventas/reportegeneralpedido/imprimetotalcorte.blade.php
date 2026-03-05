<tr>
 	<td colspan='12'>TOTAL {{$nombreCorteActual}} {{$nombreCorteActual2}}</td>
	
	@php $totalPares = 0; @endphp
	@for ($ii = config('consprod.DESDE_MEDIDA'); $ii <= config('consprod.HASTA_MEDIDA'); $ii++)
		<td align="right">
			{{number_format($total[$tipolistado][$ii], 0)}}
		</td>
		@php $totalPares += $total[$tipolistado][$ii]; @endphp
	@endfor
	<td>{{$totalPares}}</td>
</tr>


