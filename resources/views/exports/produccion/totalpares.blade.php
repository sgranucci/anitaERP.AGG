<h2> Total de Pares </h2>
<h3> Desde: {{date("d-m-Y", strtotime($desdefecha))}} hasta: {{date("d-m-Y", strtotime($hastafecha))}}</h3>
@if ($ordenestrabajo)
	<h3> Ordenes de trabajo {{ $ordenestrabajo }} </h3>
@else
	<h3> </h3>
@endif
<table> 
	<thead>
		<tr>
			<th>Per&iacute;odo</th>
			@foreach($tareas as $columna => $value)
				<th>{{$columna}}</th>
			@endforeach
		</tr>
    </thead>
    <tbody>
    @foreach ($comprobantes as $data)
        <tr>
			@if ($apertura == "DIARIA")
				<td>{{$data['encabezado'][0]['periodo']}}</td>
			@endif
			@if ($apertura == "SEMANAL")
				<td>{{$data['encabezado'][0]['detalle']}}</td>
			@endif
			@if ($apertura == "MENSUAL")
				<td>{{$data['encabezado'][0]['periodo']}}</td>
			@endif
			@foreach($tareas as $columna => $value)
				@php $flEncontroColumna = false; @endphp
				@foreach ($data['tareas'] as $tar)
					@if ($columna == $tar['columna'])
						<td align="right">{{$tar['pares'] ?? ''}}</td>		
						@php $flEncontroColumna = true; @endphp
					@endif
				@endforeach
			
				@if (!$flEncontroColumna)	
					<td></td>
				@endif
			@endforeach
        </tr>
    @endforeach
	</tbody>
</table>
