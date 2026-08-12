<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="9" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de numeradores del sistema</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Empresa</th>
			<th>Módulo</th>
			<th>Último nro</th>
			<th>Anita sist.</th>
			<th>Clave Anita</th>
			<th>Activo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->empresa->nombre ?? $data->empresa_id }}</td>
				<td>{{ $data->modulo }}</td>
				<td>{{ $data->ultimo_numero }}</td>
				<td>{{ $data->anita_sistema }}</td>
				<td>{{ $data->anita_clave }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
