
<tr>
 	<td>{{$combinacion['sku']}}</td>
	<td>{{$combinacion['nombrearticulo']}}</td>
	<td>{{$combinacion['nombrecombinacion']}}</td>
	<td>{{$combinacion['nombremarca']}}</td>
    <td>
	@switch($combinacion['estado'])
		@case ('A')
			ACTIVA
			@break
		@case ('I')
			INACTIVA
			@break
	@endswitch
	</td>
</tr>

