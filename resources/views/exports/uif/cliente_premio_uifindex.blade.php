<h2>Premios UIF</h2>
<table>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Sala</th>
			<th>Juego</th>
			<th>Fecha Entrega</th>
			<th>Monto</th>
			<th>Posición</th>
			<th>Número TITO</th>
			<th>Forma de Pago</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($cliente_premio_uifs as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombrecliente }}</td>
				<td>{{ $data->nombresala }}</td>
				<td>{{ $data->nombrejuego }}</td>
				<td>{{ $data->fechaentrega }}</td>
				<td>{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
				<td>{{ $data->posicion ?? '' }}</td>
				<td>{{ $data->numerotito ?? '' }}</td>
				<td>{{ $data->nombreformapago }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
