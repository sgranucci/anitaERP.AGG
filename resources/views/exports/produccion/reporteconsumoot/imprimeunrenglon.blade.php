
<tr>
 	<td>{{$materialCapellada}}</td>
	@if ($consumoCapellada > 0)
		<td align="right">{{number_format($consumoCapellada,2,'.','')}}</td>
	@else
		<td></td>
	@endif
	
	<td>{{$materialAvio}}</td>
	@if ($consumoAvio > 0)
    	<td align="right">{{number_format($consumoAvio,2,'.','')}}</td>
	@else
		<td></td>
	@endif
</tr>

