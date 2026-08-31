<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr><td colspan="8" style="height: 52px;">&#160;</td></tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Imputación contable de conceptos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Empresa</th>
			<th>Alcance</th>
			<th>Clave</th>
			<th>Debe código</th>
			<th>Debe nombre</th>
			<th>Haber código</th>
			<th>Haber nombre</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ optional($data->empresa)->nombre }}</td>
				<td>{{ $data->alcanceLabel() }}</td>
				<td>{{ $data->clave_label ?? $data->claveLabel() }}</td>
				<td>{{ optional($data->cuentaDebe)->codigo }}</td>
				<td>{{ optional($data->cuentaDebe)->nombre }}</td>
				<td>{{ optional($data->cuentaHaber)->codigo }}</td>
				<td>{{ optional($data->cuentaHaber)->nombre }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
