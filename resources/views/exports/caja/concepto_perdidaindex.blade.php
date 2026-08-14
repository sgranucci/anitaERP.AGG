<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="3" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="3"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de conceptos de p&eacute;rdida</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Nombre</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $fila)
			<tr>
				<td>{{ $fila->id }}</td>
				<td>{{ $fila->codigo }}</td>
				<td>{{ $fila->nombre }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
