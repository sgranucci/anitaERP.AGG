<h2> Estado de OT en Fabrica </h2>
<h3> Desde: {{date("d-m-Y", strtotime($desdefecha))}} hasta: {{date("d-m-Y", strtotime($hastafecha))}}</h3>
@if ($ordenestrabajo)
	<h3> Ordenes de trabajo {{ $ordenestrabajo }} </h3>
@else
	<h3> </h3>
@endif
<table> 
	<thead>
	<tr>
		<th colspan='4'></th>
		@foreach($tareas as $columna => $value)
       		<th colspan='3' align="center">{{$columna}}</th>
		@endforeach
	</tr>
    <tr>
		<th>Nro. de OT</th>
		<th>Articulo</th>
		<th>L&iacute;nea</th>
		<th>Pares</th>

		@foreach($tareas as $columna)
       		<th>Inicio</th>
			<th>Fin</th>
			<th>Empleado</th>
		@endforeach
    </tr>
  	</thead>
    <tbody>
    @foreach ($comprobantes as $data)
        <tr>
			@foreach ($data['encabezado'] as $enc)
				<td>{{$enc['codigo']}}</td>
				<td>{{$enc['articulo']}}</td>
				<td>{{$enc['linea']}}</td>
				<td>{{$enc['pares']}}</td>
			@endforeach
			
			@foreach($tareas as $columna => $value)
				@php $flEncontroColumna = false; @endphp
				@foreach ($data['tareas'] as $tar)
					@if ($columna == $tar['columna'])
						<td>{{date("d-m-Y", strtotime($tar['fechainicio']))}}</td>
						@if ($tar['fechafin'] != '0000-00-00' && $tar['fechafin'] != '1969-12-31' && 
							$tar['fechafin'] != null)
							<td>{{date("d-m-Y", strtotime($tar['fechafin']))}}</td>
						@else
							<td></td>
						@endif
						<td>{{$tar['empleado'] ?? ''}}</td>	
						@php $flEncontroColumna = true; @endphp
					@endif
				@endforeach
				@if (!$flEncontroColumna)	
					<td></td>
					<td></td>
					<td></td>	
				@endif
			@endforeach
        </tr>
    @endforeach
	</tbody>
</table>
