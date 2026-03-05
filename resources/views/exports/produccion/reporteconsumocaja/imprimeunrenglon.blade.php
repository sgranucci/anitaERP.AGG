
<tr>
	@if ($i < count($cajas))
		<td>{{$cajas[$i]['nombrecaja']}}</td>
		<td>{{$cajas[$i]['desdenumero']}} / {{$cajas[$i]['hastanumero']}}</td>
		<td>{{$cajas[$i]['nombrearticulocaja']}}</td>
		<td>{{number_format(floatval($cajas[$i]['consumo']), 0)}}</td>
	@else
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	@endif
	@if ($i < count($cajasespeciales))
		<td>{{$cajasespeciales[$i]['nombrecaja']}}</td>
		<td>{{$cajasespeciales[$i]['desdenumero']}} / {{$cajasespeciales[$i]['hastanumero']}}</td>
		<td>{{$cajasespeciales[$i]['nombrearticulocaja']}}</td>
		<td>{{number_format(floatval($cajasespeciales[$i]['consumo']), 0)}}</td>
	@else
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	@endif	
</tr>

