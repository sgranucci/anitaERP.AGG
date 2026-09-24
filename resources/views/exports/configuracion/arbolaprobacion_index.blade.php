<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="14" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="14"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de árboles de aprobación</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Empresa</th>
			<th>Tipo</th>
			<th>Estado</th>
			<th>Recordatorio</th>
			<th>Nivel</th>
			<th>Rama</th>
			<th>Centro de costo</th>
			<th>Firmante</th>
			<th>Desde monto</th>
			<th>Hasta monto</th>
			<th>Moneda</th>
			<th>Estado doc.</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $fila)
			<tr>
				<td>{{ $fila['id'] }}</td>
				<td>{{ $fila['nombre'] }}</td>
				<td>{{ $fila['empresa'] }}</td>
				<td>{{ $fila['tipo'] }}</td>
				<td>{{ $fila['estado'] }}</td>
				<td>{{ $fila['recordatorio'] }}</td>
				<td>{{ $fila['nivel'] }}</td>
				<td>{{ $fila['rama'] }}</td>
				<td>{{ $fila['centrocosto'] }}</td>
				<td>{{ $fila['firmante'] }}</td>
				<td>{{ $fila['desde_monto'] }}</td>
				<td>{{ $fila['hasta_monto'] }}</td>
				<td>{{ $fila['moneda'] }}</td>
				<td>{{ $fila['estado_doc'] }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
