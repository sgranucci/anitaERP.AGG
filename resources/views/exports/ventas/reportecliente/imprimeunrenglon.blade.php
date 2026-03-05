<tr>
	<td>{{$data->codigo}}</td>
	<td>{{$data->nombre}}</td>
	<td>{{$data->domicilio}}</td>
	<td>{{$data->provincias->nombre??''}}</td>
	<td>{{$data->localidades->nombre??''}}</td>
	<td>{{$data->telefono}}</td>
	<td>{{$data->email}}</td>
	<td>
		@if ($data->estado == '0')
			{{'ACTIVO'}}
		@else
			{{'SUSPENDIDO'}}
		@endif
	</td>
	<td>{{$data->tipossuspensioncliente->nombre??''}}</td>
	<td>{{$data->leyenda}}</td>
</tr>
